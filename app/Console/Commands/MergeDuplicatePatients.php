<?php

namespace App\Console\Commands;

use App\Support\PatientMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Gabungkan data pasien duplikat (nama dan nomor telepon yang sama).
 *
 * DEFAULT = DRY RUN (tidak mengubah apa pun, hanya membuat laporan CSV).
 *
 *   php artisan patients:merge-duplicates                 # laporan saja (dry-run)
 *   php artisan patients:merge-duplicates --fuzzy         # laporan + kandidat nama mirip
 *   php artisan patients:merge-duplicates --execute       # eksekusi penggabungan
 *   php artisan patients:merge-duplicates --execute --force
 *   php artisan patients:merge-duplicates --rollback=patient-merge/backup-XXXX.json
 */
class MergeDuplicatePatients extends Command
{
    protected $signature = 'patients:merge-duplicates
        {--execute : Jalankan penggabungan (tanpa ini hanya dry-run)}
        {--fuzzy : Ikutkan grup nama mirip/typo (FUZZY) dengan nomor telepon yang sama}
        {--force : Lewati konfirmasi interaktif (langsung eksekusi)}
        {--skip=* : ID pasien utama (canonical) dari grup yang TIDAK boleh digabung}
        {--rollback= : Path file backup JSON (relatif storage/app) untuk membatalkan merge}';

    protected $description = 'Deteksi & gabungkan pasien duplikat (nama dan nomor telepon yang sama)';

    private const FILL_FIELDS = ['code', 'address', 'city', 'phone', 'birth'];

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
        if (!$this->option('force') && !$this->confirm("Gabungkan {$dupCount} pasien duplikat ke dalam " . count($toMerge) . ' pasien utama?')) {
            $this->warn('Dibatalkan.');
            return self::SUCCESS;
        }

