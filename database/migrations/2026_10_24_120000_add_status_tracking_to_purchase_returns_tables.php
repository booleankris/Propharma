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
        Schema::table('purchase_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_returns', 'status_date')) {
                $table->date('status_date')->nullable()->after('status');
            }
            if (!Schema::hasColumn('purchase_returns', 'confirmed_date')) {
                $table->date('confirmed_date')->nullable()->after('status_date');
            }
        });

        Schema::table('purchase_return_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_return_items', 'status')) {
                $table->string('status')->default('diajukan')->after('subtotal'); // 'diajukan', 'dikonfirmasi', 'sudah_potong', 'barang_kembali_pmi'
            }
            if (!Schema::hasColumn('purchase_return_items', 'status_date')) {
                $table->date('status_date')->nullable()->after('status');
            }
            if (!Schema::hasColumn('purchase_return_items', 'confirmed_date')) {
                $table->date('confirmed_date')->nullable()->after('status_date');
            }
            if (!Schema::hasColumn('purchase_return_items', 'deduction_date')) {
                $table->date('deduction_date')->nullable()->after('confirmed_date');
            }
            if (!Schema::hasColumn('purchase_return_items', 'stock_deducted')) {
                $table->boolean('stock_deducted')->default(false)->after('deduction_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_returns', 'confirmed_date')) {
                $table->dropColumn('confirmed_date');
            }
            if (Schema::hasColumn('purchase_returns', 'status_date')) {
                $table->dropColumn('status_date');
            }
        });

        Schema::table('purchase_return_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_return_items', 'stock_deducted')) {
                $table->dropColumn('stock_deducted');
            }
            if (Schema::hasColumn('purchase_return_items', 'deduction_date')) {
                $table->dropColumn('deduction_date');
            }
            if (Schema::hasColumn('purchase_return_items', 'confirmed_date')) {
                $table->dropColumn('confirmed_date');
            }
            if (Schema::hasColumn('purchase_return_items', 'status_date')) {
                $table->dropColumn('status_date');
            }
            if (Schema::hasColumn('purchase_return_items', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
