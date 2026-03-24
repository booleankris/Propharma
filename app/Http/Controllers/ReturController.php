<?php

namespace App\Http\Controllers;

use App\Models\Batches;
use App\Models\History;
use App\Models\ItemsLog;
use App\Models\MedicineCart;
use App\Models\Medicines;
use App\Models\MedicineTransactions;
use App\Models\Receiving;
use App\Models\ReceivingItems;
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
            $prefix = str_pad(((int)$prefix) + 1, 4, '0', STR_PAD_LEFT);
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
                // Filter transaksi yang bukan RETUR
                $q->where('transaction_type', '!=', 'RETUR')
                    ->where(function ($q2) use ($search) {
                        // Filter search code atau patient name
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
                'name'             => $item->transactions->patients->name,
                'final_price'      => $item->final_price,
            ];
        });

        return response()->json($data);
    }
    public function getReturMedicines(Request $request)
    {
        $transactionCode = $request->transaction_code;

        $transactionCart = MedicineCart::with([
            'medicine',
            'transactions'
        ])
            ->whereHas('transactions', function ($q) use ($transactionCode) {
                $q->where('transaction_code', $transactionCode);
            })
            ->get();


        $transactionCart->map(function ($item) {
            return [
                'medicine_id'   => $item->medicine_id,
                'name'          => $item->medicine->name,
                'quantity'      => $item->quantity,
                'final_price'   => $item->final_price,
            ];
        });


        return response()->json($transactionCart);
    }
    function generateReturCode()
    {
        $now = Carbon::now();

        $year  = $now->format('y');
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

        $year  = $now->format('y');
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

        $year  = $now->format('y');
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
            'medicine_id'    => 'required|integer',
            'qty_retur'      => 'required|numeric|min:1',
            'total_retur'    => 'required',
            'old_qty'        => 'required',

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

            $batch = Batches::where('medicine_id', $request->medicine_id)
                ->where('name', $request->batch)
                ->where('expired_date', $request->expired_date)
                ->lockForUpdate()
                ->first();

            if (!$batch) {
                $batch = Batches::create([
                    'medicine_id'  => $request->medicine_id,
                    'name'         => $request->batch,
                    'expired_date' => $request->expired_date,
                    'stock'        => 0,
                    'status'       => 0,
                ]);
            }

            $batch->increment('stock', $request->qty_retur);

            // Create Retur (Retur Sales = 3)
            $itemsLog = ItemsLog::create([
                'transaction_code' => $findcode->transaction_code,
                'code'             => $this->generateItemsLogCode(),
                'type'             => "RT",
                'medicine_id'      => $request->medicine_id,
                'qty'              => $request->qty_retur,
                'qty_before'       => $qty_before,
                'qty_after'        => $medicine->stock + $request->qty_retur,
                'total'            => $request->total_retur,
                'date'             => $now,
                'status'           => 3,
                'batches_id'       => $batch->id
            ]);

            // Create New Retur Transaction
            $getTransactiondata = MedicineTransactions::findOrFail($request->transaction_id);
            $transaction = MedicineTransactions::create([
                'pharmacy_id'           => $getTransactiondata->pharmacy_id,
                "debtor_id"             => $getTransactiondata->debtor_id,
                "doctor_id"             => $getTransactiondata->doctor_id,
                "patient_id"            => $getTransactiondata->patient_id,
                "transaction_type"      => "RETUR JUAL",
                "transaction_code"      => "RT",
                "paid"                  => "-",
                "changes"               => "-",
                "subtotal"              => $request->total_retur,
                "discount"              => "-",
                "status"                => $getTransactiondata->status,
                "created_at"            => $getTransactiondata->created_at,
                "updated_at"            => $getTransactiondata->updated_at,
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

            return redirect()
                ->back()
                ->with('success', 'Retur berhasil disimpan');
        } catch (\Throwable $e) {

            DB::rollBack();

            return redirect()
                ->back()
                ->with('error', 'Gagal menyimpan retur: ' . $e->getMessage());
        }
    }





    // Retur Order

    public function getReturOrderMedicines(Request $request)
    {
        $transactionCode = $request->transaction_code;

        $transactionCart = ReceivingItems::with([
            'receiving_details.receiving',
            'order_items.medicines'
        ])
            ->whereHas('receiving_details.receiving', function ($q) use ($transactionCode) {
                $q->where('code', $transactionCode);
            })
            ->get();

        $transactionCart->map(function ($item) {
            return [
                'medicine_id'   => $item->order_items->medicines->id,
                'name'          => $item->order_items->medicines->name,
                'quantity'      => $item->order_items->medicines->quantity,
                'final_price'   => $item->order_items->medicines->final_price,
            ];
        });


        return response()->json($transactionCart);
    }

    public function returOrderdata(Request $request)
    {
        $search = $request->search;

        $data = Receiving::query()
            ->with(['receiving_details.receiving_items.order_items.medicines'])
            ->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                    ->orwhereHas(
                        'receiving_details',
                        fn($q_invoice) =>
                        $q_invoice->where('invoice_number', 'LIKE', "%{$search}%")
                    )->orwhereHas(
                        'receiving_details.receiving_items.order_items.medicines',
                        fn($q_med) =>
                        $q_med->where('name', 'LIKE', "%{$search}%")
                    );
            })
            ->where('status', 1)
            ->paginate(10);


        $data->getCollection()->transform(function ($item) {

            $finalPrice = $item->receiving_details
                ->flatMap(fn($detail) => $detail->receiving_items)
                ->sum('total');
            return [
                'transaction_code' => $item->code,
                'name'             => optional($item->receiving_details->first())->invoice_number,
                'final_price'      => $finalPrice,
            ];
        });

        return response()->json($data);
    }
    public function returOrderItems(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|integer',
            'medicine_id'    => 'required|integer',
            'qty_retur'      => 'required|numeric|min:1',
            'total_retur'    => 'required',
            'old_qty'        => 'required',

        ]);

        DB::beginTransaction();

        try {

            // $retur = Retur::create([
            //     'code'           => $this->generateReturCode(),
            //     'transaction_id' => $request->transaction_id,
            //     'medicine_id'    => $request->medicine_id,
            //     'qty_retur'      => $request->qty_retur,
            //     'total_retur'    => $request->total_retur,
            //     'status'         => 2,
            // ]);

            // Create Retur Log (Retur Orders = 4)
            $findcode = Receiving::findOrFail($request->transaction_id);
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

            $batch = Batches::where('medicine_id', $request->medicine_id)
                ->where('name', $request->batch)
                ->where('expired_date', $request->expired_date)
                ->lockForUpdate()
                ->first();

            if (!$batch) {
                $batch = Batches::create([
                    'medicine_id'  => $request->medicine_id,
                    'name'         => $request->batch,
                    'expired_date' => $request->expired_date,
                    'stock'        => 0,
                    'status'       => 0,
                ]);
            }

            $batch->decrement('stock', $request->qty_retur);

            $itemsLog = ItemsLog::create([
                'transaction_code' => $findcode->code,
                'code'             => $this->generateItemsLogCode(),
                'type'             => "RT",
                'medicine_id'      => $request->medicine_id,
                'qty'              => $request->qty_retur,
                'qty_before'       => $qty_before,
                'qty_after'        => $medicine->stock - $request->qty_retur,
                'total'            => $request->total_retur,
                'date'             => $now,
                'status'           => 4,
                'batches_id'       => $batch->id
            ]);

            // Create New Retur Transaction
            // $getTransactiondata = MedicineTransactions::findOrFail($request->transaction_id);
            // $transaction = MedicineTransactions::create([
            //     'pharmacy_id'           => $getTransactiondata->pharmacy_id,
            //     "debtor_id"             => $getTransactiondata->debtor_id,
            //     "doctor_id"             => $getTransactiondata->doctor_id,
            //     "patient_id"            => $getTransactiondata->patient_id,
            //     "transaction_type"      => "RETUR BELI",
            //     "transaction_code"      => "RT",
            //     "paid"                  => $getTransactiondata->paid,
            //     "changes"               => $getTransactiondata->changes,
            //     "subtotal"              => $getTransactiondata->subtotal,
            //     "discount"              => $getTransactiondata->discount,
            //     "status"                => $getTransactiondata->status,
            //     "created_at"            => $getTransactiondata->created_at,
            //     "updated_at"            => $getTransactiondata->updated_at,
            // ]);


            // Get & Update Cart
            $medicine = Medicines::findOrFail($request->medicine_id);

            if ($medicine->stock < $request->qty_retur) {
                return response()->json([
                    'message' => 'Stok Kurang'
                ], 400);
            }

            $medicine->decrement('stock', $request->qty_retur);


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

            return redirect()
                ->back()
                ->with('success', 'Retur berhasil disimpan');
        } catch (\Throwable $e) {

            DB::rollBack();

            return redirect()
                ->back()
                ->with('error', 'Gagal menyimpan retur: ' . $e->getMessage());
        }
    }
}
