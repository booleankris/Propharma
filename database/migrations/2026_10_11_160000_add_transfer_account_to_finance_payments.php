<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('finance_payments', 'transfer_account_id')) {
            Schema::table('finance_payments', function (Blueprint $table) {
                $table->foreignId('transfer_account_id')->nullable()->after('account_id')->constrained('finance_accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('finance_payments', 'transfer_account_id')) {
            Schema::table('finance_payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('transfer_account_id');
            });
        }
    }
};
