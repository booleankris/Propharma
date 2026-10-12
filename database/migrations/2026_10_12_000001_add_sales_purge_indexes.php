<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items_log', function (Blueprint $table) {
            $table->index('transaction_code', 'items_log_transaction_code_idx');
        });

        Schema::table('medicine_transactions', function (Blueprint $table) {
            $table->index('created_at', 'medicine_transactions_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('items_log', function (Blueprint $table) {
            $table->dropIndex('items_log_transaction_code_idx');
        });

        Schema::table('medicine_transactions', function (Blueprint $table) {
            $table->dropIndex('medicine_transactions_created_at_idx');
        });
    }
};
