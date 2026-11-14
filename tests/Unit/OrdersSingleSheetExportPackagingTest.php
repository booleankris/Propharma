<?php

namespace Tests\Unit;

use App\Exports\Orders\OrdersSingleSheetExport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrdersSingleSheetExportPackagingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropAllTables();

        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('creditors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('factories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('packaging')->nullable();
            $table->string('unit')->nullable();
            $table->unsignedBigInteger('factory_id')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('medicine_id');
            $table->string('creditor_code')->nullable();
            $table->double('price')->default(0);
            $table->boolean('pack')->nullable();
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->date('date')->nullable();
            $table->timestamps();
        });

        Schema::create('receiving_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id');
            $table->string('receiving_details_code')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('invoice_due')->nullable();
            $table->integer('invoice_times')->nullable();
            $table->string('invoice_payment')->nullable();
            $table->string('invoice_ppn')->nullable();
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
            $table->double('discount')->default(0);
            $table->double('extra_discount')->default(0);
            $table->double('total')->default(0);
            $table->double('raw_price')->default(0);
            $table->string('batch')->nullable();
            $table->date('expired_date')->nullable();
            $table->timestamps();
        });
    }

    public function test_kemasan_and_utuh_reflect_received_pack_mode(): void
    {
        $now = '2026-11-10 10:00:00';

        DB::table('pharmacies')->insert(['id' => 1, 'name' => 'APOTEK PMI', 'address' => 'Jl. PMI', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('creditors')->insert(['id' => 1, 'code' => 'PBF1', 'name' => 'PT PBF', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('factories')->insert(['id' => 1, 'name' => 'SURYA DERMATO MEDICA', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('medicines')->insert([
            'id' => 1,
            'code' => 'MED01',
            'name' => 'CREAM TEST',
            'packaging' => 'BOX',
            'unit' => 'TUBE',
            'factory_id' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('orders')->insert(['id' => 1, 'code' => 'PO-1', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('batches')->insert(['id' => 1, 'name' => 'B001', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('receiving')->insert(['id' => 1, 'pharmacy_id' => 1, 'date' => '2026-11-10', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('receiving_details')->insert([
            'id' => 1,
            'receiving_id' => 1,
            'receiving_details_code' => 'NT-01',
            'invoice_number' => 'INV-01',
            'invoice_date' => '2026-11-10',
            'invoice_due' => '2026-12-10',
            'invoice_times' => 30,
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'EXCLUDE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 1. Order awal pack=1, tetapi diterima eceran (receipt_is_pack=0) -> Kemasan harus TUBE, Utuh=0
        DB::table('order_items')->insert(['id' => 1, 'order_id' => 1, 'medicine_id' => 1, 'creditor_code' => 'PBF1', 'price' => 31600, 'pack' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('receiving_items')->insert([
            'id' => 1,
            'receiving_details_id' => 1,
            'order_items_id' => 1,
            'batches_id' => 1,
            'qty_received' => 8,
            'qty' => 8,
            'receipt_is_pack' => 0,
            'raw_price' => 31600,
            'batch' => '00W05A1',
            'expired_date' => '2028-08-01',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. Diterima utuh (receipt_is_pack=1) -> Kemasan harus BOX, Utuh=1
        DB::table('order_items')->insert(['id' => 2, 'order_id' => 1, 'medicine_id' => 1, 'creditor_code' => 'PBF1', 'price' => 316000, 'pack' => 0, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('receiving_items')->insert([
            'id' => 2,
            'receiving_details_id' => 1,
            'order_items_id' => 2,
            'batches_id' => 1,
            'qty_received' => 2,
            'qty' => 2,
            'receipt_is_pack' => 1,
            'raw_price' => 316000,
            'batch' => '00W05A2',
            'expired_date' => '2028-08-01',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3. Legacy row (receipt_is_pack=null, order_items.pack=0) -> fallback ke unit (TUBE), Utuh=0
        DB::table('order_items')->insert(['id' => 3, 'order_id' => 1, 'medicine_id' => 1, 'creditor_code' => 'PBF1', 'price' => 31600, 'pack' => 0, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('receiving_items')->insert([
            'id' => 3,
            'receiving_details_id' => 1,
            'order_items_id' => 3,
            'batches_id' => 1,
            'qty_received' => 4,
            'qty' => 4,
            'receipt_is_pack' => null,
            'raw_price' => 31600,
            'batch' => '00W05A3',
            'expired_date' => '2028-08-01',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $export = new OrdersSingleSheetExport(1, '2026-11-01', '2026-11-30', null, 'Semua');
        $rows = $export->array();

        // Header report 6 baris + tableHeader 1 baris -> data dimulai pada index 7
        $this->assertSame('TUBE', $rows[7][16]);
        $this->assertSame(0, $rows[7][17]);

        $this->assertSame('BOX', $rows[8][16]);
        $this->assertSame(1, $rows[8][17]);

        $this->assertSame('TUBE', $rows[9][16]);
        $this->assertSame(0, $rows[9][17]);
    }
}
