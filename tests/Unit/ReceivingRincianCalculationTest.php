<?php

namespace Tests\Unit;

use App\Http\Controllers\Orders\ReceivingController;
use App\Models\ReceivingDetails;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReceivingRincianCalculationTest extends TestCase
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

        Schema::create('medicine_creditors', function (Blueprint $table) {
            $table->id();
            $table->string('creditor_code');
            $table->unsignedBigInteger('medicine_id');
            $table->double('discount')->default(0);
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
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
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
            $table->double('quantity')->default(0);
            $table->double('discount')->default(0);
            $table->double('extra_discount')->default(0);
            $table->double('total')->default(0);
            $table->boolean('pack')->default(1);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('batch_number')->nullable();
            $table->date('expired_date')->nullable();
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('etalases', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->string('code')->nullable();
            $table->string('date')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('receiving_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id');
            $table->string('receiving_details_code')->nullable();
            $table->string('sp_code')->nullable();
            $table->string('creditor_code')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('invoice_due')->nullable();
            $table->integer('invoice_times')->nullable();
            $table->string('invoice_payment')->nullable();
            $table->string('invoice_ppn')->nullable();
            $table->timestamps();
        });

        Schema::create('receiving_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_details_id');
            $table->unsignedBigInteger('order_items_id');
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->unsignedBigInteger('location')->nullable();
            $table->unsignedBigInteger('etalase')->nullable();
            $table->double('qty_received')->default(0);
            $table->double('qty')->default(0);
            $table->double('discount')->default(0);
            $table->double('extra_discount')->default(0);
            $table->double('total')->default(0);
            $table->double('raw_price')->default(0);
            $table->string('batch')->nullable();
            $table->date('expired_date')->nullable();
            $table->timestamps();
        });
    }

    public function test_exclude_invoice_recalculates_stale_item_and_matches_print_receiving_totals(): void
    {
        $now = '2026-11-14 10:00:00';
        DB::table('pharmacies')->insert(['id' => 1, 'name' => 'SAHABAT MULAWARMAN', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('creditors')->insert(['id' => 1, 'code' => 'PBF1', 'name' => 'PT PBF', 'ppn_type' => 'EXCLUDE', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('factories')->insert(['id' => 1, 'name' => 'PABRIK', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('receiving')->insert(['id' => 1, 'pharmacy_id' => 1, 'code' => 'REC-1', 'date' => '2026-11-14', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('orders')->insert(['id' => 1, 'pharmacy_id' => 1, 'receiving_id' => 1, 'code' => '2611OR0113', 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('receiving_details')->insert([
            'id' => 10,
            'receiving_id' => 1,
            'receiving_details_code' => 'NT-26-11/0238',
            'sp_code' => 'SP-1',
            'creditor_code' => 'PBF1',
            'invoice_number' => '0018337',
            'invoice_date' => '2026-11-14',
            'invoice_due' => '2026-12-14',
            'invoice_times' => 30,
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'EXCLUDE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Replikasi tepat 6 item faktur 0018337; kolom total per baris = QTY * HNA - DISC (tanpa PPN),
        // meski sebagian DB total sebelumnya menyimpan angka + PPN.
        $items = [
            ['id' => 1, 'name' => 'CENDO LYTEERS 0.6ML MD', 'qty' => 5, 'price' => 20213, 'disc' => 5, 'db_total' => 106573, 'expected_total' => 96011.75],
            ['id' => 2, 'name' => 'COTEN KAPS 100 MG @30', 'qty' => 1, 'price' => 535000, 'disc' => 10, 'db_total' => 534465, 'expected_total' => 481500],
            ['id' => 3, 'name' => 'FOLAMIL GOLD KAPS @30', 'qty' => 1, 'price' => 150000, 'disc' => 2, 'db_total' => 163170, 'expected_total' => 147000],
            ['id' => 4, 'name' => '-PARACETAMOL KAPL 500 MG @100 TRIFA', 'qty' => 2, 'price' => 26700, 'disc' => 20, 'db_total' => 47419, 'expected_total' => 42720],
            ['id' => 5, 'name' => 'ROVASTAR TAB 10 MG @30', 'qty' => 1, 'price' => 403500, 'disc' => 7.5, 'db_total' => 373237.5, 'expected_total' => 373237.5],
            ['id' => 6, 'name' => 'VENTOLIN INH 100MCG/SPRAY', 'qty' => 1, 'price' => 146074, 'disc' => 0, 'db_total' => 162142, 'expected_total' => 146074],
        ];

        foreach ($items as $item) {
            DB::table('medicines')->insert([
                'id' => $item['id'],
                'code' => 'MED-' . $item['id'],
                'name' => $item['name'],
                'factory_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('order_items')->insert([
                'id' => $item['id'],
                'order_id' => 1,
                'medicine_id' => $item['id'],
                'order_items_code' => 'SP-1',
                'creditor_code' => 'PBF1',
                'price' => $item['price'],
                'quantity' => $item['qty'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('receiving_items')->insert([
                'id' => $item['id'],
                'receiving_details_id' => 10,
                'order_items_id' => $item['id'],
                'qty_received' => $item['qty'],
                'qty' => $item['qty'],
                'raw_price' => $item['price'],
                'discount' => $item['disc'],
                'extra_discount' => 0,
                'total' => $item['db_total'],
                'batch' => 'B-' . $item['id'],
                'expired_date' => '2027-12-01',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $controller = new ReceivingController();
        $view = $controller->orderRincian(1);
        $data = $view->getData();
        $itemsData = collect($data['itemsData']);

        $this->assertCount(6, $itemsData);

        foreach ($items as $item) {
            $row = $itemsData->firstWhere('medicine_name', $item['name']);
            $this->assertNotNull($row);
            $this->assertEquals((float) $item['expected_total'], (float) $row['total']);
            $this->assertEquals('EXCLUDE', $row['ppn_type']);
        }

        // Total baris = QTY * HNA - DISC (tanpa PPN), sehingga jumlah seluruh baris = DPP faktur
        $this->assertEquals(1286543.25, (float) $itemsData->sum('total'));
        $subtotal = $itemsData->sum('gross');
        $discount = $itemsData->sum('discount_nominal');
        $fakturSummary = ReceivingController::computeInvoiceSummaryFromTotals($subtotal, $discount, 'EXCLUDE');

        $this->assertEquals(1286543.25, $fakturSummary['dpp_hna']);
        $this->assertEquals(141519.0, $fakturSummary['ppn']);
        $this->assertEquals(1428062.25, $fakturSummary['grand_total']);

        // Pastikan hasil ringkasan summarizeReceivingDetailsByPrintRule identik dengan faktur cetak
        $details = ReceivingDetails::with(['receiving_items.order_items', 'creditor'])->get();
        $controllerSummary = ReceivingController::summarizeReceivingDetailsByPrintRule($details);

        $this->assertEquals($fakturSummary['dpp_hna'], $controllerSummary['price_item']);
        $this->assertEquals($fakturSummary['ppn'], $controllerSummary['price_ppn']);
        $this->assertEquals($fakturSummary['grand_total'], $controllerSummary['price_total']);
    }

    public function test_include_and_tanpa_ppn_match_print_receiving_rules(): void
    {
        $include = ReceivingController::computeInvoiceSummaryFromTotals(111000, 11000, 'INCLUDE');
        // Net = 100.000 (sudah termasuk PPN), DPP = floor(100.000 / 1.11) = 90.090, PPN = 9.910
        $this->assertEquals(90090.0, $include['dpp_hna']);
        $this->assertEquals(9910.0, $include['ppn']);
        $this->assertEquals(100000.0, $include['grand_total']);

        $tanpa = ReceivingController::computeInvoiceSummaryFromTotals(100000, 5000, 'TANPA');
        $this->assertEquals(95000.0, $tanpa['dpp_hna']);
        $this->assertEquals(0.0, $tanpa['ppn']);
        $this->assertEquals(95000.0, $tanpa['grand_total']);

        // Diskon nominal > 100 diperlakukan sebagai potongan rupiah langsung; per baris tetap QTY * HNA - DISC
        $nominalLine = ReceivingController::computeReceivingLineAmounts(2, 50000, 5000, 0, 'EXCLUDE');
        $this->assertEquals(100000.0, $nominalLine['gross']);
        $this->assertEquals(5000.0, $nominalLine['discount_nominal']);
        $this->assertEquals(95000.0, $nominalLine['net']);
        $this->assertEquals(95000.0, $nominalLine['total']);
    }

    public function test_rincian_mixed_faktur_ppn_groups_independently(): void
    {
        $now = '2026-11-14 10:00:00';
        DB::table('pharmacies')->insert(['id' => 1, 'name' => 'APOTEK', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('creditors')->insert([
            ['id' => 1, 'code' => 'PBF1', 'name' => 'PBF EXCLUDE', 'ppn_type' => 'EXCLUDE', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'code' => 'PBF2', 'name' => 'PBF INCLUDE', 'ppn_type' => 'INCLUDE', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('factories')->insert(['id' => 1, 'name' => 'PABRIK', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('medicines')->insert([
            ['id' => 1, 'code' => 'M1', 'name' => 'OBAT A', 'factory_id' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'code' => 'M2', 'name' => 'OBAT B', 'factory_id' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('receiving')->insert(['id' => 1, 'pharmacy_id' => 1, 'code' => 'REC-1', 'date' => '2026-11-14', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('orders')->insert(['id' => 1, 'pharmacy_id' => 1, 'receiving_id' => 1, 'code' => 'PO-1', 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('receiving_details')->insert([
            ['id' => 11, 'receiving_id' => 1, 'receiving_details_code' => 'NT-1', 'sp_code' => 'SP-1', 'creditor_code' => 'PBF1', 'invoice_number' => 'INV-EX', 'invoice_ppn' => 'EXCLUDE', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'receiving_id' => 1, 'receiving_details_code' => 'NT-2', 'sp_code' => 'SP-2', 'creditor_code' => 'PBF2', 'invoice_number' => 'INV-IN', 'invoice_ppn' => 'INCLUDE', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('order_items')->insert([
            ['id' => 1, 'order_id' => 1, 'medicine_id' => 1, 'order_items_code' => 'SP-1', 'creditor_code' => 'PBF1', 'price' => 10000, 'quantity' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'order_id' => 1, 'medicine_id' => 2, 'order_items_code' => 'SP-2', 'creditor_code' => 'PBF2', 'price' => 11100, 'quantity' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('receiving_items')->insert([
            ['id' => 1, 'receiving_details_id' => 11, 'order_items_id' => 1, 'qty_received' => 2, 'qty' => 2, 'raw_price' => 10000, 'discount' => 0, 'extra_discount' => 0, 'total' => 20000, 'batch' => 'A1', 'expired_date' => '2027-01-01', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'receiving_details_id' => 12, 'order_items_id' => 2, 'qty_received' => 1, 'qty' => 1, 'raw_price' => 11100, 'discount' => 0, 'extra_discount' => 0, 'total' => 11100, 'batch' => 'B1', 'expired_date' => '2027-01-01', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $view = (new ReceivingController())->orderRincian(1);
        $itemsData = collect($view->getData()['itemsData']);

        // INV-EX: total baris = QTY * HNA - DISC (20.000), PPN dihitung di ringkasan akhir faktur (2.200 -> 22.200)
        $rowEx = $itemsData->firstWhere('medicine_name', 'OBAT A');
        $this->assertEquals(20000.0, (float) $rowEx['total']);

        // INV-IN: GrandTotal = 11.100, DPP = 10.000, PPN = 1.100
        $rowIn = $itemsData->firstWhere('medicine_name', 'OBAT B');
        $this->assertEquals(10000.0, (float) $rowIn['hna']);
        $this->assertEquals(11100.0, (float) $rowIn['total']);

        $summary = ReceivingController::summarizeReceivingDetailsByPrintRule(
            ReceivingDetails::with(['receiving_items.order_items', 'creditor'])->get()
        );
        $this->assertEquals(30000.0, $summary['price_item']);
        $this->assertEquals(3300.0, $summary['price_ppn']);
        $this->assertEquals(33300.0, $summary['price_total']);
    }
}
