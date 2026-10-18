<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items_log', function (Blueprint $table) {
            if (!Schema::hasIndex('items_log', 'idx_items_log_batches_id')) {
                $table->index('batches_id', 'idx_items_log_batches_id');
            }
            if (!Schema::hasIndex('items_log', 'idx_items_log_type')) {
                $table->index('type', 'idx_items_log_type');
            }
            if (!Schema::hasIndex('items_log', 'idx_items_log_med_type')) {
                $table->index(['medicine_id', 'type'], 'idx_items_log_med_type');
            }
            if (!Schema::hasIndex('items_log', 'idx_items_log_status_date')) {
                $table->index(['status', 'date'], 'idx_items_log_status_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items_log', function (Blueprint $table) {
            if (Schema::hasIndex('items_log', 'idx_items_log_batches_id')) {
                $table->dropIndex('idx_items_log_batches_id');
            }
            if (Schema::hasIndex('items_log', 'idx_items_log_type')) {
                $table->dropIndex('idx_items_log_type');
            }
            if (Schema::hasIndex('items_log', 'idx_items_log_med_type')) {
                $table->dropIndex('idx_items_log_med_type');
            }
            if (Schema::hasIndex('items_log', 'idx_items_log_status_date')) {
                $table->dropIndex('idx_items_log_status_date');
            }
        });
    }
};
