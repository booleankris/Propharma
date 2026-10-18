<?php

namespace Tests\Feature;

use App\Models\Medicines;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SmartOrderBulkAddTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->user = User::whereHas('roles', fn ($q) => $q->where('name', 'administrator'))->first()
            ?? User::first();

        $this->actingAs($this->user);

        $pharmacyId = (int) ($this->user->pharmacy_id ?: 1);

        $this->order = Order::create([
            'pharmacy_id' => $pharmacyId,
            'user_id' => $this->user->id,
            'code' => 'TEST-ORD-' . time(),
            'date' => now()->format('Y-m-d'),
            'status' => 0,
        ]);
    }

    public function test_add_items_bulk_successfully_adds_multiple_items()
    {
        $pharmacyId = $this->order->pharmacy_id;

        $med1 = Medicines::create([
            'pharmacy_id' => $pharmacyId,
            'code' => 'TEST-REG-01',
            'name' => 'Obat Reguler Test',
            'type' => 'REGULER',
            'raw_price' => 10000,
            'content' => 1,
            'unit' => 'TAB',
            'packaging' => 'STRIP',
            'minimal_stock' => 0,
            'status' => 1,
        ]);

        $med2 = Medicines::create([
            'pharmacy_id' => $pharmacyId,
            'code' => 'TEST-REG-02',
            'name' => 'Obat Reguler Test 2',
            'type' => 'REGULER',
            'raw_price' => 20000,
            'content' => 10,
            'unit' => 'TAB',
            'packaging' => 'BOX',
            'minimal_stock' => 0,
            'status' => 1,
        ]);

        $payload = [
            'order_id' => $this->order->id,
            'items' => [
                [
                    'medicine_id' => $med1->id,
                    'quantity' => 2,
                    'pack' => false,
                ],
                [
                    'medicine_id' => $med2->id,
                    'quantity' => 1,
                    'pack' => true, // 1 box = 10 tab * 20000 = 200000
                ],
            ],
        ];

        $response = $this->postJson(route('orders.addItemsBulk'), $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $this->order->id,
            'medicine_id' => $med1->id,
            'quantity' => '2',
            'price' => '10000',
            'total' => '20000',
            'pack' => 0,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $this->order->id,
            'medicine_id' => $med2->id,
            'quantity' => '1',
            'price' => '200000',
            'total' => '200000',
            'pack' => 1,
        ]);

        // Verify shared SP code for reguler medicines
        $item1 = OrderItems::where('order_id', $this->order->id)->where('medicine_id', $med1->id)->first();
        $item2 = OrderItems::where('order_id', $this->order->id)->where('medicine_id', $med2->id)->first();
        $this->assertNotEmpty($item1->order_items_code);
        $this->assertEquals($item1->order_items_code, $item2->order_items_code);
    }

    public function test_add_items_bulk_handles_36_items_efficiently()
    {
        $meds = Medicines::take(36)->get();
        if ($meds->count() < 36) {
            $needed = 36 - $meds->count();
            for ($i = 0; $i < $needed; $i++) {
                Medicines::create([
                    'pharmacy_id' => $this->order->pharmacy_id,
                    'code' => 'BULK-TEST-' . $i . '-' . time(),
                    'name' => 'Obat Bulk Test ' . $i,
                    'type' => 'REGULER',
                    'raw_price' => 5000,
                    'content' => 1,
                    'unit' => 'TAB',
                    'status' => 1,
                ]);
            }
            $meds = Medicines::take(36)->get();
        }

        $items = [];
        foreach ($meds as $med) {
            $items[] = [
                'medicine_id' => $med->id,
                'quantity' => 1,
                'pack' => false,
            ];
        }

        $response = $this->postJson(route('orders.addItemsBulk'), [
            'order_id' => $this->order->id,
            'items' => $items,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $count = OrderItems::where('order_id', $this->order->id)->count();
        $this->assertEquals(36, $count);
    }

    public function test_add_items_bulk_validation_fails_for_invalid_input()
    {
        // 1. Missing items
        $response = $this->postJson(route('orders.addItemsBulk'), [
            'order_id' => $this->order->id,
            'items' => [],
        ]);
        $response->assertStatus(422);

        // 2. Invalid order_id
        $response = $this->postJson(route('orders.addItemsBulk'), [
            'order_id' => 99999999,
            'items' => [
                ['medicine_id' => 1, 'quantity' => 1],
            ],
        ]);
        $response->assertStatus(422);

        // 3. Zero quantity
        $response = $this->postJson(route('orders.addItemsBulk'), [
            'order_id' => $this->order->id,
            'items' => [
                ['medicine_id' => 1, 'quantity' => 0],
            ],
        ]);
        $response->assertStatus(422);
    }
}
