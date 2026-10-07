<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicine_transfers', function (Blueprint $table) {
            $table->boolean('is_request')->default(false)->after('status');
            $table->unsignedBigInteger('source_pharmacy_id')->nullable()->after('is_request');
            $table->unsignedBigInteger('destination_pharmacy_id')->nullable()->after('source_pharmacy_id');
            $table->unsignedTinyInteger('request_status')->nullable()->after('destination_pharmacy_id');
        });

        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            $table->unsignedBigInteger('requested_medicine_id')->nullable()->after('batches_id');
            $table->foreign('requested_medicine_id')->references('id')->on('medicines')->nullOnDelete();
            $table->foreignId('batches_id')->nullable()->change();
            $table->foreignId('etalases_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            $table->dropForeign(['requested_medicine_id']);
            $table->dropColumn('requested_medicine_id');
        });

        Schema::table('medicine_transfers', function (Blueprint $table) {
            $table->dropColumn(['is_request', 'source_pharmacy_id', 'destination_pharmacy_id', 'request_status']);
        });
    }
};
