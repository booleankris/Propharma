<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_revisions')) {
            return;
        }

        Schema::create('stock_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revision_code', 50)->index();
            $table->unsignedBigInteger('pharmacy_id')->index();
            $table->string('target_mode', 20)->default('pelayanan');
            $table->unsignedBigInteger('medicine_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->integer('db_stock_before')->default(0);
            $table->integer('db_stock_after')->default(0);
            $table->integer('ledger_balance_before')->default(0);
            $table->integer('ledger_balance_after')->default(0);
            $table->integer('ledger_delta')->default(0);
            $table->json('batch_details')->nullable();
            $table->unsignedBigInteger('items_log_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_revisions');
    }
};
