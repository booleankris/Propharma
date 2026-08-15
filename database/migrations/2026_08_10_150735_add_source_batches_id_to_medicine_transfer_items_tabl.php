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
        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            if (!Schema::hasColumn('medicine_transfer_items', 'source_batches_id')) {
                $table->foreignId('source_batches_id')
                    ->nullable()
                    ->after('batches_id')
                    ->constrained('batches');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            //
        });
    }
};
