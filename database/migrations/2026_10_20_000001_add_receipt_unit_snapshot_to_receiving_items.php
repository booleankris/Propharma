<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receiving_items', function (Blueprint $table) {
            if (!Schema::hasColumn('receiving_items', 'receipt_is_pack')) {
                $table->boolean('receipt_is_pack')->nullable()->after('qty');
            }
            if (!Schema::hasColumn('receiving_items', 'stock_unit_factor')) {
                $table->unsignedInteger('stock_unit_factor')->nullable()->after('receipt_is_pack');
            }
        });

        // Legacy rows can only be initialized from the currently stored BPBA/master conversion.
        // New receipts persist the conversion at entry time, so later master edits cannot rewrite history.
        DB::statement(<<<'SQL'
            UPDATE receiving_items ri
            INNER JOIN order_items oi ON oi.id = ri.order_items_id
            INNER JOIN medicines m ON m.id = oi.medicine_id
            SET ri.receipt_is_pack = COALESCE(ri.receipt_is_pack, oi.pack = 1),
                ri.stock_unit_factor = COALESCE(
                    ri.stock_unit_factor,
                    CASE WHEN oi.pack = 1 THEN GREATEST(CAST(COALESCE(NULLIF(m.content, ''), 1) AS UNSIGNED), 1) ELSE 1 END
                )
            WHERE ri.receipt_is_pack IS NULL OR ri.stock_unit_factor IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('receiving_items', function (Blueprint $table) {
            if (Schema::hasColumn('receiving_items', 'stock_unit_factor')) {
                $table->dropColumn('stock_unit_factor');
            }
            if (Schema::hasColumn('receiving_items', 'receipt_is_pack')) {
                $table->dropColumn('receipt_is_pack');
            }
        });
    }
};
