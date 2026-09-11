<?php

namespace Tests\Feature;

use App\Models\Batches;
use App\Models\Creditor;
use App\Models\Medicines;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\OrderItemMovement;
use App\Models\Receiving;
use App\Models\ReceivingDetails;
use App\Models\ReceivingItems;
use App\Models\User;
use App\Services\OrderConsolidation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderAndReceivingFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected int $pharmacyId;
    protected Creditor $creditor;
    protected Medicines $medicine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::whereHas('roles', fn ($q) => $q->where('name', 'administrator'))->first()
            ?? User::first();

        $this->actingAs($this->user);

        $this->pharmacyId = getPurchasingPharmacyId();
        if ($this->pharmacyId === 0) {
            $this->pharmacyId = 1;
        }

        $this->creditor = Creditor::first() ?? Creditor::create([
            'code' => 'PBF-TEST',
            'name' => 'PBF Test Supplier',
            'status' => 1,
        ]);

        $this->medicine = Medicines::first() ?? Medicines::create([
            'code' => 'MED-TEST-01',
            'name' => 'Obat Test Uji Coba',
            'stock' => 100,
            'raw_price' => 15000,
            'content' => 1,
            'unit' => 'Strip',
            'status' => 1,
        ]);
    }

    /**
     * 1. Test Alur Pemesanan (Order Draft -> Items -> Selesai Dipesan)
     */
    public function test_pemesanan_flow(): Order
    {
        // Buat Order / BPBA baru
        $order = Order::create([
            'pharmacy_id' => $this->pharmacyId,
            'user_id' => $this->user->id,
            'code' => 'BK-' . date('ym') . '/TEST-' . rand(1000, 9999),
            'date' => date('d/m/Y'),
            'status' => 0, // Draft
            'is_consolidation' => false,
        ]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 0]);

        // Tambah Order Items
        $item = OrderItems::create([
            'order_id' => $order->id,
            'medicine_id' => $this->medicine->id,
            'order_items_code' => 'SP-TEST-' . $order->id,
            'creditor_code' => $this->creditor->code,
            'quantity' => 20,
            'price' => 15000,
            'total' => 300000,
            'pack' => 0,
            'status' => 0,
        ]);

        $this->assertDatabaseHas('order_items', [
            'id' => $item->id,
            'order_id' => $order->id,
            'quantity' => 20,
        ]);

        // Selesaikan pesanan: ubah status ke 1 (Dipesan/Open)
        $order->update(['status' => 1]);
        $this->assertEquals(1, $order->fresh()->status);

        return $order;
    }

    /**
     * 2. Test Alur Konsolidasi (Pindahkan sisa item dari BPBA Asal ke BPBA Target)
     */
    public function test_konsolidasi_flow(): void
    {
        $sourceOrder = Order::create([
            'pharmacy_id' => $this->pharmacyId,
            'user_id' => $this->user->id,
            'code' => 'BK-SRC-' . rand(1000, 9999),
            'date' => date('d/m/Y'),
            'status' => 1,
            'is_consolidation' => false,
        ]);

        $targetOrder = Order::create([
            'pharmacy_id' => $this->pharmacyId,
            'user_id' => $this->user->id,
            'code' => 'BK-TGT-' . rand(1000, 9999),
            'date' => date('d/m/Y'),
            'status' => 1,
            'is_consolidation' => false,
        ]);

        $sourceItem = OrderItems::create([
            'order_id' => $sourceOrder->id,
            'medicine_id' => $this->medicine->id,
            'order_items_code' => 'SP-SRC-' . rand(100, 999),
            'creditor_code' => $this->creditor->code,
            'quantity' => 10,
            'price' => 20000,
            'total' => 200000,
            'pack' => 0,
            'status' => 0,
        ]);

        $targetItem = OrderItems::create([
            'order_id' => $targetOrder->id,
            'medicine_id' => $this->medicine->id,
            'order_items_code' => 'SP-TGT-' . rand(100, 999),
            'creditor_code' => $this->creditor->code,
            'quantity' => 5,
            'price' => 20000,
            'total' => 100000,
            'pack' => 0,
            'status' => 0,
        ]);

        $service = new OrderConsolidation();
        $uuid = (string) Str::uuid();

        // Konsolidasi 4 qty dari sourceItem ke targetOrder
        $resOrder = $service->create(
            [['id' => $sourceItem->id, 'quantity' => 4]],
            $targetOrder->id,
            $this->pharmacyId,
            $this->user->id,
            $uuid
        );

        $this->assertEquals($targetOrder->id, $resOrder->id);

        // Cek Source Item: quantity berkurang 4 (10 - 4 = 6), original_quantity tercatat 10
        $sourceItemFresh = $sourceItem->fresh();
        $this->assertEquals(6.0, (float) $sourceItemFresh->quantity);
        $this->assertEquals(10.0, (float) $sourceItemFresh->original_quantity);

        // Cek Movement tercatat
        $movement = OrderItemMovement::where('source_item_id', $sourceItem->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(4.0, (float) $movement->quantity);

        // Cek Target Item duplikat dibuat di targetOrder
        $createdTargetItem = OrderItems::where('id', $movement->target_item_id)->first();
        $this->assertNotNull($createdTargetItem);
        $this->assertEquals($targetOrder->id, $createdTargetItem->order_id);
        $this->assertEquals(4.0, (float) $createdTargetItem->quantity);
    }

    /**
     * 3. Test Alur Batal Konsolidasi (Rollback pemindahan item)
     */
    public function test_batal_konsolidasi_flow(): void
    {
        $sourceOrder = Order::create([
            'pharmacy_id' => $this->pharmacyId,
            'user_id' => $this->user->id,
            'code' => 'BK-SRC-' . rand(1000, 9999),
            'date' => date('d/m/Y'),
            'status' => 1,
            'is_consolidation' => false,
        ]);

        $targetOrder = Order::create([
            'pharmacy_id' => $this->pharmacyId,
            'user_id' => $this->user->id,
            'code' => 'BK-TGT-' . rand(1000, 9999),
            'date' => date('d/m/Y'),
            'status' => 1,
            'is_consolidation' => false,
        ]);

        $sourceItem = OrderItems::create([
            'order_id' => $sourceOrder->id,
            'medicine_id' => $this->medicine->id,
            'creditor_code' => $this->creditor->code,
            'quantity' => 10,
            'price' => 10000,
            'total' => 100000,
            'pack' => 0,
            'status' => 0,
        ]);

        $service = new OrderConsolidation();
        $uuid = (string) Str::uuid();

        $service->create(
            [['id' => $sourceItem->id, 'quantity' => 7]],
            $targetOrder->id,
            $this->pharmacyId,
            $this->user->id,
            $uuid
        );

        $movement = OrderItemMovement::where('source_item_id', $sourceItem->id)->firstOrFail();
        $targetItemId = $movement->target_item_id;

        // Rollback pemindahan
        $service->rollbackItem($targetItemId, $this->pharmacyId, $this->user->id);

        // Source item harus kembali ke 10 dan snapshot di-clear
        $sourceFresh = $sourceItem->fresh();
        $this->assertEquals(10.0, (float) $sourceFresh->quantity);
        $this->assertNull($sourceFresh->original_quantity);

        // Item di target dan record movement harus sudah dihapus
        $this->assertNull(OrderItems::find($targetItemId));
        $this->assertDatabaseMissing('order_item_movements', ['id' => $movement->id]);
    }

    /**
     * 4. Test Alur Penerimaan Barang & Pembuatan Nomor Terima (NT)
     */
    public function test_penerimaan_and_nt_generation_flow(): void
    {
        $order = Order::create([
            'pharmacy_id' => $this->pharmacyId,
            'user_id' => $this->user->id,
            'code' => 'BK-REC-' . rand(1000, 9999),
            'date' => date('d/m/Y'),
            'status' => 1,
            'is_consolidation' => false,
        ]);

        $orderItem = OrderItems::create([
            'order_id' => $order->id,
            'medicine_id' => $this->medicine->id,
            'order_items_code' => 'SP-TEST-' . rand(100, 999),
            'creditor_code' => $this->creditor->code,
            'quantity' => 10,
            'price' => 15000,
            'total' => 150000,
            'pack' => 0,
            'status' => 0,
        ]);

        $receiving = Receiving::create([
            'pharmacy_id' => $this->pharmacyId,
            'code' => 'RCV-' . rand(1000, 9999),
            'date' => date('d/m/Y'),
            'status' => 0,
        ]);

        $order->update(['receiving_id' => $receiving->id]);

        // Input Faktur (ReceivingDetails)
        $details = ReceivingDetails::create([
            'receiving_id' => $receiving->id,
            'invoice_number' => 'FAKTUR-TEST-' . rand(1000, 9999),
            'creditor_code' => $this->creditor->code,
            'invoice_date' => date('Y-m-d'),
            'invoice_due' => date('Y-m-d', strtotime('+30 days')),
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'TANPA',
            'invoice_times' => 30,
        ]);

        // Input Item Penerimaan
        $rItem = ReceivingItems::create([
            'receiving_details_id' => $details->id,
            'order_items_id' => $orderItem->id,
            'qty_received' => 10,
            'qty' => 10,
            'raw_price' => 15000,
            'discount' => 0,
            'extra_discount' => 0,
            'expired_date' => date('Y-m-d', strtotime('+1 year')),
            'batch' => 'BATCH-TEST-01',
            'total' => 150000,
            'status' => 0,
        ]);

        // Panggil endpoint / controller saveOrder untuk membukukan ke stok dan membuat NT
        $controller = app(\App\Http\Controllers\Orders\ReceivingController::class);
        $request = new \Illuminate\Http\Request([
            'receivingid' => $receiving->id,
            'orderid' => $order->id,
        ]);

        $response = $controller->saveOrder($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);

        // Verifikasi Nomor Terima (NT) terbuat dengan prefix NT-
        $detailsFresh = $details->fresh();
        $this->assertNotEmpty($detailsFresh->receiving_details_code);
        $this->assertStringStartsWith('NT-', $detailsFresh->receiving_details_code);

        // Verifikasi Batch dibuat dan rItem memiliki batches_id
        $rItemFresh = $rItem->fresh();
        $this->assertNotNull($rItemFresh->batches_id);

        $batch = Batches::find($rItemFresh->batches_id);
        $this->assertNotNull($batch);
        $this->assertEquals('BATCH-TEST-01', $batch->name);
    }

    /**
     * 5. Test Tambahkan Item ke Faktur/NT Tertentu & Pindahkan Item antar NT
     */
    public function test_revisi_faktur_add_and_move_item(): void
    {
        $order = Order::create([
            'pharmacy_id' => $this->pharmacyId,
            'user_id' => $this->user->id,
            'code' => 'BK-REV-' . rand(1000, 9999),
            'date' => date('d/m/Y'),
            'status' => 1,
            'is_consolidation' => false,
        ]);

        $orderItem = OrderItems::create([
            'order_id' => $order->id,
            'medicine_id' => $this->medicine->id,
            'order_items_code' => 'SP-REV-' . rand(100, 999),
            'creditor_code' => $this->creditor->code,
            'quantity' => 10,
            'price' => 15000,
            'total' => 150000,
            'pack' => 0,
            'status' => 0,
        ]);

        $receiving = Receiving::create([
            'pharmacy_id' => $this->pharmacyId,
            'code' => 'RCV-REV-' . rand(1000, 9999),
            'date' => date('d/m/Y'),
            'status' => 0,
        ]);

        // NT A
        $ntA = ReceivingDetails::create([
            'receiving_id' => $receiving->id,
            'receiving_details_code' => 'NT-26-09/9001',
            'invoice_number' => 'FAKTUR-A',
            'creditor_code' => $this->creditor->code,
        ]);

        // NT B
        $ntB = ReceivingDetails::create([
            'receiving_id' => $receiving->id,
            'receiving_details_code' => 'NT-26-09/9002',
            'invoice_number' => 'FAKTUR-B',
            'creditor_code' => $this->creditor->code,
        ]);

        $controller = app(\App\Http\Controllers\Orders\ReceivingController::class);

        // A. Tambahkan item baru ke NT A secara spesifik melalui addRevisionItem
        $addRequest = new \Illuminate\Http\Request([
            'receiving_details_id' => $ntA->id,
            'order_items_id' => $orderItem->id,
            'batch' => 'BATCH-NTA',
            'expired_date' => date('Y-m-d', strtotime('+2 years')),
            'qty_received' => 3,
            'raw_price' => 15000,
            'pack' => false,
        ]);

        $addResponse = $controller->addRevisionItem($addRequest, $order->id);
        $addData = $addResponse->getData(true);

        $this->assertTrue($addData['success']);

        $itemInNtA = ReceivingItems::where('receiving_details_id', $ntA->id)->first();
        $this->assertNotNull($itemInNtA);
        $this->assertEquals('BATCH-NTA', $itemInNtA->batch);

        // B. Pindahkan item dari NT A ke NT B melalui moveRevisionItem
        $moveRequest = new \Illuminate\Http\Request([
            'receiving_item_id' => $itemInNtA->id,
            'target_details_id' => $ntB->id,
        ]);

        $moveResponse = $controller->moveRevisionItem($moveRequest, $order->id);
        $moveData = $moveResponse->getData(true);

        $this->assertTrue($moveData['success']);

        // Item sekarang berada di NT B
        $itemMoved = $itemInNtA->fresh();
        $this->assertEquals($ntB->id, $itemMoved->receiving_details_id);

        // NT A yang sudah kosong otomatis dibersihkan / dihapus
        $this->assertNull(ReceivingDetails::find($ntA->id));
    }
}
