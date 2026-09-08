<?php

namespace App\Http\Controllers;

use App\Models\Batches;
use App\Models\History;
use App\Models\ItemsLog;
use App\Models\MedicineCart;
use App\Models\Medicines;
use App\Models\MedicineTransfers;
use App\Models\MedicineTransferItems;
use App\Models\MedicineTransactions;
use App\Models\Receiving;
use App\Models\ReceivingItems;
use App\Models\ReceivingDetails;
use App\Models\Retur;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturController extends Controller
{

    public function retur()
    {
        $now = Carbon::now()->format('d/m/Y');
        $retur_code = $this->generateReturCode();
        return view('kasir.retur.sales_retur', compact('retur_code', 'now'));
    }
    public function returOrders()
    {
        $now = Carbon::now()->format('d/m/Y');
        $retur_code = $this->generateReturOrderCode();
        return view('kasir.retur.orders_retur', compact('retur_code', 'now'));
    }


    public static function generateMedicineCode()
    {
        return \App\Models\Medicines::generateCode();
    }
    public function returdata(Request $request)
    {
        $search = $request->search;

        $data = MedicineCart::query()
            ->with(['transactions.patients'])
            ->whereHas('transactions', function ($q) use ($search) {
                $q->where('transaction_type', '!=', 'RETUR')
                    // 1. Pastikan pharmacy_id selalu terfilter
                    ->where('pharmacy_id', getActivePharmacyId())
                    // 2. Grup terpisah khusus untuk logic pencarian (OR)
                    ->where(function ($q2) use ($search) {
                        $q2->where('transaction_code', 'LIKE', "%{$search}%")
                            ->orWhereHas('patients', function ($q3) use ($search) {
                                $q3->where('name', 'LIKE', "%{$search}%");
                            });
                    });
            })
            ->select('transaction_id')
            ->selectRaw('SUM(final_price) as final_price')
            ->groupBy('transaction_id')
            ->orderByDesc('transaction_id')
            ->paginate(10);

        // format response for frontend
        $data->getCollection()->transform(function ($item) {
            return [
                'transaction_code' => $item->transactions->transaction_code,
                'name' => $item->transactions->patients->name,
                'final_price' => $item->final_price,
            ];
        });

        return response()->json($data);
    }
    public function getReturMedicines(Request $request)
    {
        $transactionCode = $request->transaction_code;

        if (empty($transactionCode)) {
            return response()->json([]);
        }

        $transactionCart = MedicineCart::with(['medicine', 'transactions'])
            ->whereHas('transactions', function ($q) use ($transactionCode) {
                $q->where('transaction_code', $transactionCode);
            })
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'medicine_id' => $item->medicine_id,
                    'transaction_id' => $item->transaction_id ?? null,
                    'quantity' => $item->quantity,
                    'final_price' => $item->final_price,
                    'item_price' => $item->item_price ?? 0,
                    'medicine' => [
                        'id' => $item->medicine->id ?? null,
                        'code' => $item->medicine->code ?? '',
                        'name' => $item->medicine->name ?? '',
                        'unit' => $item->medicine->unit ?? '',
                    ],
                ];
            });

        return response()->json($transactionCart);
    }
    function generateReturCode()
    {
        $now = Carbon::now();

        $year = $now->format('y');
        $month = $now->format('m');
        $prefix = "{$year}{$month}R";

        $lastCode = Retur::where('code', 'like', "{$prefix}%")
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
    function generateReturOrderCode()
    {
        $now = Carbon::now();

        $year = $now->format('y');
        $month = $now->format('m');
        $prefix = "{$year}{$month}R";

        $lastCode = Retur::where('code', 'like', "{$prefix}%")
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
    public function returItem(Request $request)
    {

        $request->validate([
            'transaction_id' => 'required|integer',
            'medicine_id' => 'required|integer',
            'qty_retur' => 'required|numeric|min:1',
            'total_retur' => 'required',
            'old_qty' => 'required',

        ]);
        DB::beginTransaction();

        try {
            $findcode = MedicineTransactions::findOrFail($request->transaction_id);
            $now = Carbon::now()->format('Y-m-d');


            $medicine = Medicines::where('id', $request->medicine_id)
                ->lockForUpdate()
                ->firstOrFail();
            $qty_before = $medicine->stock;



            // $batches = Batches::where('medicine_id', $request->medicine_id)
            //     ->where('expired_at', $request->expired_date)
            //     ->where('name', $request->batch)
            //     ->first();
            // if ($batches) {
            //     $batches->increment('stock', $request->qty_rsetur);
            // }

            $transfer = MedicineTransferItems::findOrFail($request->transfer_id);
            $transfer->qty += $request->qty_retur; // add back to counter stock
            $transfer->save();


            // Create Retur (Retur Sales = 3)
            $itemsLog = ItemsLog::create([
                'transaction_code' => $findcode->transaction_code,
                'code' => $this->generateItemsLogCode(),
                'type' => "RT",
                'medicine_id' => $request->medicine_id,
                'qty' => $request->qty_retur,
                'qty_before' => $qty_before,
                'qty_after' => $medicine->stock + $request->qty_retur,
                'total' => $request->total_retur,
                'date' => $now,
                'status' => 3,
                'batches_id' => $transfer->batches_id,
                'user_id' => auth()->user()->id,
            ]);

            $activeshift = activeShift();

            // Create New Retur Transaction
            $getTransactiondata = MedicineTransactions::findOrFail($request->transaction_id);
            $transaction = MedicineTransactions::create([
                'pharmacy_id' => $getTransactiondata->pharmacy_id,
                "debtor_id" => $getTransactiondata->debtor_id,
                "doctor_id" => $getTransactiondata->doctor_id,
                "patient_id" => $getTransactiondata->patient_id,
                "transaction_type" => "RETUR JUAL",
                "transaction_code" => "RT",
                "paid" => "-",
                "changes" => "-",
                "subtotal" => $request->total_retur,
                "discount" => "-",
                "shift_logs_id" => $activeshift->id,
                "status" => $getTransactiondata->status,
                "created_at" => $getTransactiondata->created_at,
                "updated_at" => $getTransactiondata->updated_at,
            ]);


            // Get & Increase stock
            $medicine->increment('stock', $request->qty_retur);


            // if ($request->old_qty - $request->qty_retur == 0) {
            //     $cart->delete();
            //     $cart->update([
            //         'final_price'   => $cart->final_price - $request->total_retur - $cart->discount,
            //         'total_price'   => $cart->final_price - $request->total_retur,
            //         'quantity'      => $request->old_qty - $request->qty_retur,
            //     ]);
            // } else if ($request->old_qty - $request->qty_retur > 0) {
            //     $cart->update([
            //         'final_price'   => $cart->final_price - $request->total_retur - $cart->discount,
            //         'total_price'   => $cart->final_price - $request->total_retur,
            //         'quantity'      => $request->old_qty - $request->qty_retur,
            //     ]);
            // } else {

            // }


            // Get & Update Transaction
            // $transaction = MedicineTransactions::findOrFail($request->transaction_id);
            // $transaction->update([
            //     'status'        => 2,
            //     'total_retur'   => $request->total_retur,
            //     'updated_at'    => now(),
            // ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Retur berhasil disimpan.',
                'retur_code' => $this->generateReturCode(), // next retur code
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();
            
            \Log::error('Retur Error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan retur: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function getBatchesByMedicine(Request $request)
    {
        $medicine_id = $request->medicine_id;

        $transfers = MedicineTransferItems::join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
            ->join('etalases', 'medicine_transfer_items.etalases_id', '=', 'etalases.id')
            ->where('batches.medicine_id', $medicine_id)
            ->where('pharmacy_id', getActivePharmacyId())
            ->orderBy('batches.expired_date', 'asc') // FEFO
            ->select(
                'medicine_transfer_items.id as transfer_id',
                'medicine_transfer_items.qty as counter_stock',
                'batches.id as batch_id',
                'batches.name as batch_name',
                'batches.expired_date',
                'etalases.name as etalase_name',
            )
            ->get();

        return response()->json($transfers);
    }





    // Retur Order

    public function getBatchesByOrderedMedicine(Request $request)
    {
        $request->validate([
            'medicine_id' => 'required|integer|exists:medicines,id',
        ]);

        $pharmacyId = getActivePharmacyId();
        $targetPharmacyIds = in_array((int) $pharmacyId, [1, 6, 9]) ? [9, 1] : [(int) $pharmacyId];

        // Return batches ordered by active pharmacy first, then latest created.
        // We only return batches with stock > 0 scoped to the active pharmacy/gudang.
        $batches = Batches::where('medicine_id', $request->medicine_id)
            ->where(function ($q) use ($targetPharmacyIds) {
                $q->whereIn('pharmacy_id', $targetPharmacyIds)
                    ->orWhereNull('pharmacy_id');
            })
            ->where('stock', '>', 0)
            ->orderByRaw("CASE WHEN pharmacy_id = ? THEN 0 ELSE 1 END", [$pharmacyId])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'expired_date', 'stock', 'pharmacy_id']);

        if ($batches->isEmpty()) {
            $batches = Batches::where('medicine_id', $request->medicine_id)
                ->where('stock', '>', 0)
                ->orderByRaw("CASE WHEN pharmacy_id = ? THEN 0 ELSE 1 END", [$pharmacyId])
                ->orderBy('created_at', 'desc')
                ->get(['id', 'name', 'expired_date', 'stock', 'pharmacy_id']);
        }

        return response()->json($batches);
    }

    // ─── 2. Get medicines for a receiving transaction ─────────────────────────────
    public function getReturOrderMedicines(Request $request)
    {
        $transactionCode = $request->transaction_code;

        if (empty($transactionCode)) {
            return response()->json([]);
        }

        $transactionCart = ReceivingItems::with([
            'receiving_details.receiving',
            'order_items.medicines',
        ])
            ->whereHas('receiving_details', function ($q) use ($transactionCode) {
                $q->where('receiving_details_code', $transactionCode)
                    ->orWhere('invoice_number', $transactionCode)
                    ->orWhere('sp_code', $transactionCode)
                    ->orWhereHas('receiving', fn($rq) => $rq->where('code', $transactionCode));
            })
            ->get();

        $result = $transactionCart->map(function ($item) {
            $medicine = $item->order_items->medicines ?? null;
            $receiving = $item->receiving_details->receiving ?? null;
            $orderItem = $item->order_items ?? null;

            $isPack = ($orderItem?->pack == 1);
            $content = (int) ($medicine?->content ?? 1);
            if ($content < 1) $content = 1;

            $packaging = !empty($medicine?->packaging) ? trim($medicine->packaging) : 'BOX';
            $unit = !empty($medicine?->unit) ? trim($medicine->unit) : 'TAB';

            $qtyReceived = (float) ($item->qty_received ?? 0);
            $rawPrice = (float) ($item->raw_price ?: ($medicine?->raw_price ?: ($item->qty_received > 0 ? ($item->total / $item->qty_received) : 0)));

            if ($isPack) {
                $packPrice = $rawPrice;
                $unitPrice = $content > 0 ? round($packPrice / $content, 2) : $packPrice;
                $qtyPack = $qtyReceived;
                $qtyUnit = $qtyReceived * $content;
            } else {
                $unitPrice = $rawPrice;
                $packPrice = round($unitPrice * $content, 2);
                $qtyUnit = $qtyReceived;
                $qtyPack = $content > 1 ? round($qtyReceived / $content, 2) : $qtyReceived;
            }

            // Calculate existing returns for this transaction & medicine
            $alreadyReturnedUnit = (float) ItemsLog::where('transaction_code', $receiving?->code)
                ->where('medicine_id', $medicine?->id)
                ->where('status', 4)
                ->sum('qty');

            $remainingUnit = max(0, $qtyUnit - $alreadyReturnedUnit);
            $remainingPack = $content > 1 ? floor($remainingUnit / $content) : $remainingUnit;

            return [
                'id' => $item->id,
                'medicine_id' => $medicine?->id,
                'code' => $medicine?->code ?? '-',
                'name' => $medicine?->name ?? '-',
                'packaging' => $packaging,
                'unit' => $unit,
                'content' => $content,
                'is_pack' => $isPack,
                'raw_price' => $isPack ? $packPrice : $unitPrice,
                'pack_price' => $packPrice,
                'unit_price' => $unitPrice,
                'qty_received' => $qtyReceived,
                'qty_received_pack' => $qtyPack,
                'qty_received_unit' => $qtyUnit,
                'already_returned_unit' => $alreadyReturnedUnit,
                'remaining_pack' => $remainingPack,
                'remaining_unit' => $remainingUnit,
                'total' => (float) ($item->total ?? 0),
                'receiving_id' => $receiving?->id,
            ];
        });

        return response()->json($result);
    }

    // ─── 3. Search receiving transactions for retur ───────────────────────────────
    public function returOrderdata(Request $request)
    {
        $search = trim((string) $request->search);
        $pharmacyId = getActivePharmacyId();
        $targetPharmacyIds = in_array((int) $pharmacyId, [1, 6, 9]) ? [9, 1] : [(int) $pharmacyId];

        $query = ReceivingDetails::query()
            ->with(['receiving', 'receiving_items.order_items.medicines', 'creditor']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('receiving_details_code', 'LIKE', "%{$search}%")
                    ->orWhere('invoice_number', 'LIKE', "%{$search}%")
                    ->orWhere('sp_code', 'LIKE', "%{$search}%")
                    ->orWhereHas('creditor', fn($cq) => $cq->where('name', 'LIKE', "%{$search}%"))
                    ->orWhereHas('receiving', fn($rq) => $rq->where('code', 'LIKE', "%{$search}%"));
            });
        }

        $data = $query->whereHas('receiving', function ($q) use ($targetPharmacyIds) {
            $q->where('status', '>=', 1)
                ->whereIn('pharmacy_id', $targetPharmacyIds);
        })
        ->orderByDesc('id')
        ->paginate(10);

        $data->getCollection()->transform(function ($item) {
            $finalPrice = $item->receiving_items->sum('total');

            return [
                'transaction_code' => $item->receiving_details_code ?: ($item->receiving?->code ?: $item->invoice_number),
                'invoice_number' => $item->invoice_number,
                'name' => $item->creditor->name ?? '-',
                'final_price' => $finalPrice,
            ];
        });

        return response()->json($data);
    }

    // ─── 4. Save retur item (AJAX-ready, returns JSON, fixes stock-check order) ───
    public function returOrderItems(Request $request)
    {
        // ── Normalize numeric inputs to handle string formatting (Rp, dots, commas) ──
        if ($request->has('total_retur')) {
            $rawTotal = (string) $request->total_retur;
            $rawTotal = str_ireplace(['rp', 'rp.', ' '], '', $rawTotal);
            if (substr_count($rawTotal, '.') > 1) {
                $rawTotal = str_replace('.', '', $rawTotal);
            } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $rawTotal)) {
                $rawTotal = str_replace('.', '', $rawTotal);
            } elseif (str_contains($rawTotal, ',') && str_contains($rawTotal, '.')) {
                $rawTotal = str_replace('.', '', $rawTotal);
                $rawTotal = str_replace(',', '.', $rawTotal);
            } elseif (str_contains($rawTotal, ',')) {
                $rawTotal = str_replace(',', '.', $rawTotal);
            }
            $request->merge(['total_retur' => is_numeric($rawTotal) ? (float) $rawTotal : $rawTotal]);
        }

        if ($request->has('qty_retur')) {
            $rawQty = str_replace([' ', ','], ['', '.'], (string) $request->qty_retur);
            $request->merge(['qty_retur' => is_numeric($rawQty) ? (float) $rawQty : $rawQty]);
        }

        if ($request->has('old_qty')) {
            $rawOldQty = str_replace([' ', ','], ['', '.'], (string) $request->old_qty);
            $request->merge(['old_qty' => is_numeric($rawOldQty) ? (float) $rawOldQty : $rawOldQty]);
        }

        $request->validate([
            'transaction_id' => 'required|integer',
            'medicine_id'    => 'required|integer',
            'batch_id'       => 'required|integer|exists:batches,id',
            'retur_type'     => 'nullable|string|in:packaging,unit',
            'qty_retur'      => 'required|numeric|min:0.01',
            'total_retur'    => 'required|numeric|min:0',
            'old_qty'        => 'required|numeric',
        ], [
            'total_retur.required' => 'Total retur wajib diisi.',
            'total_retur.numeric'  => 'Total retur harus berupa angka.',
            'qty_retur.required'   => 'Qty retur wajib diisi.',
            'qty_retur.numeric'    => 'Qty retur harus berupa angka.',
            'qty_retur.min'        => 'Qty retur minimal 0.01.',
            'batch_id.required'    => 'Batch obat wajib dipilih.',
            'batch_id.exists'      => 'Batch obat tidak valid.',
        ]);

        DB::beginTransaction();

        try {
            $findcode = Receiving::find($request->transaction_id);
            if (!$findcode) {
                $detail = ReceivingDetails::with('receiving')->find($request->transaction_id);
                $findcode = $detail?->receiving;
            }
            if (!$findcode) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaksi penerimaan tidak ditemukan.',
                ], 404);
            }
            $now = Carbon::now()->format('Y-m-d');

            // Lock medicine and batch rows to prevent race conditions
            $medicine = Medicines::where('id', $request->medicine_id)
                ->lockForUpdate()
                ->firstOrFail();

            $batch = Batches::where('id', $request->batch_id)
                ->lockForUpdate()
                ->firstOrFail();

            $content = (int) ($medicine->content ?? 1);
            if ($content < 1) $content = 1;

            $returType = $request->input('retur_type', 'unit');
            $qtyInput = (float) $request->qty_retur;

            if ($returType === 'packaging') {
                $actualDeduct = $qtyInput * $content;
            } else {
                $actualDeduct = $qtyInput;
            }

            // ── Stock check BEFORE any decrement ──
            if ($medicine->stock < $actualDeduct) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stok obat tidak mencukupi untuk diretur (stok saat ini: ' . $medicine->stock . ' ' . $medicine->unit . ', dibutuhkan: ' . $actualDeduct . ' ' . $medicine->unit . ').',
                ], 422);
            }

            if ($batch->stock < $actualDeduct) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stok batch ' . $batch->name . ' tidak mencukupi untuk diretur (stok batch: ' . $batch->stock . ' ' . $medicine->unit . ', dibutuhkan: ' . $actualDeduct . ' ' . $medicine->unit . ').',
                ], 422);
            }

            $qty_before = $medicine->stock;

            // Decrement both medicine stock and batch stock
            $medicine->decrement('stock', $actualDeduct);
            $batch->decrement('stock', $actualDeduct);

            // Also decrement MedicineTransferItems if present in outlet pharmacy
            $transferItem = MedicineTransferItems::where('batches_id', $batch->id)->first();
            if ($transferItem && $transferItem->qty >= $actualDeduct) {
                $transferItem->decrement('qty', $actualDeduct);
            }

            // Write items log (Retur Pembelian status = 4)
            ItemsLog::create([
                'transaction_code' => $findcode->code,
                'code'             => $this->generateItemsLogCode(),
                'type'             => 'RT',
                'medicine_id'      => $request->medicine_id,
                'qty'              => $actualDeduct, // Disimpan dalam satuan fisik eceran agar kartu stok persediaan akurat!
                'qty_before'       => $qty_before,
                'qty_after'        => $qty_before - $actualDeduct,
                'total'            => $request->total_retur,
                'date'             => $now,
                'status'           => 4,
                'batches_id'       => $batch->id,
                'user_id'          => auth()->user()->id,
            ]);

            DB::commit();

            $unitName = $medicine->unit ?: 'satuan';
            $packName = $medicine->packaging ?: 'kemasan';
            $desc = $returType === 'packaging'
                ? "{$qtyInput} {$packName} ({$actualDeduct} {$unitName})"
                : "{$qtyInput} {$unitName}";

            return response()->json([
                'status'     => 'success',
                'message'    => "Retur pembelian sebanyak {$desc} berhasil disimpan.",
                'retur_code' => $this->generateReturOrderCode(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menyimpan retur: ' . $e->getMessage(),
            ], 500);
        }
    }
}
