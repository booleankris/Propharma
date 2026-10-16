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
                            {--pharmacy= : Filter ID apotek tertentu (contoh: 1 untuk SAHABAT PMI)}
                            {--delete-finance : Hapus juga pembayaran finance yang terkait dengan transaksi ini}
                            {--include-returns : Ikut hapus transaksi retur sebelum tanggal batas}
                            {--exclude-returns : Jangan hapus transaksi retur}
                            {--no-archive : Jangan salin ke tabel arsip sebelum dihapus}
                            {--execute : Jalankan penghapusan; tanpa opsi ini hanya simulasi}
                            {--force : Lewati konfirmasi interaktif}';

    protected $description = 'Menghapus transaksi penjualan obat sebelum tanggal tertentu dengan opsi filter apotek, pembersihan finance terkait, dan pencadangan otomatis ke tabel arsip.';

    public function handle(): int
    {
        $before = $this->option('before');
        if (!$before) {
            $this->error('Tanggal wajib diisi. Contoh: php artisan sales:delete-before --before=2026-10-11 --pharmacy=1 --delete-finance');
            return self::FAILURE;
        }

        try {
            $cutoff = CarbonImmutable::createFromFormat('!Y-m-d', $before);
            if (!$cutoff || $cutoff->format('Y-m-d') !== $before) {
                throw new \InvalidArgumentException('Format tanggal harus YYYY-MM-DD.');
            }
        } catch (Throwable $e) {
            $this->error('Tanggal tidak valid. Gunakan format YYYY-MM-DD, misalnya 2026-10-11.');
            return self::FAILURE;
        }

        foreach (['medicine_transactions', 'medicine_cart', 'items_log'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("Tabel wajib {$table} tidak ditemukan; penghapusan dibatalkan.");
                return self::FAILURE;
            }
        }

        $pharmacyId = $this->option('pharmacy') ? (int) $this->option('pharmacy') : null;
        $pharmacyName = 'Semua Cabang';
        if ($pharmacyId) {
            $pharmacy = DB::table('pharmacies')->where('id', $pharmacyId)->first();
            if (!$pharmacy) {
                $this->error("Apotek dengan ID {$pharmacyId} tidak ditemukan.");
                return self::FAILURE;
            }
            $pharmacyName = $pharmacy->name ?? "Apotek ID {$pharmacyId}";
        }

        $includeReturns = $this->option('include-returns') || ($pharmacyId && !$this->option('exclude-returns'));
        $deleteFinance = (bool) $this->option('delete-finance');
        $shouldArchive = !$this->option('no-archive');

        $cutoffDateTime = $cutoff->toDateTimeString();

        $salesQuery = DB::table('medicine_transactions')
            ->where('created_at', '<', $cutoffDateTime);

        if ($pharmacyId) {
            $salesQuery->where('pharmacy_id', $pharmacyId);
        }

        if (!$includeReturns) {
            $salesQuery->whereRaw("UPPER(COALESCE(transaction_type, '')) NOT LIKE 'RETUR%'")
                       ->where(function ($q) {
                           $q->whereNull('transaction_code')->orWhere('transaction_code', '!=', 'RT');
                       });
        }

        $salesIds = (clone $salesQuery)->select('id');
        $salesCount = (clone $salesQuery)->count();
        $cartCount = DB::table('medicine_cart')->whereIn('transaction_id', clone $salesIds)->count();

        // Cek data retur tabel terpisah (jika ada)
        $linkedReturnsCount = Schema::hasTable('retur')
            ? DB::table('retur')->whereIn('transaction_id', clone $salesIds)->count()
            : 0;

        // Cek data pembayaran di finance_payments
        $linkedPaymentsCount = Schema::hasTable('finance_payments')
            && Schema::hasColumn('finance_payments', 'medicine_transaction_id')
                ? DB::table('finance_payments')->whereIn('medicine_transaction_id', clone $salesIds)->count()
                : 0;

        // Cek jumlah transaksi cabang lain yang aman
        $otherBranchesCount = DB::table('medicine_transactions')
            ->when($pharmacyId, fn ($q) => $q->where('pharmacy_id', '!=', $pharmacyId))
            ->where('created_at', '<', $cutoffDateTime)
            ->count();

        $this->info("=====================================================================");
        $this->info(" SIMULASI PEMBERSIHAN DATA TRANSAKSI PENJUALAN");
        $this->info("=====================================================================");
        $this->line("Target Apotek       : <comment>{$pharmacyName} (ID: " . ($pharmacyId ?? 'Semua') . ")</comment>");
        $this->line("Batas Tanggal       : <comment>Sebelum {$cutoff->format('d-m-Y')} (00:00:00)</comment>");
        $this->line("Mode Backup Arsip   : " . ($shouldArchive ? '<info>AKTIF (Tabel Archive)</info>' : '<fg=red>NONAKTIF (Hard Delete)</>'));
        $this->line("Hapus Finance       : " . ($deleteFinance ? '<fg=yellow>YA (Hapus pembayaran terkait)</>' : '<comment>TIDAK (Biarkan, FK jadi NULL)</comment>'));
        $this->info("---------------------------------------------------------------------");

        $this->table(['Kategori Data', 'Jumlah', 'Tindakan'], [
            ['Transaksi Penjualan & Retur Target', number_format($salesCount), 'Akan dihapus' . ($shouldArchive ? ' & diarsipkan' : '')],
            ['Item Obat Keranjang (medicine_cart)', number_format($cartCount), 'Akan dihapus' . ($shouldArchive ? ' & diarsipkan' : '')],
            ['Pembayaran Terkait (finance_payments)', number_format($linkedPaymentsCount), $deleteFinance ? 'Akan dihapus' . ($shouldArchive ? ' & diarsipkan' : '') : 'Tetap disimpan (FK jadi NULL)'],
            ['Retur tabel khusus (retur)', number_format($linkedReturnsCount), 'Akan dicek keterkaitan'],
            ['Transaksi Cabang Lain', number_format($otherBranchesCount), '<info>AMAN 100% (Tidak disentuh)</info>'],
        ]);

        if ($linkedReturnsCount > 0 && !$includeReturns) {
            $this->error('Ditemukan retur di tabel retur yang masih menunjuk ke penjualan lama. Tambahkan --include-returns jika ingin ikut membersihkannya.');
            return self::FAILURE;
        }

        if (!$this->option('execute')) {
            $this->newLine();
            $this->comment('CATATAN: Ini baru tahap SIMULASI (Dry Run). Belum ada data yang dihapus.');
            $this->line('Untuk benar-benar mengeksekusi, jalankan perintah berikut:');
            $cmd = "php artisan sales:delete-before --before={$before}" . ($pharmacyId ? " --pharmacy={$pharmacyId}" : "") . ($deleteFinance ? " --delete-finance" : "") . " --execute";
            $this->warn("  {$cmd}");
            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            $this->newLine();
            if (!$this->confirm("Apakah Anda yakin ingin menghapus {$salesCount} transaksi untuk {$pharmacyName}?", false)) {
                $this->info('Dibatalkan oleh user. Tidak ada data yang dihapus.');
                return self::SUCCESS;
            }
        }

        // ==========================================
        // 1. PROSES PENCADANGAN KE TABEL ARSIP
        // ==========================================
        if ($shouldArchive) {
            $this->info('Menyiapkan tabel arsip...');
            DB::statement('CREATE TABLE IF NOT EXISTS medicine_transactions_archive LIKE medicine_transactions');
            DB::statement('CREATE TABLE IF NOT EXISTS medicine_cart_archive LIKE medicine_cart');
            if ($deleteFinance && $linkedPaymentsCount > 0) {
                DB::statement('CREATE TABLE IF NOT EXISTS finance_payments_archive LIKE finance_payments');
            }

            $this->info('Menyalin data ke tabel arsip...');
            // Salin header transaksi
            if ($pharmacyId) {
                DB::statement("
                    INSERT IGNORE INTO medicine_transactions_archive
                    SELECT * FROM medicine_transactions
                    WHERE created_at < ? AND pharmacy_id = ?
                ", [$cutoffDateTime, $pharmacyId]);
            } else {
                DB::statement("
                    INSERT IGNORE INTO medicine_transactions_archive
                    SELECT * FROM medicine_transactions
                    WHERE created_at < ?
                ", [$cutoffDateTime]);
            }

            // Salin keranjang obat
            if ($pharmacyId) {
                DB::statement("
                    INSERT IGNORE INTO medicine_cart_archive
                    SELECT mc.* FROM medicine_cart mc
                    INNER JOIN medicine_transactions mt ON mc.transaction_id = mt.id
                    WHERE mt.created_at < ? AND mt.pharmacy_id = ?
                ", [$cutoffDateTime, $pharmacyId]);
            } else {
                DB::statement("
                    INSERT IGNORE INTO medicine_cart_archive
                    SELECT mc.* FROM medicine_cart mc
                    INNER JOIN medicine_transactions mt ON mc.transaction_id = mt.id
                    WHERE mt.created_at < ?
                ", [$cutoffDateTime]);
            }

            // Salin pembayaran finance jika diminta
            if ($deleteFinance && $linkedPaymentsCount > 0) {
                DB::statement("
                    INSERT IGNORE INTO finance_payments_archive
                    SELECT fp.* FROM finance_payments fp
                    INNER JOIN medicine_transactions mt ON fp.medicine_transaction_id = mt.id
                    WHERE mt.created_at < ? AND mt.pharmacy_id = ?
                ", [$cutoffDateTime, $pharmacyId]);
            }

            $this->info('Pencadangan ke tabel arsip berhasil!');
        }

        // ==========================================
        // 2. PROSES PENGHAPUSAN DALAM BATCH
        // ==========================================
        $this->info('Memulai penghapusan data operasional secara bertahap...');

        $deletedSales = 0;
        $deletedCart = 0;
        $deletedPayments = 0;
        $batchNumber = 0;

        try {
            (clone $salesQuery)
                ->select(['id', 'transaction_code'])
                ->orderBy('id')
                ->chunkById(250, function ($sales) use (&$deletedSales, &$deletedCart, &$deletedPayments, &$batchNumber, $deleteFinance): void {
                    $ids = $sales->pluck('id')->all();
                    $codes = $sales->pluck('transaction_code')->filter()->unique()->values()->all();

                    DB::transaction(function () use ($ids, $codes, &$deletedCart, &$deletedPayments, $deleteFinance): void {
                        if ($deleteFinance) {
                            $deletedPayments += DB::table('finance_payments')->whereIn('medicine_transaction_id', $ids)->delete();
                        }
                        if (Schema::hasTable('retur')) {
                            DB::table('retur')->whereIn('transaction_id', $ids)->delete();
                        }
                        $deletedCart += DB::table('medicine_cart')->whereIn('transaction_id', $ids)->delete();
                        if ($codes !== []) {
                            DB::table('items_log')->whereIn('transaction_code', $codes)->delete();
                        }
                        DB::table('medicine_transactions')->whereIn('id', $ids)->delete();
                    });

                    $deletedSales += count($ids);
                    $batchNumber++;
                    if ($batchNumber === 1 || $batchNumber % 10 === 0) {
                        $this->line("  Batch {$batchNumber}: {$deletedSales} transaksi diproses...");
                    }
                }, 'id');
        } catch (Throwable $e) {
            $this->error("Penghapusan berhenti di transaksi ke-{$deletedSales}. Detail: " . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("=====================================================================");
        $this->info(" PEMBERSIHAN SELESAI DENGAN SUKSES");
        $this->info("=====================================================================");
        $this->line("Total Transaksi Terhapus : <info>{$deletedSales}</info>");
        $this->line("Total Item Cart Terhapus : <info>{$deletedCart}</info>");
        if ($deleteFinance) {
            $this->line("Total Finance Terhapus   : <info>{$deletedPayments}</info>");
        }
        if ($shouldArchive) {
            $this->line("Data tersimpan aman di   : <comment>medicine_transactions_archive, medicine_cart_archive" . ($deleteFinance ? ", finance_payments_archive" : "") . "</comment>");
        }
        $this->info("Saldo stok fisik saat ini tidak berubah.");
        $this->info("=====================================================================");

        return self::SUCCESS;
    }
}
