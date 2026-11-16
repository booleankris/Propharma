<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etalases', function (Blueprint $table) {
            if (!Schema::hasColumn('etalases', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('status');
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('etalases', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            if (!Schema::hasColumn('medicine_transfer_items', 'etalase_updated_by')) {
                $table->unsignedBigInteger('etalase_updated_by')->nullable()->after('etalases_id');
                $table->foreign('etalase_updated_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('medicine_transfer_items', 'etalase_updated_at')) {
                $table->timestamp('etalase_updated_at')->nullable()->after('etalase_updated_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            if (Schema::hasColumn('medicine_transfer_items', 'etalase_updated_by')) {
                $table->dropForeign(['etalase_updated_by']);
                $table->dropColumn('etalase_updated_by');
            }
            if (Schema::hasColumn('medicine_transfer_items', 'etalase_updated_at')) {
                $table->dropColumn('etalase_updated_at');
            }
        });

        Schema::table('etalases', function (Blueprint $table) {
            if (Schema::hasColumn('etalases', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }
            if (Schema::hasColumn('etalases', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};
