<?php

namespace App\Console\Commands;

use App\Support\DoctorNameMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Gabungkan data dokter duplikat.
 *
 * DEFAULT = DRY RUN (tidak mengubah apa pun, hanya membuat laporan CSV).
 *
 *   php artisan doctors:merge-duplicates                 # laporan saja
 *   php artisan doctors:merge-duplicates --fuzzy         # laporan + kandidat typo
 *   php artisan doctors:merge-duplicates --execute       # gabung grup EXACT
 *   php artisan doctors:merge-duplicates --execute --fuzzy --skip=123 --skip=456
 *   php artisan doctors:merge-duplicates --rollback=doctor-merge/backup-XXXX.json
 */
class MergeDuplicateDoctors extends Command
{
    protected $signature = 'doctors:merge-duplicates
        {--execute : Jalankan penggabungan (tanpa ini hanya dry-run)}
        {--fuzzy : Ikutkan grup mirip/typo (FUZZY) — review laporan dulu!}
        {--force : Lewati konfirmasi interaktif (langsung eksekusi)}
        {--skip=* : ID dokter utama (canonical) dari grup yang TIDAK boleh digabung}
        {--rollback= : Path file backup JSON (relatif storage/app) untuk membatalkan merge}';

    protected $description = 'Deteksi & gabungkan dokter duplikat (beda spasi, tanda baca, huruf besar/kecil, typo)';

    private const FILL_FIELDS = ['specialist', 'address', 'city', 'phone', 'practice'];

    public function handle(): int
    {
        if ($rollback = $this->option('rollback')) {
            return $this->rollback($rollback);
        }

        $groups = $this->buildGroups((bool) $this->option('fuzzy'));
        $skip = array_map('intval', (array) $this->option('skip'));

        $reportPath = $this->writeReport($groups, $skip);
        $this->printSummary($groups, $skip);
        $this->info("Laporan lengkap: storage/app/{$reportPath}");

        if (!$this->option('execute')) {
            $this->warn('DRY RUN — tidak ada data yang diubah. Tambahkan --execute untuk menjalankan.');
            return self::SUCCESS;
        }

        $toMerge = array_filter($groups, fn ($g) =>
            $g['type'] !== 'CONFLICT' && !in_array($g['canonical']->id, $skip, true));

        if (empty($toMerge)) {
            $this->info('Tidak ada grup untuk digabung.');
            return self::SUCCESS;
        }

        $dupCount = array_sum(array_map(fn ($g) => count($g['members']) - 1, $toMerge));
        if (!$this->option('force') && !$this->confirm("Gabungkan {$dupCount} dokter duplikat ke dalam " . count($toMerge) . ' dokter utama?')) {
            $this->warn('Dibatalkan.');
            return self::SUCCESS;
        }

        return $this->merge($toMerge);
    }

    /**
     * Bangun grup duplikat: EXACT (strictKey sama) lalu opsional FUZZY.
     */
    private function buildGroups(bool $withFuzzy): array
    {
        $txCounts = DB::table('medicine_transactions')
            ->whereNotNull('doctor_id')
            ->selectRaw('doctor_id, COUNT(*) as c')
            ->groupBy('doctor_id')
            ->pluck('c', 'doctor_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $doctors = DB::table('doctors')->orderBy('id')->get()
            ->each(fn ($d) => $d->tx_count = $txCounts[$d->id] ?? 0)
            ->reject(fn ($d) => DoctorNameMatcher::isPlaceholder($d->name));

        // 1) Kelompok EXACT berdasarkan strictKey
        $buckets = [];
        foreach ($doctors as $d) {
            $buckets[DoctorNameMatcher::strictKey($d->name)][] = $d;
        }
        $keys = array_keys($buckets);

        // 2) Union-find antar bucket untuk FUZZY
        $parent = array_combine($keys, $keys);
        $find = function ($k) use (&$parent, &$find) {
            return $parent[$k] === $k ? $k : ($parent[$k] = $find($parent[$k]));
        };

        $parsed = [];
        foreach ($keys as $k) {
            $parsed[$k] = DoctorNameMatcher::parse($buckets[$k][0]->name);
        }

        if ($withFuzzy) {
            $n = count($keys);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    if (DoctorNameMatcher::isFuzzyMatch($parsed[$keys[$i]], $parsed[$keys[$j]])) {
                        $parent[$find($keys[$i])] = $find($keys[$j]);
                    }
                }
            }
        }

