<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('finance_opening_invoices')) {
            Schema::create('finance_opening_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pharmacy_id')->index();
                $table->string('type', 20)->index(); // HUTANG | PIUTANG
                $table->string('creditor_code', 100)->nullable()->index();
                $table->unsignedBigInteger('debtor_id')->nullable()->index();
                $table->string('invoice_number', 150)->index();
                $table->date('invoice_date')->index();
                $table->date('due_date')->nullable();
                $table->decimal('amount', 15, 2);
                $table->boolean('is_submitted')->default(false);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['pharmacy_id', 'type', 'invoice_date'], 'foi_pharmacy_type_date_idx');
            });
        }

        if (Schema::hasTable('finance_payments') && !Schema::hasColumn('finance_payments', 'opening_invoice_id')) {
            Schema::table('finance_payments', function (Blueprint $table) {
                $table->foreignId('opening_invoice_id')
                    ->nullable()
                    ->after('medicine_transaction_id')
                    ->constrained('finance_opening_invoices')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('finance_payments') && Schema::hasColumn('finance_payments', 'opening_invoice_id')) {
            Schema::table('finance_payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('opening_invoice_id');
            });
        }

        Schema::dropIfExists('finance_opening_invoices');
    }
};
