<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('receiving_details') && !Schema::hasColumn('receiving_details', 'is_submitted')) {
            Schema::table('receiving_details', function (Blueprint $table) {
                $table->boolean('is_submitted')->default(false)->after('id');
            });
        }

        if (Schema::hasTable('medicine_transactions') && !Schema::hasColumn('medicine_transactions', 'is_submitted')) {
            Schema::table('medicine_transactions', function (Blueprint $table) {
                $table->boolean('is_submitted')->default(false)->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('receiving_details') && Schema::hasColumn('receiving_details', 'is_submitted')) {
            Schema::table('receiving_details', function (Blueprint $table) {
                $table->dropColumn('is_submitted');
            });
        }

        if (Schema::hasTable('medicine_transactions') && Schema::hasColumn('medicine_transactions', 'is_submitted')) {
            Schema::table('medicine_transactions', function (Blueprint $table) {
                $table->dropColumn('is_submitted');
            });
        }
    }
};
