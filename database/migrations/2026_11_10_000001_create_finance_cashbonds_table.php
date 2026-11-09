<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('finance_cashbonds')) {
            Schema::create('finance_cashbonds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pharmacy_id')->nullable()->constrained('pharmacies')->nullOnDelete();
                $table->foreignId('account_id')->constrained('finance_accounts')->cascadeOnDelete();
                $table->string('borrower_name', 150)->default('Direktur');
                $table->decimal('amount', 15, 2);
                $table->date('transaction_date');
                $table->text('purpose')->nullable();
                $table->string('status', 30)->default('BELUM LUNAS'); // 'BELUM LUNAS', 'LUNAS'
                $table->date('settlement_date')->nullable();
                $table->text('settlement_notes')->nullable();
                $table->string('proof_image')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['pharmacy_id', 'status']);
                $table->index('account_id');
                $table->index('transaction_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_cashbonds');
    }
};

