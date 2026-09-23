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
        Schema::table('finance_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('receiving_id')->nullable()->change();
            if (!Schema::hasColumn('finance_payments', 'medicine_transaction_id')) {
                $table->foreignId('medicine_transaction_id')
                    ->nullable()
                    ->after('receiving_detail_id')
                    ->constrained('medicine_transactions')
                    ->nullOnDelete();
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
        Schema::table('finance_payments', function (Blueprint $table) {
            if (Schema::hasColumn('finance_payments', 'medicine_transaction_id')) {
                $table->dropForeign(['medicine_transaction_id']);
                $table->dropColumn('medicine_transaction_id');
            }
            $table->unsignedBigInteger('receiving_id')->nullable(false)->change();
        });
    }
};
