<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            if (DB::getDriverName() === 'sqlite') {
                return collect(DB::select("PRAGMA index_list('{$table}')"))
                    ->pluck('name')
                    ->contains($indexName);
            }
            return collect(DB::select("SHOW INDEX FROM `{$table}`"))
                ->pluck('Key_name')
                ->contains($indexName);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            if (!$this->indexExists('batches', 'batches_medicine_pharmacy_index')) {
                $table->index(['medicine_id', 'pharmacy_id'], 'batches_medicine_pharmacy_index');
            }
        });

        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            if (!$this->indexExists('medicine_transfer_items', 'mti_batches_status_index')) {
                $table->index(['batches_id', 'status'], 'mti_batches_status_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            if ($this->indexExists('batches', 'batches_medicine_pharmacy_index')) {
                $table->dropIndex('batches_medicine_pharmacy_index');
            }
        });

        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            if ($this->indexExists('medicine_transfer_items', 'mti_batches_status_index')) {
                $table->dropIndex('mti_batches_status_index');
            }
        });
    }
};
