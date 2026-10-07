<?php

namespace App\Console\Commands;

use App\Support\DoctorNameMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AuditDoctors extends Command
{
    /**
     * Nama dan deskripsi signature artisan command.
     */
    protected $signature = 'doctors:audit
        {--duplicates-only : Hanya tampilkan dokter yang terindikasi masih memiliki duplikat}
        {--search= : Filter nama atau kode dokter}
        {--limit=50 : Jumlah maksimum baris yang ditampilkan di terminal (0 untuk tampilkan semua)}
        {--export : Ekspor laporan audit lengkap ke file CSV di storage/app}';

    protected $description = 'Tampilkan semua data dokter yang sudah bersih & audit potensi duplikat yang masih tersisa';

    public function handle(): int
    {
        $this->info('🔍 Memulai Audit Data Dokter...');

        // 1. Ambil data transaksi per dokter
        $txCounts = DB::table('medicine_transactions')
            ->whereNotNull('doctor_id')
            ->selectRaw('doctor_id, COUNT(*) as c')
            ->groupBy('doctor_id')
            ->pluck('c', 'doctor_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        // 2. Ambil semua dokter
        $search = trim((string) $this->option('search'));
        $query = DB::table('doctors')->orderBy('id', 'asc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('specialist', 'like', "%{$search}%");
            });
        }

        $allDoctors = $query->get()->each(fn ($d) => $d->tx_count = $txCounts[$d->id] ?? 0);

        if ($allDoctors->isEmpty()) {
            $this->warn('Tidak ada data dokter yang ditemukan' . ($search !== '' ? " dengan kata kunci \"{$search}\"." : '.'));
            return self::SUCCESS;
        }

        // 3. Analisis Duplikat (EXACT & FUZZY)
        $strictBuckets = [];
        $parsedDocs = [];

        foreach ($allDoctors as $d) {
            $key = DoctorNameMatcher::strictKey($d->name);
            $strictBuckets[$key][] = $d->id;
            $parsedDocs[$d->id] = DoctorNameMatcher::parse($d->name);
        }

        // Map status per doctor ID
        $statusMap = [];
        $exactDupGroupCount = 0;
        $fuzzyDupGroupCount = 0;

        // Cek EXACT
        foreach ($strictBuckets as $key => $docIds) {
            if (count($docIds) > 1) {
                $exactDupGroupCount++;
                foreach ($docIds as $id) {
                    $statusMap[$id] = 'EXACT DUP (Sama Persis)';
                }
            }
        }

        // Cek FUZZY antar strict buckets
        $bucketKeys = array_keys($strictBuckets);
        $n = count($bucketKeys);
        $fuzzyClusterMap = [];

        for ($i = 0; $i < $n; $i++) {
            $k1 = $bucketKeys[$i];
            $d1 = $strictBuckets[$k1][0];
            for ($j = $i + 1; $j < $n; $j++) {
                $k2 = $bucketKeys[$j];
                $d2 = $strictBuckets[$k2][0];

                if (DoctorNameMatcher::isFuzzyMatch($parsedDocs[$d1], $parsedDocs[$d2])) {
                    $fuzzyDupGroupCount++;
                    foreach ($strictBuckets[$k1] as $id) {
                        if (!isset($statusMap[$id])) {
                            $statusMap[$id] = 'FUZZY DUP (Mirip/Typo)';
                        }
                    }
                    foreach ($strictBuckets[$k2] as $id) {
                        if (!isset($statusMap[$id])) {
                            $statusMap[$id] = 'FUZZY DUP (Mirip/Typo)';
                        }
                    }
                }
            }
        }

        // Mark placeholder or clean
        foreach ($allDoctors as $d) {
            if (DoctorNameMatcher::isPlaceholder($d->name)) {
                $statusMap[$d->id] = 'PLACEHOLDER';
            } elseif (!isset($statusMap[$d->id])) {
                $statusMap[$d->id] = 'BERSIH (FIX)';
            }
        }

        // Filter jika option --duplicates-only aktif
        $filteredDoctors = $allDoctors;
        if ($this->option('duplicates-only')) {
            $filteredDoctors = $allDoctors->filter(fn ($d) => str_contains($statusMap[$d->id], 'DUP'));
        }

        // 4. Ringkasan & Statistik
        $totalAll = $allDoctors->count();
        $usedCount = $allDoctors->filter(fn ($d) => $d->tx_count > 0)->count();
        $totalTxSum = array_sum($txCounts);
        $cleanCount = $allDoctors->filter(fn ($d) => $statusMap[$d->id] === 'BERSIH (FIX)')->count();
        $exactDupDocs = $allDoctors->filter(fn ($d) => str_contains($statusMap[$d->id], 'EXACT DUP'))->count();
        $fuzzyDupDocs = $allDoctors->filter(fn ($d) => str_contains($statusMap[$d->id], 'FUZZY DUP'))->count();

        $this->newLine();
        $this->line('<fg=cyan;options=bold>===============================================================</>');
        $this->line('<fg=cyan;options=bold>                HASIL AUDIT DATA DOKTER                       </>');
        $this->line('<fg=cyan;options=bold>===============================================================</>');
        $this->line(" 📊 Total Dokter Terdaftar     : <fg=yellow;options=bold>{$totalAll}</>");
        $this->line(" ⚕️  Dokter Memiliki Transaksi  : <fg=green;options=bold>{$usedCount}</> (Total: {$totalTxSum} transaksi)");
        $this->line(" 🧹 Dokter Berkas Bersih (Fix) : <fg=green;options=bold>{$cleanCount}</> (" . round(($cleanCount / max(1, $totalAll)) * 100, 1) . "%)");
        $this->line(" ⚠️  Potensi Duplikat EXACT    : <fg=" . ($exactDupDocs > 0 ? "red" : "green") . ";options=bold>{$exactDupDocs}</> dokter dalam {$exactDupGroupCount} grup");
        $this->line(" ⚠️  Potensi Duplikat FUZZY    : <fg=" . ($fuzzyDupDocs > 0 ? "yellow" : "green") . ";options=bold>{$fuzzyDupDocs}</> dokter dalam {$fuzzyDupGroupCount} grup");
        $this->line('<fg=cyan;options=bold>===============================================================</>');

        if ($exactDupDocs === 0 && $fuzzyDupDocs === 0) {
            $this->info('🎉 SELAMAT! Seluruh data dokter sudah BESIBERSIH (100% BEBAS DUPLIKAT).');
        } else {
            $this->warn("💡 Gunakan perintah 'php artisan doctors:merge-duplicates --execute' untuk menggabungkan duplikat.");
        }

        // 5. Tampilkan Tabel Data Dokter
        $limitInput = strtolower((string) $this->option('limit'));
        $limit = ($limitInput === '0' || $limitInput === 'all') ? 999999 : (int) $limitInput;

        $rowsToDisplay = $filteredDoctors->take($limit);

        $tableData = $rowsToDisplay->map(function ($d) use ($statusMap) {
            $status = $statusMap[$d->id];
            $statusFormatted = match (true) {
                str_contains($status, 'BERSIH') => "<fg=green>{$status}</>",
                str_contains($status, 'EXACT')  => "<fg=red;options=bold>{$status}</>",
                str_contains($status, 'FUZZY')  => "<fg=yellow;options=bold>{$status}</>",
                default                         => "<fg=gray>{$status}</>",
            };

            return [
                $d->id,
                $d->code,
                $d->name,
                $d->specialist ?? '-',
                DoctorNameMatcher::strictKey($d->name),
                $d->tx_count,
                $statusFormatted,
            ];
        })->all();

        $this->newLine();
        $this->table(
            ['ID', 'Kode', 'Nama Dokter', 'Spesialis', 'Kunci Normalisasi (Strict Key)', 'Jml Transaksi', 'Status Audit'],
            $tableData
        );

        if ($filteredDoctors->count() > $rowsToDisplay->count()) {
            $remaining = $filteredDoctors->count() - $rowsToDisplay->count();
            $this->comment("... dan {$remaining} dokter lainnya. (Gunakan --limit=all untuk menampilkan seluruhnya).");
        }

        // 6. Ekspor ke CSV
        $reportPath = $this->exportCsv($allDoctors, $statusMap);
        $this->info("📁 Laporan Audit CSV Tersimpan : storage/app/{$reportPath}");

        return self::SUCCESS;
    }

    private function exportCsv($doctors, array $statusMap): string
    {
        $path = 'doctor-audit/audit-' . now()->format('Ymd_His') . '.csv';
        $rows = [['id', 'code', 'name', 'specialist', 'address', 'city', 'phone', 'strict_key', 'tx_count', 'status_audit']];

        foreach ($doctors as $d) {
            $rows[] = [
                $d->id,
                $d->code,
                $d->name,
                $d->specialist ?? '',
                $d->address ?? '',
                $d->city ?? '',
                $d->phone ?? '',
                DoctorNameMatcher::strictKey($d->name),
                $d->tx_count,
                $statusMap[$d->id] ?? 'UNKNOWN',
            ];
        }

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF"); // UTF-8 BOM untuk Excel
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        rewind($fh);
        Storage::put($path, stream_get_contents($fh));
        fclose($fh);

        return $path;
    }
}
