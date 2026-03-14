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
        Schema::create('receiving_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_details_id')->nullable()->constrained('receiving_details')->onDelete('no action');
            $table->foreignId('order_items_id')->nullable()->constrained('order_items')->onDelete('no action');
            $table->string('qty_received')->nullable();
            $table->string('discount')->nullable();
            $table->string('extra_discount')->nullable();
            $table->string('expired_date')->nullable();
            $table->string('batch')->nullable();
            $table->string('location')->nullable();
            $table->string('etalase')->nullable();
            $table->string('total')->nullable();
            $table->string('status')->default('0')->nullable();
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
