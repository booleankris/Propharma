<?php

namespace Tests\Unit;

use App\Exports\Stocks\FinanceStockReconciliationSheetExport;
use App\Exports\Stocks\PrintStockOpnameExport;
use App\Exports\Stocks\StockDataExport;
use App\Exports\Stocks\StockDataSheetExport;
use App\Http\Controllers\FinanceController;
use App\Models\User;
use App\Services\Finance\FinanceStockReconciliationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceStockReconciliationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropAllTables();

        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('status')->default(1);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
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

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
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
            $table->string('unit')->default('TAB');
            $table->integer('content')->default(1);
            $table->unsignedBigInteger('factory_id')->nullable();
            $table->string('raw_price')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->string('name');
            $table->string('stock')->default('0');
            $table->timestamps();
        });

        Schema::create('medicine_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->unsignedBigInteger('source_pharmacy_id')->nullable();
            $table->unsignedBigInteger('destination_pharmacy_id')->nullable();
            $table->integer('status')->default(1);
            $table->integer('is_request')->default(0);
            $table->integer('request_status')->default(1);
            $table->timestamps();
        });

        Schema::create('medicine_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_transfer_id')->nullable();
            $table->unsignedBigInteger('batches_id');
            $table->unsignedBigInteger('source_batches_id')->nullable();
            $table->unsignedBigInteger('requested_medicine_id')->nullable();
            $table->string('source_type')->nullable();
            $table->double('qty')->default(0);
            $table->double('original_qty')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('items_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->string('type')->nullable();
            $table->integer('status')->default(1);
            $table->double('qty')->default(0);
            $table->double('qty_before')->default(0);
            $table->double('qty_after')->default(0);
            $table->string('total')->nullable();
            $table->string('date')->nullable();
            $table->timestamps();
        });

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('receiving_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->string('invoice_payment')->nullable();
            $table->date('invoice_date')->nullable();
            $table->timestamps();
        });

        Schema::create('receiving_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_details_id')->nullable();
            $table->unsignedBigInteger('order_items_id')->nullable();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_id');
            $table->timestamps();
        });
    }

    public function test_reconciliation_matches_finance_valuation_and_identifies_all_discrepancy_causes(): void
    {
        DB::table('pharmacies')->insert([
            ['id' => 1, 'name' => 'SAHABAT PMI', 'status' => 1],
            ['id' => 9, 'name' => 'GUDANG PMI', 'status' => 1],
        ]);

        $user = new User(['name' => 'Staff PMI', 'pharmacy_id' => 1]);
        $user->id = 1;
        $this->be($user);
        session(['ho_pharmacy_id' => 1]);
        Cache::flush();

        // 1. Obat Cocok sempurna: Gudang 10 + Etalase 5 = 15, KREDIT, HNA 1000 -> Nilai = 15 * 1110 = 16,650
        $mMatch = DB::table('medicines')->insertGetId([
            'code' => '001', 'name' => 'OBAT COCOK', 'raw_price' => '1000', 'status' => 1,
        ]);
        $bMatchGdg = DB::table('batches')->insertGetId(['medicine_id' => $mMatch, 'pharmacy_id' => 9, 'name' => 'B1', 'stock' => '10']);
        $bMatchEtl = DB::table('batches')->insertGetId(['medicine_id' => $mMatch, 'pharmacy_id' => 1, 'name' => 'B1', 'stock' => '5']);
        DB::table('medicine_transfer_items')->insert(['batches_id' => $bMatchEtl, 'qty' => 5, 'status' => 1]);

        // 2. Obat Batch Minus: Gudang OPN +20 dan batch retur -20 -> baik Excel Data Stok maupun Finance abaikan -20 (sama-sama 20)
        $mNeg = DB::table('medicines')->insertGetId([
            'code' => '002', 'name' => 'OBAT BATCH MINUS', 'raw_price' => '1000', 'status' => 1,
        ]);
        DB::table('batches')->insert(['medicine_id' => $mNeg, 'pharmacy_id' => 9, 'name' => 'OPN-1', 'stock' => '20']);
        DB::table('batches')->insert(['medicine_id' => $mNeg, 'pharmacy_id' => 9, 'name' => 'WT-NEG', 'stock' => '-20']);
        $bNegEtl = DB::table('batches')->insertGetId(['medicine_id' => $mNeg, 'pharmacy_id' => 1, 'name' => 'E-NEG', 'stock' => '-5']);
        DB::table('medicine_transfer_items')->insert(['batches_id' => $bNegEtl, 'qty' => -5, 'status' => 1]);

        // 3. Obat Faktur Terakhir KREDIT tetapi punya sisa batch Konsinyasi lama:
        //    Batch KREDIT (+10), Batch KONSINYASI (+6) -> Excel label 'Kredit' total 16, Finance hanya hitung 10!
        $mMixedNonConsLabel = DB::table('medicines')->insertGetId([
            'code' => '003', 'name' => 'OBAT KREDIT ADA KONSI', 'raw_price' => '2000', 'status' => 1,
        ]);
        $bMixKredit = DB::table('batches')->insertGetId(['medicine_id' => $mMixedNonConsLabel, 'pharmacy_id' => 9, 'name' => 'B-KRD', 'stock' => '10']);
        $bMixKonsi = DB::table('batches')->insertGetId(['medicine_id' => $mMixedNonConsLabel, 'pharmacy_id' => 9, 'name' => 'B-KNS', 'stock' => '6']);

        // 4. Obat Faktur Terakhir KONSINYASI tetapi punya batch Non-Konsinyasi (+4):
        //    Excel label 'Konsinyasi' (di-exclude = 0), Finance tetap hitung batch KREDIT +4 * 3000 * 1.11 = 13,320!
        $mMixedConsLabel = DB::table('medicines')->insertGetId([
            'code' => '004', 'name' => 'OBAT LABEL KONSI ADA KREDIT', 'raw_price' => '3000', 'status' => 1,
        ]);
        $bLabelKredit = DB::table('batches')->insertGetId(['medicine_id' => $mMixedConsLabel, 'pharmacy_id' => 9, 'name' => 'B-NONK', 'stock' => '4']);
        $bLabelKonsi = DB::table('batches')->insertGetId(['medicine_id' => $mMixedConsLabel, 'pharmacy_id' => 9, 'name' => 'B-KON2', 'stock' => '8']);

        // Receiving setup for consignment rules
        $recId = DB::table('receiving')->insertGetId(['pharmacy_id' => 9, 'status' => 1]);
        $rdOldKonsi = DB::table('receiving_details')->insertGetId([
            'receiving_id' => $recId, 'invoice_payment' => 'KONSINYASI', 'invoice_date' => '2026-10-10',
        ]);
        $rdNewKredit = DB::table('receiving_details')->insertGetId([
            'receiving_id' => $recId, 'invoice_payment' => 'KREDIT', 'invoice_date' => '2026-11-10',
        ]);
        $rdOldKredit = DB::table('receiving_details')->insertGetId([
            'receiving_id' => $recId, 'invoice_payment' => 'KREDIT', 'invoice_date' => '2026-10-10',
        ]);
        $rdNewKonsi = DB::table('receiving_details')->insertGetId([
            'receiving_id' => $recId, 'invoice_payment' => 'KONSINYASI', 'invoice_date' => '2026-11-15',
        ]);

        $oi1 = DB::table('order_items')->insertGetId(['medicine_id' => $mMatch]);
        $oi3 = DB::table('order_items')->insertGetId(['medicine_id' => $mMixedNonConsLabel]);
        $oi4 = DB::table('order_items')->insertGetId(['medicine_id' => $mMixedConsLabel]);

        DB::table('receiving_items')->insert([
            ['receiving_details_id' => $rdNewKredit, 'order_items_id' => $oi1, 'batches_id' => $bMatchGdg],
            ['receiving_details_id' => $rdOldKonsi, 'order_items_id' => $oi3, 'batches_id' => $bMixKonsi],
            ['receiving_details_id' => $rdNewKredit, 'order_items_id' => $oi3, 'batches_id' => $bMixKredit],
            ['receiving_details_id' => $rdOldKredit, 'order_items_id' => $oi4, 'batches_id' => $bLabelKredit],
            ['receiving_details_id' => $rdNewKonsi, 'order_items_id' => $oi4, 'batches_id' => $bLabelKonsi],
        ]);

        // Compare FinanceController::getStockValuation() directly against Reconciliation Service
        $financeController = new class extends FinanceController {
            public function __construct()
            {
            }

            public function valuation(): array
            {
                return $this->getStockValuation();
            }
        };

        $financeValuation = $financeController->valuation();

        $export = new StockDataExport(new Request(), 1);
        $sheets = $export->sheets();

        $this->assertCount(2, $sheets);
        $this->assertInstanceOf(StockDataSheetExport::class, $sheets[0]);
        $this->assertInstanceOf(FinanceStockReconciliationSheetExport::class, $sheets[1]);
        $this->assertSame('Rekonsiliasi Finance', $sheets[1]->title());

        // PrintStockOpnameExport tetap 1 sheet StockDataSheetExport
        $opnameExport = new PrintStockOpnameExport(new Request(), 1);
        $this->assertSame('Hasil Stock Opname', $opnameExport->title());

        $report = app(FinanceStockReconciliationService::class)->buildReport(1);
        $this->assertSame($financeValuation['total'], $report['summary']['finance_total_value']);
        $this->assertSame(2, $report['summary']['mismatch_count']);

        $byCode = collect($report['rows'])->keyBy('code');
        $this->assertSame('COCOK', $byCode['001']['status_label']);
        $this->assertSame(20, $byCode['002']['excel_qty']);
        $this->assertSame(-25.0, $byCode['002']['negative_qty']);
        $this->assertSame('COCOK', $byCode['002']['status_label']);
        $this->assertStringContainsString('batch/etalase minus -25 sama-sama diabaikan', $byCode['002']['reason']);
        $this->assertSame('ADA SELISIH', $byCode['003']['status_label']);
        $this->assertStringContainsString('batch Konsinyasi (6)', $byCode['003']['reason']);
        $this->assertSame('ADA SELISIH', $byCode['004']['status_label']);
        $this->assertStringContainsString('batch Non-Konsinyasi (4)', $byCode['004']['reason']);

        // Pastikan Sheet 1 Data Stok Export untuk OBAT BATCH MINUS (002) menampilkan stok 20 (mengabaikan -20 gudang & -5 etalase)
        $sheet1Rows = collect($sheets[0]->array())->slice(5)->values();
        $row002 = $sheet1Rows->firstWhere(1, '002');
        $this->assertNotNull($row002);
        $this->assertSame(20, $row002[13]); // STOK GUDANG
        $this->assertSame(0, $row002[14]);  // STOK PELAYANAN
        $this->assertSame(20, $row002[15]); // TOTAL STOK

        // Ensure Sheet 2 array() produces summary + 4 rows + total without errors
        $sheet2Array = $sheets[1]->array();
        $this->assertNotEmpty($sheet2Array);
        $this->assertSame('TOTAL KESELURUHAN', $sheet2Array[count($sheet2Array) - 1][0]);

        // Exercise full XLSX generation (styles, widths, binders, multiple sheets)
        $xlsxBinary = \Maatwebsite\Excel\Facades\Excel::raw(new StockDataExport(new Request(), 1), \Maatwebsite\Excel\Excel::XLSX);
        $this->assertNotEmpty($xlsxBinary);
    }
}