        $clusters = [];
        foreach ($keys as $k) {
            $clusters[$find($k)][] = $k;
        }

        $groups = [];
        foreach ($clusters as $bucketKeys) {
            $members = array_merge(...array_map(fn ($k) => $buckets[$k], $bucketKeys));
            if (count($members) < 2) {
                continue;
            }

            // Canonical: transaksi terbanyak, lalu ID terkecil
            usort($members, fn ($a, $b) => [$b->tx_count, $a->id] <=> [$a->tx_count, $b->id]);

            $type = count($bucketKeys) > 1 ? 'FUZZY' : 'EXACT';

            // Konflik: lebih dari satu gelar / spesialis berbeda dalam satu cluster
            if ($type === 'FUZZY') {
                $titles = array_unique(array_filter(array_map(fn ($k) => $parsed[$k]['titles'], $bucketKeys)));
                $specs  = array_unique(array_filter(array_map(fn ($k) => $parsed[$k]['spec'], $bucketKeys)));
                if (count($titles) > 1 || count($specs) > 1) {
                    $type = 'CONFLICT';
                }
            }

            $groups[] = [
                'type'      => $type,
                'canonical' => $members[0],
                'members'   => $members,
            ];
        }

        usort($groups, fn ($a, $b) => count($b['members']) <=> count($a['members']));

