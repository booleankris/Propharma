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

        // Warehouse: stock is directly in batches.stock (already deducted on create)
        // Cabang / Pelayanan: stock is sum of accepted MTI records
        $isWarehouse = isWarehousePharmacy($pharmacyId);

        if ($isWarehouse) {
            $query->where('stock', '>', 0);
        } else {
            $query->whereRaw('(
                SELECT COALESCE(SUM(mti1.qty), 0)
                FROM medicine_transfer_items mti1
                WHERE mti1.batches_id = batches.id
                  AND mti1.status = 1
                  AND (mti1.source_type IS NULL OR mti1.source_type != "retur_gudang")
            ) > 0');
        }

        $data = $query->paginate(20);

        // Transform
        $results = collect();
        $data->getCollection()->each(function ($item) use ($isWarehouse, &$results) {
            if ($isWarehouse) {
                $availStock = max(0, (int) $item->stock);

                if ($availStock > 0) {
                    $results->push([
                        'id' => $item->id,
                        'batches_name' => $item->name,
                        'name' => $item->medicines?->name ?? '??',
                        'stock' => $availStock,
                        'unit' => $item->medicines?->unit ?? '??',
                        'expired_date' => safeDateFormat($item->expired_date),
                        'source_type' => 'gudang',
                    ]);
                }
            } else {
                $currentPelayanan = (int) $item->medicine_transfer_items
                    ->filter(function ($mti) {
                        return (int) $mti->status === 1 && (empty($mti->source_type) || $mti->source_type !== 'retur_gudang');
                    })
                    ->sum('qty');
                $availStock = max(0, $currentPelayanan);

                if ($availStock > 0) {
                    $results->push([
                        'id' => $item->id,
                        'batches_name' => $item->name,
                        'name' => $item->medicines?->name ?? '??',
                        'stock' => $availStock,
                        'unit' => $item->medicines?->unit ?? '??',
                        'expired_date' => safeDateFormat($item->expired_date),
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

        $pharmaciesQuery = Pharmacies::where('status', 1);

        if (isWarehousePharmacy($currentPharmacyId)) {
            // Stok gudang PMI dapat dimutasi ke cabang pelayanan.
            $pharmaciesQuery->whereIn('id', [1, 2, 3, 4, 5]);
        } else {
            // Cabang / Pelayanan bisa transfer ke Gudang PMI (id = 9) dan ke cabang fisik lain
            $pharmaciesQuery->whereIn('id', [1, 2, 3, 4, 5, 9])
                ->where('id', '!=', $currentPharmacyId);
        }

        $pharmacies = $pharmaciesQuery->get();
        return view('kasir.transfers.create_transfers', compact('now', 'pharmacies', 'code'));
    }

    public function createTransferRequest()
    {
        $currentPharmacyId = getActivePharmacyId();
        $pharmacies = Pharmacies::where('status', 1)
            ->whereIn('id', [1, 2, 3, 4, 5, 9])
            ->where('id', '!=', $currentPharmacyId)
            ->orderBy('name')
            ->get();

        return view('kasir.transfers.create_request', [
            'pharmacies' => $pharmacies,
            'code' => $this->generateTransfersCode(),
            'now' => Carbon::now(),
        ]);
    }

    public function searchRequestMedicines(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $sourcePharmacyId = (int) $request->query('source_pharmacy_id');
        if (mb_strlen($search) < 1 || !in_array($sourcePharmacyId, [1, 2, 3, 4, 5, 9], true)) {
            return response()->json(['data' => []]);
        }

        $sourceType = isWarehousePharmacy($sourcePharmacyId) ? 'gudang' : 'pelayanan';
        $batches = Batches::with('medicines')
            ->where('pharmacy_id', $sourcePharmacyId)
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhereHas('medicines', function ($medicine) use ($search) {
                        $medicine->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            })
            ->orderByRaw('expired_date IS NULL, expired_date ASC')
            ->orderBy('id')
            ->limit(60)
            ->get();

        $results = $batches->map(function ($batch) use ($sourceType) {
            $available = $sourceType === 'gudang'
                ? max(0, (int) $batch->stock)
                : (int) MedicineTransferItems::where('batches_id', $batch->id)
                    ->where('qty', '>', 0)->where('status', 1)
                    ->where(function ($query) {
                        $query->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                    })->sum('qty');

            if ($available < 1) return null;

            return [
                'id' => $batch->id,
                'batches_name' => $batch->name,
                'name' => $batch->medicines?->name ?? '—',
                'medicine_id' => $batch->medicine_id,
                'medicine_code' => $batch->medicines?->code ?? '—',
                'stock' => $available,
                'unit' => $batch->medicines?->unit ?? '—',
                'expired_date' => safeDateFormat($batch->expired_date),
                'source_type' => $sourceType,
            ];
        })->filter()->values();

        return response()->json(['data' => $results]);
    }

    public function storeTransferRequest(Request $request)
    {
        $validated = $request->validate([
            'source_pharmacy_id' => 'required|exists:pharmacies,id',
            'items' => 'required|array|min:1',
            'items.*.batches_id' => 'required|exists:batches,id',
            'items.*.etalases_id' => 'required|exists:etalases,id',
            'items.*.source_type' => 'required|in:gudang,pelayanan',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        $requesterPharmacyId = getActivePharmacyId();
        if ((int) $validated['source_pharmacy_id'] === $requesterPharmacyId) {
            return back()->withInput()->withErrors(['source_pharmacy_id' => 'Cabang pengirim harus berbeda dengan cabang Anda.']);
        }
        if (!in_array((int) $validated['source_pharmacy_id'], [1, 2, 3, 4, 5, 9], true)
            || !Pharmacies::whereKey($validated['source_pharmacy_id'])->where('status', 1)->exists()) {
            return back()->withInput()->withErrors(['source_pharmacy_id' => 'Cabang pengirim tidak aktif.']);
        }

        $transfer = DB::transaction(function () use ($validated, $requesterPharmacyId) {
            $sourcePharmacyId = (int) $validated['source_pharmacy_id'];
            if (!in_array($sourcePharmacyId, [1, 2, 3, 4, 5, 9], true)
                || !Pharmacies::whereKey($sourcePharmacyId)->where('status', 1)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['source_pharmacy_id' => 'Cabang pengirim tidak aktif.']);
            }

            foreach ($validated['items'] as $line) {
                $sourceBatch = Batches::whereKey($line['batches_id'])
                    ->where('pharmacy_id', $sourcePharmacyId)
                    ->lockForUpdate()
                    ->first();
                if (!$sourceBatch) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Batch tidak tersedia di cabang pengirim yang dipilih.']);
                }
                $expectedSourceType = isWarehousePharmacy($sourcePharmacyId) ? 'gudang' : 'pelayanan';
                if ($line['source_type'] !== $expectedSourceType) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Jenis stok pada permintaan tidak sesuai dengan cabang pengirim.']);
                }
                $available = $expectedSourceType === 'gudang'
                    ? max(0, (int) $sourceBatch->stock)
                    : (int) MedicineTransferItems::where('batches_id', $sourceBatch->id)
                        ->where('qty', '>', 0)->where('status', 1)
                        ->where(function ($query) {
                            $query->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                        })->sum('qty');
                if ((int) $line['qty'] > $available) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => "Stok {$sourceBatch->name} berubah. Stok tersedia sekarang {$available}."]);
                }
                $etalaseBelongsToReceiver = Items::whereKey($line['etalases_id'])
                    ->where(function ($query) use ($requesterPharmacyId) {
                        $query->where('pharmacy_id', $requesterPharmacyId)->orWhereNull('pharmacy_id');
                    })->exists();
                if (!$etalaseBelongsToReceiver) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Etalase harus berasal dari cabang penerima.']);
                }
            }

            $transfer = MedicineTransfers::create([
                'code' => $this->generateTransfersCode(),
                'user_id' => auth()->id(),
                'status' => 0,
                'is_request' => true,
                'source_pharmacy_id' => $validated['source_pharmacy_id'],
                'destination_pharmacy_id' => $requesterPharmacyId,
                'request_status' => 0,
            ]);

            foreach ($validated['items'] as $line) {
                MedicineTransferItems::create([
                    'medicine_transfer_id' => $transfer->id,
                    'requested_medicine_id' => Batches::find($line['batches_id'])->medicine_id,
                    'source_batches_id' => $line['batches_id'],
                    'source_type' => $line['source_type'],
                    'etalases_id' => $line['etalases_id'],
                    'qty' => $line['qty'],
                    'status' => 0,
                ]);
            }

            return $transfer;
        });

        return redirect()->route('transfers.incoming', ['tab' => 'requests'])
            ->with('success', "Permintaan mutasi {$transfer->code} berhasil dikirim.");
    }

    public function approveTransferRequest(MedicineTransfers $transfer)
    {
        $pharmacyId = getActivePharmacyId();
        if (!$transfer->is_request || (int) $transfer->request_status !== 0 || (int) $transfer->source_pharmacy_id !== $pharmacyId) {
            return back()->with('message', 'Permintaan ini tidak tersedia untuk disetujui oleh cabang Anda.');
        }

        try {
            DB::transaction(function () use ($transfer, $pharmacyId) {
                $transfer = MedicineTransfers::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if (!$transfer->is_request || (int) $transfer->request_status !== 0 || (int) $transfer->source_pharmacy_id !== $pharmacyId) {
                    throw new \RuntimeException('Status permintaan sudah berubah. Muat ulang halaman.');
                }

                $now = Carbon::now();
                $requestItems = $transfer->items()->whereNull('batches_id')->lockForUpdate()->get();
                if ($requestItems->isEmpty()) {
                    throw new \RuntimeException('Tidak ada item permintaan yang dapat diproses.');
                }

                $destinationEtalaseId = \App\Models\Etalases::where('pharmacy_id', $transfer->destination_pharmacy_id)->value('id');
                if (!$destinationEtalaseId) {
                    $destinationEtalaseId = \App\Models\Etalases::query()->value('id');
                }

                foreach ($requestItems as $requestedItem) {
                    $remaining = (int) $requestedItem->qty;
                    $medicineId = (int) $requestedItem->requested_medicine_id;
                    $batches = Batches::where('pharmacy_id', $pharmacyId)
                        ->where('medicine_id', $medicineId)
                        ->when($requestedItem->source_batches_id, fn($query, $batchId) => $query->whereKey($batchId))
                        ->orderByRaw('expired_date IS NULL, expired_date ASC')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    foreach ($batches as $sourceBatch) {
                        if ($remaining <= 0) break;
                        $sourceType = isWarehousePharmacy($pharmacyId) ? 'gudang' : 'pelayanan';
                        $available = $sourceType === 'gudang'
                            ? max(0, (int) $sourceBatch->stock)
                            : (int) MedicineTransferItems::where('batches_id', $sourceBatch->id)
                                ->where('qty', '>', 0)->where('status', 1)
                                ->where(function ($query) {
                                    $query->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                                })->sum('qty');

                        if ($available <= 0) continue;
                        $qty = min($remaining, $available);
                        $destinationBatch = Batches::firstOrCreate(
                            [
                                'pharmacy_id' => $transfer->destination_pharmacy_id,
                                'medicine_id' => $sourceBatch->medicine_id,
                                'name' => $sourceBatch->name,
                                'expired_date' => $sourceBatch->expired_date,
                            ],
                            ['status' => 0, 'stock' => 0]
                        );

                        if ($sourceType === 'gudang') {
                            $qtyBefore = (int) $sourceBatch->stock;
                            $sourceBatch->decrement('stock', $qty);
                            $qtyAfter = (int) $sourceBatch->fresh()->stock;
                        } else {
                            $qtyBefore = $available;
                            $toDeduct = $qty;
                            $sourceRows = MedicineTransferItems::where('batches_id', $sourceBatch->id)
                                ->where('qty', '>', 0)->where('status', 1)
                                ->where(function ($query) {
                                    $query->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                                })->orderBy('id')->lockForUpdate()->get();
                            foreach ($sourceRows as $sourceRow) {
                                if ($toDeduct <= 0) break;
                                $deduct = min((int) $sourceRow->qty, $toDeduct);
                                $sourceRow->decrement('qty', $deduct);
                                $toDeduct -= $deduct;
                            }
                            $qtyAfter = $qtyBefore - $qty;
                        }

                        $itemData = [
                            'batches_id' => $destinationBatch->id,
                            'source_batches_id' => $sourceBatch->id,
                            'source_type' => $sourceType,
                            'etalases_id' => $requestedItem->etalases_id ?: $destinationEtalaseId,
                            'qty' => $qty,
                            'status' => 0,
                            'stock_deducted_at' => $now,
                        ];
                        if ($requestedItem->batches_id === null) {
                            $requestedItem->update($itemData);
                        } else {
                            $itemData['medicine_transfer_id'] = $transfer->id;
                            $itemData['requested_medicine_id'] = $medicineId;
                            MedicineTransferItems::create($itemData);
                        }

                        Medicines::whereKey($medicineId)->decrement('stock', $qty);
                        ItemsLog::create([
                            'transaction_code' => $transfer->code,
                            'code' => $this->generateItemsLogCode(),
                            'type' => 'MU',
                            'medicine_id' => $medicineId,
                            'qty' => $qty,
                            'qty_before' => $qtyBefore,
                            'qty_after' => $qtyAfter,
                            'total' => 0,
                            'date' => $now,
                            'status' => 7,
                            'batches_id' => $sourceBatch->id,
                            'user_id' => auth()->id(),
                        ]);
                        $remaining -= $qty;
                    }

                    if ($remaining > 0) {
                        throw new \RuntimeException('Stok ' . (Medicines::find($medicineId)?->name ?? 'obat') . " tidak mencukupi. Kekurangan {$remaining}. Tidak ada stok yang dikurangi.");
                    }
                }

                $transfer->update([
                    'request_status' => 1,
                    'user_id' => auth()->id(),
                ]);
            });

            return back()->with('success', 'Permintaan disetujui. Mutasi keluar tercatat dan stok sumber telah dikurangi.');
        } catch (\Throwable $e) {
            return back()->with('message', 'Permintaan gagal diproses: ' . $e->getMessage());
        }
    }

    public function denyTransferRequest(MedicineTransfers $transfer)
    {
        if (!$transfer->is_request || (int) $transfer->request_status !== 0 || (int) $transfer->source_pharmacy_id !== getActivePharmacyId()) {
            return back()->with('message', 'Permintaan ini tidak tersedia untuk ditolak oleh cabang Anda.');
        }

        DB::transaction(function () use ($transfer) {
            $transfer->items()->where('status', 0)->update(['status' => 2]);
            $transfer->update(['request_status' => 2, 'status' => 2]);
        });

        return back()->with('success', 'Permintaan mutasi ditolak. Stok tidak berubah.');
    }

    public function transfer(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'submission_key' => 'required|uuid',
            'pharmacy' => 'required|exists:pharmacies,id',
            'items' => 'required|array|min:1',
            'items.*.batches_id' => 'required|exists:batches,id',
            'items.*.etalases_id' => 'required|exists:etalases,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.source_type' => 'required|in:gudang,pelayanan',
        ]);

        $submissionKey = $request->input('submission_key');
        $existingTransfer = MedicineTransfers::where('submission_key', $submissionKey)->first();
        if ($existingTransfer) {
            $sameSubmission = (int) $existingTransfer->user_id === (int) auth()->id()
                && (int) $existingTransfer->source_pharmacy_id === (int) getActivePharmacyId()
                && (int) $existingTransfer->destination_pharmacy_id === (int) $request->pharmacy;

            return $sameSubmission
                ? response()->json(['success' => true, 'message' => 'Transfer ini sudah tersimpan.'])
                : response()->json(['success' => false, 'message' => 'Kunci pengiriman sudah digunakan. Muat ulang halaman dan periksa data transfer.'], 409);
        }

        try {
            DB::transaction(function () use ($request) {
                $now = Carbon::now();
                $transfer = MedicineTransfers::create([
                    'code' => $request->code,
                    'submission_key' => $request->submission_key,
                    'user_id' => auth()->id(),
                    'status' => 0,
                    'source_pharmacy_id' => getActivePharmacyId(),
                    'destination_pharmacy_id' => (int) $request->pharmacy,
                ]);

                $pharmacyId = getActivePharmacyId();

                if (isWarehousePharmacy($pharmacyId)) {
                    if (!in_array((int) $request->pharmacy, [1, 2, 3, 4, 5], true)) {
                        throw new \Exception("Gudang PMI hanya dapat melakukan mutasi ke cabang pelayanan.");
                    }
                } else {
                    if ((int) $request->pharmacy === (int) $pharmacyId || !in_array((int) $request->pharmacy, [1, 2, 3, 4, 5, 9], true)) {
                        throw new \Exception("Apotek tujuan tidak valid.");
                    }
                }

                // Aggregate items with identical batches_id and source_type to prevent duplicate entries
                $aggregatedItems = [];
                foreach ($request->items as $line) {
                    $key = $line['batches_id'] . '_' . ($line['source_type'] ?? 'gudang');
                    if (isset($aggregatedItems[$key])) {
                        $aggregatedItems[$key]['qty'] += (int) $line['qty'];
                    } else {
                        $aggregatedItems[$key] = [
                            'batches_id' => $line['batches_id'],
                            'source_type' => $line['source_type'] ?? 'gudang',
                            'etalases_id' => $line['etalases_id'],
                            'qty' => (int) $line['qty'],
                        ];
                    }
                }

                foreach ($aggregatedItems as $line) {
                    $sourceBatch = Batches::lockForUpdate()->findOrFail($line['batches_id']);
                    if ((int) $sourceBatch->pharmacy_id !== $pharmacyId) {
                        throw new \Exception("Batch sumber bukan milik cabang Anda.");
                    }
                    $sourceType = $line['source_type'];
                    $qty = (int) $line['qty'];
                    $medicine = $sourceBatch->medicines;

                    // Validate source_type vs pharmacy role
                    if ($sourceType === 'gudang' && !isWarehousePharmacy($pharmacyId)) {
                        throw new \Exception("Hanya gudang yang bisa transfer dari stok gudang.");
                    }
                    if ($sourceType === 'pelayanan' && isWarehousePharmacy($pharmacyId)) {
                        throw new \Exception("Gudang hanya dapat mentransfer dari stok gudang.");
                    }

                    // ── Check & Immediately Deduct Source Stock ───────────────
                    if ($sourceType === 'gudang') {
                        // Gudang: deduct directly from batches.stock
                        $availStock = (int) $sourceBatch->stock;
                        if ($qty > $availStock) {
                            throw new \Exception("Qty untuk {$sourceBatch->name} ({$medicine?->name}) melebihi stok gudang tersedia ({$availStock}).");
                        }
                        $srcQtyBefore = $sourceBatch->stock;
                        $sourceBatch->decrement('stock', $qty);
                        $srcQtyAfter = $sourceBatch->fresh()->stock;
                    } else {
                        // Pelayanan: deduct from MTI records (FIFO)
                        $mtiList = MedicineTransferItems::where('batches_id', $sourceBatch->id)
                            ->where('qty', '>', 0)
                            ->where('status', 1)
                            ->where(function ($q) {
                                $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                            })
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->get();
                        $totalMtiQty = $mtiList->sum('qty');

                        if ($qty > $totalMtiQty) {
                            throw new \Exception("Qty untuk {$sourceBatch->name} ({$medicine?->name}) melebihi stok pelayanan tersedia ({$totalMtiQty}).");
                        }

                        $srcQtyBefore = $totalMtiQty;
                        $toDeduct = $qty;
                        foreach ($mtiList as $mtiRow) {
                            if ($toDeduct <= 0) break;
                            $deduct = min($mtiRow->qty, $toDeduct);
                            $mtiRow->decrement('qty', $deduct);
                            $toDeduct -= $deduct;
                        }
                        $srcQtyAfter = max(0, $srcQtyBefore - $qty);
                    }

                    // ── Find or create destination batch ──────────────────────
                    $destBatch = Batches::firstOrCreate(
                        [
                            'pharmacy_id' => $request->pharmacy,
                            'medicine_id' => $sourceBatch->medicine_id,
                            'name' => $sourceBatch->name,
                            'expired_date' => $sourceBatch->expired_date,
                        ],
                        ['status' => 0, 'stock' => 0]
                    );

                    // ── Create transfer item with stock_deducted_at ───────────
                    MedicineTransferItems::create([
                        'medicine_transfer_id' => $transfer->id,
                        'batches_id' => $destBatch->id,
                        'source_batches_id' => $sourceBatch->id,
                        'source_type' => $sourceType,
                        'etalases_id' => $line['etalases_id'],
                        'qty' => $qty,
                        'status' => 0,
                        'stock_deducted_at' => $now,
                    ]);

                    // Sync medicines master stock
                    $medRecord = Medicines::find($sourceBatch->medicine_id);
                    if ($medRecord) {
                        $medRecord->decrement('stock', $qty);
                    }

                    // ── Log source outgoing ───────────────────────────────────
                    ItemsLog::create([
                        'transaction_code' => $transfer->code,
                        'code' => $this->generateItemsLogCode(),
                        'type' => 'MU',
                        'medicine_id' => $sourceBatch->medicine_id,
                        'qty' => $qty,
                        'qty_before' => $srcQtyBefore,
                        'qty_after' => $srcQtyAfter,
                        'total' => 0,
                        'date' => $now,
                        'status' => 7,
                        'batches_id' => $sourceBatch->id,
                        'user_id' => auth()->id(),
                    ]);
                }
            });

            return response()->json(['success' => true, 'message' => 'Transfer disimpan. Stok telah dikurangi.']);
        } catch (\Throwable $e) {
            // A concurrent retry can race the initial lookup. The unique index
            // makes the second request roll back; return the first result.
            $existingTransfer = MedicineTransfers::where('submission_key', $submissionKey)->first();
            if ($existingTransfer
                && (int) $existingTransfer->user_id === (int) auth()->id()
                && (int) $existingTransfer->source_pharmacy_id === (int) getActivePharmacyId()
                && (int) $existingTransfer->destination_pharmacy_id === (int) $request->pharmacy) {
                return response()->json(['success' => true, 'message' => 'Transfer ini sudah tersimpan.']);
            }

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
        $this->authorizeTransferParticipant($transfer);

        $pdf = Pdf::loadView('kasir.transfers.receipt', compact('transfer'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('tanda-terima-barang-' . $transfer->code . '.pdf');
        // use ->download(...) instead of ->stream(...) if you want a forced download
    }

    public function index(Request $request)
    {
        $pharmacyId = $request->query('pharmacy_id') ?: getActivePharmacyId();

        $query = Items::query();
        if ($pharmacyId) {
            $hasSpecific = Items::where('pharmacy_id', $pharmacyId)->exists();
            if ($hasSpecific) {
                $query->where('pharmacy_id', $pharmacyId);
            } else {
                $query->where(function ($q) use ($pharmacyId) {
                    $q->where('pharmacy_id', $pharmacyId)
                      ->orWhereNull('pharmacy_id');
                });
            }
        }

        return response()->json(
            $query->orderBy('name')->get(['id', 'name'])
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'pharmacy_id' => 'nullable|integer',
        ]);

        if (empty($validated['pharmacy_id'])) {
            $validated['pharmacy_id'] = getActivePharmacyId() ?: null;
        }

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

            if ($search) {
                $query->where(function ($sq) use ($search) {
                    $sq->where('code', 'like', "%{$search}%")
                        ->orWhereHas('items.batches.medicines', function ($subQ) use ($search) {
                            $subQ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items.sourceBatch.medicines', function ($subQ) use ($search) {
                            $subQ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            }

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }

            if ($expiredDate) {
                $query->whereHas('items.batches', function ($subQ) use ($expiredDate) {
                    $subQ->whereDate('expired_date', $expiredDate);
                });
            }
        };

        // ── Mutasi Keluar: transfers WHERE source batch belongs to this pharmacy ──
        $pendingRequests = MedicineTransfers::with(['users.pharmacy', 'sourcePharmacy', 'destinationPharmacy', 'items.requestedMedicine', 'items.sourceBatch'])
            ->where('is_request', true)->where('request_status', 0)
            ->where('source_pharmacy_id', $pharmacyId)->latest()->get();
        $submittedRequests = MedicineTransfers::with(['users.pharmacy', 'sourcePharmacy', 'destinationPharmacy', 'items.requestedMedicine', 'items.sourceBatch'])
            ->where('is_request', true)->where('destination_pharmacy_id', $pharmacyId)->latest()->get();

        $pendingQuery = MedicineTransfers::with(['users.pharmacy', 'sourcePharmacy', 'destinationPharmacy'])
            ->where(function ($query) {
                $query->where('is_request', false)->orWhere('request_status', 1);
            })
            ->where(function ($q) use ($pharmacyId) {
                $q->where('source_pharmacy_id', $pharmacyId)
                    ->orWhereHas('items.sourceBatch', fn($sb) => $sb->where('pharmacy_id', $pharmacyId))
                    ->orWhere(function ($q2) use ($pharmacyId) {
                        $q2->whereDoesntHave('items.sourceBatch')
                            ->whereHas('users', fn($u) => $u->where('pharmacy_id', $pharmacyId));
                    });
            })
            ->latest();
        $applyFilters($pendingQuery);
        $pending = $pendingQuery
            ->paginate(10, ['*'], 'pending_page')
            ->fragment('pending')
            ->withQueryString();

        // ── Mutasi Masuk: transfers WHERE destination batch belongs to this pharmacy ──
        $acceptedQuery = MedicineTransfers::with(['users.pharmacy', 'sourcePharmacy', 'destinationPharmacy'])
            ->where(function ($query) {
                $query->where('is_request', false)->orWhere('request_status', 1);
            })
            ->where(function ($q) use ($pharmacyId) {
                $q->where('destination_pharmacy_id', $pharmacyId)
                    ->orWhereHas('items.batches', fn($b) => $b->where('pharmacy_id', $pharmacyId));
            })
            ->whereIn('status', [0, 1])
            ->latest();
        $applyFilters($acceptedQuery);
        $accepted = $acceptedQuery
            ->paginate(10, ['*'], 'accepted_page')
            ->fragment('accepted')
            ->withQueryString();

        // ── Ditolak: transfers with denied status involving this pharmacy ──
        $deniedQuery = MedicineTransfers::with(['users.pharmacy'])
            ->where(function ($q) {
                $q->where('status', 2)
                    ->orWhereHas('items', fn($i) => $i->where('status', 2));
            })
            ->where(function ($q) use ($pharmacyId) {
                $q->where('source_pharmacy_id', $pharmacyId)
                    ->orWhere('destination_pharmacy_id', $pharmacyId)
                    ->orWhereHas('items.sourceBatch', fn($sb) => $sb->where('pharmacy_id', $pharmacyId))
                    ->orWhereHas('items.batches', fn($b) => $b->where('pharmacy_id', $pharmacyId));
            })
            ->latest();
        $applyFilters($deniedQuery);
        $denied = $deniedQuery
            ->paginate(10, ['*'], 'denied_page')
            ->fragment('denied')
            ->withQueryString();

        return view('kasir.transfers.transfers', compact('pending', 'accepted', 'denied', 'pendingRequests', 'submittedRequests'));
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
    /**
     * Process item acceptance: ONLY increment destination stock.
     * Source stock was already deducted at transfer creation time.
     */
    private function processItemTransferStock($item, $transfer, $now)
    {
        // 1. Strict guard: If item is not in pending status (0), skip processing to prevent duplicate stock addition
        if ((int) $item->status !== 0) {
            return;
        }

        $destBatch = $item->batches;
        $srcBatch = $item->sourceBatch;
        if (!$srcBatch) {
            $srcBatch = Batches::where('medicine_id', $destBatch->medicine_id)
                ->where('pharmacy_id', 9)
                ->first() ?? $destBatch;
        }
        $medicine = Medicines::findOrFail($destBatch->medicine_id);
        $sourceType = $item->source_type ?? 'gudang';

        $destPharmacyId = $destBatch->pharmacy_id;
        $srcPharmacyId = $srcBatch->pharmacy_id;

        // 2. Strict idempotency guard: check if an incoming log for this transfer code and dest batch already exists
        $hasIncomingLog = ItemsLog::where('transaction_code', $transfer->code)
            ->where('batches_id', $destBatch->id)
            ->where('status', 7)
            ->where('type', 'MU')
            ->where('medicine_id', $medicine->id)
            ->where('qty', $item->qty)
            ->whereRaw('qty_after > qty_before')
            ->exists();

        if ($hasIncomingLog) {
            $item->update(['status' => 1]);
            return;
        }

        // Determine if this is a "return to gudang" (pelayanan → gudang destination)
        $isReturnToGudang = ($sourceType === 'pelayanan' && (isWarehousePharmacy($destPharmacyId) || ($srcPharmacyId == $destPharmacyId && $destPharmacyId == 1)));

        // ── Increment destination ONLY (source already deducted on create) ──
        if ($isReturnToGudang) {
            // Return to gudang: increment batches.stock
            $destQtyBefore = $destBatch->stock;
            $destBatch->increment('stock', $item->qty);
            $destQtyAfter = $destBatch->fresh()->stock;

            $item->update([
                'status' => 1,
                'source_type' => 'retur_gudang',
            ]);
        } else {
            // Add to pelayanan: $item itself becomes the stock record at destination
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
        $currentPharmacyId = getActivePharmacyId();
        if ($transfer->is_request && (int) $transfer->request_status !== 1) {
            return redirect(url()->previous())->with('message', 'Permintaan mutasi harus disetujui pengirim terlebih dahulu.');
        }
        if ($transfer->destination_pharmacy_id && (int) $transfer->destination_pharmacy_id !== $currentPharmacyId) {
            return redirect(url()->previous())->with('message', 'Hanya cabang penerima yang berhak menerima mutasi ini.');
        }
        if ((int) $transfer->status === 1) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Mutasi ini sudah pernah diterima sebelumnya.');
        }
        $firstItem = $transfer->items()->first();
        $destinationPharmacyId = $firstItem?->batches?->pharmacy_id;

        if ($destinationPharmacyId && $destinationPharmacyId != $currentPharmacyId) {
            return redirect(url()->previous())->with('message', 'Hanya apotek tujuan yang berhak menerima mutasi ini.');
        }

        try {
            DB::transaction(function () use ($transfer) {
                $now = Carbon::now();
                $lockedTransfer = MedicineTransfers::whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();
                if ((int) $lockedTransfer->status === 1) {
                    return;
                }

                $pendingItems = $lockedTransfer->items()->where('status', 0)->lockForUpdate()->get();

                foreach ($pendingItems as $item) {
                    $this->processItemTransferStock($item, $lockedTransfer, $now);
                }

                // ── Update parent transfer status ─────────────────────────
                if ($lockedTransfer->items()->where('status', 0)->doesntExist()) {
                    $hasAccepted = $lockedTransfer->items()->where('status', 1)->exists();
                    $lockedTransfer->update(['status' => $hasAccepted ? 1 : 2]);
                }
            });

            return redirect(url()->previous() . '#accepted')->with('success', 'Semua item diterima.');
        } catch (\Throwable $e) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Gagal: ' . $e->getMessage());
        }
    }

    public function getTransferItems($id)
    {
        $transfer = MedicineTransfers::with(['users.pharmacy'])->findOrFail($id);
        $currentPharmacyId = getActivePharmacyId();
        $isParticipant = (int) $transfer->source_pharmacy_id === $currentPharmacyId
            || (int) $transfer->destination_pharmacy_id === $currentPharmacyId
            || $transfer->items()->whereHas('sourceBatch', fn($query) => $query->where('pharmacy_id', $currentPharmacyId))->exists()
            || $transfer->items()->whereHas('batches', fn($query) => $query->where('pharmacy_id', $currentPharmacyId))->exists();
        if (!$isParticipant) abort(403);

        $statusMap = [
            0 => ['Menunggu', 'bg-amber-50 text-amber-700 border-amber-200'],
            1 => ['Diterima', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
            2 => ['Ditolak', 'bg-rose-50 text-rose-700 border-rose-200'],
        ];

        return view('kasir.transfers.partials.items_table', compact('transfer', 'statusMap'));
    }

    public function acceptItem(MedicineTransferItems $item)
    {
        $currentPharmacyId = getActivePharmacyId();
        $transfer = $item->transfer;
        if ($transfer?->is_request && (int) $transfer->request_status !== 1) {
            return redirect(url()->previous())->with('message', 'Permintaan mutasi harus disetujui pengirim terlebih dahulu.');
        }
        if ($transfer?->destination_pharmacy_id && (int) $transfer->destination_pharmacy_id !== $currentPharmacyId) {
            return redirect(url()->previous())->with('message', 'Hanya cabang penerima yang berhak menerima mutasi ini.');
        }
        if ((int) $item->status !== 0) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Item ini sudah pernah diproses.');
        }
        $destinationPharmacyId = $item->batches?->pharmacy_id;

        if ($destinationPharmacyId && $destinationPharmacyId != $currentPharmacyId) {
            return redirect(url()->previous())->with('message', 'Hanya apotek tujuan yang berhak menerima obat ini.');
        }

        try {
            DB::transaction(function () use ($item) {
                $now = Carbon::now();
                $transfer = MedicineTransfers::whereKey($item->medicine_transfer_id)->lockForUpdate()->firstOrFail();
                $lockedItem = MedicineTransferItems::whereKey($item->getKey())->lockForUpdate()->firstOrFail();
                if ((int) $lockedItem->status !== 0) {
                    return;
                }

                $this->processItemTransferStock($lockedItem, $transfer, $now);

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
        $transfer = $item->transfer;
        if ($transfer?->is_request && (int) $transfer->request_status !== 1) {
            return redirect(url()->previous())->with('message', 'Permintaan hanya dapat ditolak dari bagian Permintaan Masuk.');
        }
        $currentPharmacyId = getActivePharmacyId();
        $isParticipant = (int) $transfer?->source_pharmacy_id === $currentPharmacyId
            || (int) $transfer?->destination_pharmacy_id === $currentPharmacyId
            || (int) $item->sourceBatch?->pharmacy_id === $currentPharmacyId
            || (int) $item->batches?->pharmacy_id === $currentPharmacyId;
        if (!$isParticipant) abort(403);

        if ((int) $item->status !== 0) {
            return redirect(url()->previous() . '#denied')->with('message', 'Item ini sudah pernah diproses.');
        }

        try {
            DB::transaction(function () use ($item) {
                $now = Carbon::now();
                $transfer = MedicineTransfers::whereKey($item->medicine_transfer_id)->lockForUpdate()->firstOrFail();
                $lockedItem = MedicineTransferItems::whereKey($item->getKey())->lockForUpdate()->firstOrFail();

                if ((int) $lockedItem->status !== 0) {
                    return;
                }

                // Rollback source stock if it was deducted on create
                if ($lockedItem->stock_deducted_at) {
                    $this->rollbackSourceStock($lockedItem, $transfer, $now);
                }

                $lockedItem->update(['status' => 2]);

                if ($transfer->items()->where('status', 0)->doesntExist()) {
                    $hasAccepted = $transfer->items()->where('status', 1)->exists();
                    $transfer->update(['status' => $hasAccepted ? 1 : 2]);
                }
            });

            $transfer = $item->transfer->fresh();
            if ($transfer->status == 2) {
                return redirect(url()->previous() . '#denied')->with('success', 'Semua item ditolak. Stok dikembalikan.');
            }

            return redirect(url()->previous() . '#accepted')->with('success', 'Item ditolak. Stok dikembalikan.');
        } catch (\Throwable $e) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Gagal: ' . $e->getMessage());
        }
    }

    public function denyTransfer(MedicineTransfers $transfer)
    {
        if ($transfer->is_request && (int) $transfer->request_status !== 1) {
            return redirect(url()->previous())->with('message', 'Gunakan aksi pada bagian Permintaan Masuk untuk menolak permintaan.');
        }
        $currentPharmacyId = getActivePharmacyId();
        if ((int) $transfer->source_pharmacy_id !== $currentPharmacyId && (int) $transfer->destination_pharmacy_id !== $currentPharmacyId) {
            $isParticipant = $transfer->items()
                ->where(function ($query) use ($currentPharmacyId) {
                    $query->whereHas('sourceBatch', fn($batch) => $batch->where('pharmacy_id', $currentPharmacyId))
                        ->orWhereHas('batches', fn($batch) => $batch->where('pharmacy_id', $currentPharmacyId));
                })->exists();
            if (!$isParticipant) abort(403);
        }
        if ((int) $transfer->status === 2) {
            return redirect(url()->previous() . '#denied')->with('message', 'Mutasi ini sudah pernah ditolak.');
        }
        try {
            DB::transaction(function () use ($transfer) {
                $now = Carbon::now();
                $lockedTransfer = MedicineTransfers::whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();
                if ((int) $lockedTransfer->status === 2) {
                    return;
                }

                $pendingItems = $lockedTransfer->items()->where('status', 0)->lockForUpdate()->get();

                foreach ($pendingItems as $item) {
                    // Rollback source stock if it was deducted on create
                    if ($item->stock_deducted_at && (int) $item->status === 0) {
                        $this->rollbackSourceStock($item, $lockedTransfer, $now);
                    }
                    $item->update(['status' => 2]);
                }

                $hasAccepted = $lockedTransfer->items()->where('status', 1)->exists();
                $lockedTransfer->update(['status' => $hasAccepted ? 1 : 2]);
            });

            return redirect(url()->previous() . '#denied')->with('success', 'Mutasi ditolak. Stok telah dikembalikan.');
        } catch (\Throwable $e) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Gagal: ' . $e->getMessage());
        }
    }

    /**
     * Rollback source stock that was deducted at transfer creation time.
     */
    private function rollbackSourceStock($item, $transfer, $now)
    {
        $srcBatch = $item->sourceBatch;
        if (!$srcBatch) return;

        $medicine = Medicines::find($srcBatch->medicine_id);
        $sourceType = $item->source_type ?? 'gudang';

        if ($sourceType === 'gudang') {
            // Return stock to batches.stock
            $srcQtyBefore = $srcBatch->stock;
            $srcBatch->increment('stock', $item->qty);
            $srcQtyAfter = $srcBatch->fresh()->stock;
        } else {
            // Return stock to pelayanan MTI records:
            // Create a new accepted MTI record to restore the pelayanan stock
            $srcQtyBefore = (int) MedicineTransferItems::where('batches_id', $srcBatch->id)
                ->where('qty', '>', 0)
                ->where('status', 1)
                ->where(function ($q) {
                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                })
                ->sum('qty');

            // Find an existing accepted MTI for this batch and increment it back
            $existingMti = MedicineTransferItems::where('batches_id', $srcBatch->id)
                ->where('status', 1)
                ->where(function ($q) {
                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                })
                ->orderBy('id', 'desc')
                ->first();

            if ($existingMti) {
                $existingMti->increment('qty', $item->qty);
            } else {
                // Edge case: no existing MTI record, create a restoration record
                MedicineTransferItems::create([
                    'medicine_transfer_id' => $transfer->id,
                    'batches_id' => $srcBatch->id,
                    'source_batches_id' => $srcBatch->id,
                    'source_type' => 'pelayanan',
                    'etalases_id' => $item->etalases_id,
                    'qty' => $item->qty,
                    'status' => 1, // accepted = stok tersedia
                ]);
            }

            $srcQtyAfter = $srcQtyBefore + $item->qty;
        }

        // Restore medicines master stock
        $medRecord = Medicines::find($srcBatch->medicine_id);
        if ($medRecord) {
            $medRecord->increment('stock', $item->qty);
        }

        // ── Log rollback (stock returned to source) ───────────────
        ItemsLog::create([
            'transaction_code' => $transfer->code,
            'code' => $this->generateItemsLogCode(),
            'type' => 'MU',
            'medicine_id' => $srcBatch->medicine_id,
            'qty' => $item->qty,
            'qty_before' => $srcQtyBefore,
            'qty_after' => $srcQtyAfter,
            'total' => 0,
            'date' => $now,
            'status' => 7,
            'batches_id' => $srcBatch->id,
            'user_id' => auth()->id(),
        ]);
    }

    private function authorizeTransferParticipant(MedicineTransfers $transfer): void
    {
        $currentPharmacyId = getActivePharmacyId();
        $isParticipant = (int) $transfer->source_pharmacy_id === $currentPharmacyId
            || (int) $transfer->destination_pharmacy_id === $currentPharmacyId
            || $transfer->items()->whereHas('sourceBatch', fn($query) => $query->where('pharmacy_id', $currentPharmacyId))->exists()
            || $transfer->items()->whereHas('batches', fn($query) => $query->where('pharmacy_id', $currentPharmacyId))->exists();
        abort_unless($isParticipant, 403);
    }
}
