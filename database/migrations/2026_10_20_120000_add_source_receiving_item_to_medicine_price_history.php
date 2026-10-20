<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicine_price_history', function (Blueprint $table) {
            if (!Schema::hasColumn('medicine_price_history', 'source_receiving_item_id')) {
                $table->unsignedBigInteger('source_receiving_item_id')->nullable()->after('medicine_id');
                $table->index('source_receiving_item_id', 'mph_source_receiving_item_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('medicine_price_history', function (Blueprint $table) {
            if (Schema::hasColumn('medicine_price_history', 'source_receiving_item_id')) {
                $table->dropIndex('mph_source_receiving_item_idx');
                $table->dropColumn('source_receiving_item_id');
            }
        });
    }
};
