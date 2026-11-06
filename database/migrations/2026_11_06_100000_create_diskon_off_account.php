<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Membuat akun khusus "Diskon Off" dengan kategori "Diskon Lain-lain".
     * Akun ini dipakai oleh fitur Finance > Diskon untuk mencatat diskon
     * tambahan (discount off) yang diterima saat pembelian baru, dan menjadi
     * sumber data kolom "DISKON OFF" pada laporan Kinerja Omzet.
     */
    public function up()
    {
        $exists = DB::table('finance_accounts')
            ->where('name', 'Diskon Off')
            ->where('category', 'Diskon Lain-lain')
            ->exists();

        if (!$exists) {
            DB::table('finance_accounts')->insert([
                'code' => '8-801',
                'name' => 'Diskon Off',
                'name_en' => 'Discount Off',
                'category' => 'Diskon Lain-lain',
                'account_number' => null,
                'balance' => 0,
                'is_active' => true,
                'account_type' => 'bank',
                'pharmacy_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::table('finance_accounts')
            ->where('name', 'Diskon Off')
            ->where('category', 'Diskon Lain-lain')
            ->whereNull('pharmacy_id')
            ->delete();
    }
};
