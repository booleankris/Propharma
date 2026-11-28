<?php

namespace Tests\Unit;

use App\Models\Creditor;
use App\Models\FinanceAccount;
use App\Models\FinanceBalanceAdjustment;
use App\Models\FinancePayment;
use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Services\ConsignmentService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditNeracaReconciliationCommandTest extends TestCase
{
    private Pharmacies $branch;
    private FinanceAccount $kas;
    private FinanceAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 11, 28, 12, 0, 0));
        ConsignmentService::clearStockFilterCache();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('creditors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('ppn_type')->default('TANPA');
            $table->timestamps();
        });

        Schema::create('debtors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->decimal('raw_price', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('name')->nullable();
            $table->date('expired_date')->nullable();
            $table->decimal('stock', 15, 2)->default(0);
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('medicine_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('medicine_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_transfer_id')->nullable();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->unsignedBigInteger('etalases_id')->nullable();
            $table->string('source_type')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('original_qty', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->string('creditor_code')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('date')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->integer('status')->default(2);
            $table->timestamps();
        });

        Schema::create('receiving_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id');
            $table->string('sp_code')->nullable();
            $table->string('receiving_details_code')->nullable();
            $table->string('creditor_code')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('invoice_date')->nullable();
            $table->string('invoice_due')->nullable();
            $table->string('invoice_payment')->nullable();
            $table->string('invoice_ppn')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->timestamps();
        });

        Schema::create('receiving_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_details_id');
            $table->unsignedBigInteger('order_items_id')->nullable();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->decimal('qty_received', 15, 2)->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('raw_price', 15, 2)->nullable();
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('extra_discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('items_log', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->string('type')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('qty_before', 15, 2)->default(0);
            $table->decimal('qty_after', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->dateTime('date')->nullable();
            $table->integer('status')->default(2);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('return_no')->nullable();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('status')->default('selesai');
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_return_id');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('status')->default('diajukan');
            $table->string('status_date')->nullable();
            $table->string('deduction_date')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->string('transaction_code')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('paid', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('embalase', 15, 2)->default(0);
            $table->decimal('service_fee', 15, 2)->default(0);
            $table->boolean('is_submitted')->default(false);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('medicine_cart', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('item_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->decimal('final_price', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('transaction_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_transaction_id');
            $table->unsignedBigInteger('doctor_service_id')->nullable();
            $table->string('service_name')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('retur', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->decimal('total_retur', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('category')->default('Kas & Bank');
            $table->string('account_type')->nullable();
            $table->string('account_number')->nullable();
            $table->decimal('balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
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

        Schema::create('finance_opening_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('type');
            $table->string('creditor_code')->nullable();
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->string('invoice_number');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('is_submitted')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
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
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        DB::table('pharmacies')->insert([
            'id' => 2,
            'name' => 'SAHABAT MULAWARMAN',
            'status' => 1,
        ]);
        $this->branch = Pharmacies::findOrFail(2);

        DB::table('finance_accounts')->insert([
            [
                'id' => 70,
                'pharmacy_id' => 2,
                'name' => 'KASIR MULAWARMAN',
                'category' => 'Kas & Bank',
                'account_type' => 'kas_apotek',
                'balance' => 1000000,
                'is_active' => true,
            ],
            [
                'id' => 71,
                'pharmacy_id' => 2,
                'name' => 'BANK MANDIRI',
                'category' => 'Kas & Bank',
                'account_type' => 'bank',
                'balance' => 5000000,
                'is_active' => true,
            ],
        ]);
        $this->kas = FinanceAccount::findOrFail(70);
        $this->bank = FinanceAccount::findOrFail(71);

        Creditor::create([
            'code' => 'PBF01',
            'name' => 'PBF CONTOH',
            'ppn_type' => 'TANPA',
        ]);
    }

    public function test_audit_command_reports_all_reconciliation_anomalies_without_mutating_data(): void
    {
        FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->kas->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-10-31',
            'amount' => 1000000,
        ]);

        $opPay = FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->kas->id,
            'payment_type' => 'PENYESUAIAN SALDO',
            'payment_date' => '2026-11-17',
            'amount' => 250000,
            'reference_number' => 'OPNAME-IN',
            'notes' => 'Opname Kasir 17 Nov',
        ]);

        FinanceBalanceAdjustment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->kas->id,
            'system_balance' => 750000,
            'real_balance' => 1000000,
            'difference' => 250000,
            'adjustment_date' => '2026-11-17',
            'finance_payment_id' => $opPay->id,
        ]);

        // 1. Missing RETUR PENJUALAN payment (05 Nov -> before Opname 17 Nov)
        $returTrx = new MedicineTransactions([
            'pharmacy_id' => 2,
            'transaction_code' => 'RT',
            'transaction_type' => 'RETUR JUAL',
            'payment_method' => 'CASH',
            'subtotal' => 200000,
            'discount' => 0,
            'status' => 1,
        ]);
        $returTrx->created_at = '2026-11-05 14:00:00';
        $returTrx->updated_at = '2026-11-05 14:00:00';
        $returTrx->save();

        // 2. Sale with discount recorded at gross subtotal
        $saleTrx = new MedicineTransactions([
            'pharmacy_id' => 2,
            'transaction_code' => '26112002602',
            'transaction_type' => 'PENJUALAN',
            'payment_method' => 'TRANSFER',
            'subtotal' => 52000,
            'discount' => 1000,
            'paid' => 51000,
            'status' => 1,
        ]);
        $saleTrx->created_at = '2026-11-03 10:00:00';
        $saleTrx->updated_at = '2026-11-03 10:00:00';
        $saleTrx->save();

        FinancePayment::create([
            'pharmacy_id' => 2,
            'medicine_transaction_id' => $saleTrx->id,
            'account_id' => $this->bank->id,
            'payment_type' => 'PENJUALAN NON TUNAI',
            'payment_date' => '2026-11-03',
            'amount' => 52000,
        ]);

        // 3. Payment without active receiving_details (like deleted 2610RE0158)
        $recDeletedId = DB::table('receiving')->insertGetId([
            'code' => '2610RE0158',
            'date' => '05/10/2026',
            'pharmacy_id' => 2,
            'status' => 3,
            'created_at' => '2026-10-05 19:56:13',
            'updated_at' => '2026-11-17 15:45:46',
        ]);

        FinancePayment::create([
            'pharmacy_id' => 2,
            'receiving_id' => $recDeletedId,
            'receiving_detail_id' => null,
            'account_id' => $this->kas->id,
            'payment_type' => 'KREDIT',
            'payment_date' => '2026-11-17',
            'amount' => 155144,
            'notes' => 'Pelunasan massal',
        ]);

        // 4. Payment against unbatched receiving (with_batch = 0, like 2608RE0011)
        $orderId = DB::table('orders')->insertGetId(['pharmacy_id' => 2, 'status' => 1]);
        $orderItemId = DB::table('order_items')->insertGetId([
            'order_id' => $orderId,
            'creditor_code' => 'PBF01',
            'price' => 100000,
        ]);

        $recUnbatchedId = DB::table('receiving')->insertGetId([
            'code' => '2608RE0011',
            'date' => '20/08/2026',
            'pharmacy_id' => 2,
            'status' => 2,
            'created_at' => '2026-08-20 10:00:00',
        ]);
        $rdUnbatchedId = DB::table('receiving_details')->insertGetId([
            'receiving_id' => $recUnbatchedId,
            'creditor_code' => 'PBF01',
            'invoice_number' => 'INV-AUG-01',
            'invoice_payment' => 'KREDIT',
            'created_at' => '2026-08-20 10:00:00',
        ]);
        DB::table('receiving_items')->insert([
            'receiving_details_id' => $rdUnbatchedId,
            'order_items_id' => $orderItemId,
            'batches_id' => null,
            'qty_received' => 5,
            'qty' => 5,
            'raw_price' => 100000,
            'total' => 500000,
        ]);
        FinancePayment::create([
            'pharmacy_id' => 2,
            'receiving_id' => $recUnbatchedId,
            'receiving_detail_id' => $rdUnbatchedId,
            'account_id' => $this->bank->id,
            'payment_type' => 'KREDIT',
            'payment_date' => '2026-11-17',
            'amount' => 500000,
        ]);

        // 5. Cross-month receiving_detail (rec_date Oct, rd.created_at Nov 2)
        $batchId = DB::table('batches')->insertGetId([
            'medicine_id' => 1,
            'pharmacy_id' => 2,
            'stock' => 10,
            'status' => 1,
        ]);
        $recCrossId = DB::table('receiving')->insertGetId([
            'code' => '2610RE9999',
            'date' => '30/10/2026',
            'pharmacy_id' => 2,
            'status' => 2,
            'created_at' => '2026-10-30 12:00:00',
        ]);
        $rdCrossId = DB::table('receiving_details')->insertGetId([
            'receiving_id' => $recCrossId,
            'creditor_code' => 'PBF01',
            'invoice_number' => '2809574434',
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'TANPA',
            'created_at' => '2026-11-02 09:00:00',
        ]);
        DB::table('receiving_items')->insert([
            'receiving_details_id' => $rdCrossId,
            'order_items_id' => $orderItemId,
            'batches_id' => $batchId,
            'qty_received' => 3,
            'qty' => 3,
            'raw_price' => 100000,
            'total' => 300000,
        ]);

        $paymentCountBefore = FinancePayment::count();
        $kasBalanceBefore = (float) FinanceAccount::find($this->kas->id)->balance;

        $exitCode = Artisan::call('finance:audit-neraca-reconciliation', [
            '--pharmacy_id' => 2,
            '--start' => '2026-11-01',
            '--end' => '2026-11-30',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('AUDIT REKONSILIASI NERACA (READ-ONLY)', $output);
        $this->assertStringContainsString('200.000,00', $output);
        $this->assertStringContainsString('YA (perlu penyeimbang Opname)', $output);
        $this->assertStringContainsString('26112002602', $output);
        $this->assertStringContainsString('1.000,00', $output);
        $this->assertStringContainsString('2610RE0158', $output);
        $this->assertStringContainsString('2608RE0011', $output);
        $this->assertStringContainsString('INV-AUG-01', $output);
        $this->assertStringContainsString('2809574434', $output);
        $this->assertStringContainsString('MASUK_BELI_PERIODE_TP_TGL_FAKTUR_DILUAR', $output);

        // Verify strictly read-only
        $this->assertSame($paymentCountBefore, FinancePayment::count());
        $this->assertSame($kasBalanceBefore, (float) FinanceAccount::find($this->kas->id)->balance);
    }
}
