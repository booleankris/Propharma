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
            if (!Schema::hasColumn('finance_payments', 'pharmacy_id')) {
                $table->foreignId('pharmacy_id')->nullable()->after('id')->constrained('pharmacies')->nullOnDelete();
            }
            if (!Schema::hasColumn('finance_payments', 'expense_account_id')) {
                $table->foreignId('expense_account_id')->nullable()->after('account_id')->constrained('finance_accounts')->nullOnDelete();
            }
            if (!Schema::hasColumn('finance_payments', 'recipient')) {
                $table->string('recipient')->nullable()->after('amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_payments', function (Blueprint $table) {
            if (Schema::hasColumn('finance_payments', 'recipient')) {
                $table->dropColumn('recipient');
            }
            if (Schema::hasColumn('finance_payments', 'expense_account_id')) {
                $table->dropForeign(['expense_account_id']);
                $table->dropColumn('expense_account_id');
            }
            if (Schema::hasColumn('finance_payments', 'pharmacy_id')) {
                $table->dropForeign(['pharmacy_id']);
                $table->dropColumn('pharmacy_id');
            }
        });
    }
};
