<?php

namespace Tests\Unit;

use App\Exports\TransfersExport;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SuppliesController;
use App\Http\Controllers\TransfersController;
use App\Models\Batches;
use App\Models\Etalases;
use App\Models\ItemsLog;
use App\Models\MedicineCart;
use App\Models\MedicineTransactions;
use App\Models\MedicineTransferItems;
use App\Models\MedicineTransfers;
use App\Models\Medicines;
use App\Models\Pharmacies;
use App\Models\User;
use App\Services\StockLedgerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AutoMinusCounterStockTest extends TestCase
{
    private User $cashierUser;
    private User $gudangUser;
    private int $etalaseId;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        // SQLite lacks MySQL's CONCAT() used by StockLedgerService::effectiveDateSql()
        DB::connection('sqlite')->getPdo()->sqliteCreateFunction(
            'CONCAT',
            static fn(...$parts) => implode('', array_map(static fn($v) => (string) $v, $parts))
        );

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

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('debtors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('shift', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->string('shift_in')->nullable();
            $table->string('shift_out')->nullable();
            $table->integer('total_retur')->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('shift_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->timestamp('clock_in')->nullable();
            $table->timestamp('clock_out')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('medicine_transaction_sequences', function (Blueprint $table) {
            $table->string('sequence_key', 32)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('medicine_transfer_sequences', function (Blueprint $table) {
            $table->string('prefix', 16)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('unit')->default('KAPS');
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
            $table->string('expired_date')->nullable();
            $table->double('stock')->default(0);
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

        Schema::create('transfer_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->integer('status')->default(1);
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
            $table->unsignedBigInteger('transfer_location_id')->nullable();
            $table->unsignedBigInteger('location_updated_by')->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->decimal('qty', 15, 3)->default(0);
            $table->decimal('original_qty', 15, 3)->nullable();
            $table->integer('status')->default(0);
            $table->timestamp('stock_deducted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('shift_logs_id')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('service_invoice_type')->nullable();
            $table->string('transaction_code')->nullable();
            $table->string('paid')->nullable();
            $table->string('changes')->nullable();
            $table->string('subtotal')->nullable();
            $table->string('discount')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('transfer_bank_name')->nullable();
            $table->boolean('has_doctor_shipping_fee')->default(false);
            $table->decimal('doctor_shipping_fee', 15, 2)->default(0);
            $table->string('doctor_shipping_payment_method')->nullable();
            $table->string('doctor_shipping_bank_name')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('medicine_cart', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedBigInteger('transaction_id');
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('embalase', 15, 2)->nullable();
            $table->string('cart_type')->nullable();
            $table->string('package')->nullable();
            $table->string('dosage_r')->nullable();
            $table->decimal('item_price', 15, 2)->nullable();
            $table->decimal('raw_total', 15, 2)->nullable();
            $table->decimal('total_price', 15, 2)->default(0);
            $table->decimal('final_price', 15, 2)->nullable();
            $table->integer('status')->default(0);
            $table->string('recipe_status')->nullable();
            $table->string('recipe_number')->nullable();
            $table->string('medicine_type')->nullable();
            $table->decimal('service_fee', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('transaction_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_transaction_id')->nullable();
            $table->timestamps();
        });

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
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
            $table->decimal('qty', 15, 3)->default(0);
            $table->decimal('qty_before', 15, 3)->default(0);
            $table->decimal('qty_after', 15, 3)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->dateTime('date')->nullable();
            $table->integer('status')->default(1);
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Pharmacies::insert([
            ['id' => 1, 'name' => 'SAHABAT PMI', 'status' => 1],
            ['id' => 9, 'name' => 'GUDANG PMI', 'status' => 1],
        ]);

        $this->cashierUser = User::create([
            'id' => 10,
            'name' => 'DITA',
            'username' => 'dita',
            'secret_pin' => '1234',
            'pharmacy_id' => 1,
        ]);

        $this->gudangUser = User::create([
            'id' => 20,
            'name' => 'LITA',
            'username' => 'lita',
            'secret_pin' => '1234',
            'pharmacy_id' => 9,
        ]);

        $etalase = Etalases::create([
            'name' => 'Etalase Umum PMI',
            'pharmacy_id' => 1,
            'status' => 1,
        ]);
        $this->etalaseId = (int) $etalase->id;

        // Seed sequence rows so MySQL REGEXP fallback scan is skipped under SQLite
        DB::table('medicine_transaction_sequences')->insert([
            ['sequence_key' => now()->format('ym') . '301', 'last_number' => 10],
        ]);
    }

    private function runningLedgerForCounter(int $medicineId, int $pharmacyId = 1): array
    {
        $ledger = app(StockLedgerService::class);
        $counterStock = $ledger->calculateRealtimeStock($medicineId, $pharmacyId, 'counter');
        $rows = $ledger->branchMedicineLogsQuery($pharmacyId, $medicineId)
            ->orderByRaw($ledger->effectiveDateSql('items_log') . ' ASC')
            ->orderBy('id', 'asc')
            ->get();

        return $ledger->buildRunningLedger($rows, $counterStock);
    }

    private function performBranchSale(Medicines $medicine, float $qty): array
    {
        $this->actingAs($this->cashierUser);
        session(['active_pharmacy_id' => 1]);

        $trx = MedicineTransactions::create([
            'pharmacy_id' => 1,
            'user_id' => $this->cashierUser->id,
            'transaction_type' => 'HV/OTC',
            'status' => 0,
        ]);

        $subtotal = $qty * 1000;
        MedicineCart::create([
            'user_id' => $this->cashierUser->id,
            'medicine_id' => $medicine->id,
            'transaction_id' => $trx->id,
            'quantity' => $qty,
            'discount' => 0,
            'cart_type' => 'UM',
            'item_price' => 1000,
            'raw_total' => $subtotal,
            'total_price' => $subtotal,
            'final_price' => $subtotal,
            'status' => 0,
        ]);

        $request = Request::create('/transaction/checkout', 'POST', [
            'transaction_id' => $trx->id,
            'paid' => 0,
            'changes' => 0,
            'totaltransaction' => $subtotal,
            'discounsubtotalvalue' => 0,
            'paymentType' => 'BELUM BAYAR',
            'user_id' => $this->cashierUser->id,
        ]);
        $request->setUserResolver(fn() => $this->cashierUser);

        $response = app(SalesController::class)->checkout($request);

        return [$response->getStatusCode(), json_decode($response->getContent(), true), $trx->fresh()];
    }

    public function test_sale_before_any_mti_creates_minus_auto_and_subsequent_mutasi_acc_settles_to_zero(): void
    {
        $medicine = Medicines::create([
            'code' => 'LENVAT01',
            'name' => 'LENVAT KAPS 4 MG @30',
            'stock' => 30, // Only exists in warehouse initially
        ]);

        $gudangBatch = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 9,
            'name' => '1825242',
            'expired_date' => '2027-08-01',
            'stock' => 30,
            'status' => 1,
        ]);

        // 1. Sell 30 at cabang before any MTI exists
        [$status, $payload, $trx] = $this->performBranchSale($medicine, 30);
        $this->assertSame(200, $status, json_encode($payload));
        $this->assertTrue((bool) ($payload['success'] ?? false));

        // Verify virtual MINUS-AUTO batch & MTI created properly
        $minusBatch = Batches::where('medicine_id', $medicine->id)
            ->where('pharmacy_id', 1)
            ->where('name', 'MINUS-AUTO')
            ->first();
        $this->assertNotNull($minusBatch);
        $this->assertNull($minusBatch->expired_date);
        $this->assertSame(0.0, (float) $minusBatch->stock);

        $minusMti = MedicineTransferItems::where('batches_id', $minusBatch->id)->first();
        $this->assertNotNull($minusMti);
        $this->assertSame(-30.0, (float) $minusMti->qty);
        $this->assertSame(0.0, (float) $minusMti->original_qty);

        // Verify sale ItemsLog is linked to MINUS-AUTO batch (not null!)
        $saleLog = ItemsLog::where('transaction_code', $trx->transaction_code)->first();
        $this->assertNotNull($saleLog);
        $this->assertSame($minusBatch->id, (int) $saleLog->batches_id);
        $this->assertSame(0.0, (float) $saleLog->qty_before);
        $this->assertSame(-30.0, (float) $saleLog->qty_after);

        // Verify Kartu Stok ledger BEFORE mutation has AWAL = 0 and SALDO = -30
        $ledger = app(StockLedgerService::class);
        $this->assertSame(-30, $ledger->calculateRealtimeStock($medicine->id, 1, 'counter'));
        $runningBefore = $this->runningLedgerForCounter($medicine->id, 1);
        $this->assertSame(0, $runningBefore['initialStart']);
        $this->assertSame(-30, $runningBefore['finalEnd']);

        // 2. Gudang creates Mutasi 30 & Pelayanan ACCs it (simulate Gudang deduct + pending transfer item)
        $gudangBatch->decrement('stock', 30);
        $destBatch = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 1,
            'name' => '1825242',
            'expired_date' => '2027-08-01',
            'stock' => 0,
            'status' => 1,
        ]);
        $transfer = MedicineTransfers::create([
            'code' => '2611MUT01877',
            'status' => 0,
            'user_id' => $this->gudangUser->id,
            'sent_by' => $this->gudangUser->id,
            'source_pharmacy_id' => 9,
            'destination_pharmacy_id' => 1,
        ]);
        $incomingItem = MedicineTransferItems::create([
            'medicine_transfer_id' => $transfer->id,
            'batches_id' => $destBatch->id,
            'source_batches_id' => $gudangBatch->id,
            'source_type' => 'gudang',
            'qty' => 30,
            'original_qty' => 30,
            'status' => 0,
        ]);

        $this->actingAs($this->cashierUser);
        session(['active_pharmacy_id' => 1]);
        $accReq = Request::create("/transfers/{$transfer->id}/accept", 'POST', [
            'pin' => '1234',
            'etalases_id' => $this->etalaseId,
        ]);
        $accReq->setUserResolver(fn() => $this->cashierUser);
        $accRes = app(TransfersController::class)->acceptTransfer($accReq, $transfer);
        $this->assertSame('Semua item diterima.', $accRes->getSession()->get('success'), (string) $accRes->getSession()->get('message'));

        // Both MINUS-AUTO and incoming MTI settle cleanly to 0, while original_qty stays 30
        $minusMti->refresh();
        $incomingItem->refresh();
        $this->assertSame(0.0, (float) $minusMti->qty);
        $this->assertSame(0.0, (float) $incomingItem->qty);
        $this->assertSame(30.0, (float) $incomingItem->original_qty);
        $this->assertSame(1, (int) $incomingItem->status);

        // Incoming MU ItemsLog logs full +30 delta (0 -> 30) so Kartu Stok stays mathematically exact
        $muLog = ItemsLog::where('transaction_code', '2611MUT01877')
            ->where('batches_id', $destBatch->id)
            ->first();
        $this->assertNotNull($muLog);
        $this->assertSame(30.0, (float) $muLog->qty);
        $this->assertSame(0.0, (float) $muLog->qty_before);
        $this->assertSame(30.0, (float) $muLog->qty_after);

        // Verify Kartu Stok AFTER mutation: AWAL = 0, SALDO = 0 (NOT phantom 30!)
        $this->assertSame(0, $ledger->calculateRealtimeStock($medicine->id, 1, 'counter'));
        $runningAfter = $this->runningLedgerForCounter($medicine->id, 1);
        $this->assertSame(0, $runningAfter['initialStart']);
        $this->assertSame(0, $runningAfter['finalEnd']);

        // Idempotency guard check: repeating ACC does NOT duplicate MU ItemsLog or change stock
        app(TransfersController::class)->acceptTransfer($accReq, $transfer->fresh());
        $this->assertSame(
            1,
            ItemsLog::where('transaction_code', '2611MUT01877')->where('batches_id', $destBatch->id)->count()
        );
        $this->assertSame(0, $ledger->calculateRealtimeStock($medicine->id, 1, 'counter'));
    }

    public function test_partial_surplus_and_subsequent_fefo_sales_with_minus_auto(): void
    {
        $medicine = Medicines::create([
            'code' => 'PART01',
            'name' => 'OBAT PARTIAL TEST',
            'stock' => 60,
        ]);

        // Initial sale of 30 with no MTI -> MINUS-AUTO = -30
        [$status] = $this->performBranchSale($medicine, 30);
        $this->assertSame(200, $status);

        $ledger = app(StockLedgerService::class);
        $this->assertSame(-30, $ledger->calculateRealtimeStock($medicine->id, 1, 'counter'));

        // Partial Mutasi Masuk +10 via direct settlement simulation (mirrors acceptItem)
        $srcBatch1 = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 9,
            'name' => 'B-PART-1',
            'expired_date' => '2027-05-01',
            'stock' => 0,
            'status' => 1,
        ]);
        $destBatch1 = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 1,
            'name' => 'B-PART-1',
            'expired_date' => '2027-05-01',
            'stock' => 0,
            'status' => 1,
        ]);
        $tr1 = MedicineTransfers::create([
            'code' => 'MUT-PART-1',
            'status' => 0,
            'source_pharmacy_id' => 9,
            'destination_pharmacy_id' => 1,
        ]);
        $item1 = MedicineTransferItems::create([
            'medicine_transfer_id' => $tr1->id,
            'batches_id' => $destBatch1->id,
            'source_batches_id' => $srcBatch1->id,
            'source_type' => 'gudang',
            'qty' => 10,
            'status' => 0,
        ]);

        $this->actingAs($this->cashierUser);
        session(['active_pharmacy_id' => 1]);
        $accReq1 = Request::create("/transfers/{$tr1->id}/accept", 'POST', ['pin' => '1234', 'etalases_id' => $this->etalaseId]);
        $accReq1->setUserResolver(fn() => $this->cashierUser);
        $accRes1 = app(TransfersController::class)->acceptTransfer($accReq1, $tr1);
        $this->assertSame('Semua item diterima.', $accRes1->getSession()->get('success'), (string) $accRes1->getSession()->get('message'));

        $minusMti = MedicineTransferItems::join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
            ->where('batches.medicine_id', $medicine->id)
            ->where('batches.name', 'MINUS-AUTO')
            ->select('medicine_transfer_items.*')
            ->firstOrFail();

        $item1->refresh();
        $this->assertSame(-20.0, (float) $minusMti->qty);
        $this->assertSame(0.0, (float) $item1->qty);
        $this->assertSame(10.0, (float) $item1->original_qty);
        $this->assertSame(-20, $ledger->calculateRealtimeStock($medicine->id, 1, 'counter'));

        // Surplus Mutasi Masuk +50 -> settles -20 to 0, leaves +30 on B-SURPLUS-2
        $srcBatch2 = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 9,
            'name' => 'B-SURPLUS-2',
            'expired_date' => '2027-06-01',
            'stock' => 0,
            'status' => 1,
        ]);
        $destBatch2 = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 1,
            'name' => 'B-SURPLUS-2',
            'expired_date' => '2027-06-01',
            'stock' => 0,
            'status' => 1,
        ]);
        $tr2 = MedicineTransfers::create([
            'code' => 'MUT-SURPLUS-2',
            'status' => 0,
            'source_pharmacy_id' => 9,
            'destination_pharmacy_id' => 1,
        ]);
        $item2 = MedicineTransferItems::create([
            'medicine_transfer_id' => $tr2->id,
            'batches_id' => $destBatch2->id,
            'source_batches_id' => $srcBatch2->id,
            'source_type' => 'gudang',
            'qty' => 50,
            'status' => 0,
        ]);

        $accReq2 = Request::create("/transfers/{$tr2->id}/accept", 'POST', ['pin' => '1234', 'etalases_id' => $this->etalaseId]);
        $accReq2->setUserResolver(fn() => $this->cashierUser);
        $accRes2 = app(TransfersController::class)->acceptTransfer($accReq2, $tr2);
        $this->assertSame('Semua item diterima.', $accRes2->getSession()->get('success'), (string) $accRes2->getSession()->get('message'));

        $minusMti->refresh();
        $item2->refresh();
        $this->assertSame(0.0, (float) $minusMti->qty);
        $this->assertSame(30.0, (float) $item2->qty);
        $this->assertSame(50.0, (float) $item2->original_qty);
        $this->assertSame(30, $ledger->calculateRealtimeStock($medicine->id, 1, 'counter'));

        // Next sale of 35 must consume real B-SURPLUS-2 (30 -> 0) and push remaining -5 onto MINUS-AUTO (NOT B-SURPLUS-2)
        [$statusSale2] = $this->performBranchSale($medicine, 35);
        $this->assertSame(200, $statusSale2);

        $minusMti->refresh();
        $item2->refresh();
        $this->assertSame(0.0, (float) $item2->qty);
        $this->assertSame(-5.0, (float) $minusMti->qty);
        $this->assertSame(-5, $ledger->calculateRealtimeStock($medicine->id, 1, 'counter'));

        $running = $this->runningLedgerForCounter($medicine->id, 1);
        $this->assertSame(0, $running['initialStart']);
        $this->assertSame(-5, $running['finalEnd']);
    }

    public function test_existing_opname_negative_batch_behavior_is_preserved_and_settled(): void
    {
        $medicine = Medicines::create([
            'code' => 'THEO01',
            'name' => 'THEOBRON SIR 100 ML',
            'stock' => 30,
        ]);

        // Existing OPN-20261012 row at qty = 0 (already present in cabang)
        $opnBatch = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 1,
            'name' => 'OPN-20261012',
            'expired_date' => '2027-01-01',
            'stock' => 0,
            'status' => 1,
        ]);
        $opnHeader = MedicineTransfers::create([
            'code' => 'TRF-INIT-1',
            'status' => 1,
            'source_pharmacy_id' => 1,
            'destination_pharmacy_id' => 1,
        ]);
        $opnItem = MedicineTransferItems::create([
            'medicine_transfer_id' => $opnHeader->id,
            'batches_id' => $opnBatch->id,
            'etalases_id' => 101,
            'qty' => 0,
            'original_qty' => 0,
            'status' => 1,
        ]);

        [$status] = $this->performBranchSale($medicine, 30);
        $this->assertSame(200, $status);

        // Must decrement existing OPN row, NOT create MINUS-AUTO
        $opnItem->refresh();
        $this->assertSame(-30.0, (float) $opnItem->qty);
        $this->assertDatabaseMissing('batches', [
            'medicine_id' => $medicine->id,
            'name' => 'MINUS-AUTO',
        ]);

        // Mutasi Masuk +30 must offset OPN-20261012 (-30 -> 0) and incoming MTI (30 -> 0, original_qty = 30)
        $srcBatch = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 9,
            'name' => '239042394929',
            'expired_date' => '2027-09-01',
            'stock' => 0,
            'status' => 1,
        ]);
        $destBatch = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 1,
            'name' => '239042394929',
            'expired_date' => '2027-09-01',
            'stock' => 0,
            'status' => 1,
        ]);
        $tr = MedicineTransfers::create([
            'code' => '2611MUT88849',
            'status' => 0,
            'source_pharmacy_id' => 9,
            'destination_pharmacy_id' => 1,
        ]);
        $incomingItem = MedicineTransferItems::create([
            'medicine_transfer_id' => $tr->id,
            'batches_id' => $destBatch->id,
            'source_batches_id' => $srcBatch->id,
            'source_type' => 'gudang',
            'qty' => 30,
            'status' => 0,
        ]);

        $this->actingAs($this->cashierUser);
        session(['active_pharmacy_id' => 1]);
        $accReq = Request::create("/transfers/{$tr->id}/accept", 'POST', ['pin' => '1234', 'etalases_id' => $this->etalaseId]);
        $accReq->setUserResolver(fn() => $this->cashierUser);
        $accRes = app(TransfersController::class)->acceptTransfer($accReq, $tr);
        $this->assertSame('Semua item diterima.', $accRes->getSession()->get('success'), (string) $accRes->getSession()->get('message'));

        $opnItem->refresh();
        $incomingItem->refresh();
        $this->assertSame(0.0, (float) $opnItem->qty);
        $this->assertSame(0.0, (float) $incomingItem->qty);
        $this->assertSame(30.0, (float) $incomingItem->original_qty);
        $this->assertSame(0, app(StockLedgerService::class)->calculateRealtimeStock($medicine->id, 1, 'counter'));
    }

    public function test_direct_branch_receiving_settlement_offsets_minus_auto(): void
    {
        $medicine = Medicines::create([
            'code' => 'RCV01',
            'name' => 'OBAT PEMBELIAN LANGSUNG',
            'stock' => 20,
        ]);

        $ledger = app(StockLedgerService::class);
        $minusItem = $ledger->recordNegativeCounterSale($medicine->id, 1, 12, $this->cashierUser->id);
        $this->assertNotNull($minusItem);
        $this->assertSame(-12.0, (float) $minusItem->qty);

        // Simulate Pembelian Langsung Cabang adding +20 via receiving MTI
        $recHeader = MedicineTransfers::create([
            'code' => 'TRF-RCV-1',
            'status' => 1,
            'source_pharmacy_id' => 1,
            'destination_pharmacy_id' => 1,
        ]);
        $recBatch = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 1,
            'name' => 'BATCH-RCV-1',
            'expired_date' => '2027-12-01',
            'stock' => 0,
            'status' => 1,
        ]);
        $recMti = MedicineTransferItems::create([
            'medicine_transfer_id' => $recHeader->id,
            'batches_id' => $recBatch->id,
            'receiving_items_id' => 555,
            'etalases_id' => 101,
            'qty' => 20,
            'original_qty' => 20,
            'status' => 1,
        ]);

        $settled = DB::transaction(fn() => $ledger->settleNegativeCounterStock($medicine->id, 1, $recMti));
        $this->assertSame(12.0, $settled);

        $minusItem->refresh();
        $recMti->refresh();
        $this->assertSame(0.0, (float) $minusItem->qty);
        $this->assertSame(8.0, (float) $recMti->qty);
        $this->assertSame(20.0, (float) $recMti->original_qty);
        $this->assertSame(8, $ledger->calculateRealtimeStock($medicine->id, 1, 'counter'));
    }

    public function test_synthetic_trf_init_minus_is_hidden_from_transfers_and_sorted_last_in_batches(): void
    {
        $medicine = Medicines::create([
            'code' => 'VIS01',
            'name' => 'OBAT VISIBILITAS',
            'stock' => 10,
        ]);

        $ledger = app(StockLedgerService::class);
        $minusItem = $ledger->recordNegativeCounterSale($medicine->id, 1, 5, $this->cashierUser->id);
        $this->assertNotNull($minusItem);

        // Add a real dated batch in the same branch so ordering can be verified
        $realBatch = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 1,
            'name' => 'REAL-BATCH-1',
            'expired_date' => '2027-03-15',
            'stock' => 0,
            'status' => 1,
        ]);
        $realHeader = MedicineTransfers::create([
            'code' => 'MUT-REAL-1',
            'status' => 1,
            'source_pharmacy_id' => 9,
            'destination_pharmacy_id' => 1,
        ]);
        MedicineTransferItems::create([
            'medicine_transfer_id' => $realHeader->id,
            'batches_id' => $realBatch->id,
            'etalases_id' => 101,
            'qty' => 10,
            'original_qty' => 10,
            'status' => 1,
        ]);

        // 1. TransfersController::incomingTransfers (both Mutasi Masuk & Keluar) excludes TRF-INIT-MINUS-1
        $this->actingAs($this->cashierUser);
        session(['active_pharmacy_id' => 1]);
        foreach (['incoming', 'outgoing'] as $tab) {
            $req = Request::create('/transfers/incoming', 'GET', ['type' => $tab]);
            $req->headers->set('X-Requested-With', 'XMLHttpRequest');
            $res = app(TransfersController::class)->incomingTransfers($req);
            $codes = collect($res->getData(true)['data'] ?? [])->pluck('code')->all();
            $this->assertNotContains('TRF-INIT-MINUS-1', $codes);
        }

        // 2. TransfersExport excludes TRF-INIT-MINUS-1
        $exportCodes = (new TransfersExport(1, null, null, null, 'all'))
            ->query()
            ->get()
            ->map(fn($mti) => optional($mti->transfer)->code)
            ->all();
        $this->assertNotContains('TRF-INIT-MINUS-1', $exportCodes);
        $this->assertContains('MUT-REAL-1', $exportCodes);

        // 3. SuppliesController batch queries return REAL-BATCH-1 first and MINUS-AUTO last
        $batchesReq = Request::create('/supplies/batches', 'GET', ['medicine_id' => $medicine->id]);
        $batchesResponse = json_decode(app(SuppliesController::class)->batches($batchesReq)->getContent(), true);
        $this->assertSame('REAL-BATCH-1', $batchesResponse[0]['name']);
        $this->assertSame('MINUS-AUTO', $batchesResponse[1]['name']);

        $byMedicineReq = Request::create('/supplies/get-batches', 'GET', ['medicine_id' => $medicine->id]);
        $byMedicineRes = json_decode(
            app(SuppliesController::class)->getBatchesByMedicine($byMedicineReq)->getContent(),
            true
        );
        $this->assertSame('REAL-BATCH-1', $byMedicineRes[0]['name']);
        $this->assertSame('MINUS-AUTO', $byMedicineRes[1]['name']);
    }
}
