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
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->string('pharmacy_registration')->nullable()->after('pharmacist');
            $table->string('logo')->nullable()->after('pharmacy_registration');
            $table->string('signature')->nullable()->after('logo');
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
