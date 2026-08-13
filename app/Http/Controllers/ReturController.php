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
        $last = self::orderBy('id', 'desc')->first();

        if (!$last || !$last->code) {
            return '054000000';
        }

        $prefix = substr($last->code, 0, 4);
        $number = (int) substr($last->code, 4);

        if ($number >= 99999) {
            $prefix = str_pad(((int) $prefix) + 1, 4, '0', STR_PAD_LEFT);
            $number = 0;
        } else {
            $number++;
        }

        return $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
    }
    public function returdata(Request $request)
    {
        $search = $request->search;

        $data = MedicineCart::query()
            ->with(['transactions.patients'])
            ->whereHas('transactions', function ($q) use ($search) {
                $q->where('transaction_type', '!=', 'RETUR')
                    // 1. Pastikan pharmacy_id selalu terfilter
                    ->where('pharmacy_id', auth()->user()->pharmacy_id)
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

            $transfer = MedicineTransfers::findOrFail($request->transfer_id);
            $transfer->stock += $request->qty_retur; // add back to counter stock
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

            return redirect()
                ->back()
                ->with('error', 'Gagal menyimpan retur: ' . $e->getMessage());
        }
    }
    public function getBatchesByMedicine(Request $request)
    {
        $medicine_id = $request->medicine_id;

        $transfers = MedicineTransferItems::join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
            ->join('etalases', 'medicine_transfer_items.etalases_id', '=', 'etalases.id')
            ->where('batches.medicine_id', $medicine_id)
            ->where('pharmacy_id', auth()->user()->pharmacy_id)
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

        // Return batches ordered by created_at DESC so the latest batch appears first.
        // We only return batches with stock > 0 so the user can only retur what exists.
        $batches = Batches::where('medicine_id', $request->medicine_id)
            ->where('stock', '>', 0)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'expired_date', 'stock']);

        return response()->json($batches);
    }

    // ─── 2. Get medicines for a receiving transaction ─────────────────────────────
    // (Fixes the broken ->map() that was discarding results and not returning batches)
    public function getReturOrderMedicines(Request $request)
    {
        $transactionCode = $request->transaction_code;

        $transactionCart = ReceivingItems::with([
            'receiving_details.receiving',
            'order_items.medicines',
        ])
            ->whereHas('receiving_details', function ($q) use ($transactionCode) {
                $q->where('receiving_details_code', $transactionCode)
                    ->orWhere('invoice_number', $transactionCode);
            })
            ->get();

        // map() result was previously discarded — fixed here
        $result = $transactionCart->map(function ($item) {
            $medicine = $item->order_items->medicines ?? null;
            $receiving = $item->receiving_details->receiving ?? null;

            return [
                'id' => $item->id,
                'medicine_id' => $medicine?->id,
                'code' => $medicine?->code,
                'name' => $medicine?->name,
                'unit' => $medicine?->unit,
                'content' => $medicine?->content,
                'raw_price' => $medicine?->raw_price,
                'qty_received' => $item->qty_received,
                'total' => $item->total,
                'receiving_id' => $receiving?->id,
            ];
        });

        return response()->json($result);
    }

    // ─── 3. Search receiving transactions for retur ───────────────────────────────
    public function returOrderdata(Request $request)
    {
        $search = $request->search;

        $data = ReceivingDetails::query()
            ->with(['receiving', 'receiving_items.order_items.medicines'])
            ->where(function ($q) use ($search) {
                $q->where('receiving_details_code', 'LIKE', "%{$search}%")
                    ->orWhere('invoice_number', 'LIKE', "%{$search}%");
            })
            ->whereHas('receiving', function ($q) {
                $q->where('status', '>=', 1)
                  ->where('pharmacy_id', auth()->user()->pharmacy_id);
            })
            ->paginate(10);

        $data->getCollection()->transform(function ($item) {
            $finalPrice = $item->receiving_items->sum('total');

            return [
                'transaction_code' => $item->receiving_details_code ?? $item->invoice_number,
                'name' => $item->invoice_number,
                'final_price' => $finalPrice,
            ];
        });

        return response()->json($data);
    }

    // ─── 4. Save retur item (AJAX-ready, returns JSON, fixes stock-check order) ───
    public function returOrderItems(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|integer',
            'medicine_id' => 'required|integer',
            'batch_id' => 'required|integer|exists:batches,id',
            'qty_retur' => 'required|numeric|min:1',
            'total_retur' => 'required|numeric',
            'old_qty' => 'required|numeric',
        ]);

        DB::beginTransaction();

        try {
            $findcode = Receiving::findOrFail($request->transaction_id);
            $now = Carbon::now()->format('Y-m-d');

            // Lock medicine and batch rows to prevent race conditions
            $medicine = Medicines::where('id', $request->medicine_id)
                ->lockForUpdate()
                ->firstOrFail();

            $batch = Batches::where('id', $request->batch_id)
                ->lockForUpdate()
                ->firstOrFail();

            // ── Stock check BEFORE any decrement (was after decrement — critical bug fix) ──
            if ($medicine->stock < $request->qty_retur) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stok obat tidak mencukupi untuk diretur.',
                ], 422);
            }

            if ($batch->stock < $request->qty_retur) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stok batch tidak mencukupi untuk diretur.',
                ], 422);
            }

            $qty_before = $medicine->stock;

            // Decrement both medicine stock and batch stock
            $medicine->decrement('stock', $request->qty_retur);
            $batch->decrement('stock', $request->qty_retur);

            // Write items log
            ItemsLog::create([
                'transaction_code' => $findcode->code,
                'code' => $this->generateItemsLogCode(),
                'type' => 'RT',
                'medicine_id' => $request->medicine_id,
                'qty' => $request->qty_retur,
                'qty_before' => $qty_before,
                'qty_after' => $qty_before - $request->qty_retur,
                'total' => $request->total_retur,
                'date' => $now,
                'status' => 4,
                'batches_id' => $batch->id,
                'user_id' => auth()->user()->id,

            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Retur berhasil disimpan.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan retur: ' . $e->getMessage(),
            ], 500);
        }
    }
}
