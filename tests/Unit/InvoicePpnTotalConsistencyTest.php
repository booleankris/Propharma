<?php

namespace Tests\Unit;

use App\Exports\Orders\InvoiceSingleSheetExport;
use App\Exports\Orders\OrdersSingleSheetExport;
use App\Exports\Orders\PurchasePaymentExport;
use App\Http\Controllers\Orders\ReceivingController;
use App\Services\Finance\CashflowTradeReportService;
use App\Services\Finance\NeracaReportService;
use App\Services\Finance\PayablesReceivablesService;
use App\Support\ReceivingInvoiceTotals;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvoicePpnTotalConsistencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 11, 28, 12, 0, 0));

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('creditors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('ppn_type')->default('EXCLUDE');
            $table->timestamps();
        });

        Schema::create('debtors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('factories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('packaging')->nullable();
            $table->string('unit')->nullable();
            $table->unsignedBigInteger('factory_id')->nullable();
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

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
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
            $table->boolean('pack')->nullable();
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
            $table->integer('invoice_times')->nullable();
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
            $table->boolean('receipt_is_pack')->nullable();
            $table->decimal('raw_price', 15, 2)->nullable();
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('extra_discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('batch')->nullable();
            $table->date('expired_date')->nullable();
            $table->timestamps();
        });

        Schema::create('items_log', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->string('type')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->dateTime('date')->nullable();
            $table->integer('status')->default(2);
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
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('name');
            $table->string('category')->default('Kas & Bank');
            $table->string('account_type')->nullable();
            $table->decimal('balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
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
            $table->string('payment_type');
            $table->date('payment_date');
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::table('pharmacies')->insert(['id' => 2, 'name' => 'SAHABAT MULAWARMAN', 'status' => 1]);
        DB::table('creditors')->insert(['id' => 1, 'code' => 'PBF1', 'name' => 'PBF TEST', 'ppn_type' => 'EXCLUDE']);
        DB::table('factories')->insert(['id' => 1, 'name' => 'PABRIK TEST']);
        DB::table('medicines')->insert([
            'id' => 1,
            'code' => 'MED-1',
            'name' => 'OBAT TEST',
            'packaging' => 'BOX',
            'unit' => 'TAB',
            'factory_id' => 1,
            'status' => 1,
        ]);
        DB::table('orders')->insert(['id' => 1, 'code' => 'SP-1', 'pharmacy_id' => 2, 'status' => 1]);
        DB::table('order_items')->insert([
            'id' => 1,
            'order_id' => 1,
            'medicine_id' => 1,
            'creditor_code' => 'PBF1',
            'price' => 1004,
            'pack' => 1,
        ]);
        DB::table('batches')->insert(['id' => 1, 'medicine_id' => 1, 'pharmacy_id' => 2, 'name' => 'B1']);
    }

    public function test_all_purchase_and_finance_reports_compute_ppn_once_at_invoice_total(): void
    {
        $now = '2026-11-15 10:00:00';
        // 5 items * 1004 = 5020.
        // Old per-item round: 5 * (1004 + round(1004*0.11)) = 5 * 1114 = 5570.
        // Authoritative invoice-level PPN: 5020 + floor(5020 * 0.11) = 5020 + 552 = 5572.
        // Plus fractional discount item: 5 * 20213 - 5% = 96011.75 => total net 101031.75, PPN = floor(101031.75*0.11) = 11113, total = 112144.75.
        DB::table('receiving')->insert([
            'id' => 1,
            'pharmacy_id' => 2,
            'code' => 'RCV-01',
            'date' => '2026-11-15',
            'status' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('receiving_details')->insert([
            'id' => 1,
            'receiving_id' => 1,
            'creditor_code' => 'PBF1',
            'receiving_details_code' => 'NT-01',
            'invoice_number' => 'INV-MULTI',
            'invoice_date' => '2026-11-15',
            'invoice_due' => '2026-12-15',
            'invoice_times' => 30,
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'EXCLUDE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            DB::table('receiving_items')->insert([
                'receiving_details_id' => 1,
                'order_items_id' => 1,
                'batches_id' => 1,
                'qty_received' => 1,
                'qty' => 1,
                'raw_price' => 1004,
                'discount' => 0,
                'extra_discount' => 0,
                'total' => 1114, // stale per-item DB total
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::table('receiving_items')->insert([
            'receiving_details_id' => 1,
            'order_items_id' => 1,
            'batches_id' => 1,
            'qty_received' => 5,
            'qty' => 5,
            'raw_price' => 20213,
            'discount' => 5,
            'extra_discount' => 0,
            'total' => 106573,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Per item lineAmounts is purely QTY * HNA - DISC without PPN
        $lineSample = ReceivingInvoiceTotals::lineAmounts((object) [
            'qty_received' => 5,
            'raw_price' => 20213,
            'discount' => 5,
            'extra_discount' => 0,
        ], 'EXCLUDE');
        $this->assertEqualsWithDelta(96011.75, $lineSample['total'], 0.001);
        $this->assertEqualsWithDelta(0.0, $lineSample['ppn'], 0.001);

        // Purchase return deduction helper retains unit tax-inclusive credit note basis
        $this->assertEqualsWithDelta(22200.0, ReceivingInvoiceTotals::linePayableDeductionTotal((object) [
            'qty_received' => 2,
            'raw_price' => 10000,
            'discount' => 0,
            'extra_discount' => 0,
        ], 'EXCLUDE'), 0.01);

        $expectedDpp = 101031.75;
        $expectedPpn = 11113.0;
        $expectedTotal = 112144.75;

        // 1. ReceivingController print summary
        $rcSummary = ReceivingController::computeInvoiceSummaryFromTotals(5 * 1004 + 5 * 20213, 5 * 20213 * 0.05, 'EXCLUDE');
        $this->assertEqualsWithDelta($expectedDpp, $rcSummary['dpp_hna'], 0.01);
        $this->assertEqualsWithDelta($expectedPpn, $rcSummary['ppn'], 0.01);
        $this->assertEqualsWithDelta($expectedTotal, $rcSummary['grand_total'], 0.01);

        // 2. InvoiceSingleSheetExport daily purchases (used by KinerjaOmzetService)
        $daily = InvoiceSingleSheetExport::queryDailyInvoices([2], '2026-11-15', '2026-11-15');
        $this->assertEqualsWithDelta($expectedTotal, $daily['2026-11-15']['pbf'], 0.01);
        $this->assertEqualsWithDelta($expectedPpn, $daily['2026-11-15']['pbf_ppn'], 0.01);
        $this->assertEqualsWithDelta($expectedDpp, $daily['2026-11-15']['pbf_dpp'], 0.01);

        // 3. PurchasePaymentExport
        $paymentRows = (new PurchasePaymentExport(2, '2026-11-15', '2026-11-15', 'Kredit'))->array();
        $this->assertEqualsWithDelta($expectedDpp, (float) $paymentRows[7][6], 0.01);
        $this->assertEqualsWithDelta($expectedPpn, (float) $paymentRows[7][7], 0.01);
        $this->assertEqualsWithDelta($expectedTotal, (float) $paymentRows[7][8], 0.01);

        // 4. OrdersSingleSheetExport (Sheet Detail Obat TOTAL row & sum of rows)
        $ordersRows = (new OrdersSingleSheetExport(2, '2026-11-15', '2026-11-15', null, 'Semua'))->array();
        $totalRow = $ordersRows[count($ordersRows) - 1];
        $this->assertSame('TOTAL', $totalRow[6]);
        $this->assertEqualsWithDelta($expectedDpp, (float) $totalRow[7], 0.01);
        $this->assertEqualsWithDelta($expectedPpn, (float) $totalRow[8], 0.01);
        $this->assertEqualsWithDelta($expectedTotal, (float) $totalRow[26], 0.01);

        // 5. PayablesReceivablesService (Menu Hutang) & NeracaReportService
        $hutang = app(PayablesReceivablesService::class)->getHutangDataset([2], [], false, null, 1, true);
        $this->assertEqualsWithDelta($expectedDpp, (float) $hutang['rows'][0]['subtotal'], 0.01);
        $this->assertEqualsWithDelta($expectedPpn, (float) $hutang['rows'][0]['ppn'], 0.01);
        $this->assertEqualsWithDelta($expectedTotal, (float) $hutang['rows'][0]['total'], 0.01);
        $this->assertEqualsWithDelta($expectedTotal, (new NeracaReportService())->payableBalanceAtDate([2], '2026-11-15'), 0.01);

        // 6. CashflowTradeReportService payables
        $cashflowTrade = (new CashflowTradeReportService())->payables([2], '2026-11-01', '2026-11-30');
        $this->assertEqualsWithDelta($expectedTotal, (float) $cashflowTrade['rows'][0]['total'], 0.01);
        $this->assertEqualsWithDelta($expectedTotal, (float) $cashflowTrade['grandTotal'], 0.01);
    }

    public function test_legacy_full_settlement_prevents_phantom_rounding_sisa_while_preserving_partial_balances(): void
    {
        $now = '2026-10-20 10:00:00';
        // 5 items * 1004 = 5020 net. Legacy per-item total = 5 * 1114 = 5570. New invoice total = 5020 + floor(552.2) = 5572.
        DB::table('receiving')->insert([
            ['id' => 10, 'pharmacy_id' => 2, 'code' => 'RCV-FULL', 'date' => '2026-10-20', 'status' => 3, 'created_at' => $now],
            ['id' => 11, 'pharmacy_id' => 2, 'code' => 'RCV-PART', 'date' => '2026-10-21', 'status' => 2, 'created_at' => $now],
        ]);
        DB::table('receiving_details')->insert([
            [
                'id' => 10,
                'receiving_id' => 10,
                'creditor_code' => 'PBF1',
                'receiving_details_code' => 'NT-FULL',
                'invoice_number' => 'INV-LEGACY-FULL',
                'invoice_date' => '2026-10-20',
                'invoice_due' => '2026-11-20',
                'invoice_payment' => 'KREDIT',
                'invoice_ppn' => 'EXCLUDE',
                'created_at' => $now,
            ],
            [
                'id' => 11,
                'receiving_id' => 11,
                'creditor_code' => 'PBF1',
                'receiving_details_code' => 'NT-PART',
                'invoice_number' => 'INV-PARTIAL',
                'invoice_date' => '2026-10-21',
                'invoice_due' => '2026-11-21',
                'invoice_payment' => 'KREDIT',
                'invoice_ppn' => 'EXCLUDE',
                'created_at' => $now,
            ],
        ]);

        foreach ([10, 11] as $rdId) {
            for ($i = 1; $i <= 5; $i++) {
                DB::table('receiving_items')->insert([
                    'receiving_details_id' => $rdId,
                    'order_items_id' => 1,
                    'batches_id' => 1,
                    'qty_received' => 1,
                    'qty' => 1,
                    'raw_price' => 1004,
                    'discount' => 0,
                    'extra_discount' => 0,
                    'total' => 1114,
                    'created_at' => $now,
                ]);
            }
        }

        DB::table('finance_payments')->insert([
            [
                'pharmacy_id' => 2,
                'receiving_id' => 10,
                'receiving_detail_id' => 10,
                'payment_type' => 'KREDIT',
                'payment_date' => '2026-10-25',
                'amount' => 5570, // exact legacy full payment
            ],
            [
                'pharmacy_id' => 2,
                'receiving_id' => 11,
                'receiving_detail_id' => 11,
                'payment_type' => 'KREDIT',
                'payment_date' => '2026-10-25',
                'amount' => 2000, // genuinely partial
            ],
        ]);

        // INV-LEGACY-FULL must be recognized as Lunas (sisa = 0), INV-PARTIAL must owe 5572 - 2000 = 3572
        $hutang = app(PayablesReceivablesService::class)->getHutangDataset([2], [], false, null, 1, false);
        $fullRow = collect($hutang['rows'])->firstWhere('nomor', 'INV-LEGACY-FULL');
        $partRow = collect($hutang['rows'])->firstWhere('nomor', 'INV-PARTIAL');

        $this->assertSame('Lunas', $fullRow['status']);
        $this->assertEqualsWithDelta(0.0, (float) $fullRow['sisa'], 0.01);
        $this->assertSame('Dibayar Sebagian', $partRow['status']);
        $this->assertEqualsWithDelta(3572.0, (float) $partRow['sisa'], 0.01);

        $neracaRemaining = (new NeracaReportService())->payableBalanceAtDate([2], '2026-10-31');
        $this->assertEqualsWithDelta(3572.0, $neracaRemaining, 0.01);
    }
}
