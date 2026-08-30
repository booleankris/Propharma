<?php

namespace App\Http\Controllers;

use App\Models\Batches;
use App\Models\Items;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\MedicineTransferItems;
use App\Models\MedicineTransfers;
use App\Models\Pharmacies;
use App\Models\Transfers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TransfersExport;
use App\Models\ExportJob;
use App\Jobs\ProcessTransfersExport;

class TransfersController extends Controller
{
    function generateItemsLogCode()
    {
        $now = Carbon::now();

        $year = $now->format('y');
        $month = $now->format('m');
        $prefix = "{$year}{$month}LOG-";

        $lastCode = ItemsLog::where('code', 'like', "{$prefix}%")
            ->orderBy('code', 'desc')
            ->value('code');

        if ($lastCode) {
            $lastNumber = (int) substr($lastCode, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
    function generateTransfersCode()
    {
        $now = Carbon::now();

        $year = $now->format('y');
        $month = $now->format('m');
        $prefix = "{$year}{$month}MUT";

        $lastCode = Transfers::where('code', 'like', "{$prefix}%")
            ->orderBy('code', 'desc')
            ->value('code');

        if ($lastCode) {
            $lastNumber = (int) substr($lastCode, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
    public function searchBatches(Request $request)
    {
        $search = trim($request->input('search', ''));

        if (blank($search)) {
            return response()->json(['data' => []]);
        }

        $pharmacyId = getActivePharmacyId();

        $query = Batches::query()
            ->with(['medicines', 'medicine_transfer_items' => function ($q) {
                $q->where('qty', '>', 0)->where('status', 1);
            }])
            ->where('pharmacy_id', $pharmacyId)
            ->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhereHas(
                        'medicines',
                        fn($qMed) =>
                        $qMed->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('code', 'LIKE', "%{$search}%")
                    );
            });

        // Warehouse: show batches with gudang stock
        // Cabang / Pelayanan: show batches with pelayanan stock only
        $isWarehouse = isWarehousePharmacy($pharmacyId);

        if ($isWarehouse) {
            $query->whereRaw('batches.stock > (
                SELECT COALESCE(SUM(mti.qty), 0)
                FROM medicine_transfer_items mti
                WHERE mti.source_batches_id = batches.id
                  AND mti.source_type = "gudang"
                  AND mti.status = 0
            )');
        } else {
            $query->whereRaw('(
                SELECT COALESCE(SUM(mti1.qty), 0)
                FROM medicine_transfer_items mti1
                WHERE mti1.batches_id = batches.id
                  AND mti1.status = 1
                  AND (mti1.source_type IS NULL OR mti1.source_type != "retur_gudang")
            ) > (
                SELECT COALESCE(SUM(mti0.qty), 0)
                FROM medicine_transfer_items mti0
                WHERE mti0.source_batches_id = batches.id
                  AND mti0.source_type = "pelayanan"
                  AND mti0.status = 0
            )');
        }

        $data = $query->paginate(20);

        // Transform
        $results = collect();
        $data->getCollection()->each(function ($item) use ($isWarehouse, &$results) {
            if ($isWarehouse) {
                $pendingOutgoing = (int) MedicineTransferItems::where('source_batches_id', $item->id)
                    ->where('source_type', 'gudang')
                    ->where('status', 0)
                    ->sum('qty');
                $availStock = max(0, (int) $item->stock - $pendingOutgoing);

                if ($availStock > 0) {
                    $results->push([
                        'id' => $item->id,
                        'batches_name' => $item->name,
                        'name' => $item->medicines?->name ?? '??',
                        'stock' => $availStock,
                        'unit' => $item->medicines?->unit ?? '??',
                        'expired_date' => $item->expired_date ? \Carbon\Carbon::parse($item->expired_date)->format('d/m/Y') : '-',
                        'source_type' => 'gudang',
                    ]);
                }
            } else {
                $currentPelayanan = (int) $item->medicine_transfer_items
                    ->where('status', 1)
                    ->where(function ($q) {
                        return is_null($q->source_type) || $q->source_type !== 'retur_gudang';
                    })
                    ->sum('qty');
                $pendingOutgoing = (int) MedicineTransferItems::where('source_batches_id', $item->id)
                    ->where('source_type', 'pelayanan')
                    ->where('status', 0)
                    ->sum('qty');
                $availStock = max(0, $currentPelayanan - $pendingOutgoing);

                if ($availStock > 0) {
                    $results->push([
                        'id' => $item->id,
                        'batches_name' => $item->name,
                        'name' => $item->medicines?->name ?? '??',
                        'stock' => $availStock,
                        'unit' => $item->medicines?->unit ?? '??',
                        'expired_date' => $item->expired_date ? \Carbon\Carbon::parse($item->expired_date)->format('d/m/Y') : '-',
                        'source_type' => 'pelayanan',
                    ]);
                }
            }
        });

        $data->setCollection($results);

        return response()->json($data);
    }
    public function transfersCreate()
    {
        $now = Carbon::now();
        $code = $this->generateTransfersCode();
        $currentPharmacyId = getActivePharmacyId();

        $pharmaciesQuery = Pharmacies::where('id', '!=', $currentPharmacyId)
            ->where('status', 1);

        if (isWarehousePharmacy($currentPharmacyId)) {
            // Gudang PMI hanya bisa mutasi ke Apotek SAHABAT PMI (id = 1)
            $pharmaciesQuery->where('id', 1);
        }

        $pharmacies = $pharmaciesQuery->get();
        return view('kasir.transfers.create_transfers', compact('now', 'pharmacies', 'code'));
    }
    public function transfer(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'pharmacy' => 'required|exists:pharmacies,id',
            'items' => 'required|array|min:1',
            'items.*.batches_id' => 'required|exists:batches,id',
            'items.*.etalases_id' => 'required|exists:etalases,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.source_type' => 'required|in:gudang,pelayanan',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $transfer = MedicineTransfers::create([
                    'code' => $request->code,
                    'user_id' => auth()->id(),
                    'status' => 0,
                ]);

                $pharmacyId = getActivePharmacyId();

                // Validate: Gudang PMI can only transfer to SAHABAT PMI (id = 1)
                if (isWarehousePharmacy($pharmacyId) && (int) $request->pharmacy !== 1) {
                    throw new \Exception("Gudang PMI hanya dapat melakukan mutasi ke Apotek SAHABAT PMI.");
                }

                foreach ($request->items as $line) {
                    $sourceBatch = Batches::findOrFail($line['batches_id']);
                    $sourceType = $line['source_type'];

                    // Validate: only warehouse can use gudang source
                    if ($sourceType === 'gudang' && !isWarehousePharmacy($pharmacyId)) {
                        throw new \Exception("Hanya gudang yang bisa transfer dari stok gudang.");
                    }

                    // Check available stock based on source_type, accounting for pending outgoing transfers
                    if ($sourceType === 'gudang') {
                        $pendingOutgoing = (int) MedicineTransferItems::where('source_batches_id', $sourceBatch->id)
                            ->where('source_type', 'gudang')
                            ->where('status', 0)
                            ->sum('qty');
                        $availStock = max(0, (int) $sourceBatch->stock - $pendingOutgoing);
                    } else {
                        $currentStock = (int) MedicineTransferItems::where('batches_id', $sourceBatch->id)
                            ->where('qty', '>', 0)
                            ->where('status', 1)
                            ->where(function ($q) {
                                $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                            })
                            ->sum('qty');
                        $pendingOutgoing = (int) MedicineTransferItems::where('source_batches_id', $sourceBatch->id)
                            ->where('source_type', 'pelayanan')
                            ->where('status', 0)
                            ->sum('qty');
                        $availStock = max(0, $currentStock - $pendingOutgoing);
                    }

                    if ((int) $line['qty'] > $availStock) {
                        throw new \Exception("Qty untuk {$sourceBatch->name} ({$sourceBatch->medicines?->name}) melebihi stok tersedia ({$availStock}).");
                    }

                    // Find or create destination batch
                    $destBatch = Batches::firstOrCreate(
                        [
                            'pharmacy_id' => $request->pharmacy,
                            'medicine_id' => $sourceBatch->medicine_id,
                            'name' => $sourceBatch->name,
                            'expired_date' => $sourceBatch->expired_date,
                        ],
                        ['status' => 0, 'stock' => 0]
                    );

                    MedicineTransferItems::create([
                        'medicine_transfer_id' => $transfer->id,
                        'batches_id' => $destBatch->id,
                        'source_batches_id' => $sourceBatch->id,
                        'source_type' => $sourceType,
                        'etalases_id' => $line['etalases_id'],
                        'qty' => $line['qty'],
                        'status' => 0,
                    ]);
                }
            });

            return response()->json(['success' => true, 'message' => 'Transfer disimpan.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function printReceipt($id)
    {
        $transfer = MedicineTransfers::with([
            'items.batches.medicines',
            'items.batches.pharmacy',
            'users.pharmacy',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('kasir.transfers.receipt', compact('transfer'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('tanda-terima-barang-' . $transfer->code . '.pdf');
        // use ->download(...) instead of ->stream(...) if you want a forced download
    }

    public function index()
    {
        return response()->json(
            Items::orderBy('name')->get(['id', 'name'])
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|',
        ]);

        $etalase = Items::create($validated);

        return response()->json($etalase, 201);
    }

    public function update(Request $request, Items $etalase)
    {
        $validated = $request->validate([
            'name' => 'required|string|',
        ]);

        $etalase->update($validated);

        return response()->json($etalase);
    }
    public function incomingTransfers()
    {
        $pharmacyId = getActivePharmacyId();
        $search = request('search');
        $startDate = request('start_date');
        $endDate = request('end_date');
        $expiredDate = request('expired_date');

        $applyFilters = function ($query) use ($search, $startDate, $endDate, $expiredDate) {
            $query->whereDoesntHave('items', function ($q) {
                $q->whereNotNull('receiving_items_id');
            });

            $query->when($search, function ($q) use ($search) {
                $q->whereHas('items.batches.medicines', function ($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%");
                });
            })->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            })->when($expiredDate, function ($q) use ($expiredDate) {
                $q->whereHas('items.batches', function ($subQ) use ($expiredDate) {
                    $subQ->whereDate('expired_date', $expiredDate);
                });
            });
        };

        $pending = MedicineTransfers::with([
            'items.batches.medicines',
            'items.batches.pharmacy',
            'items.sourceBatch.pharmacy',
            'items.etalases',
            'users.pharmacy',
        ])
            ->whereHas('users', fn($q) => $q->where('pharmacy_id', $pharmacyId))
            ->where($applyFilters)
            ->latest()
            ->paginate(10, ['*'], 'pending_page')
            ->fragment('pending')
            ->withQueryString();

        $accepted = MedicineTransfers::with([
            'items.batches.medicines',
            'items.batches.pharmacy',
            'items.sourceBatch.pharmacy',
            'items.etalases',
            'users.pharmacy',
        ])
            ->whereHas('items.batches', fn($q) => $q->where('pharmacy_id', $pharmacyId))
            ->whereIn('status', [0, 1])
            ->where($applyFilters)
            ->latest()
            ->paginate(10, ['*'], 'accepted_page')
            ->fragment('accepted')
            ->withQueryString();

        $denied = MedicineTransfers::with([
            'items.batches.medicines',
            'items.batches.pharmacy',
            'items.sourceBatch.pharmacy',
            'items.etalases',
            'users.pharmacy',
        ])
            ->where(function ($q) {
                $q->where('status', 2)
                  ->orWhereHas('items', fn($i) => $i->where('status', 2));
            })
            ->where(function ($q) use ($pharmacyId) {
                $q->whereHas('users', fn($u) => $u->where('pharmacy_id', $pharmacyId))
                    ->orWhereHas('items.batches', fn($b) => $b->where('pharmacy_id', $pharmacyId));
            })
            ->where($applyFilters)
            ->latest()
            ->paginate(10, ['*'], 'denied_page')
            ->fragment('denied')
            ->withQueryString();

        return view('kasir.transfers.transfers', compact('pending', 'accepted', 'denied'));
    }

    public function exportTransfers(Request $request)
    {
        $pharmacyId = getActivePharmacyId();
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $search = $request->search;
        $type = $request->type ?? 'semua'; // pending, accepted, denied, semua

        $job = ExportJob::create([
            'type' => 'transfers',
            'status' => 'pending',
            'progress' => 0
        ]);

        dispatch(new ProcessTransfersExport(
            $job->id, 
            $pharmacyId, 
            $startDate, 
            $endDate, 
            $search, 
            $type
        ));

        return response()->json([
            'job_id' => $job->id,
            'message' => 'Export started.'
        ]);
    }

    public function exportStatus($id)
    {
        $job = ExportJob::findOrFail($id);

        return response()->json([
            'status' => $job->status,
            'progress' => $job->progress,
            'file' => $job->file_path ? asset('storage/' . $job->file_path) : null
        ]);
    }
    private function processItemTransferStock($item, $transfer, $now)
    {
        $destBatch = $item->batches;
        $srcBatch = $item->sourceBatch;
        $medicine = Medicines::findOrFail($destBatch->medicine_id);
        $sourceType = $item->source_type ?? 'gudang';

        $srcPharmacyId = $srcBatch->pharmacy_id;
        $destPharmacyId = $destBatch->pharmacy_id;

        // Determine if this is a "return to gudang" (pelayanan → gudang destination)
        $isReturnToGudang = ($sourceType === 'pelayanan' && (isWarehousePharmacy($destPharmacyId) || ($srcPharmacyId == $destPharmacyId && $destPharmacyId == 1)));

        // ── Decrement source ──────────────────────────────────────
        if ($sourceType === 'gudang') {
            // Decrement from batches.stock (gudang)
            if ($srcBatch->stock < $item->qty) {
                throw new \Exception("Stok gudang tidak mencukupi untuk item " . $medicine->name . ".");
            }
            $srcQtyBefore = $srcBatch->stock;
            $srcBatch->decrement('stock', $item->qty);
            $srcQtyAfter = $srcBatch->stock;
        } else {
            // Decrement from medicine_transfer_items (pelayanan)
            $mtiList = MedicineTransferItems::where('batches_id', $srcBatch->id)
                ->where('qty', '>', 0)
                ->where('status', 1)
                ->where('id', '!=', $item->id) // exclude current transfer item
                ->get();
            $totalMtiQty = $mtiList->sum('qty');

            if ($totalMtiQty < $item->qty) {
                throw new \Exception("Stok pelayanan tidak mencukupi untuk item " . $medicine->name . ".");
            }

            $srcQtyBefore = $totalMtiQty;
            $toDeduct = $item->qty;
            foreach ($mtiList as $mtiRow) {
                if ($toDeduct <= 0) break;
                $deduct = min($mtiRow->qty, $toDeduct);
                $mtiRow->decrement('qty', $deduct);
                $toDeduct -= $deduct;
            }
            $srcQtyAfter = max(0, $srcQtyBefore - $item->qty);
        }

        // ── Increment destination ─────────────────────────────────
        if ($isReturnToGudang) {
            // Return to gudang: increment batches.stock
            $destQtyBefore = $destBatch->stock;
            $destBatch->increment('stock', $item->qty);
            $destQtyAfter = $destBatch->stock;

            $item->update([
                'status' => 1,
                'source_type' => 'retur_gudang',
            ]);
        } else {
            // Add to pelayanan: $item itself is the record in destination batch
            $destQtyBefore = (int) MedicineTransferItems::where('batches_id', $destBatch->id)
                ->where('status', 1)
                ->where('id', '!=', $item->id)
                ->where(function ($q) {
                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                })
                ->sum('qty');

            $item->update(['status' => 1]);

            $destQtyAfter = $destQtyBefore + $item->qty;
        }

        // ── Log source (outgoing) ─────────────────────────────────
        ItemsLog::create([
            'transaction_code' => $transfer->code,
            'code' => $this->generateItemsLogCode(),
            'type' => 'MU',
            'medicine_id' => $medicine->id,
            'qty' => $item->qty,
            'qty_before' => $srcQtyBefore,
            'qty_after' => $srcQtyAfter,
            'total' => 0,
            'date' => $now,
            'status' => 7,
            'batches_id' => $srcBatch->id,
            'user_id' => auth()->id(),
        ]);

        // ── Log destination (incoming) ────────────────────────────
        ItemsLog::create([
            'transaction_code' => $transfer->code,
            'code' => $this->generateItemsLogCode(),
            'type' => 'MU',
            'medicine_id' => $medicine->id,
            'qty' => $item->qty,
            'qty_before' => $destQtyBefore,
            'qty_after' => $destQtyAfter,
            'total' => 0,
            'date' => $now,
            'status' => 7,
            'batches_id' => $destBatch->id,
            'user_id' => auth()->id(),
        ]);
    }

    public function acceptTransfer(MedicineTransfers $transfer)
    {
        try {
            DB::transaction(function () use ($transfer) {
                $now = Carbon::now();
                $pendingItems = $transfer->items()->where('status', 0)->get();

                foreach ($pendingItems as $item) {
                    $this->processItemTransferStock($item, $transfer, $now);
                }

                // ── Update parent transfer status ─────────────────────────
                if ($transfer->items()->where('status', 0)->doesntExist()) {
                    $hasAccepted = $transfer->items()->where('status', 1)->exists();
                    $transfer->update(['status' => $hasAccepted ? 1 : 2]);
                }
            });

            return redirect(url()->previous() . '#accepted')->with('success', 'Semua item diterima.');
        } catch (\Throwable $e) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Gagal: ' . $e->getMessage());
        }
    }

    public function acceptItem(MedicineTransferItems $item)
    {
        try {
            DB::transaction(function () use ($item) {
                $now = Carbon::now();
                $transfer = $item->transfer;

                $this->processItemTransferStock($item, $transfer, $now);

                // ── Update parent transfer status ─────────────────────────
                if ($transfer->items()->where('status', 0)->doesntExist()) {
                    $hasAccepted = $transfer->items()->where('status', 1)->exists();
                    $transfer->update(['status' => $hasAccepted ? 1 : 2]);
                }
            });

            return redirect(url()->previous() . '#accepted')->with('success', 'Item diterima.');
        } catch (\Throwable $e) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Gagal: ' . $e->getMessage());
        }
    }
    public function denyItem(MedicineTransferItems $item)
    {
        try {
            $item->update(['status' => 2]);

            $transfer = $item->transfer;
            if ($transfer->items()->where('status', 0)->doesntExist()) {
                $hasAccepted = $transfer->items()->where('status', 1)->exists();
                $transfer->update(['status' => $hasAccepted ? 1 : 2]);
            }

            if ($transfer->status == 2) {
                return redirect(url()->previous() . '#denied')->with('success', 'Semua item ditolak.');
            }

            return redirect(url()->previous() . '#accepted')->with('success', 'Item ditolak.');
        } catch (\Throwable $e) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Gagal: ' . $e->getMessage());
        }
    }
}
