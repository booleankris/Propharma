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

            try {
                $parsedDate = Carbon::createFromFormat('d/m/Y', $search);
            } catch (\Exception $e) {
                $parsedDate = null;
            }

            $query = MedicineCart::query()
                ->with(['transactions.patients', 'transactions'])
                ->whereHas('transactions', function ($transaction) {
                    $transaction->where('pharmacy_id', auth()->user()->pharmacy_id);
                })
                ->selectRaw('
                transaction_id,
                MAX(created_at) as created_at,
                SUM(final_price) as final_price,
                MAX(status) as status

            ')
                ->groupBy('transaction_id')
                ->orderBy('created_at');

            if ($search) {
                $query->where(function ($q) use ($search, $parsedDate) {

                    if ($parsedDate) {
                        $q->whereDate('created_at', $parsedDate->format('Y-m-d'));
                    }

                    if (preg_match('/^\d{4}$/', $search)) {
                        $q->orWhereYear('created_at', $search);
                    }

                    $q->orWhereHas('transactions', function ($t) use ($search) {
                        $t->where('transaction_code', 'like', "%{$search}%");
                    });

                    $q->orWhereHas('transactions.patients', function ($p) use ($search) {
                        $p->where('name', 'like', "%{$search}%");
                    });
                });
            }
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');

            if ($dateFrom) {
                try {
                    $query->whereDate('created_at', '>=', Carbon::createFromFormat('d/m/Y', $dateFrom)->format('Y-m-d'));
                } catch (\Exception $e) {
                }
            }

            if ($dateTo) {
                try {
                    $query->whereDate('created_at', '<=', Carbon::createFromFormat('d/m/Y', $dateTo)->format('Y-m-d'));
                } catch (\Exception $e) {
                }
            }
            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('date', function ($row) {
                    return Carbon::parse($row->updated_at)->format('d/m/Y');
                })
                ->addColumn('time', function ($row) {
                    return Carbon::parse($row->updated_at)->format('H:i:s');
                })
                ->addColumn('code', function ($row) {
                    return $row->transactions?->transaction_code ?? '-';
                })
                ->addColumn('type', function ($row) {
                    $type =  $row->transactions?->transaction_type ?? '-';
                    if ($type == "KREDIT") {
                        $type = "UK";
                    } else if ($type == "RESEP TUNAI") {
                        $type = "UM";
                    } else if ($type == "HV/OTC") {
                        $type = "HV";
                    } else if ($type == "UPDS") {
                        $type = "UP";
                    }
                    return $type;
                })
                ->addColumn('name', function ($row) {
                    return $row->transactions?->patients?->name ?? '-';
                })
                ->addColumn('final_price', function ($row) {
                    return 'Rp ' . number_format($row->final_price - $row->transactions?->discount, 0, ',', '.');
                })
                ->addColumn('payment_method', function ($row) {
                    return $row->transactions?->payment_method;
                })
                ->addColumn('print', function ($row) {
                    return '
                    <div class="flex gap-2">
                        <a href="' . url('print/receipt/' . $row->transactions->id) . '" target="_blank">
                            <button class="group rounded-md shadow bg-blue-500 text-white cursor-pointer flex justify-between items-center overflow-hidden transition-all hover:glow">
                                <div class="relative w-12 h-12 bg-white bg-opacity-20 flex justify-center items-center transition-all">
                                    <svg class="w-4 h-4 transition-all group-hover:-translate-y-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                    </svg>
                                </div>
                                <p class="px-5">Struk</p>
                            </button>
                        </a>
                        <a href="' . url('print/payment-receipt/' . $row->transactions->id) . '" target="_blank">
                            <button style="background:#bd9c33" class="group rounded-md shadow text-white cursor-pointer flex justify-between items-center overflow-hidden transition-all hover:glow">
                                <div class="relative w-10 h-12 bg-white bg-opacity-20 flex justify-center items-center transition-all">
                                    <svg class="w-4 h-4 transition-all group-hover:-translate-y-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                    </svg>
                                </div>
                                <p class="px-3">Kwitansi</p>
                            </button>
                        </a>
                    </div>
                    ';
                })
                ->addColumn('status', function ($row) {
                    if ($row->status == 1) {
                        $status = '<a class="status-completed">Selesai</a>';
                    } else if ($row->status == 0) {
                        $status = '<a class="status-pending">Pending</a>';
                    } else {
                        $status = '<a class="status-completed">NULL</a>';
                    }
                    return $status;
                })
                ->rawColumns(['final_price', 'status', 'print'])
                ->make(true);
        }

        return view('sales.salesdata');
    }
    public function transactionItems($transactionId)
    {
        $items = MedicineCart::with('medicine')
            ->where('transaction_id', $transactionId);
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
