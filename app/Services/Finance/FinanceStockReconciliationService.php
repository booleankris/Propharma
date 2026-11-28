<?php

namespace App\Services\Finance;

use App\Services\ConsignmentService;
use App\Services\StockDataReportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinanceStockReconciliationService
{
    /**
     * Bangun data rekonsiliasi antara tarikan Excel Data Stok dengan valuasi Nilai Stok Finance.
     * Menggunakan rumus identik dengan FinanceController::getStockValuation() (qty > 0, non-konsinyasi, HNA * 1.11).
     */
    public function buildReport(int $pharmacyId, ?Collection $medicines = null, array $metricsMap = []): array
    {
        $isPmi = ($pharmacyId <= 0 || $pharmacyId === 1 || $pharmacyId === 9);
        $counterPharmacyId = $isPmi ? 1 : $pharmacyId;
        $warehouseId = function_exists('getWarehousePharmacyId') ? (int) getWarehousePharmacyId() : 9;

        if ($medicines === null) {
            $medicines = DB::table('medicines')
                ->leftJoin('factories', 'factories.id', '=', 'medicines.factory_id')
                ->where('medicines.status', 1)
                ->select([
                    'medicines.id',
                    'medicines.code',
                    'medicines.name',
                    'medicines.unit',
                    'medicines.content',
                    'medicines.raw_price',
                    'medicines.status',
                    DB::raw("COALESCE(factories.name, '-') as factory_name"),
                ])
                ->orderBy('medicines.name', 'asc')
                ->orderBy('medicines.code', 'asc')
                ->get();
        }

        if ($medicines->isEmpty()) {
            return [
                'isPmi' => $isPmi,
                'summary' => [
                    'finance_total_value' => 0.0,
                    'finance_etalase_value' => 0.0,
                    'finance_gudang_value' => 0.0,
                    'finance_total_qty' => 0.0,
                    'excel_non_cons_value' => 0.0,
                    'excel_non_cons_qty' => 0.0,
                    'total_diff_value' => 0.0,
                    'mismatch_count' => 0,
                    'row_count' => 0,
                ],
                'rows' => [],
            ];
        }

        $medicineIds = $medicines->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (empty($metricsMap)) {
            $metricsMap = app(StockDataReportService::class)->computeMetrics(
                $medicineIds,
                $pharmacyId,
                null,
                null,
                false
            );
        }

        $consFilter = ConsignmentService::getConsignmentStockFilter();
        $consBatchSet = array_flip($consFilter['consignment_batch_ids']);
        $nonConsBatchSet = array_flip($consFilter['non_consignment_batch_ids']);
        $consMedSet = array_flip($consFilter['consignment_medicine_ids']);

        $storageFinPosQty = [];
        $storageConsPosQty = [];
        $storageNegQty = [];
        $storageRawDecimalQty = [];

        if ($isPmi) {
            $storageRows = DB::table('batches as b')
                ->where('b.pharmacy_id', $warehouseId)
                ->whereIn('b.medicine_id', $medicineIds)
                ->get(['b.id', 'b.medicine_id', 'b.stock']);

            foreach ($storageRows as $row) {
                $mId = (int) ($row->medicine_id ?? 0);
                if ($mId <= 0) {
                    continue;
                }
                $qty = $this->parseSignedDecimal($row->stock);
                if (abs($qty) <= 0.00001) {
                    continue;
                }

                $storageRawDecimalQty[$mId] = ($storageRawDecimalQty[$mId] ?? 0.0) + $qty;

                if ($qty > 0) {
                    $bId = (int) $row->id;
                    $isCons = isset($consBatchSet[$bId])
                        || (isset($consMedSet[$mId]) && !isset($nonConsBatchSet[$bId]));

                    if ($isCons) {
                        $storageConsPosQty[$mId] = ($storageConsPosQty[$mId] ?? 0.0) + $qty;
                    } else {
                        $storageFinPosQty[$mId] = ($storageFinPosQty[$mId] ?? 0.0) + $qty;
                    }
                } else {
                    $storageNegQty[$mId] = ($storageNegQty[$mId] ?? 0.0) + $qty;
                }
            }
        }

        $counterFinPosQty = [];
        $counterConsPosQty = [];
        $counterNegQty = [];
        $counterRawDecimalQty = [];

        $counterRows = DB::table('medicine_transfer_items as mti')
            ->join('batches as b', 'b.id', '=', 'mti.batches_id')
            ->where('b.pharmacy_id', $counterPharmacyId)
            ->whereIn('b.medicine_id', $medicineIds)
            ->where('mti.status', 1)
            ->where(function ($q) {
                $q->whereNull('mti.source_type')->orWhere('mti.source_type', '!=', 'retur_gudang');
            })
            ->get([
                'mti.batches_id',
                'mti.source_batches_id',
                'mti.qty',
                'b.medicine_id',
            ]);

        foreach ($counterRows as $row) {
            $mId = (int) ($row->medicine_id ?? 0);
            if ($mId <= 0) {
                continue;
            }
            $qty = (float) ($row->qty ?? 0);
            if (abs($qty) <= 0.00001) {
                continue;
            }

            $counterRawDecimalQty[$mId] = ($counterRawDecimalQty[$mId] ?? 0.0) + $qty;

            if ($qty > 0) {
                $bId = (int) ($row->batches_id ?? 0);
                $srcId = (int) ($row->source_batches_id ?? 0);
                $isCons = isset($consBatchSet[$bId])
                    || ($srcId > 0 && isset($consBatchSet[$srcId]))
                    || (isset($consMedSet[$mId]) && !isset($nonConsBatchSet[$bId]));

                if ($isCons) {
                    $counterConsPosQty[$mId] = ($counterConsPosQty[$mId] ?? 0.0) + $qty;
                } else {
                    $counterFinPosQty[$mId] = ($counterFinPosQty[$mId] ?? 0.0) + $qty;
                }
            } else {
                $counterNegQty[$mId] = ($counterNegQty[$mId] ?? 0.0) + $qty;
            }
        }

        $rows = [];
        $sumFinValExact = 0.0;
        $sumFinEtalaseExact = 0.0;
        $sumFinGudangExact = 0.0;
        $sumFinQtyExact = 0.0;
        $sumExcelValExact = 0.0;
        $sumExcelQty = 0.0;
        $mismatchCount = 0;

        foreach ($medicines as $m) {
            $mId = (int) $m->id;
            $isActive = !isset($m->status) || (int) $m->status === 1;
            $metrics = $metricsMap[$mId] ?? [];

            $hnaExact = round($this->parsePositiveDecimal($m->raw_price), 4);
            $hnaExcelFloat = (float) ($m->raw_price ?? 0);
            $hnaVat = $hnaExact * 1.11;

            $excelQtyTotal = (int) ($metrics['qty_now'] ?? 0);
            $excelKeterangan = (string) ($metrics['keterangan'] ?? '-');
            $isExcelCons = strcasecmp(trim($excelKeterangan), 'Konsinyasi') === 0;
            $excelNonConsQty = $isExcelCons ? 0 : $excelQtyTotal;
            $excelValExact = $excelNonConsQty * $hnaExcelFloat * 1.11;

            $finGudangQty = ($isActive && $isPmi) ? (float) ($storageFinPosQty[$mId] ?? 0.0) : 0.0;
            $finEtalaseQty = $isActive ? (float) ($counterFinPosQty[$mId] ?? 0.0) : 0.0;
            $finQtyTotal = $finGudangQty + $finEtalaseQty;

            $finGudangValExact = $finGudangQty * $hnaVat;
            $finEtalaseValExact = $finEtalaseQty * $hnaVat;
            $finValExact = $finGudangValExact + $finEtalaseValExact;

            $consPosQty = ($isPmi ? (float) ($storageConsPosQty[$mId] ?? 0.0) : 0.0)
                + (float) ($counterConsPosQty[$mId] ?? 0.0);
            $negQtyTotal = ($isPmi ? (float) ($storageNegQty[$mId] ?? 0.0) : 0.0)
                + (float) ($counterNegQty[$mId] ?? 0.0);

            $sumFinValExact += $finValExact;
            $sumFinEtalaseExact += $finEtalaseValExact;
            $sumFinGudangExact += $finGudangValExact;
            $sumFinQtyExact += $finQtyTotal;
            $sumExcelValExact += $excelValExact;
            $sumExcelQty += $excelNonConsQty;

            $diffQty = round($finQtyTotal - $excelNonConsQty, 4);
            $diffValExact = $finValExact - $excelValExact;

            $hasStockOrDiff = abs($finValExact) > 0.005
                || abs($excelValExact) > 0.005
                || abs($diffValExact) > 0.005
                || $consPosQty > 0.0001
                || $negQtyTotal < -0.0001
                || abs($finQtyTotal) > 0.0001
                || $excelQtyTotal !== 0;

            if (!$hasStockOrDiff) {
                continue;
            }

            $isMismatch = abs($diffValExact) >= 0.01 || abs($diffQty) >= 0.01;
            if ($isMismatch) {
                $mismatchCount++;
            }

            $reasons = [];
            if (!$isActive && ($excelQtyTotal !== 0 || abs($excelValExact) > 0.005)) {
                $reasons[] = 'Obat non-aktif (diabaikan Finance)';
            }
            if ($negQtyTotal < -0.0001) {
                $reasons[] = 'Batch/etalase minus (' . $this->formatQtyLabel($negQtyTotal) . ') diabaikan Finance';
            }
            if (!$isExcelCons && $consPosQty > 0.0001) {
                $reasons[] = 'Ada batch Konsinyasi (' . $this->formatQtyLabel($consPosQty) . ') di-exclude Finance';
            }
            if ($isExcelCons && $finQtyTotal > 0.0001) {
                $reasons[] = 'Ada batch Non-Konsinyasi (' . $this->formatQtyLabel($finQtyTotal) . ') meski Keterangan Excel Konsinyasi';
            }
            if (abs($hnaExact - $hnaExcelFloat) > 0.0001 && ($finQtyTotal > 0 || $excelNonConsQty != 0)) {
                $reasons[] = 'Format koma pada HNA (' . trim((string) ($m->raw_price ?? '')) . ')';
            }

            $rawDecimalTotal = ($isPmi ? (float) ($storageRawDecimalQty[$mId] ?? 0.0) : 0.0)
                + (float) ($counterRawDecimalQty[$mId] ?? 0.0);
            if ($isMismatch && empty($reasons) && abs($rawDecimalTotal - $excelQtyTotal) > 0.0001) {
                $reasons[] = 'Selisih desimal qty / pembulatan (int) di Excel';
            }
            if ($isMismatch && empty($reasons)) {
                $reasons[] = 'Perbedaan kalkulasi stok/HNA';
            }

            $rows[] = [
                'medicine_id' => $mId,
                'code' => (string) ($m->code ?? '-'),
                'name' => (string) $m->name,
                'factory_name' => (string) ($m->factory_name ?: '-'),
                'unit' => $m->unit ? strtoupper((string) $m->unit) : '-',
                'hna_finance' => $hnaExact,
                'hna_ppn' => round($hnaVat, 4),
                'excel_qty' => $excelQtyTotal,
                'excel_keterangan' => $excelKeterangan,
                'excel_value' => round($excelValExact, 2),
                'finance_gudang_qty' => round($finGudangQty, 2),
                'finance_etalase_qty' => round($finEtalaseQty, 2),
                'finance_total_qty' => round($finQtyTotal, 2),
                'finance_value' => round($finValExact, 2),
                'diff_qty' => round($diffQty, 2),
                'diff_value' => round($diffValExact, 2),
                'consignment_pos_qty' => round($consPosQty, 2),
                'negative_qty' => round($negQtyTotal, 2),
                'is_mismatch' => $isMismatch,
                'status_label' => $isMismatch ? 'ADA SELISIH' : 'COCOK',
                'reason' => $isMismatch ? implode('; ', $reasons) : ($consPosQty > 0.0001 ? 'Konsinyasi cocok (sama-sama ter-exclude)' : 'Sesuai'),
                '_abs_diff' => abs($diffValExact),
                '_fin_val' => abs($finValExact),
            ];
        }

        usort($rows, function (array $a, array $b) {
            if ($a['is_mismatch'] !== $b['is_mismatch']) {
                return $a['is_mismatch'] ? -1 : 1;
            }
            $diffCmp = $b['_abs_diff'] <=> $a['_abs_diff'];
            if ($diffCmp !== 0) {
                return $diffCmp;
            }
            $valCmp = $b['_fin_val'] <=> $a['_fin_val'];
            if ($valCmp !== 0) {
                return $valCmp;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        foreach ($rows as &$r) {
            unset($r['_abs_diff'], $r['_fin_val']);
        }
        unset($r);

        return [
            'isPmi' => $isPmi,
            'summary' => [
                'finance_total_value' => round($sumFinValExact, 2),
                'finance_etalase_value' => round($sumFinEtalaseExact, 2),
                'finance_gudang_value' => round($sumFinGudangExact, 2),
                'finance_total_qty' => round($sumFinQtyExact, 2),
                'excel_non_cons_value' => round($sumExcelValExact, 2),
                'excel_non_cons_qty' => round($sumExcelQty, 2),
                'total_diff_value' => round($sumFinValExact - $sumExcelValExact, 2),
                'mismatch_count' => $mismatchCount,
                'row_count' => count($rows),
            ],
            'rows' => $rows,
        ];
    }

    private function parsePositiveDecimal(mixed $value): float
    {
        $val = $this->parseSignedDecimal($value);
        return max(0.0, $val);
    }

    private function parseSignedDecimal(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }
        $normalized = str_replace(',', '.', trim((string) $value));
        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function formatQtyLabel(float $qty): string
    {
        return abs($qty - round($qty)) < 0.001
            ? (string) ((int) round($qty))
            : rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
    }
}
