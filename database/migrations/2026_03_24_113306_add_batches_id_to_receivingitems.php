<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('receiving_items', function (Blueprint $table) {
            if (!Schema::hasColumn('receiving_items', 'batches_id')) {
                $table->foreignId('batches_id')
                    ->nullable()
                    ->constrained('batches')
                    ->onDelete('no action');
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
        Schema::table('receiving_items', function (Blueprint $table) {
            //
        });
    }
};
