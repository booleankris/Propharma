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
        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_items_id')->constrained('order_items')->onDelete('no action');
            $table->foreignId('creditor_id')->nullable()->constrained('creditors')->onDelete('no action');
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->onDelete('cascade');
            $table->string('code')->unique();
            $table->string('date')->nullable();
            $table->string('factor_number');
            $table->string('factor_date');
            $table->string('factor_due');
            $table->string('factor_ppn');
            $table->string('discount');
            $table->string('expired_date');
            $table->string('batch');
            $table->string('location');
            $table->string('etalase');
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
