<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add stock_deducted_at column
        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            $table->timestamp('stock_deducted_at')->nullable()->after('status');
        });

        // 2. Deny all existing pending transfers so we start clean
        //    (pending items have status = 0, pending transfers have status = 0)
        DB::table('medicine_transfer_items')
            ->where('status', 0)
            ->update(['status' => 2]);

        DB::table('medicine_transfers')
            ->where('status', 0)
            ->update(['status' => 2]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            $table->dropColumn('stock_deducted_at');
        });
    }
};
