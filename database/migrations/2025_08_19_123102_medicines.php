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
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); 
            $table->string('generic')->nullable();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->onDelete('cascade');
            $table->foreignId('medicine_category_id')->constrained('medicine_categories')->onDelete('cascade');
            $table->foreignId('composition_id')->constrained('compositions')->onDelete('cascade');
            $table->string('name');
            $table->string('packaging')->nullable();
            $table->string('unit')->nullable();
            $table->string('content')->nullable();
            $table->string('dosage')->nullable();
            $table->string('raw_price')->default(0); 
            $table->string('pharmacy_net_price')->default(0);
            $table->string('net_price')->default(0);
            $table->string('psychotropic')->default(0);
            $table->integer('minimal_stock');
            $table->integer('stock')->default(0);
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