        return $this->merge($toMerge);
    }

    /**
     * Bangun grup duplikat: EXACT (nama & telepon sama persis) lalu opsional FUZZY.
     */
    private function buildGroups(bool $withFuzzy): array
    {
        // Hitung transaksi di medicine_transactions
        $txCounts = DB::table('medicine_transactions')
            ->whereNotNull('patient_id')
            ->selectRaw('patient_id, COUNT(*) as c')
            ->groupBy('patient_id')
            ->pluck('c', 'patient_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        // Hitung transaksi di medicine_transactions_archive jika ada
        if (Schema::hasTable('medicine_transactions_archive')) {
            $archCounts = DB::table('medicine_transactions_archive')
                ->whereNotNull('patient_id')
                ->selectRaw('patient_id, COUNT(*) as c')
                ->groupBy('patient_id')
                ->pluck('c', 'patient_id')
                ->map(fn ($v) => (int) $v)
                ->all();

            foreach ($archCounts as $pid => $c) {
                $txCounts[$pid] = ($txCounts[$pid] ?? 0) + $c;
            }
        }

        $patients = DB::table('patients')->orderBy('id')->get()
            ->each(fn ($p) => $p->tx_count = $txCounts[$p->id] ?? 0)
            ->reject(fn ($p) => PatientMatcher::isPlaceholderName($p->name));

        // 1) Kelompokkan berdasarkan exact strict key: nama & telepon
        $buckets = [];
        foreach ($patients as $p) {
            $nameKey = PatientMatcher::strictNameKey($p->name);
            $phoneKey = PatientMatcher::normalizePhone($p->phone ?? '');
            $key = $nameKey . '|' . $phoneKey;
            $buckets[$key][] = $p;
        }

        $keys = array_keys($buckets);

        // 2) Union-find antar bucket untuk FUZZY jika telepon sama dan nama mirip
        $parent = array_combine($keys, $keys);
        $find = function ($k) use (&$parent, &$find) {
            return $parent[$k] === $k ? $k : ($parent[$k] = $find($parent[$k]));
        };

        $parsed = [];
        $bucketPhone = [];
        foreach ($keys as $k) {
            $first = $buckets[$k][0];
            $parsed[$k] = PatientMatcher::parseName($first->name);
            $bucketPhone[$k] = PatientMatcher::normalizePhone($first->phone ?? '');
        }

        if ($withFuzzy) {
            $n = count($keys);
            for ($i = 0; $i < $n; $i++) {
                $phoneA = $bucketPhone[$keys[$i]];
                // Hanya lakukan fuzzy jika nomor telepon sama dan valid
                if (!PatientMatcher::isValidPhone($phoneA)) {
                    continue;
                }
                for ($j = $i + 1; $j < $n; $j++) {
                    $phoneB = $bucketPhone[$keys[$j]];
                    if ($phoneA === $phoneB) {
                        if (PatientMatcher::isFuzzyNameMatch($parsed[$keys[$i]], $parsed[$keys[$j]])) {
                            $parent[$find($keys[$i])] = $find($keys[$j]);
                        }
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
        $path = 'patient-merge/report-' . now()->format('Ymd_His') . '.csv';
        $rows = [['canonical_id', 'tipe', 'aksi', 'patient_id', 'kode', 'nama', 'telepon', 'alamat', 'jumlah_transaksi']];

        foreach ($groups as $g) {
            $cid = $g['canonical']->id;
            $groupAction = in_array($cid, $skip, true) ? 'DILEWATI (--skip)' : 'GABUNG';

            foreach ($g['members'] as $m) {
                $rows[] = [
                    $cid,
                    $g['type'],
                    $m->id === $cid ? 'UTAMA' : $groupAction,
                    $m->id,
                    $m->code,
                    $m->name,
                    $m->phone,
                    $m->address,
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
        $by = ['EXACT' => [0, 0], 'FUZZY' => [0, 0]];
        foreach ($groups as $g) {
            $type = $g['type'] ?? 'EXACT';
            if (!isset($by[$type])) {
                $by[$type] = [0, 0];
            }
            $by[$type][0]++;
            $by[$type][1] += count($g['members']) - 1;
        }

        $this->table(['Tipe', 'Grup', 'Duplikat akan dihapus', 'Keterangan'], [
            ['EXACT', $by['EXACT'][0], $by['EXACT'][1], 'Nama & nomor telepon identik — aman digabung'],
            ['FUZZY', $by['FUZZY'][0], $by['FUZZY'][1], 'Nomor telepon sama, nama sedikit beda/typo'],
        ]);

        $preview = array_slice($groups, 0, 20);
        $this->table(['Utama (ID)', 'Tipe', 'Nama Pasien', 'Telepon', 'Jml Duplikat', 'Contoh Variasi'], array_map(function ($g) use ($skip) {
            $variants = array_unique(array_map(fn ($m) => trim($m->name), $g['members']));
            return [
                $g['canonical']->id . (in_array($g['canonical']->id, $skip, true) ? ' (skip)' : ''),
                $g['type'],
                $g['canonical']->name,
                $g['canonical']->phone ?: '-',
                count($g['members']),
                mb_strimwidth(implode(' | ', $variants), 0, 60, '…'),
            ];
        }, $preview));
    }

    private function merge(array $groups): int
    {
        $backup = ['created_at' => now()->toDateTimeString(), 'groups' => []];

        try {
            DB::transaction(function () use ($groups, &$backup) {
                $hasArchive = Schema::hasTable('medicine_transactions_archive');

                foreach ($groups as $g) {
                    $canonical = $g['canonical'];
                    $dupIds = array_values(array_map(fn ($m) => $m->id,
                        array_filter($g['members'], fn ($m) => $m->id !== $canonical->id)));

                    $txRemap = DB::table('medicine_transactions')
                        ->whereIn('patient_id', $dupIds)
                        ->get(['id', 'patient_id'])
                        ->map(fn ($r) => [$r->id, $r->patient_id])
                        ->all();

                    $archRemap = [];
                    if ($hasArchive) {
                        $archRemap = DB::table('medicine_transactions_archive')
                            ->whereIn('patient_id', $dupIds)
                            ->get(['id', 'patient_id'])
                            ->map(fn ($r) => [$r->id, $r->patient_id])
                            ->all();
                    }

                    // Lengkapi kolom kosong di pasien utama dari data duplikatnya
                    $original = (array) DB::table('patients')->where('id', $canonical->id)->first();
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
                        'canonical_id'             => $canonical->id,
                        'canonical_original'       => array_intersect_key($original, array_flip(self::FILL_FIELDS)),
                        'deleted_patients'         => DB::table('patients')->whereIn('id', $dupIds)->get()->map(fn ($r) => (array) $r)->all(),
                        'transaction_remap'        => $txRemap,
                        'transaction_arch_remap'   => $archRemap,
                    ];

                    // Pindahkan referensi transaksi DULU sebelum menghapus pasien
                    if (!empty($txRemap)) {
                        DB::table('medicine_transactions')
                            ->whereIn('patient_id', $dupIds)
                            ->update(['patient_id' => $canonical->id]);
                    }

                    if ($hasArchive && !empty($archRemap)) {
                        DB::table('medicine_transactions_archive')
                            ->whereIn('patient_id', $dupIds)
                            ->update(['patient_id' => $canonical->id]);
                    }

                    if (!empty($fill)) {
                        DB::table('patients')->where('id', $canonical->id)->update($fill);
                    }

                    $stillReferenced = DB::table('medicine_transactions')->whereIn('patient_id', $dupIds)->count();
                    if ($stillReferenced > 0) {
                        throw new \RuntimeException("Masih ada {$stillReferenced} transaksi merujuk duplikat grup pasien #{$canonical->id}. Dibatalkan.");
                    }

                    if ($hasArchive) {
                        $stillReferencedArch = DB::table('medicine_transactions_archive')->whereIn('patient_id', $dupIds)->count();
                        if ($stillReferencedArch > 0) {
                            throw new \RuntimeException("Masih ada {$stillReferencedArch} transaksi arsip merujuk duplikat grup pasien #{$canonical->id}. Dibatalkan.");
                        }
                    }

                    DB::table('patients')->whereIn('id', $dupIds)->delete();
                }

                // Simpan file backup JSON sebelum commit
                $path = 'patient-merge/backup-' . now()->format('Ymd_His') . '.json';
                if (!Storage::put($path, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                    throw new \RuntimeException('Gagal menulis file backup. Dibatalkan.');
                }
                $backup['path'] = $path;
            });
        } catch (\Throwable $e) {
            $this->error('GAGAL, semua perubahan dibatalkan: ' . $e->getMessage());
            return self::FAILURE;
        }

        $deleted = array_sum(array_map(fn ($g) => count($g['deleted_patients']), $backup['groups']));
        $movedTx = array_sum(array_map(fn ($g) => count($g['transaction_remap']), $backup['groups']));
        $movedArch = array_sum(array_map(fn ($g) => count($g['transaction_arch_remap']), $backup['groups']));

        $this->info("Selesai: {$deleted} pasien duplikat digabung, {$movedTx} transaksi aktif dan {$movedArch} transaksi arsip dipindahkan.");
        $this->info("Backup tersimpan di: storage/app/{$backup['path']}");
        $this->line("Dapat dibatalkan sewaktu-waktu dengan: php artisan patients:merge-duplicates --rollback={$backup['path']}");

        return self::SUCCESS;
    }

    private function rollback(string $path): int
    {
        if (!Storage::exists($path)) {
            $this->error("File tidak ditemukan: storage/app/{$path}");
            return self::FAILURE;
        }

        $backup = json_decode(Storage::get($path), true);
        if (!$this->option('force') && !$this->confirm('Kembalikan ' . count($backup['groups'] ?? []) . " grup dari backup {$backup['created_at']}?")) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($backup) {
            $hasArchive = Schema::hasTable('medicine_transactions_archive');

            foreach ($backup['groups'] as $g) {
                foreach ($g['deleted_patients'] as $row) {
                    DB::table('patients')->insertOrIgnore($row);
                }
                foreach ($g['transaction_remap'] as [$txId, $oldPatientId]) {
                    DB::table('medicine_transactions')->where('id', $txId)->update(['patient_id' => $oldPatientId]);
                }
                if ($hasArchive && !empty($g['transaction_arch_remap'])) {
                    foreach ($g['transaction_arch_remap'] as [$txId, $oldPatientId]) {
                        DB::table('medicine_transactions_archive')->where('id', $txId)->update(['patient_id' => $oldPatientId]);
                    }
                }
                if (!empty($g['canonical_original'])) {
                    DB::table('patients')->where('id', $g['canonical_id'])->update($g['canonical_original']);
                }
            }
        });

        $this->info('Rollback selesai. Data pasien dan transaksi telah dikembalikan.');
        return self::SUCCESS;
    }

    private function isBlank($v): bool
    {
        return in_array(trim((string) $v), ['', '-', '0'], true);
    }
}
