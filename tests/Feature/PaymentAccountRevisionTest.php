<?php

namespace Tests\Feature;

use App\Models\FinanceAccount;
use App\Models\FinanceBalanceAdjustment;
use App\Models\FinancePayment;
use App\Models\User;
use App\Services\Finance\PayablesReceivablesService;
use App\Support\FinanceAccountBalanceResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentAccountRevisionTest extends TestCase
{
    private User $user;
    private FinanceAccount $bcaAccount;
    private FinanceAccount $mandiriAccount;
    private FinanceAccount $kasAccount;

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
            'name' => 'STAF FINANCE',
            'username' => 'staf_fin',
            'password' => bcrypt('secret'),
            'secret_pin' => '1234',
            'pharmacy_id' => 1,
        ]);

        $this->kasAccount = FinanceAccount::create([
            'code' => '1-10001',
            'pharmacy_id' => 1,
            'name' => 'Kas Apotek',
            'category' => 'Kas & Bank',
            'account_type' => 'kas_apotek',
            'balance' => 1000000,
            'is_active' => true,
        ]);

        $this->bcaAccount = FinanceAccount::create([
            'code' => '1-10002',
            'pharmacy_id' => 1,
            'name' => 'Bank BCA',
            'category' => 'Kas & Bank',
            'account_type' => 'bank',
            'balance' => 10000000,
            'is_active' => true,
        ]);

        $this->mandiriAccount = FinanceAccount::create([
            'code' => '1-10003',
            'pharmacy_id' => 1,
            'name' => 'Bank Mandiri',
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

    public function test_revise_hutang_payment_account_moves_balance_without_breaking_saldo_awal_and_rejects_cutoff(): void
    {
        $openingBca = FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->bcaAccount->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-10-31',
            'amount' => 10000000,
            'created_by' => $this->user->id,
        ]);
        $openingMandiri = FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->mandiriAccount->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-10-31',
            'amount' => 5000000,
            'created_by' => $this->user->id,
        ]);

        // Pembayaran Hutang salah pilih Bank BCA senilai 1.250.000 pada 05 Nov
        $kreditPayment = FinancePayment::create([
            'pharmacy_id' => 1,
            'receiving_id' => 1,
            'receiving_detail_id' => 10,
            'account_id' => $this->bcaAccount->id,
            'payment_type' => 'KREDIT',
            'payment_date' => '2026-11-05',
            'amount' => 1250000,
            'reference_number' => 'TRF-WRONG-01',
            'notes' => 'Bayar PBF A',
            'created_by' => $this->user->id,
        ]);
        $this->bcaAccount->update(['balance' => 8750000]);

        $response = $this->put(route('finance.payments.updateAccount', $kreditPayment->id), [
            'account_id' => $this->mandiriAccount->id,
            'reference_number' => 'TRF-MANDIRI-01',
            'revision_reason' => 'Salah pilih rekening transfer',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $kreditPayment->refresh();
        $this->bcaAccount->refresh();
        $this->mandiriAccount->refresh();
        $openingBca->refresh();
        $openingMandiri->refresh();

        $this->assertSame($this->mandiriAccount->id, (int) $kreditPayment->account_id);
        $this->assertSame('TRF-MANDIRI-01', $kreditPayment->reference_number);
        $this->assertSame(1250000.0, (float) $kreditPayment->amount);
        $this->assertSame('2026-11-05', $kreditPayment->payment_date->format('Y-m-d'));
        $this->assertStringContainsString('[Revisi Akun: Bank BCA -> Bank Mandiri oleh STAF FINANCE', (string) $kreditPayment->notes);
        $this->assertStringContainsString('Salah pilih rekening transfer', (string) $kreditPayment->notes);

        // Saldo Awal 31 Okt tetap utuh, saldo akhir BCA kembali 10jt, Mandiri jadi 3.75jt
        $this->assertEquals(10000000.0, (float) $openingBca->amount);
        $this->assertEquals(5000000.0, (float) $openingMandiri->amount);
        $this->assertEquals(10000000.0, (float) $this->bcaAccount->balance);
        $this->assertEquals(3750000.0, (float) $this->mandiriAccount->balance);
        $this->assertEquals(13750000.0, (float) $this->bcaAccount->balance + (float) $this->mandiriAccount->balance);

        // Cutoff tidak boleh direvisi akunnya
        $cutoffPayment = FinancePayment::create([
            'pharmacy_id' => 1,
            'receiving_id' => 1,
            'receiving_detail_id' => 11,
            'account_id' => null,
            'payment_type' => 'KREDIT CUTOFF',
            'payment_date' => '2026-10-31',
            'amount' => 500000,
            'created_by' => $this->user->id,
        ]);

        $this->withExceptionHandling();
        $cutoffResp = $this->put(route('finance.payments.updateAccount', $cutoffPayment->id), [
            'account_id' => $this->bcaAccount->id,
        ]);
        $cutoffResp->assertSessionHasErrors('payment_id');
    }

    public function test_revise_piutang_payment_account_moves_inflow_balance_and_exposes_akun_id(): void
    {
        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->bcaAccount->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-10-31',
            'amount' => 10000000,
            'created_by' => $this->user->id,
        ]);
        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->mandiriAccount->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-10-31',
            'amount' => 5000000,
            'created_by' => $this->user->id,
        ]);

        DB::table('debtors')->insert(['id' => 1, 'name' => 'BPJS Kesehatan', 'code' => 'DEB-01']);
        DB::table('medicine_transactions')->insert([
            'id' => 77,
            'pharmacy_id' => 1,
            'transaction_code' => 'TRX-PIU-077',
            'transaction_type' => 'KREDIT',
            'payment_method' => 'KREDIT',
            'debtor_id' => 1,
            'subtotal' => 2000000,
            'paid' => 0,
            'total_price' => '2000000',
            'final_price' => '2000000',
            'status' => 1,
            'created_at' => '2026-11-02 10:00:00',
            'updated_at' => '2026-11-02 10:00:00',
        ]);

        // Pelunasan piutang masuk salah ke Kas Apotek -> harusnya Bank Mandiri
        $piutangPayment = FinancePayment::create([
            'pharmacy_id' => 1,
            'medicine_transaction_id' => 77,
            'account_id' => $this->bcaAccount->id,
            'payment_type' => 'PIUTANG',
            'payment_date' => '2026-11-06',
            'amount' => 2000000,
            'created_by' => $this->user->id,
        ]);
        $this->bcaAccount->update(['balance' => 12000000]);

        $serviceDataBefore = app(PayablesReceivablesService::class)->getPiutangDetail(77, [1]);
        $this->assertNotNull($serviceDataBefore);
        $this->assertNotEmpty($serviceDataBefore['payments']);
        $this->assertSame($this->bcaAccount->id, (int) $serviceDataBefore['payments'][0]['akunId']);
        $this->assertSame('2026-11-06', $serviceDataBefore['payments'][0]['rawTanggal']);

        $response = $this->put(route('finance.payments.updateAccount', $piutangPayment->id), [
            'account_id' => $this->mandiriAccount->id,
        ]);
        $response->assertSessionHasNoErrors();

        $this->bcaAccount->refresh();
        $this->mandiriAccount->refresh();

        $this->assertEquals(10000000.0, (float) $this->bcaAccount->balance);
        $this->assertEquals(7000000.0, (float) $this->mandiriAccount->balance);
        $this->assertEquals(17000000.0, (float) $this->bcaAccount->balance + (float) $this->mandiriAccount->balance);
    }

    public function test_revise_pre_opening_and_pre_opname_payment_does_not_corrupt_live_balances(): void
    {
        // Kasus 1: Kedua bank punya SALDO AWAL 31 Okt; mutasi yg direvisi bertanggal 25 Okt (< SALDO AWAL)
        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->bcaAccount->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-10-31',
            'amount' => 10000000,
            'created_by' => $this->user->id,
        ]);
        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->mandiriAccount->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-10-31',
            'amount' => 5000000,
            'created_by' => $this->user->id,
        ]);

        $preOpeningPayment = FinancePayment::create([
            'pharmacy_id' => 1,
            'receiving_id' => 2,
            'receiving_detail_id' => 20,
            'account_id' => $this->bcaAccount->id,
            'payment_type' => 'KREDIT',
            'payment_date' => '2026-10-25',
            'amount' => 900000,
            'created_by' => $this->user->id,
        ]);

        $this->put(route('finance.payments.updateAccount', $preOpeningPayment->id), [
            'account_id' => $this->mandiriAccount->id,
        ])->assertSessionHasNoErrors();

        $preOpeningPayment->refresh();
        $this->bcaAccount->refresh();
        $this->mandiriAccount->refresh();

        $this->assertSame($this->mandiriAccount->id, (int) $preOpeningPayment->account_id);
        $this->assertEquals(10000000.0, (float) $this->bcaAccount->balance);
        $this->assertEquals(5000000.0, (float) $this->mandiriAccount->balance);

        // Kasus 2: Kas Apotek tidak punya SALDO AWAL, hanya punya Opname tanggal 2026-11-10 = 1.500.000
        FinanceBalanceAdjustment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'system_balance' => 1400000,
            'real_balance' => 1500000,
            'difference' => 100000,
            'adjustment_date' => '2026-11-10',
            'adjusted_at' => now(),
            'created_by' => $this->user->id,
        ]);
        // Mutasi setelah opname (+300rb -> saldo live 1.8jt)
        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'PENJUALAN TUNAI',
            'payment_date' => '2026-11-12',
            'amount' => 300000,
            'created_by' => $this->user->id,
        ]);
        $this->kasAccount->update(['balance' => 1800000]);

        // Mutasi sebelum/pada tanggal opname (<= 2026-11-10) dipindahkan dari Kas ke Mandiri
        $preOpnamePayment = FinancePayment::create([
            'pharmacy_id' => 1,
            'receiving_id' => 3,
            'receiving_detail_id' => 30,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'KREDIT',
            'payment_date' => '2026-11-09',
            'amount' => 400000,
            'created_by' => $this->user->id,
        ]);

        $this->put(route('finance.payments.updateAccount', $preOpnamePayment->id), [
            'account_id' => $this->mandiriAccount->id,
        ])->assertSessionHasNoErrors();

        $this->kasAccount->refresh();
        $this->mandiriAccount->refresh();

        // Kas Apotek tetap 1.800.000 (Opname 1.5jt + 300rb sesudah opname), Mandiri berkurang 400rb karena 09 Nov >= SALDO AWAL 31 Okt
        $this->assertEquals(1800000.0, (float) $this->kasAccount->balance);
        $this->assertEquals(4600000.0, (float) $this->mandiriAccount->balance);
    }

    public function test_assign_cash_account_preserves_existing_date_and_respects_saldo_awal(): void
    {
        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-10-31',
            'amount' => 1000000,
            'created_by' => $this->user->id,
        ]);
        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->bcaAccount->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-10-31',
            'amount' => 10000000,
            'created_by' => $this->user->id,
        ]);

        DB::table('receiving')->insert([
            'id' => 50,
            'pharmacy_id' => 1,
            'status' => 3,
        ]);
        DB::table('receiving_details')->insert([
            'id' => 500,
            'receiving_id' => 50,
            'invoice_number' => 'CASH-001',
            'invoice_ppn' => 'TANPA',
            'invoice_payment' => 'TUNAI',
        ]);
        DB::table('receiving_items')->insert([
            'id' => 5000,
            'receiving_details_id' => 500,
            'qty_received' => 2,
            'raw_price' => 150000,
            'total' => 300000,
        ]);

        $cashPayment = FinancePayment::create([
            'pharmacy_id' => 1,
            'receiving_id' => 50,
            'receiving_detail_id' => 500,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'CASH',
            'payment_date' => '2026-11-03',
            'amount' => 300000,
            'created_by' => $this->user->id,
        ]);
        $this->kasAccount->update(['balance' => 700000]);

        // Revisi akun cash dari Kas Apotek -> Bank BCA tanpa mengirim payment_date baru
        $this->post(route('finance.cash.assign'), [
            'receiving_detail_id' => 500,
            'account_id' => $this->bcaAccount->id,
            'reference_number' => 'CASH-REVISI-BCA',
        ])->assertSessionHasNoErrors();

        $cashPayment->refresh();
        $this->kasAccount->refresh();
        $this->bcaAccount->refresh();

        $this->assertSame($this->bcaAccount->id, (int) $cashPayment->account_id);
        $this->assertSame('2026-11-03', $cashPayment->payment_date->format('Y-m-d'));
        $this->assertEquals(1000000.0, (float) $this->kasAccount->balance);
        $this->assertEquals(9700000.0, (float) $this->bcaAccount->balance);
    }

    private function createSchema(): void
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('status')->default(1);
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

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->string('code')->nullable();
            $table->date('date')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('receiving_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('creditor_code')->nullable();
            $table->string('invoice_ppn')->nullable();
            $table->string('invoice_payment')->nullable();
            $table->timestamps();
        });

        Schema::create('receiving_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_details_id')->nullable();
            $table->unsignedBigInteger('order_items_id')->nullable();
            $table->decimal('qty_received', 15, 2)->default(0);
            $table->decimal('raw_price', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('creditors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->string('ppn_type')->nullable();
            $table->timestamps();
        });

        Schema::create('debtors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('transaction_code')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('paid', 15, 2)->default(0);
            $table->string('total_price')->default('0');
            $table->string('final_price')->default('0');
            $table->integer('status')->default(1);
            $table->integer('is_submitted')->default(0);
            $table->timestamps();
        });

        Schema::create('medicine_cart', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('item_price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->string('package')->nullable();
            $table->decimal('total_price', 15, 2)->default(0);
            $table->decimal('final_price', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('retur', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->decimal('qty_retur', 15, 2)->default(0);
            $table->decimal('total_retur', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('finance_opening_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->string('type')->default('HUTANG');
            $table->string('invoice_number')->nullable();
            $table->string('creditor_code')->nullable();
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->integer('is_submitted')->default(0);
            $table->timestamps();
        });
    }
}
