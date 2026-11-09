<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat perubahan harga kini juga menyimpan HET (harga eceran tertinggi),
     * bukan hanya HNA (harga netto apotek).
     */
    public function up(): void
    {
        Schema::table('medicine_price_history', function (Blueprint $table) {
            if (!Schema::hasColumn('medicine_price_history', 'old_het_price')) {
                $table->string('old_het_price')->nullable()->after('old_price');
            }
            if (!Schema::hasColumn('medicine_price_history', 'new_het_price')) {
                $table->string('new_het_price')->nullable()->after('new_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('medicine_price_history', function (Blueprint $table) {
            if (Schema::hasColumn('medicine_price_history', 'new_het_price')) {
                $table->dropColumn('new_het_price');
            }
            if (Schema::hasColumn('medicine_price_history', 'old_het_price')) {
                $table->dropColumn('old_het_price');
            }
        });
    }
};
