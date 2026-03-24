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
        Schema::table('items_log', function (Blueprint $table) {
            if (!Schema::hasColumn('items_log', 'batches_id')) {
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
        Schema::table('items_log', function (Blueprint $table) {
            //
        });
    }
};
