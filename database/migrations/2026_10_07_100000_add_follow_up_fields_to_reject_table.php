<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reject', function (Blueprint $table) {
            $table->string('source_type', 20)->nullable()->after('reason');
            $table->string('doctor_name')->nullable()->after('source_type');
            $table->string('stock_status')->nullable()->after('doctor_name');
            $table->string('equivalent')->nullable()->after('stock_status');
            $table->text('follow_up')->nullable()->after('equivalent');
            $table->text('follow_up_note')->nullable()->after('follow_up');
            $table->text('follow_up_update')->nullable()->after('follow_up_note');
        });
    }

    public function down(): void
    {
        Schema::table('reject', function (Blueprint $table) {
            $table->dropColumn([
                'source_type', 'doctor_name', 'stock_status', 'equivalent',
                'follow_up', 'follow_up_note', 'follow_up_update',
            ]);
        });
    }
};
