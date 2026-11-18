<?php

namespace Tests\Unit;

use App\Http\Controllers\TransfersController;
use App\Models\MedicineTransferItems;
use App\Models\MedicineTransfers;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransferDefaultEtalaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('secret_pin')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
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

        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->decimal('stock', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->string('name')->nullable();
            $table->string('expired_date')->nullable();
            $table->decimal('stock', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('etalases', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->integer('status')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('submission_key')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->integer('status')->default(0);
            $table->boolean('is_request')->default(false);
            $table->unsignedBigInteger('source_pharmacy_id')->nullable();
            $table->unsignedBigInteger('destination_pharmacy_id')->nullable();
            $table->integer('request_status')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_transfer_id');
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->unsignedBigInteger('receiving_items_id')->nullable();
            $table->unsignedBigInteger('requested_medicine_id')->nullable();
            $table->unsignedBigInteger('source_batches_id')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('etalases_id')->nullable();
            $table->unsignedBigInteger('etalase_updated_by')->nullable();
            $table->timestamp('etalase_updated_at')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('original_qty', 15, 2)->default(0);
            $table->integer('status')->default(0);
            $table->timestamp('stock_deducted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('items_log', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->nullable();
            $table->string('code')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('qty_before', 15, 2)->default(0);
            $table->decimal('qty_after', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamp('date')->nullable();
            $table->integer('status')->default(7);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        DB::table('pharmacies')->insert([
            ['id' => 9, 'name' => 'Gudang PMI', 'status' => 1],
            ['id' => 2, 'name' => 'Apotek Sutomo', 'status' => 1],
            ['id' => 3, 'name' => 'Apotek Cabang Lain', 'status' => 1],
        ]);

        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Penerima Sutomo',
            'username' => 'sutomo',
            'secret_pin' => '1234',
            'pharmacy_id' => 2,
        ]);

        DB::table('medicines')->insert([
            ['id' => 10, 'code' => 'MED-A', 'name' => 'Amoxicillin 500mg', 'unit' => 'Box', 'stock' => 100],
            ['id' => 11, 'code' => 'MED-B', 'name' => 'Vitamin C', 'unit' => 'Botol', 'stock' => 100],
        ]);

        DB::table('etalases')->insert([
            ['id' => 201, 'name' => 'Rak Depan 1', 'pharmacy_id' => 2],
            ['id' => 202, 'name' => 'Rak Antibiotik', 'pharmacy_id' => 2],
            ['id' => 203, 'name' => 'Rak Vitamin', 'pharmacy_id' => 2],
            ['id' => 301, 'name' => 'Rak Cabang Lain', 'pharmacy_id' => 3],
        ]);

        // Source batches in Gudang (9)
        DB::table('batches')->insert([
            ['id' => 1, 'pharmacy_id' => 9, 'medicine_id' => 10, 'name' => 'B-SRC-A', 'stock' => 50],
            ['id' => 2, 'pharmacy_id' => 9, 'medicine_id' => 11, 'name' => 'B-SRC-B', 'stock' => 50],
        ]);

        // Previous batch & accepted MTI for medicine 10 at pharmacy 2 -> etalases_id = 202
        DB::table('batches')->insert([
            ['id' => 20, 'pharmacy_id' => 2, 'medicine_id' => 10, 'name' => 'B-PREV-A', 'stock' => 0],
            // Another branch's MTI for medicine 11 must be ignored
            ['id' => 30, 'pharmacy_id' => 3, 'medicine_id' => 11, 'name' => 'B-OTHER-B', 'stock' => 0],
        ]);
        $prevTransferId = DB::table('medicine_transfers')->insertGetId([
            'code' => 'TR-OLD-1',
            'status' => 1,
            'source_pharmacy_id' => 9,
            'destination_pharmacy_id' => 2,
        ]);
        DB::table('medicine_transfer_items')->insert([
            [
                'medicine_transfer_id' => $prevTransferId,
                'batches_id' => 20,
                'etalases_id' => 201,
                'qty' => 5,
                'original_qty' => 5,
                'status' => 1,
                'etalase_updated_at' => '2026-10-01 08:00:00',
            ],
            [
                'medicine_transfer_id' => $prevTransferId,
                'batches_id' => 20,
                'etalases_id' => 202, // Most recent valid etalase for medicine 10 at Sutomo
                'qty' => 5,
                'original_qty' => 5,
                'status' => 1,
                'etalase_updated_at' => '2026-11-10 09:00:00',
            ],
            [
                'medicine_transfer_id' => $prevTransferId,
                'batches_id' => 30,
                'etalases_id' => 301, // Different pharmacy, must not leak to pharmacy 2
                'qty' => 5,
                'original_qty' => 5,
                'status' => 1,
                'etalase_updated_at' => '2026-11-12 09:00:00',
            ],
        ]);

        $this->actingAs(User::find(1));
    }

    public function test_accept_transfer_with_default_uses_previous_mti_etalase_or_random_branch_etalase(): void
    {
        DB::table('batches')->insert([
            ['id' => 21, 'pharmacy_id' => 2, 'medicine_id' => 10, 'name' => 'B-NEW-A', 'stock' => 0],
            ['id' => 22, 'pharmacy_id' => 2, 'medicine_id' => 11, 'name' => 'B-NEW-B', 'stock' => 0],
        ]);

        $transfer = MedicineTransfers::create([
            'code' => 'TR-NEW-1',
            'status' => 0,
            'source_pharmacy_id' => 9,
            'destination_pharmacy_id' => 2,
        ]);

        $itemWithHistory = MedicineTransferItems::create([
            'medicine_transfer_id' => $transfer->id,
            'batches_id' => 21, // medicine 10 -> has previous etalase 202 at pharmacy 2
            'source_batches_id' => 1,
            'source_type' => 'gudang',
            'qty' => 10,
            'status' => 0,
        ]);

        $itemWithoutHistory = MedicineTransferItems::create([
            'medicine_transfer_id' => $transfer->id,
            'batches_id' => 22, // medicine 11 -> no prior etalase at pharmacy 2 -> random in [201, 202, 203]
            'source_batches_id' => 2,
            'source_type' => 'gudang',
            'qty' => 5,
            'status' => 0,
        ]);

        $controller = app(TransfersController::class);

        // 1. Inspect AJAX items table markup includes Default option & data attributes
        $viewHtml = $controller->getTransferItems($transfer->id)->render();
        $this->assertStringContainsString('<option value="default">', $viewHtml);
        $this->assertStringContainsString('data-default-etalase-id="202"', $viewHtml);
        $this->assertStringContainsString('Sebelumnya: Rak Antibiotik', $viewHtml);

        // 2. Accept whole transfer using bulk 'default'
        $req = Request::create("/transfers/{$transfer->id}/accept", 'POST', [
            'pin' => '1234',
            'etalases_id' => 'default',
        ]);
        $req->setUserResolver(fn() => User::find(1));

        $controller->acceptTransfer($req, $transfer);

        $itemWithHistory->refresh();
        $itemWithoutHistory->refresh();
        $transfer->refresh();

        $this->assertSame(1, (int) $transfer->status);
        $this->assertSame(1, (int) $itemWithHistory->status);
        $this->assertSame(202, (int) $itemWithHistory->etalases_id);

        $this->assertSame(1, (int) $itemWithoutHistory->status);
        $this->assertContains((int) $itemWithoutHistory->etalases_id, [201, 202, 203]);
    }

    public function test_accept_single_item_and_update_item_etalase_support_default_option(): void
    {
        DB::table('batches')->insert([
            ['id' => 23, 'pharmacy_id' => 2, 'medicine_id' => 10, 'name' => 'B-NEW-A2', 'stock' => 0],
            ['id' => 24, 'pharmacy_id' => 2, 'medicine_id' => 11, 'name' => 'B-NEW-B2', 'stock' => 0],
        ]);

        $transfer = MedicineTransfers::create([
            'code' => 'TR-NEW-2',
            'status' => 0,
            'source_pharmacy_id' => 9,
            'destination_pharmacy_id' => 2,
        ]);

        $itemA = MedicineTransferItems::create([
            'medicine_transfer_id' => $transfer->id,
            'batches_id' => 23,
            'source_batches_id' => 1,
            'source_type' => 'gudang',
            'qty' => 4,
            'status' => 0,
        ]);

        $itemB = MedicineTransferItems::create([
            'medicine_transfer_id' => $transfer->id,
            'batches_id' => 24,
            'source_batches_id' => 2,
            'source_type' => 'gudang',
            'qty' => 3,
            'status' => 0,
        ]);

        $controller = app(TransfersController::class);

        // acceptItem with etalases_id = 'default' -> medicine 10 picks 202
        $acceptReq = Request::create("/transfers/{$itemA->id}/accept-item", 'POST', [
            'pin' => '1234',
            'etalases_id' => 'default',
        ]);
        $acceptReq->setUserResolver(fn() => User::find(1));
        $controller->acceptItem($acceptReq, $itemA);

        $itemA->refresh();
        $this->assertSame(1, (int) $itemA->status);
        $this->assertSame(202, (int) $itemA->etalases_id);

        // acceptItem for medicine 11 -> picks random valid branch etalase
        $acceptReqB = Request::create("/transfers/{$itemB->id}/accept-item", 'POST', [
            'pin' => '1234',
            'etalases_id' => 'default',
        ]);
        $acceptReqB->setUserResolver(fn() => User::find(1));
        $controller->acceptItem($acceptReqB, $itemB);

        $itemB->refresh();
        $this->assertSame(1, (int) $itemB->status);
        $this->assertContains((int) $itemB->etalases_id, [201, 202, 203]);

        // updateItemEtalase with 'default' re-resolves cleanly for itemA -> 202
        $updateReq = Request::create("/transfers/items/{$itemA->id}/etalase", 'POST', [
            'pin' => '1234',
            'etalases_id' => 'default',
        ]);
        $updateReq->setUserResolver(fn() => User::find(1));
        $jsonRes = $controller->updateItemEtalase($updateReq, $itemA);

        $this->assertSame(200, $jsonRes->getStatusCode());
        $payload = $jsonRes->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertSame(202, (int) $payload['etalases_id']);
    }

    public function test_legacy_null_pharmacy_etalase_used_by_branch_is_preserved_as_default(): void
    {
        // Legacy global etalase (pharmacy_id = NULL) previously used by branch 2 for medicine 11
        DB::table('etalases')->insert([
            'id' => 299,
            'name' => 'Rak Legacy Global',
            'pharmacy_id' => null,
        ]);
        DB::table('batches')->insert([
            ['id' => 25, 'pharmacy_id' => 2, 'medicine_id' => 11, 'name' => 'B-LEG-1', 'stock' => 0],
            ['id' => 26, 'pharmacy_id' => 2, 'medicine_id' => 11, 'name' => 'B-LEG-2', 'stock' => 0],
        ]);
        $oldTransfer = MedicineTransfers::create([
            'code' => 'TR-LEG-OLD',
            'status' => 1,
            'source_pharmacy_id' => 9,
            'destination_pharmacy_id' => 2,
        ]);
        MedicineTransferItems::create([
            'medicine_transfer_id' => $oldTransfer->id,
            'batches_id' => 25,
            'etalases_id' => 299,
            'qty' => 2,
            'status' => 1,
        ]);

        $newTransfer = MedicineTransfers::create([
            'code' => 'TR-LEG-NEW',
            'status' => 0,
            'source_pharmacy_id' => 9,
            'destination_pharmacy_id' => 2,
        ]);
        $newItem = MedicineTransferItems::create([
            'medicine_transfer_id' => $newTransfer->id,
            'batches_id' => 26,
            'source_batches_id' => 2,
            'source_type' => 'gudang',
            'qty' => 3,
            'status' => 0,
        ]);

        $controller = app(TransfersController::class);
        $req = Request::create("/transfers/{$newTransfer->id}/accept", 'POST', [
            'pin' => '1234',
            'etalases_id' => 'default',
        ]);
        $req->setUserResolver(fn() => User::find(1));

        $controller->acceptTransfer($req, $newTransfer);

        $newItem->refresh();
        $this->assertSame(1, (int) $newItem->status);
        $this->assertSame(299, (int) $newItem->etalases_id);
    }
}
