<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index definitions for Hutang Dagang and Piutang optimization.
     *
     * @var array<int, array{table: string, name: string, columns: array<int, string>}>
     */
    private array $indexes = [
        [
            'table' => 'receiving',
            'name' => 'idx_receiving_pharmacy_status_updated',
            'columns' => ['pharmacy_id', 'status', 'updated_at'],
        ],
        [
            'table' => 'receiving_details',
            'name' => 'idx_rd_payment_receiving',
            'columns' => ['invoice_payment', 'receiving_id'],
        ],
        [
            'table' => 'receiving_details',
            'name' => 'idx_rd_payment_created',
            'columns' => ['invoice_payment', 'created_at'],
        ],
        [
            'table' => 'receiving_details',
            'name' => 'idx_rd_invoice_number',
            'columns' => ['invoice_number'],
        ],
        [
            'table' => 'receiving_details',
            'name' => 'idx_rd_details_code',
            'columns' => ['receiving_details_code'],
        ],
        [
            'table' => 'receiving_items',
            'name' => 'idx_ri_detail_batch',
            'columns' => ['receiving_details_id', 'batches_id'],
        ],
        [
            'table' => 'medicine_transactions',
            'name' => 'idx_mt_pharmacy_type_status_id',
            'columns' => ['pharmacy_id', 'transaction_type', 'status', 'id'],
        ],
        [
            'table' => 'medicine_transactions',
            'name' => 'idx_mt_transaction_code',
            'columns' => ['transaction_code'],
        ],
        [
            'table' => 'medicine_cart',
            'name' => 'idx_mc_tx_status',
            'columns' => ['transaction_id', 'status'],
        ],
        [
            'table' => 'finance_payments',
            'name' => 'idx_fp_rd_type',
            'columns' => ['receiving_detail_id', 'payment_type'],
        ],
        [
            'table' => 'finance_payments',
            'name' => 'idx_fp_mt_type',
            'columns' => ['medicine_transaction_id', 'payment_type'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $index) {
            $table = $index['table'];
            $name = $index['name'];
            $columns = $index['columns'];

            if (!Schema::hasTable($table)) {
                continue;
            }

            $allColumnsExist = true;
            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    $allColumnsExist = false;
                    break;
                }
            }
            if (!$allColumnsExist) {
                continue;
            }

            if ($this->indexExists($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                $blueprint->index($columns, $name);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->indexes) as $index) {
            $table = $index['table'];
            $name = $index['name'];

            if (!Schema::hasTable($table) || !$this->indexExists($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropIndex($name);
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();
            $count = DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', $database)
                ->where('TABLE_NAME', $table)
                ->where('INDEX_NAME', $indexName)
                ->count();

            return $count > 0;
        }

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");
            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }
};
