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
        Schema::table('medicine_cart', function (Blueprint $table) {
            if (!Schema::hasColumn('medicine_cart', 'medicine_type')) {
                $table->string('medicine_type')->nullable();
            }
            if (!Schema::hasColumn('medicine_cart', 'service_fee')) {
                $table->string('service_fee')->nullable();
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
        //
    }
};
