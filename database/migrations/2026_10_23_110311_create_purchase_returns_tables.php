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
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no')->unique(); // e.g. RET-2610/0001
            $table->string('invoice_number')->nullable()->index(); // No Faktur Pembelian
            $table->date('invoice_date')->nullable(); // Tanggal Faktur
            $table->date('received_date')->nullable(); // Tanggal Diterima Barang
            $table->string('creditor_code')->index(); // PBF
            $table->unsignedBigInteger('pharmacy_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index(); // Staf pembuat
            $table->unsignedBigInteger('receiving_id')->nullable()->index(); // relasi opsional jika dari faktur sistem
            $table->decimal('total_amount', 18, 2)->default(0); // Total nilai retur
            $table->string('status')->default('diajukan'); // 'diajukan', 'sudah_diambil', 'selesai_potong_tagihan', 'dibatalkan'
            $table->date('pickup_date')->nullable(); // TGL BRG DI AMBIL PBF
            $table->string('deduction_status')->nullable(); // STATUS POTONG TAGIHAN (misal: "SUDAH POTONG TAGIHAN (20/7/2026)")
            $table->date('deduction_date')->nullable(); // Tanggal potong tagihan
            $table->string('reason')->nullable(); // Alasan umum retur: ED DEKAT, SALAH PESAN, DISKON TIDAK SESUAI, TERDOUBLE, PESAN YANG INJ, dsb.
            $table->text('notes')->nullable();
            $table->boolean('is_manual')->default(true); // true jika custom/manual, false jika generated dari receiving otomatis
            $table->timestamps();

            $table->foreign('creditor_code')->references('code')->on('creditors')->onDelete('cascade');
            $table->foreign('pharmacy_id')->references('id')->on('pharmacies')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_return_id')->index();
            $table->unsignedBigInteger('medicine_id')->nullable()->index();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->string('medicine_name'); // Nama obat
            $table->string('unit')->nullable(); // Satuan (BOX, BTL, TUBE, PCS, STRIP)
            $table->string('batch_number')->nullable(); // Nomor batch
            $table->date('expired_date')->nullable(); // Expired Date (ED)
            $table->decimal('qty', 12, 2)->default(0); // Jumlah retur
            $table->decimal('unit_price', 18, 2)->default(0); // Harga satuan
            $table->decimal('subtotal', 18, 2)->default(0); // Total harga per item
            $table->string('reason')->nullable(); // ED DEKAT, SALAH PESAN, DISKON TIDAK SESUAI, dll.
            $table->string('pickup_status')->nullable(); // 'sudah_diambil', 'belum_diambil'
            $table->date('pickup_date')->nullable();
            $table->string('deduction_note')->nullable(); // misal "SUDAH POTONG TAGIHAN (20/7/2026)"
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('purchase_return_id')->references('id')->on('purchase_returns')->onDelete('cascade');
            $table->foreign('medicine_id')->references('id')->on('medicines')->onDelete('set null');
            $table->foreign('batch_id')->references('id')->on('batches')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};
