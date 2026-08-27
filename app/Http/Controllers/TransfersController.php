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

        // PMI: show batches with gudang stock OR pelayanan stock
        // Cabang: show batches with pelayanan stock only
        if ($pharmacyId == 1) {
            $query->where(function ($q) {
                $q->where('stock', '>', 0)
                    ->orWhereHas('medicine_transfer_items', function ($qMti) {
                        $qMti->where('qty', '>', 0)->where('status', 1);
                    });
            });
        } else {
            $query->whereHas('medicine_transfer_items', function ($qMti) {
                $qMti->where('qty', '>', 0)->where('status', 1);
            });
        }

        $data = $query->paginate(20);

        // Transform: for PMI, split into separate rows for gudang & pelayanan
        $results = collect();
        $data->getCollection()->each(function ($item) use ($pharmacyId, &$results) {
            $gudangStock = (int) $item->stock;
            $pelayananStock = (int) $item->medicine_transfer_items->sum('qty');

            if ($pharmacyId == 1 && $gudangStock > 0) {
                $results->push([
                    'id' => $item->id,
                    'batches_name' => $item->name,
                    'name' => $item->medicines?->name ?? '??',
                    'stock' => $gudangStock,
                    'unit' => $item->medicines?->unit ?? '??',
                    'source_type' => 'gudang',
                ]);
            }

            if ($pelayananStock > 0) {
                $results->push([
                    'id' => $item->id,
                    'batches_name' => $item->name,
                    'name' => $item->medicines?->name ?? '??',
                    'stock' => $pelayananStock,
                    'unit' => $item->medicines?->unit ?? '??',
                    'source_type' => 'pelayanan',
                ]);
            }
        });

        $data->setCollection($results);

        return response()->json($data);
    }
    public function transfersCreate()
    {
        $now = Carbon::now();
        $code = $this->generateTransfersCode();
        $pharmacies = Pharmacies::all();
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

                foreach ($request->items as $line) {
                    $sourceBatch = Batches::findOrFail($line['batches_id']);
                    $sourceType = $line['source_type'];

                    // Validate: only PMI can use gudang source
                    if ($sourceType === 'gudang' && $pharmacyId != 1) {
                        throw new \Exception("Cabang tidak bisa transfer dari gudang.");
                    }

                    // Check available stock based on source_type
                    if ($sourceType === 'gudang') {
                        $availStock = (int) $sourceBatch->stock;
                    } else {
                        $availStock = (int) MedicineTransferItems::where('batches_id', $sourceBatch->id)
                            ->where('qty', '>', 0)
                            ->where('status', 1)
                            ->sum('qty');
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

        $applyFilters = function ($query) use ($search, $startDate, $endDate) {
            $query->when($search, function ($q) use ($search) {
                $q->whereHas('items.batches.medicines', function ($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%");
                });
            })->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            });
        };

        $pending = MedicineTransfers::with([
            'items.batches.medicines',
            'items.batches.pharmacy',
            'items.etalases',
            'users.pharmacy',
        ])
            ->whereHas('users', fn($q) => $q->where('pharmacy_id', $pharmacyId))
            ->where($applyFilters)
            ->latest()
            ->paginate(10, ['*'], 'pending_page')
            ->withQueryString();

        $accepted = MedicineTransfers::with([
            'items.batches.medicines',
            'items.etalases',
            'users.pharmacy',
        ])
            ->whereHas('items.batches', fn($q) => $q->where('pharmacy_id', $pharmacyId))
            ->whereIn('status', [0, 1])
            ->where($applyFilters)
            ->latest()
            ->paginate(10, ['*'], 'accepted_page')
            ->withQueryString();

        $denied = MedicineTransfers::with([
            'items.batches.medicines',
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

        // Determine if this is a "return to gudang" (pelayanan → gudang, same pharmacy, PMI only)
        $isReturnToGudang = ($sourceType === 'pelayanan' && $srcPharmacyId == $destPharmacyId && $destPharmacyId == 1);

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
        } else {
            // Add to pelayanan: create/increment medicine_transfer_items
            $destQtyBefore = (int) MedicineTransferItems::where('batches_id', $destBatch->id)
                ->where('status', 1)
                ->sum('qty');

            $destMti = MedicineTransferItems::where('batches_id', $destBatch->id)
                ->where('etalases_id', $item->etalases_id ?? 99)
                ->where('status', 1)
                ->first();

            if (!$destMti) {
                MedicineTransferItems::create([
                    'medicine_transfer_id' => $transfer->id,
                    'batches_id' => $destBatch->id,
                    'source_type' => 'pelayanan',
                    'etalases_id' => $item->etalases_id ?? 99,
                    'qty' => $item->qty,
                    'status' => 1,
                ]);
            } else {
                $destMti->increment('qty', $item->qty);
            }

            $destQtyAfter = $destQtyBefore + $item->qty;
        }

        $item->update(['status' => 1]);

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
