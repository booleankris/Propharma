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
        Schema::create('items_log', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code');
            $table->string('code');
            $table->string('type');
            $table->foreignId('medicine_id')->constrained('medicines')->onDelete('no action');
            $table->string('qty');
            $table->string('qty_before');
            $table->string('qty_after');
            $table->string('total');
            $table->string('date');
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
