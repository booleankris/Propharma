<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Copy all historical transfers into medicine_transfer_items
        DB::statement("
        INSERT INTO medicine_transfer_items (
            medicine_transfer_id, 
            batches_id, 
            source_batches_id, 
            etalases_id, 
            qty, 
            status, 
            created_at, 
            updated_at
        )
        SELECT 
            id AS medicine_transfer_id, 
            batches_id, 
            batches_id AS source_batches_id, 
            etalases_id, 
            CAST(COALESCE(NULLIF(stock, ''), '0') AS SIGNED) AS qty, 
            status, 
            created_at, 
            updated_at
        FROM medicine_transfers
        WHERE batches_id IS NOT NULL;
    ");

        // 2. Drop the old columns from medicine_transfers
        Schema::table('medicine_transfers', function (Blueprint $table) {
            $table->dropForeign(['batches_id']);
            $table->dropForeign(['etalases_id']);
            $table->dropColumn(['batches_id', 'etalases_id', 'stock']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
