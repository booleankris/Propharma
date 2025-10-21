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
        Schema::create('medicine_cart', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('medicine_id')->constrained('medicines')->onDelete('cascade');
            $table->foreignId('transaction_id')->nullable()->default(null)->constrained('medicine_transactions')->onDelete('cascade');
            $table->string('quantity');
            $table->string('discount');
            $table->string('embalase')->default(null)->nullable(); 
            $table->string('cart_type')->default(null)->nullable();
            $table->string('package')->default(null)->nullable();
            $table->string('dosage_r')->default(null)->nullable();  
            $table->string('total_price');
            $table->string('final_price')->default(null)->nullable();
            $table->integer('status')->default('0')->nullable(); 
            $table->string('recipe_status')->default(null)->nullable();
            $table->string('recipe_number')->default(null)->nullable();
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
