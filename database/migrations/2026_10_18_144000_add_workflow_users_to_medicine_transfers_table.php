<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('medicine_transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('medicine_transfers', 'sent_by')) {
                $table->foreignId('sent_by')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('medicine_transfers', 'received_by')) {
                $table->foreignId('received_by')
                    ->nullable()
                    ->after('sent_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('medicine_transfers', 'requested_by')) {
                $table->foreignId('requested_by')
                    ->nullable()
                    ->after('received_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        // Fast indexed backfill for direct transfers: creator was the sender
        DB::statement("UPDATE medicine_transfers SET sent_by = user_id WHERE is_request = 0 AND sent_by IS NULL AND user_id IS NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicine_transfers', function (Blueprint $table) {
            if (Schema::hasColumn('medicine_transfers', 'sent_by')) {
                $table->dropForeign(['sent_by']);
                $table->dropColumn('sent_by');
            }
            if (Schema::hasColumn('medicine_transfers', 'received_by')) {
                $table->dropForeign(['received_by']);
                $table->dropColumn('received_by');
            }
            if (Schema::hasColumn('medicine_transfers', 'requested_by')) {
                $table->dropForeign(['requested_by']);
                $table->dropColumn('requested_by');
            }
        });
    }
};
