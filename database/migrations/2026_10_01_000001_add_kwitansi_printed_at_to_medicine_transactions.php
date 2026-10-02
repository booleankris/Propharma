<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicine_transactions', function (Blueprint $table) {
            $table->timestamp('kwitansi_printed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('medicine_transactions', function (Blueprint $table) {
            $table->dropColumn('kwitansi_printed_at');
        });
    }
};
