<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            // Legacy rows are backfilled only when their original quantity can
            // be linked unambiguously to a mutation log.
            $table->integer('original_qty')->nullable()->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            $table->dropColumn('original_qty');
        });
    }
};
