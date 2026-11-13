<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RebalanceNegativeBatchStock extends Command
{
    protected $signature = 'stock:rebalance-negative-batches
        {--apply : Simpan perubahan ke database (tanpa flag ini hanya mode preview/dry-run)}
        {--scope=all : Ruang lingkup rebalancing: all, batches, atau etalase}
        {--pharmacy_id= : Filter ID cabang tertentu (opsional)}
        {--medicine_id= : Filter ID obat tertentu (opsional)}
        {--limit-preview=25 : Maksimal grup obat yang ditampilkan detail perubahannya di terminal}';

    protected $description = 'Menolkan stok batch negatif dengan mengoffset dari batch positif di obat & cabang yang sama tanpa mengubah total stok';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $scope = strtolower((string) $this->option('scope'));
        $pharmacyId = $this->option('pharmacy_id') !== null ? (int) $this->option('pharmacy_id') : null;
        $medicineId = $this->option('medicine_id') !== null ? (int) $this->option('medicine_id') : null;
        $previewLimit = max(0, (int) $this->option('limit-preview'));

        if (!in_array($scope, ['all', 'batches', 'etalase'], true)) {
            $this->error("Nilai --scope tidak valid: {$scope}. Gunakan: all, batches, atau etalase.");
            return self::FAILURE;
        }

        $this->info('=== REBALANCING STOK BATCH NEGATIF (ZERO-SUM OFFSET) ===');
        $this->line($apply
            ? '<fg=green>MODE APPLY: Perubahan akan disimpan di dalam transaksi database.</>'
            : '<fg=yellow>MODE DRY-RUN: Simulasi saja, tidak ada perubahan yang disimpan ke database.</>');

        if ($scope === 'all' || $scope === 'batches') {
            $this->newLine();
            $this->processScope(
                scopeName: 'GUDANG / MASTER BATCH (batches.stock)',
                table: 'batches',
                scale: 1,
                preferSameEtalase: false,
                apply: $apply,
                pharmacyId: $pharmacyId,
                medicineId: $medicineId,
                previewLimit: $previewLimit
            );
        }

        if ($scope === 'all' || $scope === 'etalase') {
            $this->newLine();
            $this->processScope(
                scopeName: 'ETALASE / PELAYANAN (medicine_transfer_items.qty)',
                table: 'medicine_transfer_items',
                scale: 1000,
                preferSameEtalase: true,
                apply: $apply,
                pharmacyId: $pharmacyId,
                medicineId: $medicineId,
                previewLimit: $previewLimit
            );
        }

        if (!$apply) {
            $this->newLine();
            $this->comment('Jika hasil pratinjau sudah sesuai, jalankan dengan --apply:');
            $this->line('  php artisan stock:rebalance-negative-batches --apply');
        }

        return self::SUCCESS;
    }

    private function processScope(
        string $scopeName,
        string $table,
        int $scale,
        bool $preferSameEtalase,
        bool $apply,
        ?int $pharmacyId,
        ?int $medicineId,
        int $previewLimit
    ): void {
        $this->info("--- {$scopeName} ---");

        $groups = $this->findCandidateGroups($table, $pharmacyId, $medicineId);
        if ($groups->isEmpty()) {
            $this->line('Tidak ditemukan batch dengan stok negatif.');
            return;
        }

        $safeGroups = $groups->filter(fn ($g) => (float) $g->total_qty >= -0.0001)->values();
        $unsafeGroups = $groups->filter(fn ($g) => (float) $g->total_qty < -0.0001)->values();

        $this->line(sprintf(
            'Ditemukan %d grup (obat × cabang) ber-batch minus: %d AMAN (SUM >= 0), %d DILEWATI (SUM < 0, perlu cek/opname).',
            $groups->count(),
            $safeGroups->count(),
            $unsafeGroups->count()
        ));

        $processedGroups = 0;
        $updatedRowsCount = 0;
        $skippedRaceGroups = 0;
        $previewPrinted = 0;

        foreach ($safeGroups as $group) {
            $pId = $group->pharmacy_id !== null ? (int) $group->pharmacy_id : null;
            $mId = (int) $group->medicine_id;

            $execute = function () use (
                $table,
                $scale,
                $preferSameEtalase,
                $apply,
                $pId,
                $mId,
                $group,
                &$processedGroups,
                &$updatedRowsCount,
                &$skippedRaceGroups,
                &$previewPrinted,
                $previewLimit
            ) {
                $rows = $this->loadGroupRows($table, $pId, $mId, $scale, $apply);
                $plan = $this->computeRebalancePlan($rows, $preferSameEtalase);

                if ($plan === null || empty($plan['updates'])) {
                    $skippedRaceGroups++;
                    return;
                }

                if ($apply) {
                    $now = now();
                    foreach ($plan['updates'] as $upd) {
                        $value = $scale === 1
                            ? (int) $upd['new_units']
                            : round($upd['new_units'] / $scale, 3);

                        $col = $table === 'batches' ? 'stock' : 'qty';
                        DB::table($table)
                            ->where('id', $upd['id'])
                            ->update([
                                $col => $value,
                                'updated_at' => $now,
                            ]);
                    }

                    // Verifikasi ulang langsung dari database setelah update sebelum commit
                    $verifyRows = $this->loadGroupRows($table, $pId, $mId, $scale, false);
                    $verifyTotalUnits = array_sum(array_column($verifyRows, 'stock_units'));
                    if ($verifyTotalUnits !== $plan['total_before_units']) {
                        throw new RuntimeException(
                            "Mismatch total stok pada {$table} (pharmacy_id={$pId}, medicine_id={$mId}): "
                            . "before={$plan['total_before_units']}, after={$verifyTotalUnits}"
                        );
                    }
                }

                $processedGroups++;
                $updatedRowsCount += count($plan['updates']);

                if ($previewPrinted < $previewLimit) {
                    $previewPrinted++;
                    $branchLabel = $group->pharmacy_name ?: ('Pharmacy#' . ($pId ?? 'NULL'));
                    $totalHuman = $this->formatUnits($plan['total_before_units'], $scale);
                    $this->line(sprintf(
                        '  [%s] %s - %s (Total tetap: %s)',
                        $branchLabel,
                        $group->medicine_code ?: '-',
                        $group->medicine_name,
                        $totalHuman
                    ));

                    foreach ($plan['updates'] as $upd) {
                        $this->line(sprintf(
                            '     • ID #%d (%s): %s -> %s',
                            $upd['id'],
                            $upd['label'],
                            $this->formatUnits($upd['old_units'], $scale),
                            $this->formatUnits($upd['new_units'], $scale)
                        ));
                    }
                }
            };

            if ($apply) {
                DB::transaction($execute);
            } else {
                $execute();
            }
        }

        if ($safeGroups->count() > $previewLimit && $previewPrinted >= $previewLimit) {
            $this->line("  ... dan grup lainnya tidak ditampilkan (gunakan --limit-preview=9999 untuk melihat semua).");
        }

        $this->info(sprintf(
            '%s selesai pada %s: %d grup diproses, %d baris diubah, %d grup dilewati (SUM < 0 / tidak ada selisih).',
            $apply ? 'APPLY' : 'DRY-RUN',
            $table,
            $processedGroups,
            $updatedRowsCount,
            $unsafeGroups->count() + $skippedRaceGroups
        ));
    }

    private function findCandidateGroups(string $table, ?int $pharmacyId, ?int $medicineId)
    {
        if ($table === 'batches') {
            $query = DB::table('batches as b')
                ->join('medicines as m', 'm.id', '=', 'b.medicine_id')
                ->leftJoin('pharmacies as p', 'p.id', '=', 'b.pharmacy_id')
                ->when($pharmacyId !== null, fn ($q) => $q->where('b.pharmacy_id', $pharmacyId))
                ->when($medicineId !== null, fn ($q) => $q->where('b.medicine_id', $medicineId))
                ->groupBy('b.pharmacy_id', 'p.name', 'b.medicine_id', 'm.code', 'm.name')
                ->havingRaw('MIN(b.stock) < 0')
                ->orderBy('p.name')
                ->orderBy('m.name')
                ->selectRaw('
                    b.pharmacy_id,
                    p.name as pharmacy_name,
                    b.medicine_id,
                    m.code as medicine_code,
                    m.name as medicine_name,
                    SUM(b.stock) as total_qty
                ');

            return $query->get();
        }

        $query = DB::table('medicine_transfer_items as mti')
            ->join('batches as b', 'b.id', '=', 'mti.batches_id')
            ->join('medicines as m', 'm.id', '=', 'b.medicine_id')
            ->leftJoin('pharmacies as p', 'p.id', '=', 'b.pharmacy_id')
            ->where('mti.status', 1)
            ->where(function ($q) {
                $q->whereNull('mti.source_type')
                  ->orWhere('mti.source_type', '!=', 'retur_gudang');
            })
            ->when($pharmacyId !== null, fn ($q) => $q->where('b.pharmacy_id', $pharmacyId))
            ->when($medicineId !== null, fn ($q) => $q->where('b.medicine_id', $medicineId))
            ->groupBy('b.pharmacy_id', 'p.name', 'b.medicine_id', 'm.code', 'm.name')
            ->havingRaw('MIN(mti.qty) < -0.0001')
            ->orderBy('p.name')
            ->orderBy('m.name')
            ->selectRaw('
                b.pharmacy_id,
                p.name as pharmacy_name,
                b.medicine_id,
                m.code as medicine_code,
                m.name as medicine_name,
                SUM(mti.qty) as total_qty
            ');

        return $query->get();
    }

    private function loadGroupRows(string $table, ?int $pharmacyId, int $medicineId, int $scale, bool $lockForUpdate): array
    {
        if ($table === 'batches') {
            $q = DB::table('batches as b')
                ->where('b.medicine_id', $medicineId)
                ->when(
                    $pharmacyId === null,
                    fn ($q) => $q->whereNull('b.pharmacy_id'),
                    fn ($q) => $q->where('b.pharmacy_id', $pharmacyId)
                )
                ->orderByRaw('CASE WHEN b.expired_date IS NULL OR b.expired_date = "" THEN 1 ELSE 0 END ASC')
                ->orderBy('b.expired_date', 'asc')
                ->orderBy('b.id', 'asc')
                ->select(['b.id', 'b.stock', 'b.name', 'b.expired_date']);

            if ($lockForUpdate) {
                $q->lockForUpdate();
            }

            return $q->get()->map(fn ($r) => [
                'id' => (int) $r->id,
                'stock_units' => (int) round(((float) $r->stock) * $scale),
                'etalases_id' => null,
                'label' => ($r->name ?: '-') . ' | ED:' . ($r->expired_date ?: '-'),
            ])->all();
        }

        $q = DB::table('medicine_transfer_items as mti')
            ->join('batches as b', 'b.id', '=', 'mti.batches_id')
            ->where('mti.status', 1)
            ->where(function ($sub) {
                $sub->whereNull('mti.source_type')
                    ->orWhere('mti.source_type', '!=', 'retur_gudang');
            })
            ->where('b.medicine_id', $medicineId)
            ->when(
                $pharmacyId === null,
                fn ($sub) => $sub->whereNull('b.pharmacy_id'),
                fn ($sub) => $sub->where('b.pharmacy_id', $pharmacyId)
            )
            ->orderByRaw('CASE WHEN b.expired_date IS NULL OR b.expired_date = "" THEN 1 ELSE 0 END ASC')
            ->orderBy('b.expired_date', 'asc')
            ->orderBy('mti.id', 'asc')
            ->select(['mti.id', 'mti.qty', 'mti.etalases_id', 'b.name as batch_name', 'b.expired_date']);

        if ($lockForUpdate) {
            $q->lockForUpdate();
        }

        return $q->get()->map(fn ($r) => [
            'id' => (int) $r->id,
            'stock_units' => (int) round(((float) $r->qty) * $scale),
            'etalases_id' => $r->etalases_id !== null ? (int) $r->etalases_id : null,
            'label' => ($r->batch_name ?: '-') . ' | ED:' . ($r->expired_date ?: '-') . ' | Etalase#' . ($r->etalases_id ?? '-'),
        ])->all();
    }

    /**
     * Hitung rencana rebalancing antar-batch dengan jaminan total stok sebelum === sesudah.
     */
    private function computeRebalancePlan(array $rows, bool $preferSameEtalase): ?array
    {
        if (empty($rows)) {
            return null;
        }

        $totalBeforeUnits = 0;
        $hasNegative = false;
        $balances = [];
        $meta = [];

        foreach ($rows as $row) {
            $id = $row['id'];
            $units = $row['stock_units'];
            $balances[$id] = $units;
            $meta[$id] = $row;
            $totalBeforeUnits += $units;
            if ($units < 0) {
                $hasNegative = true;
            }
        }

        if (!$hasNegative || $totalBeforeUnits < 0) {
            return null;
        }

        $negRows = array_values(array_filter($rows, fn ($r) => $r['stock_units'] < 0));
        $posRows = array_values(array_filter($rows, fn ($r) => $r['stock_units'] > 0));

        foreach ($negRows as $neg) {
            $negId = $neg['id'];
            $need = -$balances[$negId];
            if ($need <= 0) {
                continue;
            }

            // Tahap 1: utamakan batch positif di etalase yang sama (jika ada)
            if ($preferSameEtalase && $neg['etalases_id'] !== null) {
                foreach ($posRows as $pos) {
                    if ($need <= 0) {
                        break;
                    }
                    if ($pos['etalases_id'] !== $neg['etalases_id']) {
                        continue;
                    }
                    $posId = $pos['id'];
                    if ($balances[$posId] <= 0) {
                        continue;
                    }
                    $take = min($need, $balances[$posId]);
                    $balances[$posId] -= $take;
                    $balances[$negId] += $take;
                    $need -= $take;
                }
            }

            // Tahap 2: ambil sisa pengurang dari batch positif lain (urut FEFO) di cabang & obat yg sama
            foreach ($posRows as $pos) {
                if ($need <= 0) {
                    break;
                }
                $posId = $pos['id'];
                if ($balances[$posId] <= 0) {
                    continue;
                }
                $take = min($need, $balances[$posId]);
                $balances[$posId] -= $take;
                $balances[$negId] += $take;
                $need -= $take;
            }

            if ($need !== 0) {
                return null;
            }
        }

        $totalAfterUnits = array_sum($balances);
        if ($totalAfterUnits !== $totalBeforeUnits) {
            throw new RuntimeException("Gagal menjaga zero-sum invariant: {$totalBeforeUnits} !== {$totalAfterUnits}");
        }

        $updates = [];
        foreach ($rows as $row) {
            $id = $row['id'];
            if ($balances[$id] !== $row['stock_units']) {
                $updates[] = [
                    'id' => $id,
                    'label' => $meta[$id]['label'],
                    'old_units' => $row['stock_units'],
                    'new_units' => $balances[$id],
                ];
            }
        }

        return [
            'total_before_units' => $totalBeforeUnits,
            'total_after_units' => $totalAfterUnits,
            'updates' => $updates,
        ];
    }

    private function formatUnits(int $units, int $scale): string
    {
        if ($scale === 1) {
            return (string) $units;
        }
        return rtrim(rtrim(number_format($units / $scale, 3, '.', ''), '0'), '.');
    }
}

