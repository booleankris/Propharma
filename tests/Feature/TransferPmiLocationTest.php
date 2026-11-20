<?php

namespace Tests\Feature;

use App\Models\Batches;
use App\Models\Etalases;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\MedicineTransferItems;
use App\Models\MedicineTransfers;
use App\Models\TransferLocation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferPmiLocationTest extends TestCase
{
    private User $warehouseUser;
    private User $pmiUser;
    private Medicines $medicine;
    private Batches $warehouseBatch;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        $this->createSchema();

        DB::table('pharmacies')->insert([
            ['id' => 1, 'name' => 'SAHABAT PMI', 'status' => 1],
            ['id' => 2, 'name' => 'SAHABAT MULAWARMAN', 'status' => 1],
            ['id' => 9, 'name' => 'GUDANG PMI', 'status' => 1],
        ]);

        $this->warehouseUser = User::create([
            'name' => 'STAFF GUDANG',
            'username' => 'gudang_staff',
            'password' => bcrypt('secret'),
            'secret_pin' => '4567',
            'pharmacy_id' => 9,
        ]);

        $this->pmiUser = User::create([
            'name' => 'STAFF PMI',
            'username' => 'pmi_staff',
            'password' => bcrypt('secret'),
            'secret_pin' => '6789',
            'pharmacy_id' => 1,
        ]);

        $this->medicine = Medicines::create([
            'code' => 'MED-001',
            'name' => 'AMOXICILLIN 500MG',
            'unit' => 'TAB',
            'stock' => 100,
        ]);

        $this->warehouseBatch = Batches::create([
            'pharmacy_id' => 9,
            'medicine_id' => $this->medicine->id,
            'name' => 'BCH-PMI-01',
            'stock' => 50,
            'status' => 1,
        ]);

        Etalases::create([
            'name' => 'Etalase A',
            'pharmacy_id' => 1,
            'status' => 1,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('secret_pin')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('pharmacies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->decimal('stock', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('medicine_id');
            $table->string('name')->nullable();
            $table->string('expired_date')->nullable();
            $table->decimal('stock', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('etalases', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->integer('status')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('transfer_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('pharmacy_id')->nullable()->default(1);
            $table->integer('status')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->string('submission_key')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->integer('status')->default(0);
            $table->boolean('is_request')->default(false);
            $table->integer('request_status')->nullable();
            $table->unsignedBigInteger('source_pharmacy_id')->nullable();
            $table->unsignedBigInteger('destination_pharmacy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transfer_items', function (Blueprint $table): void {
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
            $table->unsignedBigInteger('transfer_location_id')->nullable();
            $table->unsignedBigInteger('location_updated_by')->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('original_qty', 15, 2)->nullable();
            $table->integer('status')->default(0);
            $table->timestamp('stock_deducted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('items_log', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_code')->nullable();
            $table->string('code')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('qty_before', 15, 2)->default(0);
            $table->decimal('qty_after', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->dateTime('date')->nullable();
            $table->integer('status')->default(7);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_transfer_locations_crud_and_default_seed_work_like_etalase(): void
    {
        $this->actingAs($this->warehouseUser);

        $listRes = $this->getJson(route('transfer_locations.index'));
        $listRes->assertOk();
        $names = collect($listRes->json())->pluck('name')->all();
        $this->assertSame(['Pelayanan Lt.1', 'Pelayanan Lt.2', 'Online'], $names);

        // Store custom location
        $createRes = $this->postJson(route('transfer_locations.store'), [
            'name' => 'Pelayanan Lt.3',
            'pin' => '4567',
        ]);
        $createRes->assertStatus(201)
            ->assertJson([
                'name' => 'Pelayanan Lt.3',
                'updated_by_name' => 'STAFF GUDANG',
            ]);
        $createdId = $createRes->json('id');

        // Duplicate name rejected
        $this->postJson(route('transfer_locations.store'), [
            'name' => 'pelayanan lt.3',
            'pin' => '4567',
        ])->assertStatus(422);

        // Update name
        $this->putJson(route('transfer_locations.update', $createdId), [
            'name' => 'Racikan Khusus',
            'pin' => '6789',
        ])->assertOk()
            ->assertJson([
                'name' => 'Racikan Khusus',
                'updated_by_name' => 'STAFF PMI',
            ]);

        // Delete clears reference on items
        $transfer = MedicineTransfers::create([
            'code' => 'MUT-TEST-01',
            'source_pharmacy_id' => 9,
            'destination_pharmacy_id' => 1,
            'status' => 0,
        ]);
        $item = MedicineTransferItems::create([
            'medicine_transfer_id' => $transfer->id,
            'batches_id' => $this->warehouseBatch->id,
            'transfer_location_id' => $createdId,
            'qty' => 2,
            'status' => 0,
        ]);

        $this->deleteJson(route('transfer_locations.destroy', $createdId), ['pin' => '6789'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNull(TransferLocation::find($createdId));
        $this->assertNull($item->fresh()->transfer_location_id);
    }

    public function test_transfer_to_pmi_requires_location_and_allows_splitting_same_batch_by_location(): void
    {
        $this->actingAs($this->warehouseUser);

        // Trigger default locations creation
        $this->getJson(route('transfer_locations.index'))->assertOk();
        $lt1 = TransferLocation::where('name', 'Pelayanan Lt.1')->firstOrFail();
        $online = TransferLocation::where('name', 'Online')->firstOrFail();

        // Missing location when transferring to PMI -> rejected 422
        $this->postJson(route('transfer'), [
            'code' => 'MUT-PMI-FAIL',
            'submission_key' => (string) Str::uuid(),
            'pharmacy' => 1,
            'pin' => '4567',
            'items' => [
                [
                    'batches_id' => $this->warehouseBatch->id,
                    'qty' => 3,
                    'source_type' => 'gudang',
                ],
            ],
        ])->assertStatus(422);

        // Valid transfer splitting same batch into Lt.1 & Online
        $this->postJson(route('transfer'), [
            'code' => 'MUT-PMI-OK',
            'submission_key' => (string) Str::uuid(),
            'pharmacy' => 1,
            'pin' => '4567',
            'items' => [
                [
                    'batches_id' => $this->warehouseBatch->id,
                    'qty' => 3,
                    'source_type' => 'gudang',
                    'transfer_location_id' => $lt1->id,
                ],
                [
                    'batches_id' => $this->warehouseBatch->id,
                    'qty' => 2,
                    'source_type' => 'gudang',
                    'transfer_location_id' => $online->id,
                ],
            ],
        ])->assertOk()->assertJson(['success' => true]);

        $transfer = MedicineTransfers::where('code', 'MUT-PMI-OK')->firstOrFail();
        $items = $transfer->items()->orderBy('id')->get();
        $this->assertCount(2, $items);
        $this->assertSame($lt1->id, (int) $items[0]->transfer_location_id);
        $this->assertSame(3, (int) $items[0]->qty);
        $this->assertSame($online->id, (int) $items[1]->transfer_location_id);
        $this->assertSame(2, (int) $items[1]->qty);

        // PMI staff can change item location via PIN
        $lt2 = TransferLocation::where('name', 'Pelayanan Lt.2')->firstOrFail();
        $this->actingAs($this->pmiUser);
        $this->postJson(route('transfers.updateItemLocation', $items[1]), [
            'transfer_location_id' => $lt2->id,
            'pin' => '6789',
        ])->assertOk()
            ->assertJson([
                'success' => true,
                'transfer_location_id' => $lt2->id,
                'location_name' => 'Pelayanan Lt.2',
                'updated_by_name' => 'STAFF PMI',
            ]);

        $this->assertSame($lt2->id, (int) $items[1]->fresh()->transfer_location_id);
        $this->assertSame(0, ItemsLog::where('status', 999)->count());

        // Non-PMI destination does not require location
        $this->actingAs($this->warehouseUser);
        $this->postJson(route('transfer'), [
            'code' => 'MUT-CABANG-OK',
            'submission_key' => (string) Str::uuid(),
            'pharmacy' => 2,
            'pin' => '4567',
            'items' => [
                [
                    'batches_id' => $this->warehouseBatch->id,
                    'qty' => 1,
                    'source_type' => 'gudang',
                ],
            ],
        ])->assertOk()->assertJson(['success' => true]);
    }
}
