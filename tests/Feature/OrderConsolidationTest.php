<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItems;
use App\Services\OrderConsolidation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderConsolidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'consolidation_test', 'database.connections.consolidation_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        Schema::create('users', fn (Blueprint $t) => $t->id());
        DB::table('users')->insert(['id' => 1]);
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->integer('pharmacy_id'); $t->integer('user_id');
            $t->string('code')->unique(); $t->string('date')->nullable();
            $t->integer('status'); $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('medicine_id');
            $t->string('creditor_code'); $t->string('quantity'); $t->string('total');
            $t->string('note')->nullable(); $t->integer('status'); $t->timestamps();
        });
        Schema::create('receiving_items', function (Blueprint $t) {
            $t->id(); $t->integer('order_items_id'); $t->decimal('qty_received');
            $t->integer('batches_id')->nullable();
        });
        (require database_path('migrations/2026_09_09_120000_add_order_consolidation.php'))->up();
        foreach ([1, 2] as $id) {
            Order::create(['pharmacy_id' => 9, 'user_id' => 1, 'code' => 'BPBA-' . $id, 'status' => 1]);
            OrderItems::create(['order_id' => $id, 'medicine_id' => $id, 'creditor_code' => 'PBF-A', 'quantity' => 10, 'total' => 1000, 'status' => 0]);
        }
    }

    protected function tearDown(): void
    {
        DB::purge('consolidation_test');
        parent::tearDown();
    }

    public function test_partial_receipt_consolidation_preserves_history_and_retry_is_idempotent(): void
    {
        DB::table('receiving_items')->insert(['order_items_id' => 1, 'qty_received' => 4, 'batches_id' => 8]);
        $selection = [['id' => 1, 'quantity' => 6], ['id' => 2, 'quantity' => 5]];
        $key = (string) Str::uuid();
        $service = new OrderConsolidation;
        $order = $service->create($selection, 9, 1, $key);
        $this->assertSame($order->id, $service->create($selection, 9, 1, $key)->id);
        $this->assertStringStartsWith('KONS-', $order->code);
        $this->assertSame('Konsolidasi untuk penerimaan—bukan pesanan ulang ke PBF', $order->note);
        $this->assertEquals(4, OrderItems::find(1)->quantity);
        $this->assertEquals(10, OrderItems::find(1)->original_quantity);
        $this->assertEquals(1000, OrderItems::find(1)->original_total);
        $this->assertEquals(11, OrderItems::where('order_id', $order->id)->sum('quantity'));
        $this->assertEquals(2000, OrderItems::sum('total'));
        $this->assertEquals(2, DB::table('order_item_movements')->count());
        $this->assertEquals(1, DB::table('receiving_items')->count());
        $this->assertEquals(8, DB::table('receiving_items')->value('batches_id'));
    }

    /** @dataProvider invalidSelections */
    public function test_invalid_consolidation_is_atomic(string $scenario): void
    {
        $selection = [['id' => 1, 'quantity' => 5], ['id' => 2, 'quantity' => 5]];
        if ($scenario === 'consolidation') Order::find(2)->update(['is_consolidation' => true]);
        if ($scenario === 'draft') DB::table('receiving_items')->insert(['order_items_id' => 2, 'qty_received' => 1]);
        if ($scenario === 'supplier') OrderItems::find(2)->update(['creditor_code' => 'PBF-B']);
        if ($scenario === 'pharmacy') Order::find(2)->update(['pharmacy_id' => 2]);
        if ($scenario === 'closed') Order::find(2)->update(['status' => 3]);
        if ($scenario === 'excess') $selection[1]['quantity'] = 11;
        if ($scenario === 'duplicate') $selection[1]['id'] = 1;
        if ($scenario === 'single_order') OrderItems::find(2)->update(['order_id' => 1]);
        try {
            (new OrderConsolidation)->create($selection, 9, 1, (string) Str::uuid());
            $this->fail('Invalid selection was accepted.');
        } catch (ValidationException $e) {
            $this->assertEquals(2, Order::count());
            $this->assertEquals(20, OrderItems::sum('quantity'));
            $this->assertEquals(0, DB::table('order_item_movements')->count());
        }
    }

    public static function invalidSelections(): array
    {
        return array_map(fn ($value) => [$value], ['consolidation', 'draft', 'supplier', 'pharmacy', 'closed', 'excess', 'duplicate', 'single_order']);
    }

    public function test_cancel_consolidation_restores_original_quantities_and_cleans_up_records(): void
    {
        $selection = [['id' => 1, 'quantity' => 6], ['id' => 2, 'quantity' => 5]];
        $service = new OrderConsolidation;
        $order = $service->create($selection, 9, 1, (string) Str::uuid());

        $this->assertEquals(4, OrderItems::find(1)->quantity);
        $this->assertEquals(5, OrderItems::find(2)->quantity);
        $this->assertEquals(2, DB::table('order_item_movements')->count());

        $service->cancel($order->id, 9, 1);

        $this->assertNull(Order::find($order->id));
        $this->assertEquals(10, OrderItems::find(1)->quantity);
        $this->assertEquals(1000, OrderItems::find(1)->total);
        $this->assertNull(OrderItems::find(1)->original_quantity);
        $this->assertNull(OrderItems::find(1)->original_total);

        $this->assertEquals(10, OrderItems::find(2)->quantity);
        $this->assertEquals(1000, OrderItems::find(2)->total);
        $this->assertNull(OrderItems::find(2)->original_quantity);
        $this->assertNull(OrderItems::find(2)->original_total);

        $this->assertEquals(0, DB::table('order_item_movements')->count());
        $this->assertEquals(2, OrderItems::count());
    }

    public function test_cancel_consolidation_fails_if_already_received(): void
    {
        $selection = [['id' => 1, 'quantity' => 6], ['id' => 2, 'quantity' => 5]];
        $service = new OrderConsolidation;
        $order = $service->create($selection, 9, 1, (string) Str::uuid());

        $targetItem = OrderItems::where('order_id', $order->id)->first();
        DB::table('receiving_items')->insert([
            'order_items_id' => $targetItem->id,
            'qty_received' => 2,
            'batches_id' => 99,
        ]);

        $this->expectException(ValidationException::class);
        $service->cancel($order->id, 9, 1);
    }
}
