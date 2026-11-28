<?php

namespace Tests\Unit;

use App\Models\Creditor;
use App\Models\FinanceAccount;
use App\Models\FinanceBalanceAdjustment;
use App\Models\FinancePayment;
use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Services\ConsignmentService;
use App\Services\Finance\NeracaReportService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReconcileNeracaNovemberCommandTest extends TestCase
{
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

        DB::table('finance_accounts')->insert([
            [
                'id' => 15,
                'pharmacy_id' => 2,
                'name' => 'Kas Apotek',
                'category' => 'Kas & Bank',
                'account_type' => 'kas_apotek',
                'balance' => 800000,
                'is_active' => true,
            ],
            [
                'id' => 17,
                'pharmacy_id' => 2,
                'name' => 'Rekening Mandiri',
                'category' => 'Kas & Bank',
                'account_type' => 'bank',
                'balance' => 5051000,
                'is_active' => true,
            ],
        ]);
        $this->kas = FinanceAccount::findOrFail(15);
        $this->bank = FinanceAccount::findOrFail(17);

        Creditor::create([
            'code' => 'PBF01',
            'name' => 'PBF CONTOH',
            'ppn_type' => 'TANPA',
        ]);
    }

    public function test_reconcile_dry_run_leaves_db_untouched_and_apply_fixes_laba_without_shifting_balances(): void
    {
        // Opening balances 2026-11-01 (kas=1.000.000, bank=5.000.000)
        FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->kas->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-11-01',
            'amount' => 1000000,
        ]);
        FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->bank->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-11-01',
            'amount' => 5000000,
        ]);

        // Unrecorded RETUR JUAL (-200.000) on 05 Nov -> Kas physical became 800.000 via OPNAME-OUT 200.000 on 23 Nov
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
        $returTrx->save();

        $kasOp = FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->kas->id,
            'payment_type' => 'PENYESUAIAN SALDO',
            'payment_date' => '2026-11-23',
            'amount' => 200000,
            'reference_number' => 'OPNAME-OUT',
            'notes' => 'Opname Kasir 23 Nov',
        ]);
        FinanceBalanceAdjustment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->kas->id,
            'system_balance' => 1000000,
            'real_balance' => 800000,
            'difference' => -200000,
            'adjustment_date' => '2026-11-23',
            'finance_payment_id' => $kasOp->id,
        ]);

        // Over-recorded sale with discount on Bank (sub=52.000, disc=1.000, net=51.000, fp=52.000)
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
        $saleTrx->save();

        $saleFp = FinancePayment::create([
            'pharmacy_id' => 2,
            'medicine_transaction_id' => $saleTrx->id,
            'account_id' => $this->bank->id,
            'payment_type' => 'PENJUALAN NON TUNAI',
            'payment_date' => '2026-11-03',
            'amount' => 52000,
        ]);

        // Fake August trial payment (500.000) paid on 17 Nov from Bank
        $orderId = DB::table('orders')->insertGetId(['pharmacy_id' => 2, 'status' => 1]);
        $orderItemId = DB::table('order_items')->insertGetId([
            'order_id' => $orderId,
            'creditor_code' => 'PBF01',
            'price' => 500000,
        ]);
        $recAugId = DB::table('receiving')->insertGetId([
            'code' => '2608RE0011',
            'date' => '18/08/2026',
            'pharmacy_id' => 2,
            'status' => 3,
            'created_at' => '2026-08-18 10:00:00',
        ]);
        $rdAugId = DB::table('receiving_details')->insertGetId([
            'receiving_id' => $recAugId,
            'creditor_code' => 'PBF01',
            'invoice_number' => 'TRIAL-01',
            'invoice_payment' => 'KREDIT',
            'created_at' => '2026-08-18 10:00:00',
        ]);
        DB::table('receiving_items')->insert([
            'receiving_details_id' => $rdAugId,
            'order_items_id' => $orderItemId,
            'batches_id' => null,
            'qty_received' => 1,
            'qty' => 1,
            'raw_price' => 500000,
            'total' => 500000,
        ]);
        $trialFp = FinancePayment::create([
            'pharmacy_id' => 2,
            'receiving_id' => $recAugId,
            'receiving_detail_id' => $rdAugId,
            'account_id' => $this->bank->id,
            'payment_type' => 'KREDIT',
            'payment_date' => '2026-11-17',
            'amount' => 500000,
        ]);

        // Bank real balance on 23 Nov = 5.051.000 (book was 5.000.000 + 52.000 - 500.000 = 4.552.000 => OPNAME-IN 499.000)
        $bankOp = FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->bank->id,
            'payment_type' => 'PENYESUAIAN SALDO',
            'payment_date' => '2026-11-23',
            'amount' => 499000,
            'reference_number' => 'OPNAME-IN',
            'notes' => 'Opname Mandiri 23 Nov',
        ]);
        FinanceBalanceAdjustment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->bank->id,
            'system_balance' => 4552000,
            'real_balance' => 5051000,
            'difference' => 499000,
            'adjustment_date' => '2026-11-23',
            'finance_payment_id' => $bankOp->id,
        ]);

        $neracaService = new NeracaReportService();
        $before = $neracaService->build([2], '2026-11-01', '2026-11-30', 2);
        // Before: Kas=0 (return ignored into opname), Bank=+52.000 - 500.000 = -448.000
        $this->assertEqualsWithDelta(-448000.0, $before['laba_tanpa_stok']['selisih'], 0.01);

        // 1. Dry-run changes nothing
        $dryCode = Artisan::call('finance:reconcile-neraca', ['--pharmacy_id' => 2]);
        $this->assertSame(0, $dryCode);
        $this->assertNotNull(FinancePayment::find($trialFp->id));
        $this->assertEqualsWithDelta(52000.0, (float) FinancePayment::find($saleFp->id)->amount, 0.01);

        // 2. Apply changes
        $applyCode = Artisan::call('finance:reconcile-neraca', [
            '--pharmacy_id' => 2,
            '--apply' => true,
        ]);
        $this->assertSame(0, $applyCode);

        $this->assertNull(FinancePayment::find($trialFp->id));
        $this->assertEqualsWithDelta(51000.0, (float) FinancePayment::find($saleFp->id)->amount, 0.01);
        $this->assertTrue(
            FinancePayment::where('medicine_transaction_id', $returTrx->id)
                ->where('payment_type', 'RETUR PENJUALAN')
                ->where('amount', 200000)
                ->exists()
        );

        // Ending balances per 30 Nov (= 1 Dec opening) & live DB balances must be 100% IDENTICAL!
        $after = $neracaService->build([2], '2026-11-01', '2026-11-30', 2);
        $this->assertEqualsWithDelta($before['items'][0]['akhir'], $after['items'][0]['akhir'], 0.01);
        $this->assertEqualsWithDelta($before['items'][1]['akhir'], $after['items'][1]['akhir'], 0.01);
        $this->assertEqualsWithDelta($before['harta']['akhir'], $after['harta']['akhir'], 0.01);
        $this->assertEqualsWithDelta($before['saldo']['akhir'], $after['saldo']['akhir'], 0.01);
        $this->assertEqualsWithDelta(800000.0, (float) FinanceAccount::find($this->kas->id)->balance, 0.01);
        $this->assertEqualsWithDelta(5051000.0, (float) FinanceAccount::find($this->bank->id)->balance, 0.01);

        // Laba Tanpa Stok now equals true operational result: +51.000 - 200.000 = -149.000
        $this->assertEqualsWithDelta(-149000.0, $after['laba_tanpa_stok']['selisih'], 0.01);
        $this->assertEqualsWithDelta(0.0, $after['penyesuaian_non_operasional']['opname_kas_bank'], 0.01);

        // 3. Running --apply a second time is strictly idempotent
        $secondCode = Artisan::call('finance:reconcile-neraca', [
            '--pharmacy_id' => 2,
            '--apply' => true,
        ]);
        $this->assertSame(0, $secondCode);
        $afterSecond = $neracaService->build([2], '2026-11-01', '2026-11-30', 2);
        $this->assertEqualsWithDelta(-149000.0, $afterSecond['laba_tanpa_stok']['selisih'], 0.01);
    }

    public function test_reconcile_aborts_safely_when_target_account_has_no_post_event_opname(): void
    {
        FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->kas->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-11-01',
            'amount' => 1000000,
        ]);

        // Unrecorded return WITHOUT any PENYESUAIAN SALDO on Kas afterward
        $returTrx = new MedicineTransactions([
            'pharmacy_id' => 2,
            'transaction_code' => 'RT',
            'transaction_type' => 'RETUR JUAL',
            'payment_method' => 'CASH',
            'subtotal' => 50000,
            'status' => 1,
        ]);
        $returTrx->created_at = '2026-11-05 14:00:00';
        $returTrx->save();

        $code = Artisan::call('finance:reconcile-neraca', [
            '--pharmacy_id' => 2,
            '--apply' => true,
        ]);

        $this->assertSame(1, $code);
        $this->assertFalse(
            FinancePayment::where('medicine_transaction_id', $returTrx->id)
                ->where('payment_type', 'RETUR PENJUALAN')
                ->exists()
        );
    }
}
