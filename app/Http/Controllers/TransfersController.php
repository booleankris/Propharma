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
            ->with(['medicines', 'medicine_transfer_items'])
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

        if ($pharmacyId == 1) {
            $query->where('stock', '>', 0);
        } else {
            $query->where(function ($q) {
                $q->where('stock', '>', 0)
                    ->orWhereHas('medicine_transfer_items', function ($qMti) {
                        $qMti->where('qty', '>', 0);
                    });
            });
        }

        $data = $query->paginate(10);

        $data->getCollection()->transform(function ($item) use ($pharmacyId) {
            $counterStock = (int) $item->medicine_transfer_items?->sum('qty');
            $calcStock = ($pharmacyId == 1)
                ? (int) $item->stock
                : ((int) $item->stock > 0 ? (int) $item->stock : $counterStock);

            return [
                'id' => $item->id,
                'batches_name' => $item->name,
                'name' => $item->medicines?->name ?? "??",
                'stock' => $calcStock,
                'unit' => $item->medicines?->unit ?? "??",
            ];
        });

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
        ]);

        try {
            DB::transaction(function () use ($request) {
                // Create transfer header
                $transfer = MedicineTransfers::create([
                    'code' => $request->code,
                    'user_id' => auth()->id(),
                    'status' => 0,
                ]);

                $pharmacyId = getActivePharmacyId();

                foreach ($request->items as $line) {
                    $sourceBatch = Batches::findOrFail($line['batches_id']);

                    $availStock = ($pharmacyId == 1)
                        ? (int) $sourceBatch->stock
                        : max((int) $sourceBatch->stock, (int) $sourceBatch->medicine_transfer_items()->sum('qty'));

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
            'batches.medicines',
            'batches.pharmacy',
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

        $pending = MedicineTransfers::with([
            'items.batches.medicines',
            'items.etalases',
            'users.pharmacy',
        ])
            ->whereHas('users', fn($q) => $q->where('pharmacy_id', $pharmacyId))
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
            ->latest()
            ->paginate(10, ['*'], 'accepted_page')
            ->withQueryString();

        $denied = MedicineTransfers::with([
            'items.batches.medicines',
            'items.etalases',
            'users.pharmacy',
        ])
            ->whereHas('users', fn($q) => $q->where('pharmacy_id', $pharmacyId))
            ->where('status', 2)
            ->latest()
            ->paginate(10, ['*'], 'denied_page')
            ->withQueryString();

        return view('kasir.transfers.transfers', compact('pending', 'accepted', 'denied'));
    }
    private function processItemTransferStock($item, $transfer, $now)
    {
        $destBatch = $item->batches;
        $srcBatch = $item->sourceBatch;
        $medicine = Medicines::findOrFail($destBatch->medicine_id);

        $srcPharmacyId = $srcBatch->pharmacy_id;
        $destPharmacyId = $destBatch->pharmacy_id;

        // ── Decrement source ──────────────────────────────────────
        if ($srcPharmacyId == 1) {
            if ($srcBatch->stock < $item->qty) {
                throw new \Exception("Stok sumber tidak mencukupi untuk item " . $medicine->name . ".");
            }
            $srcQtyBefore = $srcBatch->stock;
            $srcBatch->decrement('stock', $item->qty);
            $srcQtyAfter = $srcBatch->stock;
        } else {
            // Cabang: decrement from medicine_transfer_items
            $mtiList = MedicineTransferItems::where('batches_id', $srcBatch->id)
                ->where('qty', '>', 0)
                ->get();
            $totalMtiQty = $mtiList->sum('qty');
            $availSrc = max((int) $srcBatch->stock, (int) $totalMtiQty);

            if ($availSrc < $item->qty) {
                throw new \Exception("Stok etalase sumber tidak mencukupi untuk item " . $medicine->name . ".");
            }

            $srcQtyBefore = $availSrc;
            if ($srcBatch->stock > 0) {
                $srcBatch->decrement('stock', min($srcBatch->stock, $item->qty));
            }

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
        if ($destPharmacyId == 1) {
            $destQtyBefore = $destBatch->stock;
            $destBatch->increment('stock', $item->qty);
            $destQtyAfter = $destBatch->stock;
        } else {
            // Cabang destination: add to medicine_transfer_items
            $destQtyBefore = (int) MedicineTransferItems::where('batches_id', $destBatch->id)->sum('qty');

            $destMti = MedicineTransferItems::where('batches_id', $destBatch->id)
                ->where('etalases_id', $item->etalases_id ?? 99)
                ->first();

            if (!$destMti) {
                $destMti = MedicineTransferItems::create([
                    'medicine_transfer_id' => $transfer->id,
                    'batches_id' => $destBatch->id,
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

            return redirect(url()->previous() . '#accepted')->with('success', 'Item ditolak.');
        } catch (\Throwable $e) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Gagal: ' . $e->getMessage());
        }
    }
}
