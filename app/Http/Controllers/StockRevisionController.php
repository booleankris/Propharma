<?php

namespace App\Http\Controllers;

use App\Models\Batches;
use App\Models\Etalases;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\MedicineTransferItems;
use App\Models\Pharmacies;
use App\Models\StockRevision;
use App\Services\StockLedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockRevisionController extends Controller
{
    public function __construct(private StockLedgerService $ledgerService)
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            $allowedRoles = [
                'General Manager',
                'Koordinator',
                'koordinator',
                'administrator',
            ];

            if (!$user || !hasFeatureAccess('supplies.revision', $allowedRoles, $user)) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'message' => 'Akses ditolak: Anda tidak memiliki izin untuk melakukan Revisi Stok.',
                    ], 403);
                }
                abort(403, 'Akses ditolak: Anda tidak memiliki izin untuk melakukan Revisi Stok.');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $activePharmacyId = (int) getActivePharmacyId();
        $activePharmacy = Pharmacies::find($activePharmacyId);
        $canAccessWarehouse = canAccessWarehouseStock($activePharmacyId);
        $defaultMode = isWarehousePharmacy($activePharmacyId) ? 'gudang' : 'pelayanan';

        return view('supply.stockRevision', [
            'activePharmacy'     => $activePharmacy,
            'canAccessWarehouse' => $canAccessWarehouse,
            'defaultMode'        => $defaultMode,
            'preselectedMedId'   => $request->integer('medicine_id') ?: null,
        ]);
    }

    public function searchMedicines(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }

        $activePharmacyId = (int) getActivePharmacyId();
        $targetMode = $this->resolveTargetMode($request->input('target_mode'), $activePharmacyId);
        $targetPharmacyId = $this->resolveTargetPharmacyId($targetMode, $activePharmacyId);

        $medicines = Medicines::query()
            ->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'code', 'name', 'unit']);

        if ($medicines->isEmpty()) {
            return response()->json([]);
        }

        $medIds = $medicines->pluck('id')->all();

        if ($targetMode === 'gudang') {
            $stockMap = Batches::whereIn('medicine_id', $medIds)
                ->where('pharmacy_id', $targetPharmacyId)
                ->groupBy('medicine_id')
                ->selectRaw('medicine_id, COALESCE(SUM(stock), 0) as total_stock, MIN(stock) as min_stock')
                ->get()
                ->keyBy('medicine_id');
        } else {
            $stockMap = MedicineTransferItems::join('batches', 'batches.id', '=', 'medicine_transfer_items.batches_id')
                ->whereIn('batches.medicine_id', $medIds)
                ->where('batches.pharmacy_id', $targetPharmacyId)
                ->where('medicine_transfer_items.status', 1)
                ->where(function ($query) {
                    $query->whereNull('medicine_transfer_items.source_type')
                        ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                })
                ->groupBy('batches.medicine_id')
                ->selectRaw('batches.medicine_id, COALESCE(SUM(medicine_transfer_items.qty), 0) as total_stock, MIN(medicine_transfer_items.qty) as min_stock')
                ->get()
                ->keyBy('medicine_id');
        }

        $results = $medicines->map(function ($m) use ($stockMap) {
            $row = $stockMap->get($m->id);
            return [
                'id'           => $m->id,
                'code'         => $m->code,
                'name'         => $m->name,
                'unit'         => $m->unit ?: 'PCS',
                'db_stock'     => $row ? (int) $row->total_stock : 0,
                'has_negative' => $row ? ((float) $row->min_stock < 0) : false,
            ];
        });

        return response()->json($results->values());
    }

    public function inspectMedicine(Request $request, int $medicineId)
    {
        $medicine = Medicines::findOrFail($medicineId);
        $activePharmacyId = (int) getActivePharmacyId();
        $targetMode = $this->resolveTargetMode($request->input('target_mode'), $activePharmacyId);

        $summary = $this->ledgerService->getMedicineLedgerSummary($medicine->id, $activePharmacyId, $targetMode);
        $targetPharmacyId = $summary['target_pharmacy_id'];
        $targetPharmacy = Pharmacies::find($targetPharmacyId);

        $batches = $this->loadTargetBatches($medicine->id, $targetPharmacyId, $targetMode);
        $etalases = $targetMode === 'pelayanan'
            ? Etalases::where('pharmacy_id', $targetPharmacyId)
                ->orderByDesc('status')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name])
                ->values()
                ->all()
            : [];

        return response()->json([
            'medicine' => [
                'id'   => $medicine->id,
                'code' => $medicine->code,
                'name' => $medicine->name,
                'unit' => $medicine->unit ?: 'PCS',
            ],
            'target_pharmacy' => [
                'id'   => $targetPharmacyId,
                'name' => $targetPharmacy?->name ?? '-',
            ],
            'summary'  => $summary,
            'batches'  => $batches,
            'etalases' => $etalases,
        ]);
    }

    public function anomalies(Request $request)
    {
        $activePharmacyId = (int) getActivePharmacyId();
        $targetMode = $this->resolveTargetMode($request->input('target_mode'), $activePharmacyId);
        $targetPharmacyId = $this->resolveTargetPharmacyId($targetMode, $activePharmacyId);

        if ($targetMode === 'gudang') {
            $negativeMedIds = Batches::where('pharmacy_id', $targetPharmacyId)
                ->where('stock', '<', 0)
                ->distinct()
                ->pluck('medicine_id')
                ->all();
        } else {
            $negativeMedIds = MedicineTransferItems::join('batches', 'batches.id', '=', 'medicine_transfer_items.batches_id')
                ->where('batches.pharmacy_id', $targetPharmacyId)
                ->where('medicine_transfer_items.status', 1)
                ->where(function ($q) {
                    $q->whereNull('medicine_transfer_items.source_type')
                        ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                })
                ->where('medicine_transfer_items.qty', '<', 0)
                ->distinct()
                ->pluck('batches.medicine_id')
                ->all();
        }

        // Sertakan juga obat yang punya mutasi retur beli / opname / mutasi dalam 30 hari terakhir (maks 60)
        $recentMedIds = ItemsLog::whereHas('batches', fn ($b) => $b->where('pharmacy_id', $targetPharmacyId))
            ->whereIn('status', [4, 5, 6, 7, 8, 9])
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->distinct()
            ->limit(60)
            ->pluck('medicine_id')
            ->all();

        $candidateIds = array_values(array_unique(array_merge($negativeMedIds, $recentMedIds)));
        if (empty($candidateIds)) {
            return response()->json([]);
        }

        $meds = Medicines::whereIn('id', $candidateIds)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'unit'])
            ->keyBy('id');

        $negativeSet = array_flip($negativeMedIds);
        $items = [];

        foreach ($meds as $medId => $med) {
            $hasNegative = isset($negativeSet[$medId]);
            $summary = $this->ledgerService->getMedicineLedgerSummary($medId, $activePharmacyId, $targetMode);

            if ($hasNegative || $summary['discrepancy'] !== 0) {
                $items[] = [
                    'medicine_id'        => $medId,
                    'code'               => $med->code,
                    'name'               => $med->name,
                    'unit'               => $med->unit ?: 'PCS',
                    'opening_stock'      => $summary['opening_stock'],
                    'ledger_balance'     => $summary['ledger_balance'],
                    'db_stock'           => $summary['db_stock'],
                    'discrepancy'        => $summary['discrepancy'],
                    'has_negative_batch' => $hasNegative,
                ];
            }
        }

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'medicine_id'                 => 'required|integer|exists:medicines,id',
            'target_mode'                 => 'required|in:gudang,pelayanan',
            'reason'                      => 'required|string|min:3|max:255',
            'notes'                       => 'nullable|string|max:1000',
            'batches'                     => 'nullable|array',
            'batches.*.batch_id'          => 'required|integer|exists:batches,id',
            'batches.*.new_qty'           => 'required|integer|min:0',
            'new_batches'                 => 'nullable|array',
            'new_batches.*.name'          => 'required|string|max:255',
            'new_batches.*.expired_date'  => 'nullable|date',
            'new_batches.*.etalases_id'   => 'nullable|integer',
            'new_batches.*.new_qty'       => 'required|integer|min:0',
        ]);

        $activePharmacyId = (int) getActivePharmacyId();
        $targetMode = $validated['target_mode'];

        if ($targetMode === 'gudang' && !canAccessWarehouseStock($activePharmacyId)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk merevisi stok Gudang.',
            ], 403);
        }

        $existingRows = $validated['batches'] ?? [];
        $newBatchRows = $validated['new_batches'] ?? [];
        if (empty($existingRows) && empty($newBatchRows)) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal 1 batch atau 1 batch baru harus diisi.',
            ], 422);
        }

        $targetPharmacyId = $this->resolveTargetPharmacyId($targetMode, $activePharmacyId);
        $medicine = Medicines::findOrFail((int) $validated['medicine_id']);

        $requestedByBatchId = [];
        foreach ($existingRows as $row) {
            $requestedByBatchId[(int) $row['batch_id']] = (int) $row['new_qty'];
        }

        try {
            $result = DB::transaction(function () use ($medicine, $activePharmacyId, $targetPharmacyId, $targetMode, $validated, $requestedByBatchId, $newBatchRows) {
                $summaryBefore = $this->ledgerService->getMedicineLedgerSummary($medicine->id, $activePharmacyId, $targetMode);
                $dbBefore = $summaryBefore['db_stock'];
                $ledgerBefore = $summaryBefore['ledger_balance'];

                $batchDetails = [];
                $primaryBatchId = null;
                $maxAbsChange = -1;

                if (!empty($requestedByBatchId)) {
                    $batches = Batches::where('medicine_id', $medicine->id)
                        ->where('pharmacy_id', $targetPharmacyId)
                        ->whereIn('id', array_keys($requestedByBatchId))
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    if ($batches->isEmpty()) {
                        throw new \RuntimeException('Batch tujuan tidak ditemukan pada lokasi stok aktif.');
                    }

                    foreach ($requestedByBatchId as $batchId => $newQty) {
                        $batch = $batches->get($batchId);
                        if (!$batch) {
                            throw new \RuntimeException("Batch ID {$batchId} tidak valid untuk obat dan lokasi ini.");
                        }

                        if ($targetMode === 'gudang') {
                            $beforeQty = (int) $batch->stock;
                            $batch->stock = $newQty;
                            $batch->save();
                        } else {
                            $transfers = MedicineTransferItems::where('batches_id', $batch->id)
                                ->where('status', 1)
                                ->where(function ($q) {
                                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                                })
                                ->orderBy('id')
                                ->lockForUpdate()
                                ->get();

                            $beforeQty = (int) $transfers->sum('qty');

                            if ($transfers->isNotEmpty()) {
                                $primaryTransfer = $transfers->first();
                                $primaryTransfer->qty = $newQty;
                                $primaryTransfer->save();

                                foreach ($transfers->slice(1) as $extra) {
                                    if ((int) $extra->qty !== 0) {
                                        $extra->qty = 0;
                                        $extra->save();
                                    }
                                }
                            } elseif ($newQty > 0) {
                                $this->createRevisionTransferItem($batch, $targetPharmacyId, $newQty);
                            }
                        }

                        $change = abs($newQty - $beforeQty);
                        if ($primaryBatchId === null || $change > $maxAbsChange) {
                            $maxAbsChange = $change;
                            $primaryBatchId = $batch->id;
                        }

                        $batchDetails[] = [
                            'batch_id'     => $batch->id,
                            'batch_name'   => $batch->name,
                            'expired_date' => $batch->expired_date,
                            'before'       => $beforeQty,
                            'after'        => $newQty,
                            'is_new'       => false,
                        ];
                    }
                }

                // Proses penambahan batch baru jika ada
                $seenNewNames = [];
                foreach ($newBatchRows as $newRow) {
                    $batchName = trim((string) ($newRow['name'] ?? ''));
                    if ($batchName === '') {
                        throw new \RuntimeException('Nama batch baru tidak boleh kosong.');
                    }

                    $lowerName = mb_strtolower($batchName);
                    if (isset($seenNewNames[$lowerName])) {
                        throw new \RuntimeException("Nama batch baru '{$batchName}' terduplikat.");
                    }
                    $seenNewNames[$lowerName] = true;

                    $existsInDb = Batches::where('medicine_id', $medicine->id)
                        ->where('pharmacy_id', $targetPharmacyId)
                        ->whereRaw('LOWER(TRIM(name)) = ?', [$lowerName])
                        ->exists();

                    if ($existsInDb) {
                        throw new \RuntimeException("Batch '{$batchName}' sudah terdaftar di lokasi ini. Ubah stok langsung pada baris batch yang sudah ada.");
                    }

                    $newQty = (int) $newRow['new_qty'];
                    $expiredDate = !empty($newRow['expired_date'])
                        ? Carbon::parse($newRow['expired_date'])->toDateString()
                        : now()->addYears(2)->toDateString();

                    $newBatch = Batches::create([
                        'medicine_id'  => $medicine->id,
                        'pharmacy_id'  => $targetPharmacyId,
                        'name'         => $batchName,
                        'expired_date' => $expiredDate,
                        'stock'        => $targetMode === 'gudang' ? $newQty : 0,
                        'status'       => 1,
                    ]);

                    if ($targetMode === 'pelayanan' && $newQty > 0) {
                        $etalaseId = !empty($newRow['etalases_id']) ? (int) $newRow['etalases_id'] : null;
                        $this->createRevisionTransferItem($newBatch, $targetPharmacyId, $newQty, $etalaseId);
                    }

                    if ($primaryBatchId === null || $newQty > $maxAbsChange) {
                        $maxAbsChange = $newQty;
                        $primaryBatchId = $newBatch->id;
                    }

                    $batchDetails[] = [
                        'batch_id'     => $newBatch->id,
                        'batch_name'   => $newBatch->name,
                        'expired_date' => $newBatch->expired_date,
                        'before'       => 0,
                        'after'        => $newQty,
                        'is_new'       => true,
                    ];
                }

                $summaryAfter = $this->ledgerService->getMedicineLedgerSummary($medicine->id, $activePharmacyId, $targetMode);
                $dbAfter = $summaryAfter['db_stock'];

                if (empty($newBatchRows) && $dbAfter === $dbBefore && $dbAfter === $ledgerBefore) {
                    throw new \RuntimeException('Tidak ada perubahan stok maupun selisih saldo yang perlu direvisi.');
                }

                // Update stok master medicines agar selalu konsisten dengan stok real-time
                $medicine->update([
                    'stock' => $this->ledgerService->calculateRealtimeStock($medicine->id, $activePharmacyId, 'total'),
                ]);

                // Hitung delta agar SALDO akhir Kartu Stok menjadi persis sama dengan $dbAfter,
                // sementara SALDO AWAL (SO-SALDO-AWAL) tetap terkunci dan tidak berubah.
                $ledgerDelta = $dbAfter - $ledgerBefore;
                $ledgerAfter = $ledgerBefore + $ledgerDelta;

                $now = Carbon::now();
                $revisionCode = $this->generateRevisionCode($now);

                $itemsLog = ItemsLog::create([
                    'transaction_code' => $revisionCode,
                    'code'             => $this->generateItemsLogCode($now),
                    'type'             => 'REVISI STOK',
                    'medicine_id'      => $medicine->id,
                    'batches_id'       => $primaryBatchId,
                    'qty'              => abs($ledgerDelta),
                    'qty_before'       => $ledgerBefore,
                    'qty_after'        => $ledgerAfter,
                    'total'            => $ledgerDelta,
                    'date'             => $now->toDateTimeString(),
                    'status'           => 6,
                    'user_id'          => auth()->id() ?: 1,
                ]);

                $revision = null;
                if (Schema::hasTable('stock_revisions')) {
                    $revision = StockRevision::create([
                        'revision_code'         => $revisionCode,
                        'pharmacy_id'           => $targetPharmacyId,
                        'target_mode'           => $targetMode,
                        'medicine_id'           => $medicine->id,
                        'user_id'               => auth()->id() ?: 1,
                        'reason'                => trim($validated['reason']),
                        'notes'                 => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
                        'db_stock_before'       => $dbBefore,
                        'db_stock_after'        => $dbAfter,
                        'ledger_balance_before' => $ledgerBefore,
                        'ledger_balance_after'  => $ledgerAfter,
                        'ledger_delta'          => $ledgerDelta,
                        'batch_details'         => $batchDetails,
                        'items_log_id'          => $itemsLog->id,
                    ]);
                }

                return [
                    'revision_code'         => $revisionCode,
                    'revision'              => $revision,
                    'items_log_id'          => $itemsLog->id,
                    'db_stock_before'       => $dbBefore,
                    'db_stock_after'        => $dbAfter,
                    'ledger_balance_before' => $ledgerBefore,
                    'ledger_balance_after'  => $ledgerAfter,
                    'ledger_delta'          => $ledgerDelta,
                    'opening_stock'         => $summaryBefore['opening_stock'],
                ];
            });

            return response()->json([
                'success' => true,
                'message' => "Revisi stok ({$result['revision_code']}) berhasil disimpan dan tercatat di Kartu Stok.",
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function history(Request $request)
    {
        $activePharmacyId = (int) getActivePharmacyId();
        $targetMode = $this->resolveTargetMode($request->input('target_mode'), $activePharmacyId);
        $targetPharmacyId = $this->resolveTargetPharmacyId($targetMode, $activePharmacyId);

        if (Schema::hasTable('stock_revisions')) {
            $rows = StockRevision::with(['medicine:id,code,name,unit', 'user:id,name', 'pharmacy:id,name'])
                ->where('pharmacy_id', $targetPharmacyId)
                ->when($request->filled('medicine_id'), fn ($q) => $q->where('medicine_id', (int) $request->medicine_id))
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(function ($r) {
                    return [
                        'id'                    => $r->id,
                        'revision_code'         => $r->revision_code,
                        'date'                  => $r->created_at?->format('Y-m-d H:i:s') ?? '-',
                        'medicine_name'         => $r->medicine?->name ?? '-',
                        'medicine_code'         => $r->medicine?->code ?? '-',
                        'unit'                  => $r->medicine?->unit ?? 'PCS',
                        'target_mode'           => strtoupper($r->target_mode),
                        'pharmacy_name'         => $r->pharmacy?->name ?? '-',
                        'db_stock_before'       => (int) $r->db_stock_before,
                        'db_stock_after'        => (int) $r->db_stock_after,
                        'ledger_balance_before' => (int) $r->ledger_balance_before,
                        'ledger_balance_after'  => (int) $r->ledger_balance_after,
                        'ledger_delta'          => (int) $r->ledger_delta,
                        'reason'                => $r->reason,
                        'notes'                 => $r->notes,
                        'user_name'             => $r->user?->name ?? '-',
                        'batch_details'         => $r->batch_details ?? [],
                    ];
                });

            return response()->json($rows);
        }

        $logs = ItemsLog::with(['medicines:id,code,name,unit', 'users:id,name', 'batches:id,name,pharmacy_id'])
            ->where('type', 'REVISI STOK')
            ->whereHas('batches', fn ($b) => $b->where('pharmacy_id', $targetPharmacyId))
            ->when($request->filled('medicine_id'), fn ($q) => $q->where('medicine_id', (int) $request->medicine_id))
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function ($l) {
                $delta = (int) $l->qty_after - (int) $l->qty_before;
                return [
                    'id'                    => $l->id,
                    'revision_code'         => $l->transaction_code,
                    'date'                  => $l->date?->format('Y-m-d H:i:s') ?? (string) $l->date,
                    'medicine_name'         => $l->medicines?->name ?? '-',
                    'medicine_code'         => $l->medicines?->code ?? '-',
                    'unit'                  => $l->medicines?->unit ?? 'PCS',
                    'target_mode'           => strtoupper($targetMode),
                    'pharmacy_name'         => '-',
                    'db_stock_before'       => (int) $l->qty_before,
                    'db_stock_after'        => (int) $l->qty_after,
                    'ledger_balance_before' => (int) $l->qty_before,
                    'ledger_balance_after'  => (int) $l->qty_after,
                    'ledger_delta'          => $delta,
                    'reason'                => 'Revisi Stok (Kartu Stok)',
                    'notes'                 => null,
                    'user_name'             => $l->users?->name ?? '-',
                    'batch_details'         => [],
                ];
            });

        return response()->json($logs);
    }

    private function resolveTargetMode(?string $requestedMode, int $activePharmacyId): string
    {
        if ($requestedMode === 'gudang' && canAccessWarehouseStock($activePharmacyId)) {
            return 'gudang';
        }
        if ($requestedMode === 'pelayanan') {
            return 'pelayanan';
        }
        return isWarehousePharmacy($activePharmacyId) ? 'gudang' : 'pelayanan';
    }

    private function resolveTargetPharmacyId(string $targetMode, int $activePharmacyId): int
    {
        if ($targetMode === 'gudang') {
            return (int) getWarehousePharmacyId();
        }
        return isWarehousePharmacy($activePharmacyId) ? 1 : $activePharmacyId;
    }

    private function loadTargetBatches(int $medicineId, int $targetPharmacyId, string $targetMode): array
    {
        $batches = Batches::where('medicine_id', $medicineId)
            ->where('pharmacy_id', $targetPharmacyId)
            ->orderBy('expired_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($batches->isEmpty()) {
            return [];
        }

        $transferQtyByBatch = collect();
        $etalaseNameByBatch = [];

        if ($targetMode === 'pelayanan') {
            $mtiRows = MedicineTransferItems::with('etalases:id,name')
                ->whereIn('batches_id', $batches->pluck('id')->all())
                ->where('status', 1)
                ->where(function ($q) {
                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                })
                ->get();

            foreach ($mtiRows->groupBy('batches_id') as $bId => $rows) {
                $transferQtyByBatch->put((int) $bId, (int) $rows->sum('qty'));
                $firstEtalase = $rows->first(fn ($r) => $r->etalases !== null)?->etalases?->name;
                if ($firstEtalase) {
                    $etalaseNameByBatch[(int) $bId] = $firstEtalase;
                }
            }
        }

        return $batches->map(function ($b) use ($targetMode, $transferQtyByBatch, $etalaseNameByBatch) {
            $currentQty = $targetMode === 'gudang'
                ? (int) $b->stock
                : (int) ($transferQtyByBatch->get((int) $b->id, 0));

            return [
                'batch_id'     => $b->id,
                'name'         => $b->name,
                'expired_date' => $b->expired_date ? Carbon::parse($b->expired_date)->format('d/m/Y') : '-',
                'current_qty'  => $currentQty,
                'etalase_name' => $etalaseNameByBatch[(int) $b->id] ?? null,
                'is_negative'  => $currentQty < 0,
            ];
        })->values()->all();
    }

    private function createRevisionTransferItem(Batches $batch, int $pharmacyId, int $qty, ?int $preferredEtalaseId = null): void
    {
        $data = [
            'batches_id' => $batch->id,
            'qty'        => $qty,
        ];

        if (Schema::hasColumn('medicine_transfer_items', 'medicine_transfer_id')) {
            $initCode = 'TRF-INIT-REV-' . $pharmacyId;
            $headerId = Schema::hasTable('medicine_transfers')
                ? DB::table('medicine_transfers')->where('code', $initCode)->value('id')
                : null;

            if (!$headerId && Schema::hasTable('medicine_transfers')) {
                $headerData = [
                    'code'       => $initCode,
                    'status'     => 1,
                    'user_id'    => auth()->id() ?: 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('medicine_transfers', 'source_pharmacy_id')) {
                    $headerData['source_pharmacy_id'] = $pharmacyId;
                }
                if (Schema::hasColumn('medicine_transfers', 'destination_pharmacy_id')) {
                    $headerData['destination_pharmacy_id'] = $pharmacyId;
                }
                $headerId = DB::table('medicine_transfers')->insertGetId($headerData);
            }

            if ($headerId) {
                $data['medicine_transfer_id'] = $headerId;
            }
        }

        if (Schema::hasColumn('medicine_transfer_items', 'source_batches_id')) {
            $data['source_batches_id'] = $batch->id;
        }
        if (Schema::hasColumn('medicine_transfer_items', 'source_type')) {
            $data['source_type'] = 'pelayanan';
        }
        if (Schema::hasColumn('medicine_transfer_items', 'status')) {
            $data['status'] = 1;
        }
        if (Schema::hasColumn('medicine_transfer_items', 'original_qty')) {
            $data['original_qty'] = 0;
        }
        if (Schema::hasColumn('medicine_transfer_items', 'etalases_id')) {
            $etalaseId = $preferredEtalaseId;
            if ($etalaseId && Schema::hasTable('etalases')) {
                $valid = Etalases::where('id', $etalaseId)->where('pharmacy_id', $pharmacyId)->exists();
                if (!$valid) {
                    $etalaseId = null;
                }
            }

            if (!$etalaseId) {
                $etalaseId = MedicineTransferItems::whereHas('batches', function ($q) use ($batch, $pharmacyId) {
                        $q->where('pharmacy_id', $pharmacyId)->where('medicine_id', $batch->medicine_id);
                    })
                    ->whereNotNull('etalases_id')
                    ->value('etalases_id');
            }

            if (!$etalaseId && Schema::hasTable('etalases')) {
                $etalaseId = Etalases::where('pharmacy_id', $pharmacyId)
                    ->orderByDesc('status')
                    ->orderBy('id')
                    ->value('id') ?? Etalases::orderBy('id')->value('id');
            }

            if ($etalaseId) {
                $data['etalases_id'] = $etalaseId;
            }
        }

        MedicineTransferItems::create($data);
    }

    private function generateRevisionCode(Carbon $now): string
    {
        $prefix = 'REV-' . $now->format('ym') . '-';
        $maxSeq = 0;

        $codes = ItemsLog::where('transaction_code', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->pluck('transaction_code');

        if (Schema::hasTable('stock_revisions')) {
            $codes = $codes->merge(
                StockRevision::where('revision_code', 'like', "{$prefix}%")
                    ->lockForUpdate()
                    ->pluck('revision_code')
            );
        }

        foreach ($codes as $code) {
            if (preg_match('/^REV-\d{4}-(\d+)/', (string) $code, $m)) {
                $seq = (int) $m[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        return $prefix . str_pad((string) ($maxSeq + 1), 4, '0', STR_PAD_LEFT);
    }

    private function generateItemsLogCode(Carbon $now): string
    {
        $prefix = $now->format('ym') . 'LOG-';
        $lastCode = ItemsLog::where('code', 'like', "{$prefix}%")
            ->orderByRaw('LENGTH(code) DESC, code DESC')
            ->lockForUpdate()
            ->value('code');

        $nextNumber = 1;
        if ($lastCode) {
            $numericPart = substr((string) $lastCode, strlen($prefix));
            $nextNumber = ((int) preg_replace('/\D/', '', $numericPart)) + 1;
        }

        $code = $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        while (ItemsLog::where('code', $code)->exists()) {
            $nextNumber++;
            $code = $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $code;
    }
}
