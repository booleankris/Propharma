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
        if (!Schema::hasTable('transaction_services')) {
            Schema::create('transaction_services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('medicine_transaction_id')
                    ->constrained('medicine_transactions')
                    ->cascadeOnDelete();
                $table->foreignId('doctor_service_id')
                    ->nullable()
                    ->constrained('doctor_services')
                    ->nullOnDelete();
                // Snapshot nama & harga saat transaksi dibuat, agar riwayat tidak
                // berubah bila master jasa diubah/dihapus.
                $table->string('service_name', 150);
                $table->decimal('price', 15, 2)->default(0);
                $table->timestamps();

                // Satu transaksi hanya boleh memiliki satu jasa.
                $table->unique('medicine_transaction_id');
            });
        }

        // Penanda jenis faktur obat untuk transaksi tipe JASA (UPDS / HV/OTC / RESEP TUNAI).
        if (!Schema::hasColumn('medicine_transactions', 'service_invoice_type')) {
            Schema::table('medicine_transactions', function (Blueprint $table) {
                $table->string('service_invoice_type', 30)->nullable()->after('doctor_shipping_bank_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('medicine_transactions', 'service_invoice_type')) {
            Schema::table('medicine_transactions', function (Blueprint $table) {
                $table->dropColumn('service_invoice_type');
            });
        }

        Schema::dropIfExists('transaction_services');
    }
};
