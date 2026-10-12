<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DeleteSalesBeforeDate extends Command
{
    protected $signature = 'sales:delete-before
                            {--before= : Hapus penjualan sebelum tanggal ini (YYYY-MM-DD), eksklusif}
                            {--execute : Jalankan penghapusan; tanpa opsi ini hanya simulasi}
                            {--force : Lewati konfirmasi setelah simulasi dihitung}';

    protected $description = 'Menghapus transaksi penjualan obat sebelum tanggal tertentu tanpa mengubah saldo stok.';

    public function handle(): int
    {
        $before = $this->option('before');
        if (!$before) {
            $this->error('Tanggal wajib diisi. Contoh: php artisan sales:delete-before --before=2026-09-13');
            return self::FAILURE;
        }

        try {
            $cutoff = CarbonImmutable::createFromFormat('!Y-m-d', $before);
            if (!$cutoff || $cutoff->format('Y-m-d') !== $before) {
                throw new \InvalidArgumentException('Format tanggal harus YYYY-MM-DD.');
            }
        } catch (Throwable $e) {
            $this->error('Tanggal tidak valid. Gunakan format YYYY-MM-DD, misalnya 2026-09-13.');
            return self::FAILURE;
        }

        foreach (['medicine_transactions', 'medicine_cart', 'items_log', 'retur'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("Tabel wajib {$table} tidak ditemukan; penghapusan dibatalkan.");
                return self::FAILURE;
            }
        }

        $cutoffDateTime = $cutoff->toDateTimeString();
        $salesQuery = DB::table('medicine_transactions')
            ->where('created_at', '<', $cutoffDateTime)
            ->whereRaw("UPPER(COALESCE(transaction_type, '')) NOT LIKE 'RETUR%'");
        $salesIds = (clone $salesQuery)->select('id');

        $salesCount = (clone $salesQuery)->count();
        $cartCount = DB::table('medicine_cart')->whereIn('transaction_id', clone $salesIds)->count();
        $linkedReturnsCount = DB::table('retur')->whereIn('transaction_id', clone $salesIds)->count();
        $linkedPaymentsCount = Schema::hasTable('finance_payments')
            && Schema::hasColumn('finance_payments', 'medicine_transaction_id')
                ? DB::table('finance_payments')->whereIn('medicine_transaction_id', clone $salesIds)->count()
                : 0;

        $this->info('Batas tanggal: sebelum ' . $cutoff->format('d-m-Y') . ' (transaksi tanggal 13 September tidak ikut).');
        $this->table(['Data', 'Jumlah'], [
            ['Transaksi penjualan obat', $salesCount],
            ['Baris obat (medicine_cart)', $cartCount],
            ['Log stok terkait penjualan', 'Ikut dihapus'],
            ['Retur yang masih terkait', $linkedReturnsCount],
            ['Pembayaran piutang terkait yang akan tetap disimpan', $linkedPaymentsCount],
        ]);
        $this->warn('Stok batch dan stok master obat tidak diubah.');

        if ($linkedReturnsCount > 0) {
            $this->error('Ditemukan retur yang masih menunjuk ke penjualan lama. Tidak ada data yang dihapus agar relasi retur tetap utuh.');
            return self::FAILURE;
        }

        if (!$this->option('execute')) {
            $this->comment('Simulasi saja. Untuk benar-benar menghapus, tambahkan --execute.');
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('Hapus transaksi penjualan dan log terkait sesuai jumlah di atas?', false)) {
            $this->info('Dibatalkan. Tidak ada data yang dihapus.');
            return self::SUCCESS;
        }

        $deletedSales = 0;
        $batchNumber = 0;
        try {
            (clone $salesQuery)
                ->select(['id', 'transaction_code'])
                ->orderBy('id')
                ->chunkById(100, function ($sales) use (&$deletedSales, &$batchNumber): void {
                    $ids = $sales->pluck('id')->all();
                    $codes = $sales->pluck('transaction_code')->filter()->unique()->values()->all();

                    DB::transaction(function () use ($ids, $codes): void {
                        DB::table('medicine_cart')->whereIn('transaction_id', $ids)->delete();
                        if ($codes !== []) {
                            DB::table('items_log')->whereIn('transaction_code', $codes)->delete();
                        }
                        DB::table('medicine_transactions')->whereIn('id', $ids)->delete();
                    });

                    $deletedSales += count($ids);
                    $batchNumber++;
                    if ($batchNumber === 1 || $batchNumber % 10 === 0) {
                        $this->line("Batch {$batchNumber}: {$deletedSales} transaksi diproses.");
                    }
                }, 'id');
        } catch (Throwable $e) {
            $this->error("Penghapusan berhenti setelah {$deletedSales} transaksi terhapus. Batch yang belum selesai dibatalkan; jalankan ulang command untuk melanjutkan. Detail: " . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Selesai. {$deletedSales} transaksi penjualan lama dihapus; saldo stok tetap seperti semula.");
        if ($linkedPaymentsCount > 0) {
            $this->comment('Pembayaran terkait tetap disimpan; relasi medicine_transaction_id akan menjadi NULL sesuai aturan database.');
        }

        return self::SUCCESS;
    }
}
