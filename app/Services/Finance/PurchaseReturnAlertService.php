<?php

namespace App\Services\Finance;

use App\Models\Batches;
use App\Models\PurchaseReturnItem;
use App\Models\ReturnPolicy;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PurchaseReturnAlertService
{
    /**
     * Normalisasi nama pabrik untuk pencocokan toleran
     */
    protected function normalizeFactoryName(?string $name): string
    {
        if (!$name) return '';
        $clean = preg_replace('/\b(pt|tbk|indonesia|pharma|pharmaceutical|laboratories|lab|jaya|corp|corporation)\b/i', '', $name);
        return trim(strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $clean)));
    }

    /**
     * Cari policy retur yang cocok untuk kreditor dan pabrik
     */
    protected function findMatchingPolicy(?string $creditorCode, $factoryId, ?string $factoryName, Collection $policies)
    {
        if (!$creditorCode) return null;

        // 1. Cek kreditor + factory_id
        if ($factoryId) {
            $p = $policies->first(function ($item) use ($creditorCode, $factoryId) {
                return $item->creditor_code === $creditorCode && $item->factory_id && $item->factory_id == $factoryId;
            });
            if ($p) return $p;
        }

        // 2. Cek kreditor + normalisasi nama pabrik
        if ($factoryName) {
            $normF = $this->normalizeFactoryName($factoryName);
            if (!empty($normF)) {
                $p = $policies->first(function ($item) use ($creditorCode, $normF) {
                    if ($item->creditor_code !== $creditorCode) return false;
                    $normP = $this->normalizeFactoryName($item->factory_name);
                    if (empty($normP)) return false;
                    return $normP === $normF
                        || (strlen($normP) >= 4 && str_contains($normF, $normP))
                        || (strlen($normF) >= 4 && str_contains($normP, $normF));
                });
                if ($p) return $p;
            }
        }

        return null;
    }

    /**
     * Dapatkan daftar obat yang mendekati / siap diretur berdasarkan ketentuan
     */
    public function getApproachingReturns(?int $pharmacyId = null, array $filters = [], int $perPage = 25): array
    {
        if ($pharmacyId === null) {
            $activeId = function_exists('getActivePharmacyId') ? getActivePharmacyId() : 0;
            $pharmacyId = $activeId > 0 ? $activeId : null;
        }

        $now = Carbon::now()->startOfMonth();

        // 1. Ambil semua batch dengan stok > 0 dan memiliki expired_date
        $batchesQuery = Batches::where('stock', '>', 0)
            ->whereNotNull('expired_date')
            ->with([
                'medicines' => function ($q) {
                    $q->with(['factory:id,name', 'creditor:id,code,name']);
                },
                'receivingItems' => function ($q) {
                    $q->latest('id')->with(['receiving_details' => function ($rd) {
                        $rd->with('creditor:id,code,name');
                    }]);
                }
            ]);

        if ($pharmacyId) {
            $batchesQuery->where('pharmacy_id', $pharmacyId);
        }

        $batches = $batchesQuery->get();

        // 1b. Batch tanpa faktur penerimaan (mis. batch hasil Stok Opname "OPN-...")
        // tetap butuh referensi faktur untuk retur. Gunakan faktur pembelian
        // terakhir untuk obat yang sama sebagai referensi default.
        $medIdsWithoutReceipt = $batches
            ->filter(fn ($b) => $b->receivingItems->isEmpty())
            ->pluck('medicine_id')
            ->filter()
            ->unique()
            ->values();

        $latestReceiptByMedicine = collect();
        if ($medIdsWithoutReceipt->isNotEmpty()) {
            $latestReceiptByMedicine = \App\Models\ReceivingItems::query()
                ->whereNotNull('batches_id')
                ->whereHas('order_items', fn ($q) => $q->whereIn('medicine_id', $medIdsWithoutReceipt))
                ->when($pharmacyId, fn ($q) => $q->whereHas('receiving_details.receiving', fn ($r) => $r->where('pharmacy_id', $pharmacyId)))
                ->with([
                    'order_items:id,medicine_id',
                    'receiving_details' => fn ($rd) => $rd->with('creditor:id,code,name'),
                ])
                ->orderByDesc('id')
                ->get()
                ->filter(fn ($ri) => $ri->order_items && $ri->receiving_details)
                ->unique(fn ($ri) => $ri->order_items->medicine_id)
                ->keyBy(fn ($ri) => $ri->order_items->medicine_id);
        }

        // 2. Pre-load Master Ketentuan Retur
        $policies = ReturnPolicy::all();

        // 3. Pre-load obat/batch yang sudah pernah diajukan returnya (kecuali dibatalkan)
        $submittedReturnItems = PurchaseReturnItem::query()
            ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
            ->when($pharmacyId, fn ($q) => $q->where('purchase_returns.pharmacy_id', $pharmacyId))
            ->where(function ($q) {
                $q->whereNull('purchase_return_items.status')
                    ->orWhere('purchase_return_items.status', '!=', 'dibatalkan');
            })
            ->where(function ($q) {
                $q->whereNull('purchase_returns.status')
                    ->orWhere('purchase_returns.status', '!=', 'dibatalkan');
            })
            ->get([
                'purchase_return_items.batch_id',
                'purchase_return_items.medicine_id',
                'purchase_return_items.batch_number',
                'purchase_return_items.qty',
                'purchase_return_items.stock_unit_factor',
                'purchase_return_items.stock_deducted',
                'purchase_returns.pharmacy_id',
            ]);

        $positiveBatchIds = [];
        $positiveMedBatchKeys = [];
        foreach ($batches as $posBatch) {
            $positiveBatchIds[(int) $posBatch->id] = true;
            $posNorm = strtoupper(trim((string) ($posBatch->name ?? '')));
            if (!empty($posBatch->medicine_id) && $posNorm !== '' && $posNorm !== '-') {
                $posPharm = (int) ($posBatch->pharmacy_id ?? 0);
                $positiveMedBatchKeys["{$posPharm}_{$posBatch->medicine_id}_{$posNorm}"] = true;
            }
        }

        $submittedBatchIds = [];
        $submittedMedBatchKeys = [];
        $unmatchedDeficitByKey = [];
        foreach ($submittedReturnItems as $sri) {
            $matchedPositive = false;
            if (!empty($sri->batch_id)) {
                $submittedBatchIds[(int) $sri->batch_id] = true;
                if (isset($positiveBatchIds[(int) $sri->batch_id])) {
                    $matchedPositive = true;
                }
            }
            $normBatch = strtoupper(trim((string) ($sri->batch_number ?? '')));
            $pKey = (int) ($sri->pharmacy_id ?? 0);
            if (!empty($sri->medicine_id) && $normBatch !== '' && $normBatch !== '-') {
                $submittedMedBatchKeys["{$pKey}_{$sri->medicine_id}_{$normBatch}"] = true;
                if (isset($positiveMedBatchKeys["{$pKey}_{$sri->medicine_id}_{$normBatch}"])) {
                    $matchedPositive = true;
                }
            }

            // Retur non-deducted pada batch berbeda/manual (mis. nomor batch pabrik sedangkan stok berada di batch OPN)
            if (!$matchedPositive && empty($sri->stock_deducted) && !empty($sri->medicine_id)) {
                $factor = max(1, (int) ($sri->stock_unit_factor ?? 1));
                $qtyUnits = (float) ($sri->qty ?? 0) * $factor;
                if ($qtyUnits > 0) {
                    $defKey = "{$pKey}_{$sri->medicine_id}";
                    $unmatchedDeficitByKey[$defKey] = ($unmatchedDeficitByKey[$defKey] ?? 0.0) + $qtyUnits;
                }
            }
        }

        // 3b. Hitung defisit batch stok negatif (mis. retur memotong batch pabrik WT0077 menjadi minus sementara stok fisik tercatat di batch OPN)
        $medIdsInPositive = $batches->pluck('medicine_id')->filter()->unique()->values();
        if ($medIdsInPositive->isNotEmpty()) {
            $negativeQuery = Batches::where('stock', '<', 0)
                ->whereIn('medicine_id', $medIdsInPositive);
            if ($pharmacyId) {
                $negativeQuery->where('pharmacy_id', $pharmacyId);
            }
            foreach ($negativeQuery->get(['pharmacy_id', 'medicine_id', 'stock']) as $negBatch) {
                $negStock = (float) str_replace(',', '.', (string) ($negBatch->stock ?? 0));
                if ($negStock < 0) {
                    $defKey = (int) ($negBatch->pharmacy_id ?? 0) . '_' . (int) $negBatch->medicine_id;
                    $unmatchedDeficitByKey[$defKey] = ($unmatchedDeficitByKey[$defKey] ?? 0.0) + abs($negStock);
                }
            }
        }

        // Prioritaskan batch OPN- dan ED terdekat saat menyerap defisit lintas batch
        $batches = $batches->sort(function ($a, $b) {
            $aOpn = str_starts_with(strtoupper(trim((string) ($a->name ?? ''))), 'OPN-') ? 0 : 1;
            $bOpn = str_starts_with(strtoupper(trim((string) ($b->name ?? ''))), 'OPN-') ? 0 : 1;
            if ($aOpn !== $bOpn) return $aOpn <=> $bOpn;
            $cmpDate = strcmp((string) $a->expired_date, (string) $b->expired_date);
            if ($cmpDate !== 0) return $cmpDate;
            return ((int) $a->id) <=> ((int) $b->id);
        })->values();

        // 4. Proses pencocokan ketentuan retur
        $items = collect();

        foreach ($batches as $b) {
            $med = $b->medicines;
            if (!$med) continue;

            // Hanya tampilkan obat/batch yang belum pernah diajukan returnya
            $batchPharmacyId = (int) ($b->pharmacy_id ?? 0);
            $normBatchName = strtoupper(trim((string) ($b->name ?? '')));
            $isAlreadyReturned = isset($submittedBatchIds[(int) $b->id])
                || ($normBatchName !== '' && $normBatchName !== '-' && (
                    isset($submittedMedBatchKeys["{$batchPharmacyId}_{$med->id}_{$normBatchName}"])
                    || isset($submittedMedBatchKeys["0_{$med->id}_{$normBatchName}"])
                ));
            if ($isAlreadyReturned) {
                continue;
            }

            $ri = $b->receivingItems->first();
            $rd = $ri ? $ri->receiving_details : null;
            $invoiceSource = $rd ? 'batch' : null;

            // Fallback referensi faktur: pembelian terakhir obat ini (hanya untuk
            // data faktur/PBF; satuan & harga tetap dihitung dari batch/master).
            if (!$rd && $latestReceiptByMedicine->has($med->id)) {
                $rd = $latestReceiptByMedicine->get($med->id)->receiving_details;
                $invoiceSource = 'latest_purchase';
            }

            // Prioritas PBF: dari receiving_details, fallback ke master obat creditor
            $creditorCode = $rd?->creditor_code ?: $med->creditor?->code;
            $creditorName = $rd?->creditor?->name ?: $med->creditor?->name ?: $creditorCode;

            $factoryId = $med->factory_id;
            $factoryName = $med->factory?->name;

            if (!$creditorCode) continue;

            $policy = $this->findMatchingPolicy($creditorCode, $factoryId, $factoryName, $policies);

            // Hanya proses jika PBF & Pabrik punya ketentuan retur dan diperbolehkan retur (is_returnable = true)
            if (!$policy || !$policy->is_returnable) continue;

            $monthsBefore = (int) ($policy->months_before_ed ?? 0);
            $monthsAfter = (int) ($policy->months_after_ed ?? 0);

            $ed = Carbon::parse($b->expired_date)->startOfMonth();
            $diffMonths = $now->diffInMonths($ed, false);

            // Cek apakah masuk dalam batas retur atau mendekati (hingga H+2 bulan sebelum batas retur)
            $isEligible = ($diffMonths <= $monthsBefore && $diffMonths >= (-1 * $monthsAfter));
            $isApproaching = ($diffMonths > $monthsBefore && $diffMonths <= ($monthsBefore + 2));

            if (!$isEligible && !$isApproaching) {
                continue; // Belum masuk periode peringatan
            }

            // Tentukan tingkat urgensi
            if ($isEligible) {
                if ($diffMonths <= (-1 * $monthsAfter)) {
                    $urgencyKey = 'critical';
                    $urgencyLabel = 'Bulan Terakhir Retur!';
                    $urgencyBadge = 'bg-rose-100 text-rose-800 border-rose-300';
                } elseif ($diffMonths <= 1) {
                    $urgencyKey = 'high';
                    $urgencyLabel = 'Segera Retur (H-' . max(0, $diffMonths) . ' Bln)';
                    $urgencyBadge = 'bg-amber-100 text-amber-800 border-amber-300';
                } else {
                    $urgencyKey = 'eligible';
                    $urgencyLabel = 'Bisa Diretur Sekarang';
                    $urgencyBadge = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                }
            } else {
                $rem = $diffMonths - $monthsBefore;
                $urgencyKey = 'approaching';
                $urgencyLabel = "Mendekati Batas (Sisa {$rem} Bln)";
                $urgencyBadge = 'bg-blue-100 text-blue-800 border-blue-300';
            }

            // Batas akhir pengajuan retur
            $deadlineDate = $ed->copy()->subMonths($monthsBefore)->endOfMonth();

            $stock = (float) $b->stock; // Always stored in basic unit (e.g. TAB / BTL)
            foreach (["{$batchPharmacyId}_{$med->id}", "0_{$med->id}"] as $defKey) {
                $availableDeficit = $unmatchedDeficitByKey[$defKey] ?? 0.0;
                if ($availableDeficit > 0 && $stock > 0) {
                    $consumed = min($stock, $availableDeficit);
                    $stock = round($stock - $consumed, 4);
                    $unmatchedDeficitByKey[$defKey] = round($availableDeficit - $consumed, 4);
                }
            }
            if ($stock <= 0.0001) {
                continue;
            }

            $stockUnit = $med->unit ?: 'PCS';
            $content = (int) ($med->content ?: 1);
            if ($content < 1) $content = 1;
            $packaging = $med->packaging ?: ($content > 1 ? 'BOX' : $stockUnit);

            $isPack = false;
            $factor = 1;
            if ($ri) {
                $isPack = $ri->receipt_is_pack !== null ? (bool) $ri->receipt_is_pack : false;
                $factor = (int) ($ri->stock_unit_factor ?: ($isPack && $content > 1 ? $content : 1));
                if ($factor < 1) $factor = 1;
            } else {
                $isPack = ($content > 1 && !empty($med->packaging));
                $factor = $isPack ? $content : 1;
            }

            $rawPrice = (float) ($ri?->raw_price ?: $med->raw_price ?: 0);

            if ($isPack && $factor > 1) {
                $packPrice = $rawPrice;
                $pricePerUnit = round($rawPrice / $factor, 4);
                $estimatedValue = $stock * $pricePerUnit;
                $stockPack = round($stock / $factor, 2);
                $stockDisplay = "{$stock} {$stockUnit} (" . (float)$stockPack . " {$packaging})";
                $defaultUnit = $packaging;
                $defaultUnitPrice = $packPrice;
            } else {
                $pricePerUnit = $rawPrice;
                $packPrice = round($rawPrice * $factor, 2);
                $estimatedValue = $stock * $pricePerUnit;
                $stockPack = $stock;
                $stockDisplay = "{$stock} {$stockUnit}";
                $defaultUnit = $stockUnit;
                $defaultUnitPrice = $pricePerUnit;
            }

            $items->push([
                'batch_id' => $b->id,
                'batch_number' => $b->name,
                'medicine_id' => $med->id,
                'medicine_name' => $med->name,
                'unit' => $defaultUnit,
                'retur_type' => ($isPack && $factor > 1) ? 'packaging' : 'unit',
                'stock_unit_factor' => ($isPack && $factor > 1) ? $factor : 1,
                'packaging' => $packaging,
                'stock_unit' => $stockUnit,
                'content' => $factor,
                'has_pack' => ($factor > 1),
                'factory_id' => $factoryId,
                'factory_name' => $factoryName ?: '-',
                'creditor_code' => $creditorCode,
                'creditor_name' => $creditorName ?: '-',
                'stock' => $stock,
                'stock_pack' => $stockPack,
                'stock_display' => $stockDisplay,
                'expired_date' => $b->expired_date,
                'expired_date_display' => Carbon::parse($b->expired_date)->format('d/m/Y'),
                'months_before_ed' => $monthsBefore,
                'months_after_ed' => $monthsAfter,
                'rule_text' => $policy->notes ?: "Maks H-{$monthsBefore} bln sebelum ED",
                'deadline_date' => $deadlineDate->format('Y-m-d'),
                'deadline_display' => $deadlineDate->format('d/m/Y'),
                'diff_months' => $diffMonths,
                'urgency_key' => $urgencyKey,
                'urgency_label' => $urgencyLabel,
                'urgency_badge' => $urgencyBadge,
                'is_eligible' => $isEligible,
                'invoice_number' => $rd?->invoice_number ?: '-',
                'invoice_date' => $rd?->invoice_date ?: null,
                'receiving_id' => $rd?->id ?: null,
                'invoice_source' => $invoiceSource,
                'unit_price' => $defaultUnitPrice,
                'price_per_unit' => $pricePerUnit,
                'pack_price' => $packPrice,
                'estimated_value' => $estimatedValue,
                'active_return_status' => null,
            ]);
        }

        // Summary Stats sebelum filter pencarian
        $eligibleTotal = $items->where('is_eligible', true)->count();
        $approachingTotal = $items->where('is_eligible', false)->count();
        $totalValueAtRisk = $items->where('is_eligible', true)->sum('estimated_value');
        $totalStockAtRisk = $items->where('is_eligible', true)->sum('stock');
        $uniqueCreditors = $items->pluck('creditor_code')->unique()->count();

        // 5. Terapkan filter
        if (!empty($filters['search'])) {
            $search = strtolower(trim($filters['search']));
            $items = $items->filter(function ($it) use ($search) {
                return str_contains(strtolower($it['medicine_name']), $search)
                    || str_contains(strtolower($it['batch_number']), $search)
                    || str_contains(strtolower($it['factory_name']), $search)
                    || str_contains(strtolower($it['invoice_number']), $search)
                    || str_contains(strtolower($it['creditor_name']), $search);
            });
        }

        if (!empty($filters['creditor_code'])) {
            $items = $items->where('creditor_code', $filters['creditor_code']);
        }

        if (!empty($filters['urgency'])) {
            if ($filters['urgency'] === 'eligible') {
                $items = $items->where('is_eligible', true);
            } elseif ($filters['urgency'] === 'approaching') {
                $items = $items->where('is_eligible', false);
            } elseif (in_array($filters['urgency'], ['critical', 'high'])) {
                $items = $items->where('urgency_key', $filters['urgency']);
            }
        }

        // 6. Urutkan: Critical -> High -> Eligible -> Approaching, lalu ED terdekat
        $items = $items->sort(function ($a, $b) {
            $urgencyOrder = ['critical' => 1, 'high' => 2, 'eligible' => 3, 'approaching' => 4];
            $uA = $urgencyOrder[$a['urgency_key']] ?? 5;
            $uB = $urgencyOrder[$b['urgency_key']] ?? 5;
            if ($uA !== $uB) return $uA <=> $uB;
            return strcmp($a['expired_date'], $b['expired_date']);
        })->values();

        // 7. Manual pagination
        $currentPage = LengthAwarePaginator::resolveCurrentPage('approaching_page');
        $total = $items->count();
        $pagedData = $items->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $pagedData,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'approaching_page',
                'query' => request()->query(),
            ]
        );

        return [
            'data' => $paginator,
            'stats' => [
                'eligible_count' => $eligibleTotal,
                'approaching_count' => $approachingTotal,
                'total_count' => $eligibleTotal + $approachingTotal,
                'total_value_at_risk' => $totalValueAtRisk,
                'total_stock_at_risk' => $totalStockAtRisk,
                'total_creditors' => $uniqueCreditors,
            ],
        ];
    }

    /**
     * Hitung ringkasan cepat untuk widget dashboard dan sidebar badge
     */
    public function getQuickSummary(?int $pharmacyId = null): array
    {
        $pharmacyId = $pharmacyId ?: (function_exists('getActivePharmacyId') ? getActivePharmacyId() : 1);
        $res = $this->getApproachingReturns($pharmacyId, [], 1);
        return $res['stats'];
    }
}
