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

            $search   = trim($request->input('search.value'));
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');

            $query = MedicineCart::query()
                ->join('medicine_transactions', 'medicine_transactions.id', '=', 'medicine_cart.transaction_id')
                ->with(['transactions.patients', 'transactions'])
                ->where('medicine_transactions.status', 1)
                ->whereHas('transactions', function ($transaction) {
                    $transaction->where('pharmacy_id', auth()->user()->pharmacy_id);
                })
                ->selectRaw('
                transaction_id,
                MAX(medicine_transactions.transaction_code) as transaction_code,
                MAX(medicine_cart.updated_at) as updated_at,
                SUM(medicine_cart.discount) as totaldiscount,
                MAX(medicine_cart.status) as status,
                SUM(final_price) as final_price,
                SUM(total_price) as subtotal,
                MAX(medicine_transactions.discount) as cart_discount
            ')
                ->groupBy('transaction_id')
                ->orderBy('transaction_code');

            // text search only
            if ($search) {
                $query->where(function ($q) use ($search) {
                    if (preg_match('/^\d{4}$/', $search)) {
                        $q->whereYear('medicine_cart.updated_at', $search);
                    } else {
                        $q->whereHas('transactions', function ($t) use ($search) {
                            $t->where('transaction_code', 'like', "%{$search}%");
                        })
                            ->orWhereHas('transactions.patients', function ($p) use ($search) {
                                $p->where('name', 'like', "%{$search}%");
                            });
                    }
                });
            }

            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');

            // If only one date was sent, treat it as a single-day filter
            if ($dateFrom && !$dateTo) {
                $dateTo = $dateFrom;
            } elseif ($dateTo && !$dateFrom) {
                $dateFrom = $dateTo;
            }

            if ($dateFrom) {
                try {
                    $query->whereDate('medicine_cart.updated_at', '>=', Carbon::createFromFormat('d/m/Y', $dateFrom)->format('Y-m-d'));
                } catch (\Exception $e) {
                }
            }

            if ($dateTo) {
                try {
                    $query->whereDate('medicine_cart.updated_at', '<=', Carbon::createFromFormat('d/m/Y', $dateTo)->format('Y-m-d'));
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
                    $type = $row->transactions?->transaction_type ?? '-';

                    if ($type == "KREDIT") $type = "UK";
                    else if ($type == "RESEP TUNAI") $type = "UM";
                    else if ($type == "HV/OTC") $type = "HV";
                    else if ($type == "UPDS") $type = "UP";

                    return $type;
                })
                ->addColumn('name', function ($row) {
                    return $row->transactions?->patients?->name ?? '-';
                })
                ->addColumn('final_price', function ($row) {
                    return 'Rp ' . number_format($row->final_price - $row->transactions?->discount, 0, ',', '.');
                })
                ->addColumn('subtotal', function ($row) {
                    return 'Rp ' . number_format($row->subtotal, 0, ',', '.');
                })
                ->addColumn('totaldiscount', function ($row) {
                    return 'Rp ' . number_format($row->totaldiscount + $row->cart_discount, 0, ',', '.');
                })
                ->addColumn('payment_method', function ($row) {
                    return $row->transactions?->payment_method;
                })

                ->rawColumns(['final_price'])
                ->make(true);
        }

        return view('sales.salesdata');
    }
    public function pending(Request $request)
    {
        if ($request->ajax()) {

            $search   = trim($request->input('search.value'));
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');

            $query = MedicineCart::query()
                ->join('medicine_transactions', 'medicine_transactions.id', '=', 'medicine_cart.transaction_id')
                ->with(['transactions.patients', 'transactions'])
                ->whereHas('transactions', function ($transaction) {
                    $transaction->where('pharmacy_id', auth()->user()->pharmacy_id)
                        ->where('status', 0);
                })
                ->selectRaw('
                transaction_id,
                MAX(medicine_transactions.transaction_code) as transaction_code,
                MAX(medicine_cart.updated_at) as updated_at,
                SUM(medicine_cart.discount) as totaldiscount,
                MAX(medicine_cart.status) as status,
                SUM(final_price) as final_price,
                SUM(total_price) as subtotal,
                MAX(medicine_transactions.discount) as cart_discount
            ')
                ->groupBy('transaction_id')
                ->orderBy('transaction_code');

            // text search only
            if ($search) {
                $query->where(function ($q) use ($search) {
                    if (preg_match('/^\d{4}$/', $search)) {
                        $q->whereYear('medicine_cart.updated_at', $search);
                    } else {
                        $q->whereHas('transactions', function ($t) use ($search) {
                            $t->where('transaction_code', 'like', "%{$search}%");
                        })
                            ->orWhereHas('transactions.patients', function ($p) use ($search) {
                                $p->where('name', 'like', "%{$search}%");
                            });
                    }
                });
            }

            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');

            // If only one date was sent, treat it as a single-day filter
            if ($dateFrom && !$dateTo) {
                $dateTo = $dateFrom;
            } elseif ($dateTo && !$dateFrom) {
                $dateFrom = $dateTo;
            }

            if ($dateFrom) {
                try {
                    $query->whereDate('medicine_cart.updated_at', '>=', Carbon::createFromFormat('d/m/Y', $dateFrom)->format('Y-m-d'));
                } catch (\Exception $e) {
                }
            }

            if ($dateTo) {
                try {
                    $query->whereDate('medicine_cart.updated_at', '<=', Carbon::createFromFormat('d/m/Y', $dateTo)->format('Y-m-d'));
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
                    $type = $row->transactions?->transaction_type ?? '-';

                    if ($type == "KREDIT") $type = "UK";
                    else if ($type == "RESEP TUNAI") $type = "UM";
                    else if ($type == "HV/OTC") $type = "HV";
                    else if ($type == "UPDS") $type = "UP";

                    return $type;
                })
                ->addColumn('name', function ($row) {
                    return $row->transactions?->patients?->name ?? '-';
                })
                ->addColumn('final_price', function ($row) {
                    return 'Rp ' . number_format($row->final_price - $row->transactions?->discount, 0, ',', '.');
                })
                ->addColumn('subtotal', function ($row) {
                    return 'Rp ' . number_format($row->subtotal, 0, ',', '.');
                })
                ->addColumn('totaldiscount', function ($row) {
                    return 'Rp ' . number_format($row->totaldiscount + $row->cart_discount, 0, ',', '.');
                })
                ->addColumn('payment_method', function ($row) {
                    return $row->transactions?->payment_method;
                })

                ->rawColumns(['final_price'])
                ->make(true);
        }

        return view('sales.pending');
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
                return 'Rp ' . number_format($row->item_price, 0, ',', '.');
            })

            ->addColumn('total', function ($row) {
                return 'Rp ' . number_format($row->final_price, 0, ',', '.');
            })

            ->make(true);
    }
}
