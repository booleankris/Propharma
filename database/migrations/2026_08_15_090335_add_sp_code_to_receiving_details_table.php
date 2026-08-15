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
        Schema::table('receiving_details', function (Blueprint $table) {
            if (!Schema::hasColumn('receiving_details', 'sp_code')) {
                $table->string('sp_code')->nullable()->after('id');
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
        Schema::table('receiving_details', function (Blueprint $table) {
            //
        });
    }
};
