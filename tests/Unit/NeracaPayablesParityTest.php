<?php

namespace Tests\Unit;

use App\Services\Finance\NeracaReportService;
use App\Services\Finance\PayablesReceivablesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NeracaPayablesParityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

        Schema::create('creditors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('ppn_type')->default('EXCLUDE');
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
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('date')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('receiving_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id');
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
            $table->integer('status')->default(2);
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('return_no')->nullable();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('status')->default('diajukan');
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_return_id');
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->string('medicine_name')->nullable();
            $table->string('batch_number')->nullable();
            $table->string('expired_date')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('status')->default('diajukan');
            $table->string('status_date')->nullable();
            $table->string('deduction_date')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_opening_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('type');
            $table->string('creditor_code')->nullable();
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->string('invoice_number');
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('is_submitted')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->unsignedBigInteger('receiving_detail_id')->nullable();
            $table->unsignedBigInteger('opening_invoice_id')->nullable();
            $table->unsignedBigInteger('medicine_transaction_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('payment_type')->nullable();
            $table->date('payment_date')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('reference_number')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        DB::table('pharmacies')->insert([
            ['id' => 2, 'name' => 'SAHABAT MULAWARMAN', 'status' => 1],
        ]);

        DB::table('creditors')->insert([
            ['code' => 'PBF1', 'name' => 'MAHAKAM SEJAHTERA ABADI', 'ppn_type' => 'EXCLUDE'],
        ]);
    }

    public function test_neraca_payables_matches_hutang_dagang_with_discounts_unposted_items_returns_and_opening_balance(): void
    {
        // Invoice 1: discounted credit invoice (EXCLUDE 11% PPN) + unposted batches_id=null row that must be ignored
        // Posted row: qty=10 * 100000 = 1,000,000; discount=20% (200,000), extra_discount=10% (100,000) -> net 700,000 + 11% PPN = 777,000
        $rec1 = DB::table('receiving')->insertGetId([
            'code' => 'NT-26-11/0001',
            'date' => '20/11/2026',
            'pharmacy_id' => 2,
            'status' => 1,
            'created_at' => '2026-11-20 10:00:00',
            'updated_at' => '2026-11-20 10:00:00',
        ]);
        $det1 = DB::table('receiving_details')->insertGetId([
            'receiving_id' => $rec1,
            'creditor_code' => 'PBF1',
            'invoice_number' => 'INV-DISC-01',
            'invoice_date' => '2026-11-20',
            'invoice_due' => '2026-12-20',
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'EXCLUDE',
        ]);
        DB::table('receiving_items')->insert([
            [
                'receiving_details_id' => $det1,
                'batches_id' => 501,
                'qty_received' => 10,
                'qty' => 10,
                'raw_price' => 100000,
                'discount' => 20,
                'extra_discount' => 10,
                'total' => 777000,
            ],
            [
                // Unposted / deleted line without batches_id -> must NOT inflate Neraca
                'receiving_details_id' => $det1,
                'batches_id' => null,
                'qty_received' => 5,
                'qty' => 5,
                'raw_price' => 100000,
                'discount' => 0,
                'extra_discount' => 0,
                'total' => 500000,
            ],
        ]);
        // Purchase return with pharmacy_id=null, status=sudah_potong matching invoice_number -> deducts 77,000 -> net 700,000
        $pr1 = DB::table('purchase_returns')->insertGetId([
            'pharmacy_id' => null,
            'return_no' => 'RET-01',
            'receiving_id' => $det1,
            'invoice_number' => 'INV-DISC-01',
            'status' => 'selesai',
            'created_at' => '2026-11-22 10:00:00',
        ]);
        DB::table('purchase_return_items')->insert([
            [
                'purchase_return_id' => $pr1,
                'medicine_name' => 'OBAT A',
                'qty' => 1,
                'unit_price' => 77000,
                'subtotal' => 77000,
                'status' => 'sudah_potong',
                'deduction_date' => '2026-11-22',
            ],
            [
                // Not yet deducted -> must NOT cut payable yet
                'purchase_return_id' => $pr1,
                'medicine_name' => 'OBAT B',
                'qty' => 1,
                'unit_price' => 50000,
                'subtotal' => 50000,
                'status' => 'diajukan',
                'deduction_date' => null,
            ],
        ]);
        // Partial payment on 2026-11-23 of 200,000 -> leaves 500,000
        DB::table('finance_payments')->insert([
            'receiving_detail_id' => $det1,
            'pharmacy_id' => 2,
            'payment_type' => 'HUTANG',
            'payment_date' => '2026-11-23',
            'amount' => 200000,
        ]);

        // Invoice 2: raw_price is null, qty_received is null, falls back to order_items.price (50,000) & ri.qty (4), TANPA PPN -> 200,000
        $orderItemId = DB::table('order_items')->insertGetId(['price' => 50000]);
        $rec2 = DB::table('receiving')->insertGetId([
            'code' => 'NT-26-11/0002',
            'date' => '21/11/2026',
            'pharmacy_id' => 2,
            'status' => 1,
            'created_at' => '2026-11-21 10:00:00',
            'updated_at' => '2026-11-21 10:00:00',
        ]);
        $det2 = DB::table('receiving_details')->insertGetId([
            'receiving_id' => $rec2,
            'creditor_code' => 'PBF1',
            'invoice_number' => 'INV-FALLBACK-02',
            'invoice_date' => '2026-11-21',
            'invoice_due' => '2026-12-21',
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'TANPA',
        ]);
        DB::table('receiving_items')->insert([
            'receiving_details_id' => $det2,
            'order_items_id' => $orderItemId,
            'batches_id' => 502,
            'qty_received' => null,
            'qty' => 4,
            'raw_price' => null,
            'discount' => 0,
            'extra_discount' => 0,
            'total' => 200000,
        ]);

        // Opening invoice balance (+150,000) and negative opening adjustment (-50,000) -> +100,000
        DB::table('finance_opening_invoices')->insert([
            [
                'pharmacy_id' => 2,
                'type' => 'HUTANG',
                'creditor_code' => 'PBF1',
                'invoice_number' => 'OB-1',
                'invoice_date' => '2026-10-31',
                'due_date' => '2026-11-30',
                'amount' => 150000,
            ],
            [
                'pharmacy_id' => 2,
                'type' => 'HUTANG',
                'creditor_code' => 'PBF1',
                'invoice_number' => 'OB-ADJ-1',
                'invoice_date' => '2026-10-31',
                'due_date' => '2026-10-31',
                'amount' => -50000,
            ],
        ]);

        // Expected total as of 2026-11-24: 500,000 + 200,000 + 100,000 = 800,000
        $payablesService = app(PayablesReceivablesService::class);
        $neracaService = app(NeracaReportService::class);

        $hutangDataset = $payablesService->getHutangDataset([2], [], false, null, 1, true);
        $hutangTotal = (float) $hutangDataset['stats']['totalHutangSisa'];
        $neracaPayable = $neracaService->payableBalanceAtDate([2], '2026-11-24');

        $this->assertSame(800000.0, $hutangTotal);
        $this->assertSame(800000.0, $neracaPayable);

        // Also check historical cutoff as of 2026-10-31 (only opening balance 100,000 exists)
        $this->assertSame(100000.0, $neracaService->payableBalanceAtDate([2], '2026-10-31'));

        // Cutoff on 2026-11-22 (before 200,000 payment on 2026-11-23, but after return on 2026-11-22): 700k + 200k + 100k = 1,000,000
        $this->assertSame(1000000.0, $neracaService->payableBalanceAtDate([2], '2026-11-22'));
    }
}
