<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reject') && !Schema::hasColumn('reject', 'location')) {
            Schema::table('reject', function (Blueprint $table) {
                $table->string('location', 30)->nullable()->after('reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reject') && Schema::hasColumn('reject', 'location')) {
            Schema::table('reject', function (Blueprint $table) {
                $table->dropColumn('location');
            });
        }
    }
};

