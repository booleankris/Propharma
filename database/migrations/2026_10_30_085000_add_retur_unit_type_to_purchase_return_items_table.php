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
        Schema::table('purchase_return_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_return_items', 'retur_type')) {
                $table->string('retur_type', 20)->default('unit')->after('unit'); // 'packaging' (Kemasan/Utuh) atau 'unit' (Eceran/Satuan Stok)
            }
            if (!Schema::hasColumn('purchase_return_items', 'stock_unit_factor')) {
                $table->unsignedInteger('stock_unit_factor')->default(1)->after('retur_type'); // Faktor isi konversi ke satuan stok fisik
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_return_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_return_items', 'stock_unit_factor')) {
                $table->dropColumn('stock_unit_factor');
            }
            if (Schema::hasColumn('purchase_return_items', 'retur_type')) {
                $table->dropColumn('retur_type');
            }
        });
    }
};

