<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->contains($indexName);
    }

    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            if (!$this->indexExists('medicines', 'medicines_code_index'))    $table->index('code');
            if (!$this->indexExists('medicines', 'medicines_barcode_index')) $table->index('barcode');
            if (!$this->indexExists('medicines', 'medicines_name_index'))    $table->index('name');
        });

        Schema::table('batches', function (Blueprint $table) {
            if (!$this->indexExists('batches', 'batches_medicine_id_index')) $table->index('medicine_id');
        });

        Schema::table('medicine_transfers', function (Blueprint $table) {
            if (!$this->indexExists('medicine_transfers', 'medicine_transfers_batch_id_status_index')) $table->index(['batches_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            if ($this->indexExists('medicines', 'medicines_code_index'))    $table->dropIndex(['code']);
            if ($this->indexExists('medicines', 'medicines_barcode_index')) $table->dropIndex(['barcode']);
            if ($this->indexExists('medicines', 'medicines_name_index'))    $table->dropIndex(['name']);
        });

        Schema::table('batches', function (Blueprint $table) {
            if ($this->indexExists('batches', 'batches_medicine_id_index')) $table->dropIndex(['medicine_id']);
        });

        Schema::table('medicine_transfers', function (Blueprint $table) {
            if ($this->indexExists('medicine_transfers', 'medicine_transfers_batch_id_status_index')) $table->dropIndex(['batches_id', 'status']);
        });
    }
};
