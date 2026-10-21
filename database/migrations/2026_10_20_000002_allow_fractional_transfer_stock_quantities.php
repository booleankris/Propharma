<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            $table->decimal('qty', 15, 3)->change();
            if (Schema::hasColumn('medicine_transfer_items', 'original_qty')) {
                $table->decimal('original_qty', 15, 3)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Keep decimal storage on rollback so fractional stock quantities are never silently truncated.
    }
};
