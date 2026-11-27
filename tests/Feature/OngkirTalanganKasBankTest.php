<?php

namespace Tests\Feature;

use App\Models\FinanceAccount;
use App\Models\FinancePayment;
use App\Models\MedicineTransactions;
use App\Models\TransactionService;
use App\Models\User;
use App\Services\Finance\CashflowReportService;
use App\Services\Finance\KeuanganReportService;
use App\Support\FinanceAccountBalanceResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OngkirTalanganKasBankTest extends TestCase
{
    private User $user;
    private FinanceAccount $kasAccount;
    private FinanceAccount $bcaAccount;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        $this->createSchema();

        DB::table('pharmacies')->insert([
            ['id' => 1, 'name' => 'SAHABAT PMI', 'status' => 1],
        ]);

        $this->user = User::create([
            'name' => 'ADMIN TEST',
            'username' => 'admin_test',
            'password' => bcrypt('secret'),
            'secret_pin' => '1234',
            'pharmacy_id' => 1,
        ]);

        $this->kasAccount = FinanceAccount::create([
            'code' => '1-10001',
            'pharmacy_id' => 1,
            'name' => 'Kas Apotek / Drawer',
            'category' => 'Kas & Bank',
            'account_type' => 'kas_apotek',
            'balance' => 1000000,
            'is_active' => true,
        ]);

        $this->bcaAccount = FinanceAccount::create([
            'code' => '1-10002',
            'pharmacy_id' => 1,
            'name' => 'Rekening BCA',
            'category' => 'Kas & Bank',
            'account_type' => 'bank',
            'balance' => 5000000,
            'is_active' => true,
        ]);

        $this->withoutMiddleware();
        $this->withoutExceptionHandling();
        $this->actingAs($this->user);
        session(['ho_pharmacy_id' => 1]);
    }

    public function test_ongkir_mutasi_reduces_kas_apotek_balance_and_is_excluded_from_biaya(): void
    {
        $opening = FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-11-01',
            'amount' => 1000000,
            'created_by' => $this->user->id,
        ]);

        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'ONGKIR',
            'payment_date' => '2026-11-05',
            'amount' => 25000,
            'reference_number' => 'TRX-ONG-001',
            'notes' => 'Pengeluaran talangan ongkir dari Kas Apotek (QRIS melalui BCA)',
            'created_by' => $this->user->id,
        ]);

        $balance = FinanceAccountBalanceResolver::calculateFromOpening(
            $this->kasAccount->id,
            $opening,
            '2026-11-10'
        );
        $this->assertSame(975000.0, $balance);

        $flows = FinanceAccountBalanceResolver::sumOperationalFlows(
            $this->kasAccount->id,
            '2026-11-01',
            '2026-11-10'
        );
        $this->assertSame(0.0, $flows['inflow']);
        $this->assertSame(25000.0, $flows['outflow']);
        $this->assertSame(-25000.0, $flows['net']);

        // Pastikan mutasi ONGKIR tidak dianggap sebagai BIAYA operasional di Finance
        $this->assertSame(0.0, (float) FinancePayment::where('payment_type', 'BIAYA')->sum('amount'));
    }

    public function test_checkout_with_ongkir_records_bank_inflow_and_deducts_kas_apotek_drawer(): void
    {
        $tx = MedicineTransactions::create([
            'pharmacy_id' => 1,
            'user_id' => $this->user->id,
            'transaction_type' => 'JASA',
            'status' => 0,
        ]);

        TransactionService::create([
            'medicine_transaction_id' => $tx->id,
            'service_name' => 'Jasa Racik',
            'price' => 30000,
        ]);

        $sequenceKey = now()->format('ym') . '501';
        DB::table('medicine_transaction_sequences')->insert([
            'sequence_key' => $sequenceKey,
            'last_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/transaction/checkout', [
            'transaction_id' => $tx->id,
            'paid' => 30000,
            'discounsubtotalvalue' => 0,
            'totaltransaction' => 30000,
            'changes' => 0,
            'paymentType' => 'CASH',
            'has_doctor_shipping_fee' => true,
            'doctor_shipping_fee' => 20000,
            'doctor_shipping_payment_method' => 'QRIS',
            'doctor_shipping_bank_name' => 'BCA',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        // Kas Apotek: +30,000 penjualan tunai - 20,000 talangan ongkir = +10,000 -> 1,010,000
        $this->assertSame(1010000.0, (float) $this->kasAccount->fresh()->balance);
        // Bank BCA: +20,000 penerimaan talangan ongkir QRIS -> 5,020,000
        $this->assertSame(5020000.0, (float) $this->bcaAccount->fresh()->balance);

        $ongkirPayment = FinancePayment::where('medicine_transaction_id', $tx->id)
            ->where('payment_type', 'ONGKIR')
            ->first();

        $this->assertNotNull($ongkirPayment);
        $this->assertSame($this->kasAccount->id, (int) $ongkirPayment->account_id);
        $this->assertSame(20000.0, (float) $ongkirPayment->amount);
        $this->assertSame(0, FinancePayment::where('payment_type', 'BIAYA')->count());
    }

    public function test_backfill_ongkir_talangan_syncs_kas_apotek_idempotently_and_respects_saldo_awal(): void
    {
        $opening = FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-11-01',
            'amount' => 1000000,
            'created_by' => $this->user->id,
        ]);

        // Transaksi sebelum Saldo Awal (2026-10-29): tetap dicatat mutasi sejarahnya, tidak mengurangi saldo setelah 2026-11-01
        $preOpeningTx = new MedicineTransactions([
            'pharmacy_id' => 1,
            'user_id' => $this->user->id,
            'transaction_type' => 'HV/OTC',
            'transaction_code' => 'TRX-PRE-01',
            'status' => 1,
            'subtotal' => 50000,
            'discount' => 0,
            'payment_method' => 'CASH',
            'has_doctor_shipping_fee' => true,
            'doctor_shipping_fee' => 15000,
            'doctor_shipping_payment_method' => 'QRIS',
            'doctor_shipping_bank_name' => 'BCA',
        ]);
        $preOpeningTx->forceFill(['created_at' => '2026-10-29 10:00:00', 'updated_at' => '2026-10-29 10:00:00'])->save();

        // Transaksi setelah Saldo Awal (2026-11-12): mengurangi Kas Apotek
        $postOpeningTx = new MedicineTransactions([
            'pharmacy_id' => 1,
            'user_id' => $this->user->id,
            'transaction_type' => 'HV/OTC',
            'transaction_code' => 'TRX-POST-01',
            'status' => 1,
            'subtotal' => 100000,
            'discount' => 0,
            'payment_method' => 'QRIS',
            'transfer_bank_name' => 'BCA',
            'has_doctor_shipping_fee' => true,
            'doctor_shipping_fee' => 25000,
            'doctor_shipping_payment_method' => 'QRIS',
            'doctor_shipping_bank_name' => 'BCA',
        ]);
        $postOpeningTx->forceFill(['created_at' => '2026-11-12 14:30:00', 'updated_at' => '2026-11-12 14:30:00'])->save();

        $res1 = $this->post('/finance/kas-bank/backfill-ongkir', [
            'from_date' => '2026-10-01',
            'to_date' => '2026-11-27',
        ]);
        $res1->assertRedirect();

        $this->assertSame(2, FinancePayment::where('payment_type', 'ONGKIR')->count());
        $this->assertSame(975000.0, (float) $this->kasAccount->fresh()->balance);
        $this->assertSame(0, FinancePayment::where('payment_type', 'BIAYA')->count());

        // Jalankan ulang (harus idempoten, tidak double potong)
        $res2 = $this->post('/finance/kas-bank/backfill-ongkir', [
            'from_date' => '2026-10-01',
            'to_date' => '2026-11-27',
        ]);
        $res2->assertRedirect();

        $this->assertSame(2, FinancePayment::where('payment_type', 'ONGKIR')->count());
        $this->assertSame(975000.0, (float) $this->kasAccount->fresh()->balance);
    }

    public function test_kas_bank_search_query_works_for_ongkir_mutasi_without_unknown_invoice_number_error(): void
    {
        $tx = new MedicineTransactions([
            'pharmacy_id' => 1,
            'user_id' => $this->user->id,
            'transaction_type' => 'HV/OTC',
            'transaction_code' => 'TRX-SEARCH-01',
            'status' => 1,
            'subtotal' => 50000,
            'discount' => 0,
            'payment_method' => 'QRIS',
            'transfer_bank_name' => 'BCA',
            'has_doctor_shipping_fee' => true,
            'doctor_shipping_fee' => 15000,
            'doctor_shipping_payment_method' => 'QRIS',
            'doctor_shipping_bank_name' => 'BCA',
        ]);
        $tx->forceFill(['created_at' => '2026-11-15 10:00:00', 'updated_at' => '2026-11-15 10:00:00'])->save();

        FinancePayment::create([
            'pharmacy_id' => 1,
            'medicine_transaction_id' => $tx->id,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'ONGKIR',
            'payment_date' => '2026-11-15',
            'amount' => 15000,
            'reference_number' => 'TRX-SEARCH-01',
            'notes' => 'Pengeluaran talangan ongkir dari Kas Apotek (QRIS melalui BCA)',
            'created_by' => $this->user->id,
        ]);

        $controller = new class extends \App\Http\Controllers\FinanceController {
            public function queryMutasi(\Illuminate\Http\Request $req, string $start, string $end, array $ids)
            {
                return $this->getPaginatedMutasiData($req, $start, $end, $ids);
            }
        };

        $req = \Illuminate\Http\Request::create('/finance/kas-bank', 'GET', [
            'search' => 'TRX-SEARCH-01',
            'direction' => 'OUT',
        ]);

        $paginator = $controller->queryMutasi($req, '2026-11-01', '2026-11-30', [1]);

        $this->assertSame(1, $paginator->total());
        $first = $paginator->items()[0];
        $this->assertSame('ONGKIR', $first['category_type']);
        $this->assertSame('Ongkir', $first['type_label']);
        $this->assertSame('OUT', $first['direction']);
    }

    private function createSchema(): void
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->integer('status')->default(1);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('secret_pin')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('debtors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->unsignedBigInteger('shift_logs_id')->nullable();
            $table->string('transaction_type');
            $table->string('service_invoice_type')->nullable();
            $table->string('transaction_code')->nullable();
            $table->decimal('paid', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('changes', 15, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('transfer_bank_name')->nullable();
            $table->boolean('has_doctor_shipping_fee')->default(false);
            $table->decimal('doctor_shipping_fee', 15, 2)->default(0);
            $table->string('doctor_shipping_payment_method')->nullable();
            $table->string('doctor_shipping_bank_name')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('medicine_cart', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->string('quantity')->default('1');
            $table->string('discount')->default('0');
            $table->string('embalase')->nullable();
            $table->string('cart_type')->nullable();
            $table->string('total_price')->default('0');
            $table->string('final_price')->default('0');
            $table->string('service_fee')->nullable();
            $table->string('recipe_status')->nullable();
            $table->string('recipe_number')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('transaction_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_transaction_id');
            $table->unsignedBigInteger('doctor_service_id')->nullable();
            $table->string('service_name');
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('category')->default('Kas & Bank');
            $table->string('account_type')->nullable();
            $table->string('account_number')->nullable();
            $table->decimal('balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->unsignedBigInteger('receiving_detail_id')->nullable();
            $table->unsignedBigInteger('medicine_transaction_id')->nullable();
            $table->unsignedBigInteger('opening_invoice_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('transfer_account_id')->nullable();
            $table->unsignedBigInteger('expense_account_id')->nullable();
            $table->unsignedBigInteger('income_account_id')->nullable();
            $table->string('payment_type');
            $table->date('payment_date');
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('recipient')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('account_id');
            $table->decimal('system_balance', 18, 2)->default(0);
            $table->decimal('real_balance', 18, 2)->default(0);
            $table->decimal('difference', 18, 2)->default(0);
            $table->date('adjustment_date');
            $table->timestamp('adjusted_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('finance_payment_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transaction_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_key')->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->timestamps();
        });

        Schema::create('receiving_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('creditor_code')->nullable();
            $table->timestamps();
        });

        Schema::create('creditors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_opening_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->string('invoice_number')->nullable();
            $table->string('creditor_code')->nullable();
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->timestamps();
        });
    }
}
