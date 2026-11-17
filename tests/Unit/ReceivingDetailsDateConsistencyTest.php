<?php

namespace Tests\Unit;

use App\Exports\Finance\CashExport;
use App\Exports\Finance\HutangExport;
use App\Exports\Finance\PiutangExport;
use App\Exports\Orders\OrdersSingleSheetExport;
use App\Exports\Orders\PurchasePaymentExport;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\Orders\ReceivingController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ReceivingDetailsDateConsistencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropAllTables();

        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('creditors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('ppn_type')->nullable();
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
            $table->integer('content')->default(1);
            $table->double('stock')->default(0);
            $table->unsignedBigInteger('factory_id')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(9);
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->string('code')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('medicine_id');
            $table->string('order_items_code')->nullable();
            $table->string('creditor_code')->nullable();
            $table->double('price')->default(0);
            $table->double('total')->default(0);
            $table->boolean('pack')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('name');
            $table->date('expired_date')->nullable();
            $table->double('stock')->default(0);
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->string('code')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('date')->nullable();
            $table->integer('status')->default(2);
            $table->timestamps();
        });

        Schema::create('receiving_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id');
            $table->string('sp_code')->nullable();
            $table->string('creditor_code')->nullable();
            $table->string('receiving_details_code')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('invoice_due')->nullable();
            $table->integer('invoice_times')->nullable();
            $table->string('invoice_payment')->nullable();
            $table->string('invoice_ppn')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->timestamps();
        });

        Schema::create('receiving_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_details_id');
            $table->unsignedBigInteger('order_items_id');
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->double('qty_received')->default(0);
            $table->double('qty')->default(0);
            $table->boolean('receipt_is_pack')->nullable();
            $table->integer('stock_unit_factor')->nullable();
            $table->double('discount')->default(0);
            $table->double('extra_discount')->default(0);
            $table->double('total')->default(0);
            $table->double('raw_price')->default(0);
            $table->string('batch')->nullable();
            $table->date('expired_date')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('items_log', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->nullable();
            $table->string('code')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->double('qty')->default(0);
            $table->double('qty_before')->default(0);
            $table->double('qty_after')->default(0);
            $table->double('total')->default(0);
            $table->dateTime('date')->nullable();
            $table->integer('status')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->string('return_no')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_return_id');
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->string('medicine_name')->nullable();
            $table->string('batch_number')->nullable();
            $table->date('expired_date')->nullable();
            $table->double('qty')->default(0);
            $table->string('unit')->nullable();
            $table->double('unit_price')->default(0);
            $table->double('subtotal')->default(0);
            $table->string('status')->nullable();
            $table->date('status_date')->nullable();
            $table->date('deduction_date')->nullable();
            $table->string('deduction_note')->nullable();
            $table->string('reason')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_detail_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('payment_type')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('notes')->nullable();
            $table->double('amount')->default(0);
            $table->timestamps();
        });

        DB::table('pharmacies')->insert(['id' => 9, 'name' => 'SAHABAT MULAWARMAN', 'address' => 'Samarinda']);
        DB::table('creditors')->insert(['id' => 1, 'code' => 'KR0005', 'name' => 'PARIT PADANG GLOBAL, PT', 'ppn_type' => 'EXCLUDE']);
        DB::table('factories')->insert(['id' => 1, 'name' => 'PABRIK TEST']);
        DB::table('medicines')->insert([
            'id' => 1,
            'code' => 'OBT-1',
            'name' => 'Obat Test',
            'packaging' => 'BOX',
            'unit' => 'TAB',
            'factory_id' => 1,
        ]);
        DB::table('orders')->insert(['id' => 1, 'code' => 'SP-1']);
        DB::table('order_items')->insert([
            'id' => 1,
            'order_id' => 1,
            'medicine_id' => 1,
            'creditor_code' => 'KR0005',
            'price' => 999000,
            'pack' => 1,
        ]);
        DB::table('batches')->insert(['id' => 1, 'name' => 'B001']);

        // Header receiving dibuka tgl 12, tetapi faktur diinput tgl 13 jam 00:23
        DB::table('receiving')->insert([
            'id' => 1,
            'pharmacy_id' => 9,
            'code' => 'RCV-1',
            'date' => '2026-11-12',
            'status' => 2,
            'created_at' => '2026-11-12 23:00:00',
            'updated_at' => '2026-11-13 00:23:00',
        ]);
        DB::table('receiving_details')->insert([
            'id' => 1,
            'receiving_id' => 1,
            'creditor_code' => 'KR0005',
            'receiving_details_code' => 'NT-26-11/0209',
            'invoice_number' => '441383',
            'invoice_date' => '2026-11-13',
            'invoice_due' => '2026-12-13',
            'invoice_times' => 30,
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'EXCLUDE',
            'created_at' => '2026-11-13 00:23:00',
            'updated_at' => '2026-11-13 00:23:00',
        ]);
        DB::table('receiving_items')->insert([
            'id' => 1,
            'receiving_details_id' => 1,
            'order_items_id' => 1,
            'batches_id' => 1,
            'qty_received' => 1,
            'qty' => 1,
            'receipt_is_pack' => 1,
            'discount' => 0,
            'extra_discount' => 0,
            'total' => 999000,
            'raw_price' => 999000,
            'batch' => 'B001',
            'expired_date' => '2028-01-01',
        ]);
    }

    public function test_hutang_export_filters_and_displays_receiving_details_created_at(): void
    {
        $onThirteenth = (new HutangExport([9], [
            'date_field' => 'received',
            'start_date' => '2026-11-13',
            'end_date' => '2026-11-13',
        ]))->array();

        $this->assertSame('441383', $onThirteenth[5][1]);
        $this->assertSame('NT-26-11/0209', $onThirteenth[5][3]);
        $this->assertSame('13/11/2026', $onThirteenth[5][5]);

        $onTwelfth = (new HutangExport([9], [
            'date_field' => 'received',
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-12',
        ]))->array();

        $this->assertStringContainsString('Tidak ada data hutang dagang', $onTwelfth[5][0]);
    }

    public function test_orders_and_purchase_payment_exports_display_receiving_details_created_at(): void
    {
        $ordersRows = (new OrdersSingleSheetExport(9, '2026-11-13', '2026-11-13'))->array();
        $this->assertSame('13/11/2026', $ordersRows[7][2]);

        $paymentRows = (new PurchasePaymentExport(9, '2026-11-13', '2026-11-13', 'Kredit'))->array();
        $this->assertSame('13/11/2026', $paymentRows[7][5]);
    }

    public function test_hutang_export_formats_currency_columns_with_two_decimal_places(): void
    {
        $export = new HutangExport([9], [
            'date_field' => 'received',
            'start_date' => '2026-11-13',
            'end_date' => '2026-11-13',
        ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($export->array());
        $export->styles($sheet);

        $this->assertSame('#,##0.00', $sheet->getStyle('I6')->getNumberFormat()->getFormatCode());
        $this->assertSame('#,##0.00', $sheet->getStyle('O6')->getNumberFormat()->getFormatCode());
        $this->assertSame('#,##0.00', $sheet->getStyle('I7')->getNumberFormat()->getFormatCode());
        $this->assertSame('#,##0.00', $sheet->getStyle('O7')->getNumberFormat()->getFormatCode());
    }

    public function test_piutang_export_formats_currency_columns_with_two_decimal_places(): void
    {
        $export = new PiutangExport([9]);
        $ref = new \ReflectionClass($export);
        foreach ([
            'dataRowCount' => 1,
            'lastRow' => 7,
            'detailTitleRow' => 9,
            'detailHeaderRow' => 10,
            'detailDataStartRow' => 11,
            'detailDataEndRow' => 11,
        ] as $prop => $val) {
            $property = $ref->getProperty($prop);
            $property->setAccessible(true);
            $property->setValue($export, $val);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('N6', 'BELUM BAYAR');
        $export->styles($sheet);

        $this->assertSame('#,##0.00', $sheet->getStyle('O6')->getNumberFormat()->getFormatCode());
        $this->assertSame('#,##0.00', $sheet->getStyle('R6')->getNumberFormat()->getFormatCode());
        $this->assertSame('#,##0.00', $sheet->getStyle('O7')->getNumberFormat()->getFormatCode());
        $this->assertSame('#,##0.00', $sheet->getStyle('R7')->getNumberFormat()->getFormatCode());
        $this->assertSame('#,##0.00', $sheet->getStyle('E11')->getNumberFormat()->getFormatCode());
        $this->assertSame('#,##0.00', $sheet->getStyle('G11')->getNumberFormat()->getFormatCode());
    }

    public function test_hutang_and_cash_include_posted_batches_even_when_receiving_status_is_zero_and_exclude_drafts(): void
    {
        // Receiving status=0 tetapi item sudah punya batches_id (kasus 98256)
        DB::table('receiving')->insert([
            'id' => 2,
            'pharmacy_id' => 9,
            'code' => 'RCV-2',
            'date' => '2026-10-22',
            'status' => 0,
            'created_at' => '2026-10-22 21:18:38',
            'updated_at' => '2026-10-22 21:23:03',
        ]);
        DB::table('receiving_details')->insert([
            'id' => 2,
            'receiving_id' => 2,
            'creditor_code' => 'KR0005',
            'receiving_details_code' => 'NT-26-10/0379',
            'invoice_number' => '98256',
            'invoice_date' => '2026-10-21',
            'invoice_due' => '2026-11-20',
            'invoice_times' => 30,
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'EXCLUDE',
            'created_at' => '2026-10-22 21:18:38',
            'updated_at' => '2026-10-22 21:23:03',
        ]);
        DB::table('receiving_items')->insert([
            'id' => 2,
            'receiving_details_id' => 2,
            'order_items_id' => 1,
            'batches_id' => 1,
            'qty_received' => 1,
            'qty' => 1,
            'receipt_is_pack' => 1,
            'discount' => 0,
            'extra_discount' => 0,
            'total' => 275200,
            'raw_price' => 275200,
            'batch' => 'B002',
            'expired_date' => '2028-01-01',
        ]);

        // Draft faktur (batches_id = null) di bawah receiving status=2 harus tetap tidak muncul
        DB::table('receiving')->insert([
            'id' => 3,
            'pharmacy_id' => 9,
            'code' => 'RCV-3',
            'date' => '2026-11-17',
            'status' => 2,
        ]);
        DB::table('receiving_details')->insert([
            'id' => 3,
            'receiving_id' => 3,
            'creditor_code' => 'KR0005',
            'receiving_details_code' => 'NT-26-11/9999',
            'invoice_number' => 'DRAFT-ONLY',
            'invoice_date' => '2026-11-17',
            'invoice_due' => '2026-12-17',
            'invoice_times' => 30,
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'EXCLUDE',
            'created_at' => '2026-11-17 10:00:00',
            'updated_at' => '2026-11-17 10:00:00',
        ]);
        DB::table('receiving_items')->insert([
            'id' => 3,
            'receiving_details_id' => 3,
            'order_items_id' => 1,
            'batches_id' => null,
            'qty_received' => 1,
            'qty' => 1,
            'raw_price' => 50000,
            'total' => 50000,
        ]);

        // Tunai dengan receiving status=0 tapi sudah masuk stok
        DB::table('receiving_details')->insert([
            'id' => 4,
            'receiving_id' => 2,
            'creditor_code' => 'KR0005',
            'receiving_details_code' => 'NT-26-10/0380',
            'invoice_number' => 'TUNAI-98256',
            'invoice_date' => '2026-10-21',
            'invoice_payment' => 'TUNAI',
            'invoice_ppn' => 'TANPA',
            'created_at' => '2026-10-22 21:18:38',
            'updated_at' => '2026-10-22 21:23:03',
        ]);
        DB::table('receiving_items')->insert([
            'id' => 4,
            'receiving_details_id' => 4,
            'order_items_id' => 1,
            'batches_id' => 1,
            'qty_received' => 1,
            'qty' => 1,
            'raw_price' => 100000,
            'total' => 100000,
        ]);

        $hutangExportRows = (new HutangExport([9]))->array();
        $hutangInvoiceNumbers = collect($hutangExportRows)->pluck(1)->all();
        $this->assertContains('98256', $hutangInvoiceNumbers);
        $this->assertNotContains('DRAFT-ONLY', $hutangInvoiceNumbers);
        $this->assertSame(2, (int) DB::table('receiving')->where('id', 2)->value('status'));

        // Reset status=0 lagi untuk menguji bahwa getHutangData() juga auto-sync status 0 -> 2 di DB
        DB::table('receiving')->where('id', 2)->update(['status' => 0]);

        $cashExportRows = (new CashExport([9]))->array();
        $cashInvoiceNumbers = collect($cashExportRows)->pluck(1)->all();
        $this->assertContains('TUNAI-98256', $cashInvoiceNumbers);

        DB::table('receiving')->where('id', 2)->update(['status' => 0]);

        $controller = new class extends FinanceController {
            public function __construct() {}
            protected function getTargetPharmacyIds(): array { return [1, 9]; }
            public function exposeHutang(): array { return $this->getHutangData(); }
            public function exposeCash(): array { return $this->getCashData(); }
        };

        $hutangList = collect($controller->exposeHutang())->pluck('nomor')->all();
        $this->assertContains('98256', $hutangList);
        $this->assertNotContains('DRAFT-ONLY', $hutangList);
        $this->assertSame(2, (int) DB::table('receiving')->where('id', 2)->value('status'));

        $cashList = collect($controller->exposeCash())->pluck('nomor')->all();
        $this->assertContains('TUNAI-98256', $cashList);
    }

    public function test_save_order_promotes_all_receiving_headers_whose_items_enter_stock(): void
    {
        DB::table('orders')->insert([
            'id' => 2,
            'pharmacy_id' => 9,
            'receiving_id' => 10,
            'code' => 'SP-2',
            'status' => 1,
        ]);
        DB::table('order_items')->insert([
            'id' => 2,
            'order_id' => 2,
            'medicine_id' => 1,
            'order_items_code' => 'SP-2',
            'creditor_code' => 'KR0005',
            'price' => 10000,
            'total' => 10000,
            'pack' => 0,
            'status' => 1,
        ]);
        DB::table('receiving')->insert([
            ['id' => 10, 'pharmacy_id' => 9, 'code' => 'RCV-10', 'date' => '2026-11-17', 'status' => 0],
            ['id' => 11, 'pharmacy_id' => 9, 'code' => 'RCV-11', 'date' => '2026-11-17', 'status' => 0],
        ]);
        DB::table('receiving_details')->insert([
            'id' => 11,
            'receiving_id' => 11,
            'sp_code' => 'SP-2',
            'creditor_code' => 'KR0005',
            'receiving_details_code' => 'NT-26-11/7777',
            'invoice_number' => 'INV-SPLIT-11',
            'invoice_date' => '2026-11-17',
            'invoice_due' => '2026-12-17',
            'invoice_times' => 30,
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'TANPA',
            'created_at' => '2026-11-17 17:00:00',
            'updated_at' => '2026-11-17 17:00:00',
        ]);
        DB::table('receiving_items')->insert([
            'id' => 11,
            'receiving_details_id' => 11,
            'order_items_id' => 2,
            'batches_id' => null,
            'qty_received' => 2,
            'qty' => 2,
            'receipt_is_pack' => 0,
            'stock_unit_factor' => 1,
            'raw_price' => 10000,
            'discount' => 0,
            'extra_discount' => 0,
            'total' => 20000,
            'batch' => 'BATCH-SPLIT',
            'expired_date' => '2028-01-01',
            'status' => 1,
        ]);

        $controller = app(ReceivingController::class);
        $response = $controller->saveOrder(new Request([
            'receivingid' => 10,
            'orderid' => 2,
        ]));

        $this->assertTrue($response->getData(true)['success']);
        $this->assertNotNull(DB::table('receiving_items')->where('id', 11)->value('batches_id'));
        $this->assertSame(0, (int) DB::table('receiving')->where('id', 10)->value('status'));
        $this->assertSame(2, (int) DB::table('receiving')->where('id', 11)->value('status'));
    }
}
