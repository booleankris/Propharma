<?php

namespace Tests\Unit;

use App\Http\Controllers\Orders\OrdersController;
use App\Http\Controllers\Orders\ReceivingController;
use App\Models\Batches;
use App\Models\Creditor;
use App\Models\FinancePayment;
use App\Models\Medicines;
use App\Models\Order;
use App\Models\OrderItemMovement;
use App\Models\OrderItems;
use App\Models\Pharmacies;
use App\Models\Receiving;
use App\Models\ReceivingDetails;
use App\Models\ReceivingItems;
use App\Services\OrderConsolidation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaidReceivingProtectionTest extends TestCase
{
    protected Pharmacies $warehouse;
    protected Creditor $creditor;
    protected Medicines $medicine;
    protected Order $order;
    protected OrderItems $orderItem;
    protected Receiving $receiving;

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
            $table->string('ppn_type')->nullable();
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('unit')->default('TAB');
            $table->string('packaging')->nullable();
            $table->integer('content')->default(1);
            $table->double('stock')->default(0);
            $table->double('raw_price')->default(0);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedBigInteger('pharmacy_id')->default(9);
            $table->string('name');
            $table->date('expired_date')->nullable();
            $table->double('stock')->default(0);
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(9);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->string('code')->nullable();
            $table->string('date')->nullable();
            $table->integer('status')->default(2);
            $table->boolean('is_consolidation')->default(false);
            $table->string('consolidation_key')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('medicine_id');
            $table->string('order_items_code')->nullable();
            $table->string('creditor_code')->nullable();
            $table->boolean('pack')->default(false);
            $table->double('price')->default(0);
            $table->double('quantity')->default(0);
            $table->double('original_quantity')->nullable();
            $table->double('total')->default(0);
            $table->double('original_total')->nullable();
            $table->text('note')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('order_item_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_order_id')->nullable();
            $table->unsignedBigInteger('target_order_id')->nullable();
            $table->unsignedBigInteger('source_item_id')->nullable();
            $table->unsignedBigInteger('target_item_id')->nullable();
            $table->string('consolidation_key')->nullable();
            $table->timestamps();
        });

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(9);
            $table->string('code')->nullable();
            $table->string('date')->nullable();
            $table->integer('status')->default(3);
            $table->timestamps();
        });

        Schema::create('receiving_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id');
            $table->string('sp_code')->nullable();
            $table->string('receiving_details_code')->nullable();
            $table->string('creditor_code')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->integer('invoice_times')->nullable();
            $table->date('invoice_due')->nullable();
            $table->string('invoice_payment')->default('KREDIT');
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
            $table->double('raw_price')->default(0);
            $table->double('discount')->default(0);
            $table->double('extra_discount')->default(0);
            $table->double('subtotal')->default(0);
            $table->double('total')->default(0);
            $table->string('batch')->nullable();
            $table->date('expired_date')->nullable();
            $table->string('location')->nullable();
            $table->string('etalase')->nullable();
            $table->string('status')->default('Diterima Lengkap');
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

        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->unsignedBigInteger('receiving_detail_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('payment_type');
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->warehouse = Pharmacies::create([
            'id' => 9,
            'name' => 'GUDANG PMI',
            'status' => 1,
        ]);

        $this->creditor = Creditor::create([
            'code' => 'PBF01',
            'name' => 'PBF TEST',
            'ppn_type' => 'TANPA',
        ]);

        $this->medicine = Medicines::create([
            'code' => 'OBAT01',
            'name' => 'OXOPI SIR 60 ML',
            'unit' => 'BTL',
            'content' => 1,
            'stock' => 10,
            'raw_price' => 36000,
        ]);

        $this->receiving = Receiving::create([
            'pharmacy_id' => $this->warehouse->id,
            'code' => '2610RE0158',
            'date' => '05/10/2026',
            'status' => 3,
        ]);

        $this->order = Order::create([
            'pharmacy_id' => $this->warehouse->id,
            'receiving_id' => $this->receiving->id,
            'code' => 'BPBA-001',
            'date' => '05/10/2026',
            'status' => 3,
        ]);

        $this->orderItem = OrderItems::create([
            'order_id' => $this->order->id,
            'medicine_id' => $this->medicine->id,
            'order_items_code' => 'SP-001',
            'creditor_code' => $this->creditor->code,
            'pack' => false,
            'price' => 36000,
            'quantity' => 20,
            'total' => 720000,
            'status' => 1,
        ]);
    }

    private function seedDetail(string $code, string $invoice, bool $paid = true, bool $withItem = true, bool $withBatch = true): array
    {
        $detail = ReceivingDetails::create([
            'receiving_id' => $this->receiving->id,
            'sp_code' => 'SP-001',
            'receiving_details_code' => $code,
            'creditor_code' => $this->creditor->code,
            'invoice_number' => $invoice,
            'invoice_date' => '2026-10-05',
            'invoice_due' => '2026-11-05',
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'TANPA',
        ]);

        $batch = null;
        $item = null;
        if ($withItem) {
            if ($withBatch) {
                $batch = Batches::create([
                    'medicine_id' => $this->medicine->id,
                    'pharmacy_id' => $this->warehouse->id,
                    'name' => 'BATCH-' . $code,
                    'expired_date' => '2028-10-05',
                    'stock' => 2,
                ]);
            }

            $item = ReceivingItems::create([
                'receiving_details_id' => $detail->id,
                'order_items_id' => $this->orderItem->id,
                'batches_id' => $batch?->id,
                'qty_received' => 2,
                'qty' => 2,
                'raw_price' => 36000,
                'total' => 72000,
                'batch' => 'BATCH-' . $code,
                'expired_date' => '2028-10-05',
                'status' => 'Diterima Lengkap',
            ]);
        }

        $payment = null;
        if ($paid) {
            $payment = FinancePayment::create([
                'pharmacy_id' => $this->warehouse->id,
                'receiving_id' => $this->receiving->id,
                'receiving_detail_id' => $detail->id,
                'payment_type' => 'KREDIT',
                'payment_date' => '2026-11-17',
                'amount' => 72000,
            ]);
        }

        return compact('detail', 'batch', 'item', 'payment');
    }

    public function test_model_blocks_deleting_paid_receiving_detail_and_paid_receiving(): void
    {
        $paid = $this->seedDetail('NT-PAID', 'INV-PAID');

        try {
            $paid['detail']->delete();
            $this->fail('Expected RuntimeException when deleting paid ReceivingDetails.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sudah memiliki catatan pembayaran di Finance', $e->getMessage());
        }

        $this->assertDatabaseHas('receiving_details', ['id' => $paid['detail']->id]);
        $this->assertDatabaseHas('finance_payments', [
            'id' => $paid['payment']->id,
            'receiving_detail_id' => $paid['detail']->id,
        ]);

        // Even when a payment row only retains receiving_id (historical orphan), Deleting Receiving must fail.
        $orphanReceiving = Receiving::create([
            'pharmacy_id' => $this->warehouse->id,
            'code' => '2610RE9999',
            'date' => '05/10/2026',
            'status' => 3,
        ]);
        $orphanPayment = FinancePayment::create([
            'pharmacy_id' => $this->warehouse->id,
            'receiving_id' => $orphanReceiving->id,
            'receiving_detail_id' => null,
            'payment_type' => 'KREDIT',
            'payment_date' => '2026-11-17',
            'amount' => 155144,
        ]);

        try {
            $orphanReceiving->delete();
            $this->fail('Expected RuntimeException when deleting Receiving with FinancePayment.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sudah memiliki catatan pembayaran di Finance', $e->getMessage());
        }

        $this->assertDatabaseHas('receiving', ['id' => $orphanReceiving->id]);
        $this->assertDatabaseHas('finance_payments', ['id' => $orphanPayment->id]);
    }

    public function test_delete_revision_and_draft_items_are_blocked_when_detail_is_paid(): void
    {
        $paidCommitted = $this->seedDetail('NT-LOCK-1', 'INV-LOCK-1', true, true, true);
        $paidDraft = $this->seedDetail('NT-LOCK-2', 'INV-LOCK-2', true, true, false);
        $controller = app(ReceivingController::class);

        $resCommitted = $controller->deleteReceivingItem($paidCommitted['item']->id);
        $this->assertSame(422, $resCommitted->getStatusCode());
        $this->assertDatabaseHas('receiving_items', ['id' => $paidCommitted['item']->id]);
        $this->assertSame(10.0, (float) $this->medicine->fresh()->stock);
        $this->assertSame(2.0, (float) $paidCommitted['batch']->fresh()->stock);

        $resDraft = $controller->deleteReceivingDraftItem($paidDraft['item']->id);
        $this->assertSame(422, $resDraft->getStatusCode());
        $this->assertDatabaseHas('receiving_items', ['id' => $paidDraft['item']->id]);
        $this->assertDatabaseHas('receiving_details', ['id' => $paidDraft['detail']->id]);
    }

    public function test_update_add_merge_move_and_empty_delete_block_paid_details_while_allowing_unpaid_details(): void
    {
        $paid = $this->seedDetail('NT-PAID-A', 'INV-PAID-A', true, true, true);
        $unpaid1 = $this->seedDetail('NT-FREE-1', 'INV-FREE-1', false, true, true);
        $unpaid2 = $this->seedDetail('NT-FREE-2', 'INV-FREE-2', false, true, true);
        $paidEmpty = $this->seedDetail('NT-PAID-EMPTY', 'INV-PAID-EMPTY', true, false, false);
        $controller = app(ReceivingController::class);

        // 1. Update item inside paid detail -> blocked 422
        $resUpdatePaid = $controller->updateReceivingItem(new Request([
            'qty_received' => 1,
            'raw_price' => 36000,
            'batch' => 'BATCH-NT-PAID-A',
            'expired_date' => '2028-10-05',
            'discount' => 0,
            'extra_discount' => 0,
            'status' => 'Diterima Kurang',
            'receiving_details_id' => $paid['detail']->id,
        ]), $paid['item']->id);
        $this->assertSame(422, $resUpdatePaid->getStatusCode());
        $this->assertSame(2.0, (float) $paid['item']->fresh()->qty_received);

        // 2. Move unpaid item INTO paid detail -> blocked 422
        $resMoveIntoPaid = $controller->updateReceivingItem(new Request([
            'qty_received' => 2,
            'raw_price' => 36000,
            'batch' => 'BATCH-NT-FREE-1',
            'expired_date' => '2028-10-05',
            'discount' => 0,
            'extra_discount' => 0,
            'status' => 'Diterima Lengkap',
            'receiving_details_id' => $paid['detail']->id,
        ]), $unpaid1['item']->id);
        $this->assertSame(422, $resMoveIntoPaid->getStatusCode());
        $this->assertSame($unpaid1['detail']->id, $unpaid1['item']->fresh()->receiving_details_id);

        // 3. Add revision item to paid detail -> blocked 422
        $resAdd = $controller->addRevisionItem(new Request([
            'receiving_details_id' => $paid['detail']->id,
            'order_items_id' => $this->orderItem->id,
            'batch' => 'NEW-B',
            'expired_date' => '2028-10-05',
            'qty_received' => 1,
            'raw_price' => 36000,
        ]), $this->order->id);
        $this->assertSame(422, $resAdd->getStatusCode());

        // 4. Merge paid -> unpaid or unpaid -> paid -> blocked 422
        $resMerge = $controller->mergeRevisionDetails(new Request([
            'source_details_id' => $paid['detail']->id,
            'target_details_id' => $unpaid1['detail']->id,
        ]), $this->order->id);
        $this->assertSame(422, $resMerge->getStatusCode());
        $this->assertDatabaseHas('receiving_details', ['id' => $paid['detail']->id]);

        // 5. Move item out of paid detail -> blocked 422
        $resMoveItem = $controller->moveRevisionItem(new Request([
            'receiving_item_id' => $paid['item']->id,
            'target_details_id' => $unpaid1['detail']->id,
        ]), $this->order->id);
        $this->assertSame(422, $resMoveItem->getStatusCode());
        $this->assertSame($paid['detail']->id, $paid['item']->fresh()->receiving_details_id);

        // 6. Delete empty paid detail -> blocked 422
        $resDeleteEmpty = $controller->deleteEmptyRevisionDetails($this->order->id, $paidEmpty['detail']->id);
        $this->assertSame(422, $resDeleteEmpty->getStatusCode());
        $this->assertDatabaseHas('receiving_details', ['id' => $paidEmpty['detail']->id]);

        // 7. Reusing paid invoice_number in addReceivingItem (even when receiving status is still 2) -> blocked 422
        $this->receiving->update(['status' => 2]);
        $resAddReceiving = $controller->addReceivingItem(new Request([
            'receiving_id' => $this->receiving->id,
            'order_items_id' => $this->orderItem->id,
            'invoice_number' => 'INV-PAID-A',
            'creditor_code' => $this->creditor->code,
            'invoice_date' => '2026-10-05',
            'invoice_payment' => 'TUNAI',
            'invoice_ppn' => 'TANPA',
            'qty_received' => 1,
            'raw_price' => 36000,
            'discount' => 0,
            'extra_discount' => 0,
            'batch' => 'NEW-B2',
            'expired_date' => '2028-10-05',
            'status' => 'Diterima Kurang',
        ]));
        $this->assertSame(422, $resAddReceiving->getStatusCode());
        $this->assertSame('KREDIT', $paid['detail']->fresh()->invoice_payment);
        $this->receiving->update(['status' => 3]);

        // 8. Unpaid details still merge & move normally!
        $resMoveUnpaid = $controller->moveRevisionItem(new Request([
            'receiving_item_id' => $unpaid2['item']->id,
            'target_details_id' => $unpaid1['detail']->id,
        ]), $this->order->id);
        $this->assertSame(200, $resMoveUnpaid->getStatusCode());
        $this->assertSame($unpaid1['detail']->id, $unpaid2['item']->fresh()->receiving_details_id);
        $this->assertDatabaseMissing('receiving_details', ['id' => $unpaid2['detail']->id]);
    }

    public function test_deleting_empty_order_or_cancelling_consolidation_blocks_paid_receiving(): void
    {
        $paidEmpty = $this->seedDetail('NT-ORD-PAID', 'INV-ORD-PAID', true, false, false);

        $ordersController = app(OrdersController::class);
        $resDestroy = $ordersController->destroy($this->order->id);
        $this->assertSame(422, $resDestroy->getStatusCode());
        $this->assertDatabaseHas('receiving', ['id' => $this->receiving->id]);
        $this->assertDatabaseHas('receiving_details', ['id' => $paidEmpty['detail']->id]);
        $this->assertDatabaseHas('finance_payments', ['id' => $paidEmpty['payment']->id]);

        $consolidatedOrder = Order::create([
            'pharmacy_id' => $this->warehouse->id,
            'receiving_id' => $this->receiving->id,
            'code' => 'BPBA-KONSOL',
            'date' => '05/10/2026',
            'status' => 2,
            'is_consolidation' => true,
        ]);

        $service = new OrderConsolidation();
        $this->expectException(ValidationException::class);
        $service->cancel($consolidatedOrder->id, $this->warehouse->id, 1);
    }

    public function test_revision_blade_marks_paid_detail_locked_and_keeps_unpaid_editable(): void
    {
        $paid = $this->seedDetail('NT-UI-PAID', 'INV-UI-PAID', true, true, true);
        $unpaid = $this->seedDetail('NT-UI-FREE', 'INV-UI-FREE', false, true, true);

        $details = ReceivingDetails::with(['receiving_items.order_items.medicines'])
            ->withCount('payments')
            ->whereIn('id', [$paid['detail']->id, $unpaid['detail']->id])
            ->orderBy('id')
            ->get();

        $source = file_get_contents(resource_path('views/orders/revision.blade.php'));
        $source = str_replace([
            "@extends('layouts.app')", "@section('title', 'Revisi Faktur')",
            "@section('content')", "@section('scripts')", '@endsection',
        ], '', $source);

        $html = \Illuminate\Support\Facades\Blade::render($source, [
            'order' => $this->order,
            'allReceivingDetails' => $details,
            'orphanedItems' => collect(),
            'orderItemsData' => collect(),
        ]);

        $this->assertStringContainsString('Sudah Dibayar di Finance (Terkunci)', $html);
        $this->assertStringContainsString('Terkunci (Sudah Dibayar)', $html);
        $this->assertStringContainsString('deleteRow(' . $unpaid['item']->id . ')', $html);
        $this->assertStringNotContainsString('deleteRow(' . $paid['item']->id . ')', $html);
    }
}
