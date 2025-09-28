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
        Schema::create('medicine_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->onDelete('cascade');
            $table->foreignId('debtor_id')->nullable()->constrained('debtors')->onDelete('cascade');
            $table->string('transaction_type')->nullable();
            $table->string('transaction_code')->nullable();
            $table->string('paid')->nullable();
            $table->string('changes')->nullable();
            $table->string('subtotal')->nullable();
            $table->string('discount')->nullable(); 
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
