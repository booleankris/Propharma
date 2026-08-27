<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            $table->string('source_type', 20)->default('gudang')->after('source_batches_id');
            // 'gudang' = from batches.stock (warehouse)
            // 'pelayanan' = from medicine_transfer_items.qty (counter/etalase)
        });

        // Update existing records: if source_batches_id pharmacy_id != 1, it was from pelayanan
        DB::statement("
            UPDATE medicine_transfer_items mti
            JOIN batches b ON b.id = mti.source_batches_id
            SET mti.source_type = CASE
                WHEN b.pharmacy_id = 1 THEN 'gudang'
                ELSE 'pelayanan'
            END
            WHERE mti.source_batches_id IS NOT NULL
        ");
    }

    public function down()
    {
        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            $table->dropColumn('source_type');
        });
    }
};
