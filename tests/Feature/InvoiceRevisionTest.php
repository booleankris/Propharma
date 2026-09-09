<?php

namespace Tests\Feature;

use App\Models\Creditor;
use App\Models\Medicines;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\ReceivingDetails;
use App\Models\ReceivingItems;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceRevisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    protected function getTestUser(): User
    {
        return User::first() ?? User::factory()->create();
    }

    protected function getCreditorCode(): string
    {
        return Creditor::first()->code ?? '001';
    }

    public function test_can_add_item_to_existing_nomor_terima(): void
    {
        $user = $this->getTestUser();
        $creditorCode = $this->getCreditorCode();
        $this->actingAs($user);

        $med = Medicines::first();

        $order = Order::create([
            'pharmacy_id' => 1,
            'user_id' => $user->id,
            'code' => 'BPBA-TEST-' . uniqid(),
            'status' => 1,
        ]);

        $orderItem = OrderItems::create([
            'order_id' => $order->id,
            'medicine_id' => $med->id,
            'creditor_code' => $creditorCode,
            'pack' => 0,
            'quantity' => 5,
            'price' => 550000,
            'total' => 2750000,
            'status' => 0,
        ]);

        $recDetail = ReceivingDetails::create([
            'receiving_details_code' => 'NT-TEST-' . uniqid(),
            'invoice_number' => 'FAK-12345',
            'creditor_code' => $creditorCode,
        ]);

        $initialStock = (float) $med->fresh()->stock;

        $response = $this->postJson("/orders/{$order->id}/revision/add-item", [
            'receiving_details_id' => $recDetail->id,
            'order_items_id' => $orderItem->id,
            'batch' => 'BATCH-TEST-PLX1',
            'expired_date' => '2027-12-31',
            'qty_received' => 5,
            'raw_price' => '550000',
            'discount' => '0',
            'extra_discount' => '0',
            'total' => '2750000',
            'status' => 1,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('receiving_items', [
            'receiving_details_id' => $recDetail->id,
            'order_items_id' => $orderItem->id,
            'batch' => 'BATCH-TEST-PLX1',
            'qty_received' => 5,
        ]);

        $this->assertEquals($initialStock + 5, (float) $med->fresh()->stock);
    }

    public function test_can_merge_separate_nomor_terima(): void
    {
        $user = $this->getTestUser();
        $creditorCode = $this->getCreditorCode();
        $this->actingAs($user);

        $meds = Medicines::take(2)->get();
        $med1 = $meds[0];
        $med2 = $meds[1] ?? $meds[0];

        $order = Order::create(['pharmacy_id' => 1, 'user_id' => $user->id, 'code' => 'BPBA-TEST-' . uniqid(), 'status' => 1]);
        $oi1 = OrderItems::create(['order_id' => $order->id, 'medicine_id' => $med1->id, 'creditor_code' => $creditorCode, 'pack' => 0, 'quantity' => 10, 'price' => 10000, 'total' => 100000]);
        $oi2 = OrderItems::create(['order_id' => $order->id, 'medicine_id' => $med2->id, 'creditor_code' => $creditorCode, 'pack' => 0, 'quantity' => 5, 'price' => 550000, 'total' => 2750000]);

        $rd1 = ReceivingDetails::create(['receiving_details_code' => 'NT-TEST-1', 'invoice_number' => 'FAK-123', 'creditor_code' => $creditorCode]);
        $rd2 = ReceivingDetails::create(['receiving_details_code' => 'NT-TEST-2', 'invoice_number' => 'FAK-123', 'creditor_code' => $creditorCode]);

        $ri1 = ReceivingItems::create(['receiving_details_id' => $rd1->id, 'order_items_id' => $oi1->id, 'qty_received' => 10, 'batch' => 'B1']);
        $ri2 = ReceivingItems::create(['receiving_details_id' => $rd2->id, 'order_items_id' => $oi2->id, 'qty_received' => 5, 'batch' => 'B2']);

        $response = $this->postJson("/orders/{$order->id}/revision/merge-details", [
            'source_details_id' => $rd2->id,
            'target_details_id' => $rd1->id,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertEquals($rd1->id, $ri2->fresh()->receiving_details_id);
        $this->assertDatabaseMissing('receiving_details', ['id' => $rd2->id]);
    }

    public function test_can_move_single_item_to_another_nomor_terima(): void
    {
        $user = $this->getTestUser();
        $creditorCode = $this->getCreditorCode();
        $this->actingAs($user);

        $med = Medicines::first();
        $order = Order::create(['pharmacy_id' => 1, 'user_id' => $user->id, 'code' => 'BPBA-TEST-' . uniqid(), 'status' => 1]);
        $oi = OrderItems::create(['order_id' => $order->id, 'medicine_id' => $med->id, 'creditor_code' => $creditorCode, 'pack' => 0, 'quantity' => 5, 'price' => 50000, 'total' => 250000]);

        $rdSource = ReceivingDetails::create(['receiving_details_code' => 'NT-SRC-' . uniqid(), 'creditor_code' => $creditorCode]);
        $rdTarget = ReceivingDetails::create(['receiving_details_code' => 'NT-TGT-' . uniqid(), 'creditor_code' => $creditorCode]);

        $ri = ReceivingItems::create(['receiving_details_id' => $rdSource->id, 'order_items_id' => $oi->id, 'qty_received' => 5, 'batch' => 'B2']);

        $response = $this->postJson("/orders/{$order->id}/revision/move-item", [
            'receiving_item_id' => $ri->id,
            'target_details_id' => $rdTarget->id,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertEquals($rdTarget->id, $ri->fresh()->receiving_details_id);
    }

    public function test_can_move_multiple_items_and_cleanup_empty_source(): void
    {
        $user = $this->getTestUser();
        $creditorCode = $this->getCreditorCode();
        $this->actingAs($user);

        $meds = Medicines::take(2)->get();
        $med1 = $meds[0];
        $med2 = $meds[1] ?? $meds[0];

        $order = Order::create(['pharmacy_id' => 1, 'user_id' => $user->id, 'code' => 'BPBA-TEST-' . uniqid(), 'status' => 1]);
        $oi1 = OrderItems::create(['order_id' => $order->id, 'medicine_id' => $med1->id, 'creditor_code' => $creditorCode, 'pack' => 0, 'quantity' => 10, 'price' => 10000, 'total' => 100000]);
        $oi2 = OrderItems::create(['order_id' => $order->id, 'medicine_id' => $med2->id, 'creditor_code' => $creditorCode, 'pack' => 0, 'quantity' => 5, 'price' => 550000, 'total' => 2750000]);

        $rdSource = ReceivingDetails::create(['receiving_details_code' => 'NT-SRC-' . uniqid(), 'creditor_code' => $creditorCode]);
        $rdTarget = ReceivingDetails::create(['receiving_details_code' => 'NT-TGT-' . uniqid(), 'creditor_code' => $creditorCode]);

        $ri1 = ReceivingItems::create(['receiving_details_id' => $rdSource->id, 'order_items_id' => $oi1->id, 'qty_received' => 10, 'batch' => 'B1']);
        $ri2 = ReceivingItems::create(['receiving_details_id' => $rdSource->id, 'order_items_id' => $oi2->id, 'qty_received' => 5, 'batch' => 'B2']);

        $response = $this->postJson("/orders/{$order->id}/revision/move-item", [
            'receiving_item_ids' => [$ri1->id, $ri2->id],
            'target_details_id' => $rdTarget->id,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertEquals($rdTarget->id, $ri1->fresh()->receiving_details_id);
        $this->assertEquals($rdTarget->id, $ri2->fresh()->receiving_details_id);
        // Source should be auto-deleted since all items were moved out
        $this->assertDatabaseMissing('receiving_details', ['id' => $rdSource->id]);
    }

    public function test_can_delete_empty_nomor_terima(): void
    {
        $user = $this->getTestUser();
        $creditorCode = $this->getCreditorCode();
        $this->actingAs($user);

        $order = Order::create(['pharmacy_id' => 1, 'user_id' => $user->id, 'code' => 'BPBA-TEST-' . uniqid(), 'status' => 1]);
        $rd = ReceivingDetails::create(['receiving_details_code' => 'NT-EMPTY-' . uniqid(), 'creditor_code' => $creditorCode]);

        $response = $this->deleteJson("/orders/{$order->id}/revision/details/{$rd->id}");
        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('receiving_details', ['id' => $rd->id]);
    }

    public function test_can_render_invoice_revision_page(): void
    {
        $user = $this->getTestUser();
        $creditorCode = $this->getCreditorCode();
        $this->actingAs($user);

        $med = Medicines::first();
        $order = Order::create(['pharmacy_id' => 1, 'user_id' => $user->id, 'code' => 'BPBA-TEST-' . uniqid(), 'status' => 1]);
        $oi = OrderItems::create(['order_id' => $order->id, 'medicine_id' => $med->id, 'creditor_code' => $creditorCode, 'pack' => 0, 'quantity' => 5, 'price' => 50000, 'total' => 250000]);

        $rd = ReceivingDetails::create(['receiving_details_code' => 'NT-TEST-' . uniqid(), 'creditor_code' => $creditorCode]);
        ReceivingItems::create(['receiving_details_id' => $rd->id, 'order_items_id' => $oi->id, 'qty_received' => 5, 'batch' => 'B1']);

        $response = $this->get("/orders/{$order->id}/revision");
        $response->assertOk();
        $response->assertSee('Revisi Faktur');
        $response->assertSee($rd->receiving_details_code);
    }
}
