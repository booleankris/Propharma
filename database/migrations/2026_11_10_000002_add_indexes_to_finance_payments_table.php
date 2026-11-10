<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('finance_payments', function (Blueprint $table) {
            // Index for date range filtering and sorting
            $table->index('payment_date');
            // Composite index for branch, date, and payment type
            $table->index(['pharmacy_id', 'payment_date']);
            $table->index(['account_id', 'payment_date']);
            $table->index('payment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_payments', function (Blueprint $table) {
            $table->dropIndex(['payment_date']);
            $table->dropIndex(['pharmacy_id', 'payment_date']);
            $table->dropIndex(['account_id', 'payment_date']);
            $table->dropIndex(['payment_type']);
        });
    }
};

