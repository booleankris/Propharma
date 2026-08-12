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
        Schema::create('medicine_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_transfer_id')
                ->constrained('medicine_transfers')
                ->cascadeOnDelete();
            $table->foreignId('batches_id')->constrained('batches');
            $table->foreignId('etalases_id')->constrained('etalases');
            $table->integer('qty');
            $table->tinyInteger('status')->default(0); // 0=pending, 1=accepted, 2=denied
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('medicine_transfer_items');
    }
};
