<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            $table->unsignedBigInteger('receiving_items_id')->nullable()->after('batches_id');
            $table->index('receiving_items_id');
        });
    }

    public function down()
    {
        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            $table->dropIndex(['receiving_items_id']);
            $table->dropColumn('receiving_items_id');
        });
    }
};