<?php

namespace Tests\Unit;

use App\Models\Batches;
use App\Models\Creditor;
use App\Models\Medicines;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\ReturnPolicy;
use App\Services\Finance\PurchaseReturnAlertService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseReturnAlertServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropAllTables();

        Schema::create('creditors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('factories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('unit')->default('TAB');
            $table->string('packaging')->nullable();
            $table->integer('content')->default(1);
            $table->unsignedBigInteger('factory_id')->nullable();
            $table->unsignedBigInteger('creditors_id')->nullable();
            $table->double('stock')->default(0);
            $table->double('raw_price')->default(10000);
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

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->string('code')->nullable();
            $table->timestamps();
        });

        Schema::create('receiving_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->string('creditor_code')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->timestamps();
        });

        Schema::create('receiving_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_details_id')->nullable();
            $table->unsignedBigInteger('order_items_id')->nullable();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->boolean('receipt_is_pack')->nullable();
            $table->integer('stock_unit_factor')->nullable();
            $table->double('raw_price')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_id');
            $table->timestamps();
        });

        Schema::create('return_policies', function (Blueprint $table) {
            $table->id();
            $table->string('creditor_code');
            $table->unsignedBigInteger('factory_id')->nullable();
            $table->string('factory_name')->nullable();
            $table->boolean('is_returnable')->default(true);
            $table->integer('months_before_ed')->default(3);
            $table->integer('months_after_ed')->default(0);
            $table->text('notes')->nullable();
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
            $table->string('unit')->default('TAB');
            $table->string('retur_type')->default('unit');
            $table->integer('stock_unit_factor')->default(1);
            $table->string('batch_number')->nullable();
            $table->date('expired_date')->nullable();
            $table->double('qty')->default(1);
            $table->double('unit_price')->default(10000);
            $table->double('subtotal')->default(10000);
            $table->string('status')->default('dikonfirmasi');
            $table->date('status_date')->nullable();
            $table->date('confirmed_date')->nullable();
            $table->boolean('stock_deducted')->default(false);
            $table->string('reason')->nullable();
            $table->date('pickup_date')->nullable();
            $table->string('deduction_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_excludes_batches_already_submitted_for_return_except_cancelled_and_respects_pharmacy(): void
    {
        $creditor = Creditor::create(['code' => 'PBF1', 'name' => 'PT PBF Satu']);
        $factoryId = \DB::table('factories')->insertGetId([
            'name' => 'DEXA MEDICA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ReturnPolicy::create([
            'creditor_code' => 'PBF1',
            'factory_id' => $factoryId,
            'factory_name' => 'DEXA MEDICA',
            'is_returnable' => true,
            'months_before_ed' => 3,
            'months_after_ed' => 0,
        ]);

        $medicine = Medicines::create([
            'code' => 'MED-01',
            'name' => 'OBAT TEST',
            'unit' => 'TAB',
            'content' => 1,
            'factory_id' => $factoryId,
            'creditors_id' => $creditor->id,
            'stock' => 50,
            'raw_price' => 5000,
        ]);

        $expDate = Carbon::now()->startOfMonth()->addMonths(1)->toDateString();

        $batchUnreturned = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 1,
            'name' => 'B-BELUM',
            'expired_date' => $expDate,
            'stock' => 10,
        ]);

        $batchConfirmed = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 1,
            'name' => 'B-KONFIRM',
            'expired_date' => $expDate,
            'stock' => 10,
        ]);

        $batchDeductedByName = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 1,
            'name' => 'B-POTONG',
            'expired_date' => $expDate,
            'stock' => 5,
        ]);

        $batchCancelled = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 1,
            'name' => 'B-BATAL',
            'expired_date' => $expDate,
            'stock' => 10,
        ]);

        $batchOtherPharmacySameName = Batches::create([
            'medicine_id' => $medicine->id,
            'pharmacy_id' => 2,
            'name' => 'B-KONFIRM',
            'expired_date' => $expDate,
            'stock' => 10,
        ]);

        $pr = PurchaseReturn::create([
            'return_no' => 'RET-01',
            'creditor_code' => 'PBF1',
            'pharmacy_id' => 1,
            'status' => 'dikonfirmasi',
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $pr->id,
            'medicine_id' => $medicine->id,
            'batch_id' => $batchConfirmed->id,
            'medicine_name' => $medicine->name,
            'batch_number' => 'B-KONFIRM',
            'status' => 'dikonfirmasi',
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $pr->id,
            'medicine_id' => $medicine->id,
            'batch_id' => null,
            'medicine_name' => $medicine->name,
            'batch_number' => 'b-potong',
            'status' => 'sudah_potong',
        ]);

        $prCancelled = PurchaseReturn::create([
            'return_no' => 'RET-02',
            'creditor_code' => 'PBF1',
            'pharmacy_id' => 1,
            'status' => 'dibatalkan',
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $prCancelled->id,
            'medicine_id' => $medicine->id,
            'batch_id' => $batchCancelled->id,
            'medicine_name' => $medicine->name,
            'batch_number' => 'B-BATAL',
            'status' => 'dibatalkan',
        ]);

        $service = new PurchaseReturnAlertService();

        $resPharmacy1 = $service->getApproachingReturns(1, [], 25);
        $returnedBatchIdsP1 = collect($resPharmacy1['data']->items())->pluck('batch_id')->all();

        $this->assertEqualsCanonicalizing(
            [$batchUnreturned->id, $batchCancelled->id],
            $returnedBatchIdsP1
        );
        $this->assertSame(2, $resPharmacy1['stats']['total_count']);

        $resPharmacy2 = $service->getApproachingReturns(2, [], 25);
        $returnedBatchIdsP2 = collect($resPharmacy2['data']->items())->pluck('batch_id')->all();
        $this->assertSame([$batchOtherPharmacySameName->id], $returnedBatchIdsP2);
    }
}
