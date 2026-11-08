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
        if (!Schema::hasTable('finance_balance_adjustments')) {
            Schema::create('finance_balance_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pharmacy_id')->nullable()->constrained('pharmacies')->nullOnDelete();
                $table->foreignId('account_id')->constrained('finance_accounts')->cascadeOnDelete();
                $table->decimal('system_balance', 15, 2);
                $table->decimal('real_balance', 15, 2);
                $table->decimal('difference', 15, 2);
                $table->date('adjustment_date');
                $table->dateTime('adjusted_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('finance_payment_id')->nullable()->constrained('finance_payments')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['pharmacy_id', 'account_id']);
                $table->index('adjustment_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_balance_adjustments');
    }
};

