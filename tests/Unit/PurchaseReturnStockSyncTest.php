<?php

namespace Tests\Unit;

use App\Http\Controllers\Finance\PurchaseReturnController;
use App\Models\Batches;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseReturnStockSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropAllTables();

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('unit')->default('BTL');
            $table->string('packaging')->nullable();
            $table->integer('content')->default(1);
            $table->double('stock')->default(0);
            $table->double('raw_price')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->string('name');
            $table->date('expired_date')->nullable();
            $table->double('stock')->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('medicine_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batches_id');
            $table->double('qty')->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no');
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('received_date')->nullable();
            $table->string('creditor_code')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->double('total_amount')->default(0);
            $table->string('status')->default('dikonfirmasi');
            $table->date('status_date')->nullable();
            $table->date('confirmed_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_manual')->default(true);
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_return_id');
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('medicine_name');
            $table->string('unit')->default('BTL');
            $table->string('retur_type')->default('unit');
            $table->integer('stock_unit_factor')->default(1);
            $table->string('batch_number')->nullable();
            $table->date('expired_date')->nullable();
            $table->double('qty')->default(1);
            $table->double('unit_price')->default(0);
            $table->double('subtotal')->default(0);
            $table->string('status')->default('dikonfirmasi');
            $table->date('status_date')->nullable();
            $table->date('confirmed_date')->nullable();
            $table->boolean('stock_deducted')->default(false);
            $table->string('reason')->nullable();
            $table->date('pickup_date')->nullable();
            $table->date('deduction_date')->nullable();
            $table->string('deduction_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('items_log', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->nullable();
            $table->string('code')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->double('qty')->default(0);
            $table->double('qty_before')->default(0);
            $table->double('qty_after')->default(0);
            $table->double('total')->default(0);
            $table->dateTime('date')->nullable();
            $table->integer('status')->default(4);
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->nullable();
            $table->double('amount')->default(0);
            $table->timestamps();
        });
    }

    public function test_sync_command_reconciles_ret_2610_0007_multi_batch_discrepancy(): void
    {
        $med = Medicines::create([
            'code' => 'FIESTA01',
            'name' => '-FIESTA INTIM WARM GEL 70 ML',
            'unit' => 'BTL',
            'content' => 1,
            'stock' => -4,
        ]);

        $batch215 = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 1,
            'name' => 'LWJ24215',
            'expired_date' => '2027-04-01',
            'stock' => -1, // terpotong 2x (-1 dan -1) padahal seharusnya hanya -1
            'status' => 1,
        ]);

        $batch260 = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 1,
            'name' => 'LWJ24260',
            'expired_date' => '2027-06-01',
            'stock' => 0, // awalnya 3, sudah terpotong 3
            'status' => 1,
        ]);

        $batch346 = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 1,
            'name' => 'LWJ24346',
            'expired_date' => '2027-09-01',
            'stock' => 6, // belum terpotong sama sekali karena jatuh ke LWJ24215
            'status' => 1,
        ]);

        $pr = PurchaseReturn::create([
            'return_no' => 'RET-2610/0007',
            'invoice_number' => 'D261430965',
            'creditor_code' => 'AMS',
            'pharmacy_id' => 1,
            'total_amount' => 346696,
            'status' => 'sudah_diambil',
            'status_date' => '2026-10-20',
            'confirmed_date' => '2026-10-20',
            'is_manual' => true,
        ]);

        // Baris 1: LWJ24215 (Qty: 1)
        $item1 = PurchaseReturnItem::create([
            'purchase_return_id' => $pr->id,
            'medicine_id' => $med->id,
            'batch_id' => $batch215->id,
            'medicine_name' => $med->name,
            'unit' => 'BTL',
            'batch_number' => 'LWJ24215',
            'expired_date' => '2027-04-01',
            'qty' => 1,
            'unit_price' => 49528,
            'subtotal' => 49528,
            'status' => 'sudah_diambil',
            'stock_deducted' => true,
        ]);

        // Baris 2: LWJ24260 (Qty: 3)
        $item2 = PurchaseReturnItem::create([
            'purchase_return_id' => $pr->id,
            'medicine_id' => $med->id,
            'batch_id' => $batch260->id,
            'medicine_name' => $med->name,
            'unit' => 'BTL',
            'batch_number' => 'LWJ24260',
            'expired_date' => '2027-06-01',
            'qty' => 3,
            'unit_price' => 49528,
            'subtotal' => 148584,
            'status' => 'sudah_diambil',
            'stock_deducted' => true,
        ]);

        // Baris 3: LWJ24346 (Qty: 3), tetapi batch_id masih menunjuk ke LWJ24215 dan log hanya memotong 1
        $item3 = PurchaseReturnItem::create([
            'purchase_return_id' => $pr->id,
            'medicine_id' => $med->id,
            'batch_id' => $batch215->id,
            'medicine_name' => $med->name,
            'unit' => 'BTL',
            'batch_number' => 'LWJ24346',
            'expired_date' => '2027-09-01',
            'qty' => 3,
            'unit_price' => 49528,
            'subtotal' => 148584,
            'status' => 'sudah_diambil',
            'stock_deducted' => true,
        ]);

        // Kondisi ItemsLog di production sebelum sinkronisasi:
        // Log 1: LWJ24215, Qty 1
        ItemsLog::create([
            'transaction_code' => 'RET-2610/0007',
            'code' => '2610LOG-0001',
            'type' => 'RT',
            'medicine_id' => $med->id,
            'qty' => 1,
            'qty_before' => 1,
            'qty_after' => 0,
            'total' => 49528,
            'date' => '2026-10-20 12:30:05',
            'status' => 4,
            'batches_id' => $batch215->id,
        ]);

        // Log 2: LWJ24215, Qty 1 (seharusnya LWJ24346, Qty 3)
        $log2 = ItemsLog::create([
            'transaction_code' => 'RET-2610/0007',
            'code' => '2610LOG-0002',
            'type' => 'RT',
            'medicine_id' => $med->id,
            'qty' => 1,
            'qty_before' => 0,
            'qty_after' => -1,
            'total' => 49528,
            'date' => '2026-10-20 12:30:20',
            'status' => 4,
            'batches_id' => $batch215->id,
        ]);

        // Log 3: LWJ24260, Qty 3
        ItemsLog::create([
            'transaction_code' => 'RET-2610/0007',
            'code' => '2610LOG-0003',
            'type' => 'RT',
            'medicine_id' => $med->id,
            'qty' => 3,
            'qty_before' => -1,
            'qty_after' => -4,
            'total' => 148584,
            'date' => '2026-10-20 12:30:32',
            'status' => 4,
            'batches_id' => $batch260->id,
        ]);

        $exitCode = Artisan::call('retur:sync-manual-stock', [
            '--return-no' => 'RET-2610/0007',
        ]);

        $this->assertSame(0, $exitCode);

        $item1->refresh();
        $item2->refresh();
        $item3->refresh();
        $batch215->refresh();
        $batch260->refresh();
        $batch346->refresh();
        $med->refresh();
        $log2->refresh();

        // Item 3 sekarang terhubung ke batch LWJ24346
        $this->assertSame($batch346->id, (int) $item3->batch_id);
        $this->assertSame('LWJ24346', $item3->batch_number);

        // Log 2 sekarang mencatat LWJ24346 dengan Qty 3
        $this->assertSame($batch346->id, (int) $log2->batches_id);
        $this->assertEquals(3.0, (float) $log2->qty);

        // Stok batch LWJ24215 kembali +1 (dari -1 menjadi 0), LWJ24260 tetap 0, LWJ24346 berkurang 3 (dari 6 menjadi 3)
        $this->assertEquals(0.0, (float) $batch215->stock);
        $this->assertEquals(0.0, (float) $batch260->stock);
        $this->assertEquals(3.0, (float) $batch346->stock);

        // Stok master obat disesuaikan selisih -2 (dari -4 menjadi -6)
        $this->assertEquals(-6.0, (float) $med->stock);
    }

    public function test_update_item_adjusts_stock_and_items_log_when_already_deducted(): void
    {
        $med = Medicines::create([
            'code' => 'FIESTA01',
            'name' => '-FIESTA INTIM WARM GEL 70 ML',
            'unit' => 'BTL',
            'content' => 1,
            'stock' => 9,
        ]);

        $batch = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 1,
            'name' => 'LWJ24346',
            'expired_date' => '2027-09-01',
            'stock' => 5,
            'status' => 1,
        ]);

        $pr = PurchaseReturn::create([
            'return_no' => 'RET-2610/0008',
            'invoice_number' => 'D261430965',
            'creditor_code' => 'AMS',
            'pharmacy_id' => 1,
            'total_amount' => 49528,
            'status' => 'dikonfirmasi',
            'status_date' => '2026-10-20',
            'confirmed_date' => '2026-10-20',
            'is_manual' => true,
        ]);

        $item = PurchaseReturnItem::create([
            'purchase_return_id' => $pr->id,
            'medicine_id' => $med->id,
            'batch_id' => $batch->id,
            'medicine_name' => $med->name,
            'unit' => 'BTL',
            'batch_number' => 'LWJ24346',
            'expired_date' => '2027-09-01',
            'qty' => 1,
            'unit_price' => 49528,
            'subtotal' => 49528,
            'status' => 'dikonfirmasi',
            'stock_deducted' => true,
        ]);

        $log = ItemsLog::create([
            'transaction_code' => 'RET-2610/0008',
            'code' => '2610LOG-0009',
            'type' => 'RT',
            'medicine_id' => $med->id,
            'qty' => 1,
            'qty_before' => 10,
            'qty_after' => 9,
            'total' => 49528,
            'date' => '2026-10-20 12:30:20',
            'status' => 4,
            'batches_id' => $batch->id,
        ]);

        $controller = new class extends PurchaseReturnController {
            public function __construct()
            {
                // Bypass auth middleware for unit testing updateItem logic
            }
        };

        $request = Request::create("/finance/purchase-returns/items/{$item->id}", 'PUT', [
            'qty' => 3,
            'unit_price' => 49528,
            'reason' => 'Salah Pesan',
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = $controller->updateItem($request, $item->id);
        $this->assertSame(200, $response->getStatusCode());

        $item->refresh();
        $batch->refresh();
        $med->refresh();
        $log->refresh();
        $pr->refresh();

        $this->assertEquals(3.0, (float) $item->qty);
        $this->assertEquals(148584.0, (float) $item->subtotal);
        $this->assertEquals(148584.0, (float) $pr->total_amount);

        // Selisih +2 ekstra dipotong dari stok obat (9 -> 7) dan batch (5 -> 3)
        $this->assertEquals(7.0, (float) $med->stock);
        $this->assertEquals(3.0, (float) $batch->stock);

        // Log kartu stok ikut ter-update ke qty = 3 dan qty_after = 7
        $this->assertEquals(3.0, (float) $log->qty);
        $this->assertEquals(7.0, (float) $log->qty_after);
        $this->assertEquals(148584.0, (float) $log->total);
    }

    public function test_resolve_batch_ignores_stale_batch_id_when_batch_number_differs(): void
    {
        $med = Medicines::create([
            'code' => 'FIESTA01',
            'name' => '-FIESTA INTIM WARM GEL 70 ML',
            'unit' => 'BTL',
            'content' => 1,
            'stock' => 10,
        ]);

        $staleBatch = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 1,
            'name' => 'LWJ24215',
            'expired_date' => '2027-04-01',
            'stock' => 1,
            'status' => 1,
        ]);

        $otherPharmacyBatch = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 9,
            'name' => 'LWJ24346',
            'expired_date' => '2027-09-01',
            'stock' => 6,
            'status' => 1,
        ]);

        $pr = PurchaseReturn::create([
            'return_no' => 'RET-2610/0010',
            'invoice_number' => 'D261430965',
            'creditor_code' => 'AMS',
            'pharmacy_id' => 1,
            'total_amount' => 148584,
            'status' => 'dikonfirmasi',
            'status_date' => '2026-10-20',
            'is_manual' => true,
        ]);

        $item = PurchaseReturnItem::create([
            'purchase_return_id' => $pr->id,
            'medicine_id' => $med->id,
            'batch_id' => $staleBatch->id, // batch_id bawaan menunjuk LWJ24215, tetapi batch_number LWJ24346
            'medicine_name' => $med->name,
            'unit' => 'BTL',
            'batch_number' => 'LWJ24346',
            'expired_date' => '2027-09-01',
            'qty' => 3,
            'unit_price' => 49528,
            'subtotal' => 148584,
            'status' => 'dikonfirmasi',
            'stock_deducted' => false,
        ]);

        $controller = new class extends PurchaseReturnController {
            public function __construct() {}
            public function resolveForTest(PurchaseReturnItem $item, Medicines $med, int $pharmacyId): Batches
            {
                return $this->resolveBatchForItem($item, $med, $pharmacyId);
            }
        };

        $resolved = $controller->resolveForTest($item, $med, 1);

        $this->assertNotSame($staleBatch->id, (int) $resolved->id);
        $this->assertNotSame($otherPharmacyBatch->id, (int) $resolved->id);
        $this->assertSame(1, (int) $resolved->pharmacy_id);
        $this->assertSame('LWJ24346', $resolved->name);
    }

    public function test_sync_dry_run_does_not_create_missing_batches_or_mutate_stock(): void
    {
        $med = Medicines::create([
            'code' => 'FIESTA01',
            'name' => '-FIESTA INTIM WARM GEL 70 ML',
            'unit' => 'BTL',
            'content' => 1,
            'stock' => 6,
        ]);

        $pr = PurchaseReturn::create([
            'return_no' => 'RET-2610/0011',
            'invoice_number' => 'D261430965',
            'creditor_code' => 'AMS',
            'pharmacy_id' => 1,
            'total_amount' => 148584,
            'status' => 'dikonfirmasi',
            'status_date' => '2026-10-20',
            'is_manual' => true,
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $pr->id,
            'medicine_id' => $med->id,
            'batch_id' => null,
            'medicine_name' => $med->name,
            'unit' => 'BTL',
            'batch_number' => 'LWJ99999',
            'expired_date' => '2027-09-01',
            'qty' => 2,
            'unit_price' => 49528,
            'subtotal' => 99056,
            'status' => 'dikonfirmasi',
            'stock_deducted' => false,
        ]);

        $exitCode = Artisan::call('retur:sync-manual-stock', [
            '--return-no' => 'RET-2610/0011',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, Batches::count());
        $this->assertSame(0, ItemsLog::count());
        $this->assertEquals(6.0, (float) $med->fresh()->stock);
    }
}
