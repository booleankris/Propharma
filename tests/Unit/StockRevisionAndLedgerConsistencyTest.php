<?php

namespace Tests\Unit;

use App\Http\Controllers\StockRevisionController;
use App\Models\Batches;
use App\Models\Etalases;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\MedicineTransfers;
use App\Models\MedicineTransferItems;
use App\Models\Pharmacies;
use App\Models\StockRevision;
use App\Models\User;
use App\Services\StockLedgerService;
use App\Support\PermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockRevisionAndLedgerConsistencyTest extends TestCase
{
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

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
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

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('unit')->default('TAB');
            $table->string('packaging')->nullable();
            $table->integer('content')->default(1);
            $table->double('stock')->default(0);
            $table->string('barcode')->nullable();
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

        Schema::create('etalases', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('medicine_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->integer('status')->default(1);
            $table->unsignedBigInteger('source_pharmacy_id')->nullable();
            $table->unsignedBigInteger('destination_pharmacy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_transfer_id')->nullable();
            $table->unsignedBigInteger('batches_id');
            $table->unsignedBigInteger('source_batches_id')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('etalases_id')->nullable();
            $table->double('qty')->default(0);
            $table->double('original_qty')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('items_log', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->nullable();
            $table->string('code')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->double('qty')->default(0);
            $table->double('qty_before')->default(0);
            $table->double('qty_after')->default(0);
            $table->double('total')->default(0);
            $table->dateTime('date')->nullable();
            $table->integer('status')->default(6);
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revision_code', 50);
            $table->unsignedBigInteger('pharmacy_id');
            $table->string('target_mode', 20)->default('pelayanan');
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->integer('db_stock_before')->default(0);
            $table->integer('db_stock_after')->default(0);
            $table->integer('ledger_balance_before')->default(0);
            $table->integer('ledger_balance_after')->default(0);
            $table->integer('ledger_delta')->default(0);
            $table->json('batch_details')->nullable();
            $table->unsignedBigInteger('items_log_id')->nullable();
            $table->timestamps();
        });

        Pharmacies::create(['id' => 1, 'name' => 'SAHABAT PMI', 'status' => 1]);
        Pharmacies::create(['id' => 3, 'name' => 'SAHABAT MULAWARMAN', 'status' => 1]);
        Pharmacies::create(['id' => 9, 'name' => 'GUDANG PMI', 'status' => 1]);
    }

    public function test_ledger_locks_opening_stock_from_so_saldo_awal_even_when_warehouse_batches_are_negative(): void
    {
        $med = Medicines::create([
            'code' => 'FIESTA01',
            'name' => '-FIESTA INTIM WARM GEL 70 ML',
            'unit' => 'BTL',
            'stock' => -8,
        ]);

        $opnBatch = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 9,
            'name' => 'OPN-20281015',
            'expired_date' => '2028-10-15',
            'stock' => 0,
        ]);
        $b346 = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 9,
            'name' => 'LWJ24346',
            'expired_date' => '2027-09-01',
            'stock' => -6,
        ]);
        $b260 = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 9,
            'name' => 'LWJ24260',
            'expired_date' => '2027-06-01',
            'stock' => -3,
        ]);
        $b215 = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 9,
            'name' => 'LWJ24215',
            'expired_date' => '2027-04-01',
            'stock' => 1,
        ]);

        $recId = DB::table('receiving')->insertGetId([
            'pharmacy_id' => 9,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ItemsLog::create([
            'transaction_code' => 'SO-26101375',
            'code' => '2610LOG-0001',
            'type' => 'SO-SALDO-AWAL',
            'medicine_id' => $med->id,
            'qty' => 0,
            'qty_before' => 0,
            'qty_after' => 0,
            'total' => 0,
            'date' => '2026-10-11 23:59:59',
            'status' => 5,
            'batches_id' => $opnBatch->id,
        ]);
        ItemsLog::create([
            'transaction_code' => '2610RE0957',
            'code' => '2610LOG-0002',
            'type' => 'OR',
            'medicine_id' => $med->id,
            'receiving_id' => $recId,
            'qty' => 10,
            'qty_before' => 0,
            'qty_after' => 10,
            'date' => '2026-10-19 16:50:56',
            'status' => 2,
            'batches_id' => $b346->id,
        ]);
        ItemsLog::create([
            'transaction_code' => 'RET-2610/0007',
            'code' => '2610LOG-0003',
            'type' => 'RT',
            'medicine_id' => $med->id,
            'qty' => 7,
            'qty_before' => 10,
            'qty_after' => 3,
            'date' => '2026-10-20 12:30:05',
            'status' => 4,
            'batches_id' => $b215->id,
        ]);
        ItemsLog::create([
            'transaction_code' => '2610MUT14796',
            'code' => '2610LOG-0004',
            'type' => 'MU',
            'medicine_id' => $med->id,
            'qty' => 1,
            'qty_before' => 3,
            'qty_after' => 2,
            'date' => '2026-10-22 11:24:41',
            'status' => 7,
            'batches_id' => $b346->id,
        ]);
        ItemsLog::create([
            'transaction_code' => '2611MUT01417',
            'code' => '2611LOG-0005',
            'type' => 'MU',
            'medicine_id' => $med->id,
            'qty' => 1,
            'qty_before' => 2,
            'qty_after' => 1,
            'date' => '2026-11-14 14:25:36',
            'status' => 7,
            'batches_id' => $b215->id,
        ]);

        $service = app(StockLedgerService::class);
        $summary = $service->getMedicineLedgerSummary($med->id, 9, 'gudang');

        $this->assertTrue($summary['has_opening_anchor']);
        $this->assertSame(0, $summary['opening_stock']);
        $this->assertSame(1, $summary['ledger_balance']);
        $this->assertSame(-8, $summary['db_stock']);
        $this->assertSame(-9, $summary['discrepancy']);
    }

    public function test_stock_revision_reconciles_negative_warehouse_batches_into_kartu_stok_without_shifting_opening(): void
    {
        $user = User::create(['id' => 10, 'name' => 'GM', 'pharmacy_id' => 9]);
        $role = Role::create(['name' => 'General Manager', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'supplies.revision', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $user->assignRole($role);
        Auth::login($user);
        session(['active_pharmacy_id' => 9]);

        $med = Medicines::create([
            'code' => 'FIESTA01',
            'name' => '-FIESTA INTIM WARM GEL 70 ML',
            'unit' => 'BTL',
            'stock' => -8,
        ]);

        $opnBatch = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 9,
            'name' => 'OPN-20281015',
            'expired_date' => '2028-10-15',
            'stock' => 0,
        ]);
        $b346 = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 9,
            'name' => 'LWJ24346',
            'expired_date' => '2027-09-01',
            'stock' => -6,
        ]);
        $b260 = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 9,
            'name' => 'LWJ24260',
            'expired_date' => '2027-06-01',
            'stock' => -3,
        ]);
        $b215 = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 9,
            'name' => 'LWJ24215',
            'expired_date' => '2027-04-01',
            'stock' => 1,
        ]);

        $recId = DB::table('receiving')->insertGetId(['pharmacy_id' => 9, 'status' => 1]);
        ItemsLog::create([
            'transaction_code' => 'SO-26101375',
            'code' => '2610LOG-0001',
            'type' => 'SO-SALDO-AWAL',
            'medicine_id' => $med->id,
            'qty' => 0,
            'qty_before' => 0,
            'qty_after' => 0,
            'total' => 0,
            'date' => '2026-10-11 23:59:59',
            'status' => 5,
            'batches_id' => $opnBatch->id,
        ]);
        ItemsLog::create([
            'transaction_code' => '2610RE0957',
            'code' => '2610LOG-0002',
            'type' => 'OR',
            'medicine_id' => $med->id,
            'receiving_id' => $recId,
            'qty' => 10,
            'qty_before' => 0,
            'qty_after' => 10,
            'date' => '2026-10-19 16:50:56',
            'status' => 2,
            'batches_id' => $b346->id,
        ]);
        ItemsLog::create([
            'transaction_code' => 'RET-2610/0007',
            'code' => '2610LOG-0003',
            'type' => 'RT',
            'medicine_id' => $med->id,
            'qty' => 7,
            'qty_before' => 10,
            'qty_after' => 3,
            'date' => '2026-10-20 12:30:05',
            'status' => 4,
            'batches_id' => $b215->id,
        ]);
        ItemsLog::create([
            'transaction_code' => '2611MUT01417',
            'code' => '2611LOG-0004',
            'type' => 'MU',
            'medicine_id' => $med->id,
            'qty' => 2,
            'qty_before' => 3,
            'qty_after' => 1,
            'date' => '2026-11-14 14:25:36',
            'status' => 7,
            'batches_id' => $b215->id,
        ]);

        $controller = app(StockRevisionController::class);
        $request = Request::create('/stock-revision', 'POST', [
            'medicine_id' => $med->id,
            'target_mode' => 'gudang',
            'reason' => 'Koreksi batch minus / selisih stok DB',
            'notes' => 'Perbaikan RET-2610/0007',
            'batches' => [
                ['batch_id' => $opnBatch->id, 'new_qty' => 0],
                ['batch_id' => $b346->id, 'new_qty' => 1],
                ['batch_id' => $b260->id, 'new_qty' => 0],
                ['batch_id' => $b215->id, 'new_qty' => 0],
            ],
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = $controller->store($request);
        $this->assertSame(200, $response->getStatusCode());

        $b346->refresh();
        $b260->refresh();
        $b215->refresh();
        $this->assertEquals(1.0, (float) $b346->stock);
        $this->assertEquals(0.0, (float) $b260->stock);
        $this->assertEquals(0.0, (float) $b215->stock);

        $log = ItemsLog::where('type', 'REVISI STOK')->first();
        $this->assertNotNull($log);
        $this->assertSame(6, (int) $log->status);
        $this->assertSame(0, (int) $log->qty);
        $this->assertSame(1, (int) $log->qty_before);
        $this->assertSame(1, (int) $log->qty_after);

        $rev = StockRevision::first();
        $this->assertNotNull($rev);
        $this->assertSame(-8, (int) $rev->db_stock_before);
        $this->assertSame(1, (int) $rev->db_stock_after);
        $this->assertSame(1, (int) $rev->ledger_balance_before);
        $this->assertSame(1, (int) $rev->ledger_balance_after);
        $this->assertSame(0, (int) $rev->ledger_delta);

        $summaryAfter = app(StockLedgerService::class)->getMedicineLedgerSummary($med->id, 9, 'gudang');
        $this->assertSame(0, $summaryAfter['opening_stock']);
        $this->assertSame(1, $summaryAfter['ledger_balance']);
        $this->assertSame(1, $summaryAfter['db_stock']);
        $this->assertSame(0, $summaryAfter['discrepancy']);
    }

    public function test_stock_revision_on_outlet_updates_etalase_and_records_kartu_stok_delta(): void
    {
        $user = User::create(['id' => 11, 'name' => 'Koordinator', 'pharmacy_id' => 3]);
        $role = Role::create(['name' => 'Koordinator', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'supplies.revision', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $user->assignRole($role);
        Auth::login($user);
        session(['active_pharmacy_id' => 3]);

        Etalases::create(['id' => 1, 'name' => 'ETALASE A', 'pharmacy_id' => 3, 'status' => 1]);

        $med = Medicines::create([
            'code' => 'ATEVIR01',
            'name' => 'ATEVIR TAB 0.5 MG @30',
            'unit' => 'TAB',
            'stock' => 0,
        ]);

        $opnBatch = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 3,
            'name' => 'OPN-20270201',
            'expired_date' => '2027-02-01',
            'stock' => 0,
        ]);
        $retBatch = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 3,
            'name' => '56C0440',
            'expired_date' => '2027-02-01',
            'stock' => -30,
        ]);

        $mtiOpn = MedicineTransferItems::create([
            'batches_id' => $opnBatch->id,
            'source_batches_id' => $opnBatch->id,
            'source_type' => 'pelayanan',
            'etalases_id' => 1,
            'qty' => 0,
            'original_qty' => 30,
            'status' => 1,
        ]);

        ItemsLog::create([
            'transaction_code' => 'SO-26100364',
            'code' => '2610LOG-0001',
            'type' => 'SO-SALDO-AWAL',
            'medicine_id' => $med->id,
            'qty' => 0,
            'qty_before' => 30,
            'qty_after' => 30,
            'total' => 0,
            'date' => '2026-09-26 23:59:59',
            'status' => 5,
            'batches_id' => $opnBatch->id,
        ]);
        ItemsLog::create([
            'transaction_code' => 'RET-2611/0035',
            'code' => '2611LOG-0035',
            'type' => 'RT',
            'medicine_id' => $med->id,
            'qty' => 30,
            'qty_before' => 30,
            'qty_after' => 0,
            'total' => 33333,
            'date' => '2026-11-14 21:04:23',
            'status' => 4,
            'batches_id' => $retBatch->id,
        ]);

        $controller = app(StockRevisionController::class);
        $request = Request::create('/stock-revision', 'POST', [
            'medicine_id' => $med->id,
            'target_mode' => 'pelayanan',
            'reason' => 'Penyesuaian stok fisik rak',
            'notes' => '1 box fisik masih ada di rak',
            'batches' => [
                ['batch_id' => $opnBatch->id, 'new_qty' => 30],
                ['batch_id' => $retBatch->id, 'new_qty' => 0],
            ],
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = $controller->store($request);
        $this->assertSame(200, $response->getStatusCode());

        $mtiOpn->refresh();
        $this->assertEquals(30.0, (float) $mtiOpn->qty);

        $log = ItemsLog::where('type', 'REVISI STOK')->first();
        $this->assertNotNull($log);
        $this->assertSame(6, (int) $log->status);
        $this->assertSame(30, (int) $log->qty);
        $this->assertSame(0, (int) $log->qty_before);
        $this->assertSame(30, (int) $log->qty_after);

        $summaryAfter = app(StockLedgerService::class)->getMedicineLedgerSummary($med->id, 3, 'pelayanan');
        $this->assertSame(30, $summaryAfter['opening_stock']);
        $this->assertSame(30, $summaryAfter['ledger_balance']);
        $this->assertSame(30, $summaryAfter['db_stock']);
        $this->assertSame(0, $summaryAfter['discrepancy']);
    }

    public function test_stock_revision_can_add_new_batch_and_rejects_duplicate_name(): void
    {
        $user = User::create(['id' => 12, 'name' => 'GM', 'pharmacy_id' => 9]);
        $role = Role::create(['name' => 'General Manager', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'supplies.revision', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $user->assignRole($role);
        Auth::login($user);
        session(['active_pharmacy_id' => 9]);

        $med = Medicines::create([
            'code' => 'NEWB01',
            'name' => 'OBAT BARU BATCH',
            'unit' => 'TAB',
            'stock' => 10,
        ]);

        $existingBatch = Batches::create([
            'medicine_id' => $med->id,
            'pharmacy_id' => 9,
            'name' => 'OPN-20261101',
            'expired_date' => '2028-01-01',
            'stock' => 10,
        ]);

        ItemsLog::create([
            'transaction_code' => 'SO-26110001',
            'code' => '2611LOG-0001',
            'type' => 'SO-SALDO-AWAL',
            'medicine_id' => $med->id,
            'qty' => 0,
            'qty_before' => 10,
            'qty_after' => 10,
            'total' => 0,
            'date' => '2026-11-01 23:59:59',
            'status' => 5,
            'batches_id' => $existingBatch->id,
        ]);

        $controller = app(StockRevisionController::class);

        // Tambah batch baru
        $createReq = Request::create('/stock-revision', 'POST', [
            'medicine_id' => $med->id,
            'target_mode' => 'gudang',
            'reason' => 'Penyesuaian stok fisik rak',
            'batches' => [
                ['batch_id' => $existingBatch->id, 'new_qty' => 10],
            ],
            'new_batches' => [
                ['name' => 'BATCH-BARU-99', 'expired_date' => '2028-05-01', 'new_qty' => 15],
            ],
        ]);
        $createReq->headers->set('Accept', 'application/json');

        $res = $controller->store($createReq);
        $this->assertSame(200, $res->getStatusCode());

        $newBatch = Batches::where('medicine_id', $med->id)->where('name', 'BATCH-BARU-99')->first();
        $this->assertNotNull($newBatch);
        $this->assertEquals(15.0, (float) $newBatch->stock);

        $log = ItemsLog::where('type', 'REVISI STOK')->first();
        $this->assertNotNull($log);
        $this->assertSame(15, (int) $log->qty);
        $this->assertSame(10, (int) $log->qty_before);
        $this->assertSame(25, (int) $log->qty_after);

        // Tolak nama batch yang sudah ada (duplikat)
        $dupReq = Request::create('/stock-revision', 'POST', [
            'medicine_id' => $med->id,
            'target_mode' => 'gudang',
            'reason' => 'Penyesuaian stok fisik rak',
            'new_batches' => [
                ['name' => 'batch-baru-99', 'expired_date' => '2028-05-01', 'new_qty' => 5],
            ],
        ]);
        $dupReq->headers->set('Accept', 'application/json');
        $dupRes = $controller->store($dupReq);
        $this->assertSame(422, $dupRes->getStatusCode());
    }

    public function test_supplies_revision_permission_is_registered_for_role_management_and_restricted_to_koordinator_and_gm(): void
    {
        $koordinatorRole = Role::create(['name' => 'Koordinator', 'guard_name' => 'web']);
        $gmRole = Role::create(['name' => 'General Manager', 'guard_name' => 'web']);
        $hoRole = Role::create(['name' => 'HO', 'guard_name' => 'web']);
        $gudangRole = Role::create(['name' => 'Gudang PMI', 'guard_name' => 'web']);
        $kasirRole = Role::create(['name' => 'Kasir', 'guard_name' => 'web']);

        PermissionCatalog::ensureSynced();

        $perm = Permission::where('name', 'supplies.revision')->first();
        $this->assertNotNull($perm);

        $grouped = PermissionCatalog::groupedForView(Permission::all());
        $inventoryPerms = collect($grouped['inventory']['items'] ?? [])->pluck('name')->all();
        $this->assertContains('supplies.revision', $inventoryPerms);

        $this->assertTrue($koordinatorRole->fresh()->hasPermissionTo('supplies.revision'));
        $this->assertTrue($gmRole->fresh()->hasPermissionTo('supplies.revision'));
        $this->assertFalse($hoRole->fresh()->hasPermissionTo('supplies.revision'));
        $this->assertFalse($gudangRole->fresh()->hasPermissionTo('supplies.revision'));
        $this->assertFalse($kasirRole->fresh()->hasPermissionTo('supplies.revision'));
    }
}
