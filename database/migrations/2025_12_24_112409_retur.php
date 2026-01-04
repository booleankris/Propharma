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
        Schema::create('retur', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('transaction_id')->constrained('medicine_transactions')->onDelete('no action');
            $table->foreignId('medicine_id')->constrained('medicines')->onDelete('no action');
            $table->string('qty_retur');
            $table->string('total_retur');
            $table->integer('status')->default('0')->nullable();
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
        //
    }
};
