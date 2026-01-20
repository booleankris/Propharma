<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\MedicineCart;
use App\Models\MedicineTransactions;
use App\Models\Retur;
use Carbon\Carbon;
use Illuminate\Http\Request;
use DataTables;
use Form;
use Illuminate\Support\Facades\DB;

class SalesDataController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {

            $search = trim($request->input('search.value'));
            $parsedDate = null;

            // Try parsing dd/mm/YYYY
            try {
                $parsedDate = Carbon::createFromFormat('d/m/Y', $search);
            } catch (\Exception $e) {
                $parsedDate = null;
            }

            $query = MedicineCart::query()
                ->with(['transactions.patients'])
                ->where('status', 1)
                ->selectRaw('
                transaction_id,
                MAX(created_at) as created_at,
                SUM(final_price) as final_price
            ')
                ->groupBy('transaction_id');

            if ($search) {
                $query->where(function ($q) use ($search, $parsedDate) {

                    // ✅ Exact date search (31/12/2025)
                    if ($parsedDate) {
                        $q->whereDate('created_at', $parsedDate->format('Y-m-d'));
                    }

                    // ✅ Year search (2026)
                    if (preg_match('/^\d{4}$/', $search)) {
                        $q->orWhereYear('created_at', $search);
                    }

                    // ✅ Transaction code
                    $q->orWhereHas('transactions', function ($t) use ($search) {
                        $t->where('transaction_code', 'like', "%{$search}%");
                    });

                    // ✅ Patient name
                    $q->orWhereHas('transactions.patients', function ($p) use ($search) {
                        $p->where('name', 'like', "%{$search}%");
                    });
                });
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('date', function ($row) {
                    return Carbon::parse($row->created_at)->format('d/m/Y');
                })
                ->addColumn('code', function ($row) {
                    return $row->transactions?->transaction_code ?? '-';
                })
                ->addColumn('name', function ($row) {
                    return $row->transactions?->patients?->name ?? '-';
                })
                ->addColumn('final_price', function ($row) {
                    return 'Rp ' . number_format($row->final_price, 0, ',', '.');
                })
                ->rawColumns(['final_price'])
                ->make(true);
        }

        return view('sales.salesdata');
    }

    public function returdata(Request $request)
    {
        $search = $request->search;

        $data = MedicineCart::query()
            ->with(['transactions.patients'])
            ->whereHas('transactions', function ($q) use ($search) {
                $q->where('transaction_code', 'LIKE', "%{$search}%")
                    ->orWhereHas('patients', function ($q2) use ($search) {
                        $q2->where('name', 'LIKE', "%{$search}%");
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

        $year  = $now->format('y'); // 25
        $month = $now->format('m'); // 11
        $prefix = "{$year}{$month}R";

        // Get last code for this month
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
    public function retur()
    {
        $now = Carbon::now()->format('d/m/Y');
        $retur_code = $this->generateReturCode();
        return view('sales.salesretur', compact('retur_code', 'now'));
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

            $retur = Retur::create([
                'code'           => $this->generateReturCode(),
                'transaction_id' => $request->transaction_id,
                'medicine_id'    => $request->medicine_id,
                'qty_retur'      => $request->qty_retur,
                'total_retur'    => $request->total_retur,
                'status'         => 1,
            ]);



            // Get & UPdate Cart
            $cart = MedicineCart::findOrFail($request->cart_id);
            if ($request->old_qty - $request->qty_retur == 0) {
                $cart->delete();
                $cart->update([
                    'final_price'   => $cart->final_price - $request->total_retur - $cart->discount,
                    'total_price'   => $cart->final_price - $request->total_retur,
                    'quantity'      => $request->old_qty - $request->qty_retur,
                ]);
            } else if ($request->old_qty - $request->qty_retur > 0) {
                $cart->update([
                    'final_price'   => $cart->final_price - $request->total_retur - $cart->discount,
                    'total_price'   => $cart->final_price - $request->total_retur,
                    'quantity'      => $request->old_qty - $request->qty_retur,
                ]);
            } else {
                return redirect()
                    ->back()
                    ->with('failed', 'Gagal menyimpan retur: ' . 'Qty tidak bisa dibawah 0');
            }


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
    public function transactionItems($transactionId)
    {
        $items = MedicineCart::with('medicine')
            ->where('transaction_id', $transactionId)
            ->where('status', 1);

        return DataTables::of($items)
            ->addIndexColumn()

            ->addColumn('medicine', function ($row) {
                return $row->medicine?->name ?? '-';
            })

            ->addColumn('price', function ($row) {
                return 'Rp ' . number_format($row->total_price, 0, ',', '.');
            })

            ->addColumn('total', function ($row) {
                return 'Rp ' . number_format($row->final_price, 0, ',', '.');
            })

            ->make(true);
    }
}