        return $groups;
    }

    private function writeReport(array $groups, array $skip): string
    {
        $path = 'doctor-merge/report-' . now()->format('Ymd_His') . '.csv';
        $rows = [['canonical_id', 'tipe', 'aksi', 'doctor_id', 'kode', 'nama', 'jumlah_transaksi']];

        foreach ($groups as $g) {
            $cid = $g['canonical']->id;
            $groupAction = $g['type'] === 'CONFLICT' ? 'TIDAK DIGABUNG (konflik gelar/spesialis)'
                : (in_array($cid, $skip, true) ? 'DILEWATI (--skip)' : 'GABUNG');

            foreach ($g['members'] as $m) {
                $rows[] = [
                    $cid,
                    $g['type'],
                    $m->id === $cid ? 'UTAMA' : $groupAction,
                    $m->id,
                    $m->code,
                    $m->name,
                    $m->tx_count,
                ];
            }
        }

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        rewind($fh);
        Storage::put($path, stream_get_contents($fh));
        fclose($fh);

        return $path;
    }

    private function printSummary(array $groups, array $skip): void
    {
        $by = ['EXACT' => [0, 0], 'FUZZY' => [0, 0], 'CONFLICT' => [0, 0]];
        foreach ($groups as $g) {
            $by[$g['type']][0]++;
            $by[$g['type']][1] += count($g['members']) - 1;
        }

        $this->table(['Tipe', 'Grup', 'Duplikat akan dihapus', 'Keterangan'], [
            ['EXACT', $by['EXACT'][0], $by['EXACT'][1], 'Beda spasi/tanda baca/huruf saja — aman'],
            ['FUZZY', $by['FUZZY'][0], $by['FUZZY'][1], 'Kemungkinan typo — WAJIB review'],
            ['CONFLICT', $by['CONFLICT'][0], 0, 'Mirip tapi gelar/spesialis beda — tidak digabung'],
        ]);

        $preview = array_slice($groups, 0, 25);
        $this->table(['Utama (ID)', 'Tipe', 'Nama utama', 'Jml data', 'Variasi penulisan'], array_map(function ($g) use ($skip) {
            $variants = array_unique(array_map(fn ($m) => trim($m->name), $g['members']));
            return [
                $g['canonical']->id . (in_array($g['canonical']->id, $skip, true) ? ' (skip)' : ''),
                $g['type'],
                $g['canonical']->name,
                count($g['members']),
                mb_strimwidth(implode(' | ', $variants), 0, 90, '…'),
            ];
        }, $preview));
    }

    private function merge(array $groups): int
    {
        $backup = ['created_at' => now()->toDateTimeString(), 'groups' => []];

        try {
            DB::transaction(function () use ($groups, &$backup) {
                foreach ($groups as $g) {
                    $canonical = $g['canonical'];
                    $dupIds = array_values(array_map(fn ($m) => $m->id,
                        array_filter($g['members'], fn ($m) => $m->id !== $canonical->id)));

                    $txRemap = DB::table('medicine_transactions')
                        ->whereIn('doctor_id', $dupIds)
                        ->get(['id', 'doctor_id'])
                        ->map(fn ($r) => [$r->id, $r->doctor_id])
                        ->all();

                    // Lengkapi kolom kosong di dokter utama dari duplikatnya
                    $original = (array) DB::table('doctors')->where('id', $canonical->id)->first();
                    $fill = [];
                    foreach (self::FILL_FIELDS as $f) {
                        if (!array_key_exists($f, $original) || !$this->isBlank($original[$f])) {
                            continue;
                        }
                        foreach ($g['members'] as $m) {
                            if ($m->id !== $canonical->id && !$this->isBlank($m->$f ?? null)) {
                                $fill[$f] = $m->$f;
                                break;
                            }
                        }
                    }

                    $backup['groups'][] = [
                        'canonical_id'       => $canonical->id,
                        'canonical_original' => array_intersect_key($original, array_flip(self::FILL_FIELDS)),
                        'deleted_doctors'    => DB::table('doctors')->whereIn('id', $dupIds)->get()->map(fn ($r) => (array) $r)->all(),
                        'transaction_remap'  => $txRemap,
                    ];

                    // PENTING: pindahkan transaksi DULU. FK doctor_id memakai
                    // ON DELETE CASCADE — menghapus dokter yang masih dirujuk
                    // akan ikut menghapus transaksinya.
                    DB::table('medicine_transactions')
                        ->whereIn('doctor_id', $dupIds)
                        ->update(['doctor_id' => $canonical->id]);

                    if (!empty($fill)) {
                        DB::table('doctors')->where('id', $canonical->id)->update($fill);
                    }

                    $stillReferenced = DB::table('medicine_transactions')->whereIn('doctor_id', $dupIds)->count();
                    if ($stillReferenced > 0) {
                        throw new \RuntimeException("Masih ada {$stillReferenced} transaksi merujuk duplikat grup #{$canonical->id}. Dibatalkan.");
                    }

                    DB::table('doctors')->whereIn('id', $dupIds)->delete();
                }

                // Simpan backup sebelum commit — jika gagal tulis, seluruh merge di-rollback
                $path = 'doctor-merge/backup-' . now()->format('Ymd_His') . '.json';
                if (!Storage::put($path, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                    throw new \RuntimeException('Gagal menulis file backup. Dibatalkan.');
                }
                $backup['path'] = $path;
            });
        } catch (\Throwable $e) {
            $this->error('GAGAL, semua perubahan dibatalkan: ' . $e->getMessage());
            return self::FAILURE;
        }

        $deleted = array_sum(array_map(fn ($g) => count($g['deleted_doctors']), $backup['groups']));
        $moved = array_sum(array_map(fn ($g) => count($g['transaction_remap']), $backup['groups']));

        $this->info("Selesai: {$deleted} dokter duplikat digabung, {$moved} transaksi dipindahkan.");
        $this->info("Backup: storage/app/{$backup['path']}");
        $this->line("Batalkan dengan: php artisan doctors:merge-duplicates --rollback={$backup['path']}");

        return self::SUCCESS;
    }

    private function rollback(string $path): int
    {
        if (!Storage::exists($path)) {
            $this->error("File tidak ditemukan: storage/app/{$path}");
            return self::FAILURE;
        }

        $backup = json_decode(Storage::get($path), true);
        if (!$this->confirm('Kembalikan ' . count($backup['groups'] ?? []) . " grup dari backup {$backup['created_at']}?")) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($backup) {
            foreach ($backup['groups'] as $g) {
                foreach ($g['deleted_doctors'] as $row) {
                    DB::table('doctors')->insertOrIgnore($row);
                }
                foreach ($g['transaction_remap'] as [$txId, $oldDoctorId]) {
                    DB::table('medicine_transactions')->where('id', $txId)->update(['doctor_id' => $oldDoctorId]);
                }
                if (!empty($g['canonical_original'])) {
                    DB::table('doctors')->where('id', $g['canonical_id'])->update($g['canonical_original']);
                }
            }
        });

        $this->info('Rollback selesai.');
        return self::SUCCESS;
    }

    private function isBlank($v): bool
    {
        return in_array(trim((string) $v), ['', '-', '0'], true);
    }
}
