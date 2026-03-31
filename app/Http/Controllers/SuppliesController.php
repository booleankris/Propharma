<?php

namespace App\Http\Controllers;

use App\Exports\Stocks\PrintStockOpnameExport;
use App\Exports\Stocks\StockDataExport;
use App\Models\Batches;
use App\Models\ItemsLog;
use App\Models\MedicineCart;
use App\Models\Medicines;
use App\Models\MedicineTransfers;
use App\Models\ReceivingItems;
use App\Models\StockOpname;
use Carbon\Carbon;
use DataTables;
use Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class SuppliesController extends Controller
{
    public function supplies(Request $request)
    {
        return view('supply.history');
    }
    public function getSupplies(Request $request)
    {
        if ($request->ajax()) {
            $items = ItemsLog::with(['medicines']);
            if ($request->filled('searchMedicine')) {
                $items->whereHas('medicines', function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->searchMedicine}%")
                        ->orWhere('code', 'like', "%{$request->searchMedicine}%");
                });
            }

            if ($request->filled('start_date')) {
                $items->whereDate('date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $items->whereDate('date', '<=', $request->end_date);
            }

            return DataTables::eloquent($items)
                ->addIndexColumn()
                ->addColumn('date', function ($row) {
                    return $row->date;
                })
                ->addColumn('transaction_code', function ($row) {
                    return $row->transaction_code;
                })
                ->addColumn('code', function ($row) {
                    return $row->code;
                })
                ->addColumn('type', function ($row) {
                    return $row->type;
                })
                ->addColumn('name', function ($row) {
                    return $row->medicines->name;
                })
                ->addColumn('stock', function ($row) {
                    if ($row->status == 1) {
                        return "
                        <div style='color:#16a34a;font-weight:bold;'>
                            <span>-</span>
                            <b'>" . $row->qty . "</b>
                        </div>";
                    } else if ($row->status == 2) {
                        return "
                        <div style='color:#4173d3;font-weight:bold;'>
                            <span>+</span>
                            <b'>" . $row->qty . "</b>
                        </div>";
                    } else if ($row->status == 3) {
                        return " 
                    <div style='color:#d34163;font-weight:bold;'>
                        <span>+</span>
                        <b'>" . $row->qty . "</b>
                    </div>";
                    } else if ($row->status == 4) {
                        return "   <div style='color:#d34163;font-weight:bold;'>
                        <span>-</span>
                        <b'>" . $row->qty . "</b>
                    </div>";
                    } else if ($row->status == 5) {
                        if ($row->qty < 0) {
                            return "<div style='color:#d34163;font-weight:bold;'>
                                        <span></span>
                                        <b'>" . $row->qty . "</b>
                                    </div>";
                        } else if ($row->qty > 0) {
                            return "<div style='color:#d34163;font-weight:bold;'>
                                        <span>+</span>
                                        <b'>" . $row->qty . "</b>
                                    </div>";
                        } else {
                            return "<div style='color:#d34163;font-weight:bold;'>
                                        <span></span>
                                        <b'>" . $row->qty . "</b>
                                    </div>";
                        }
                    } else if ($row->status == 7) {
                        return "<div style='color:#248787;font-weight:bold;'>
                        <span></span>
                        <b'> -" . $row->qty . "</b>
                    </div>";
                    }
                })
                ->addColumn('qty_before', function ($row) {
                    return "
                        <div style='color:#000000;font-weight:bold;'>
                            <span></span>
                            <b'>" . $row->qty_before . "</b>
                        </div>";
                })
                ->addColumn('qty_after', function ($row) {
                    return "
                    <div style='color:#000000;font-weight:bold;'>
                        <span></span>
                        <b'>" . $row->qty_after . "</b>
                    </div>";
                })
                ->addColumn('qty_after_number', function ($row) {
                    return  $row->qty_after;
                })
                ->addColumn('qty_before_number', function ($row) {
                    return  $row->qty_after;
                })
                ->addColumn('qty_after_number', function ($row) {
                    return  $row->qty_after;
                })
                ->addColumn('supply', function ($row) {
                    return $row->medicines->stock;
                })
                ->addColumn('status', function ($row) {
                    if ($row->status == 1) {
                        return "<div style='
                        text-align:center;
                        font-weight:bold;
                        text-transform:uppercase;
                        background-color:rgba(34,197,94,0.2);
                        color:#16a34a;
                        padding:6px 4px;
                        font-size:12px;
                        font-family: Poppins;
                        border-radius:25px;'>
                        Penjualan
                        </div";
                    } else if ($row->status == 2) {
                        return "<div style='text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: #d6e8ff94;
                        color: #7f8eff;
                        padding: 6px 4px;
                        font-size: 12px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Pembelian
                        </div>";
                    } else if ($row->status == 3) {
                        return "<div style='
                        text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: rgb(255 0 0 / 17%);
                        color: #a31616;
                        padding: 6px 4px;
                        font-size: 12px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Retur Jual
                        </div";
                    } else if ($row->status == 4) {
                        return "<div style='
                        text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: rgb(255 177 0 / 31%);
                        color: #c17800;
                        padding: 6px 4px;
                        font-size: 12px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Retur Beli
                        </div";
                    } else if ($row->status == 5) {
                        return "<div style='
                        text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: #fff035;
                        color: #7a7817;
                        padding: 7px 6px;
                        font-size: 10px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Stock Opname
                        </div";
                    } else if ($row->status == 7) {
                        return "<div style='
                        text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: #aeffeaad;
                        color: #238787;
                        padding: 7px 6px;
                        font-size: 10px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Mutasi Stok
                        </div";
                    }
                })
                ->rawColumns(['status', 'stock', 'qty_before', 'qty_after'])
                ->make(true);
        }
    }

    // Data Stok
    public function stockData()
    {
        return view('supply.stockData');
    }
    public function getStockData(Request $request)
    {
        if ($request->ajax()) {
            $medicines = Medicines::query()
                ->withSum(['items_log as qty_orders' => function ($q) {
                    $q->where('status', 2);
                }], 'qty')
                ->withSum(['items_log as qty_sales' => function ($q) {
                    $q->where('status', 1);
                }], 'qty')
                ->addSelect([
                    'qty_start' => ItemsLog::select('qty_before')
                        ->whereColumn('medicine_id', 'medicines.id')
                        ->orderByDesc('id')
                        ->limit(1),

                    'qty_now' => ItemsLog::select('qty_after')
                        ->whereColumn('medicine_id', 'medicines.id')
                        ->orderByDesc('id')
                        ->limit(1),
                ]);
            if ($request->filled('searchMedicine')) {
                $medicines->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->searchMedicine}%")
                        ->orWhere('code', 'like', "%{$request->searchMedicine}%");
                });
            }

            if ($request->filled('start_date')) {
                $medicines->whereDate('date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $medicines->whereDate('date', '<=', $request->end_date);
            }
            return DataTables::of($medicines)
                ->addIndexColumn()

                ->editColumn('qty_start', fn($m) => $m->qty_start ?? 0)
                ->editColumn('qty_orders', fn($m) => $m->qty_orders ?? 0)
                ->editColumn('qty_sales', fn($m) => $m->qty_sales ?? 0)
                ->editColumn('qty_now', fn($m) => $m->qty_now ?? 0)
                ->make(true);
        }
    }
    public function printStockData(Request $request)
    {
        return Excel::download(new StockDataExport, 'stock_data.xlsx');
    }

    // Stock Opname

    public function getMedicineLogs(Request $request)
    {
        if ($request->ajax()) {

            $logs = ItemsLog::query()
                ->with('medicines')
                ->select('items_log.*');

            if ($request->filled('searchMedicine')) {
                $logs->whereHas('medicines', function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->searchMedicine}%")
                        ->orWhere('code', 'like', "%{$request->searchMedicine}%");
                });
            }

            if ($request->filled('start_date')) {
                $logs->whereDate('date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $logs->whereDate('date', '<=', $request->end_date);
            }

            return DataTables::of($logs)
                ->addIndexColumn()

                ->addColumn('name', function ($log) {
                    return $log->medicine->name ?? '-';
                })

                ->editColumn('type', function ($log) {
                    return $log->type;
                })

                ->editColumn('stock', function ($log) {
                    return $log->qty;
                })

                ->editColumn('supply', function ($log) {
                    return $log->qty_after;
                })

                ->addColumn('status', function ($row) {
                    if ($row->status == 1) {
                        return "<div style='
                        text-align:center;
                        font-weight:bold;
                        text-transform:uppercase;
                        background-color:rgba(34,197,94,0.2);
                        color:#16a34a;
                        padding:6px 4px;
                        font-size:12px;
                        font-family: Poppins;
                        border-radius:25px;'>
                        Penjualan
                        </div";
                    } else if ($row->status == 2) {
                        return "<div style='text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: #d6e8ff94;
                        color: #7f8eff;
                        padding: 6px 4px;
                        font-size: 12px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Pembelian
                        </div>";
                    } else if ($row->status == 3) {
                        return "<div style='
                        text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: rgb(255 0 0 / 17%);
                        color: #a31616;
                        padding: 6px 4px;
                        font-size: 12px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Retur Jual
                        </div";
                    } else if ($row->status == 4) {
                        return "<div style='
                        text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: rgb(255 177 0 / 31%);
                        color: #c17800;
                        padding: 6px 4px;
                        font-size: 12px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Retur Beli
                        </div";
                    } else if ($row->status == 5) {
                        return "<div style='
                        text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: #fff035;
                        color: #7a7817;
                        padding: 7px 6px;
                        font-size: 10px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Stock Opname
                        </div";
                    } else if ($row->status == 7) {
                        return "<div style='
                        text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: #aeffeaad;
                        color: #238787;
                        padding: 7px 6px;
                        font-size: 10px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Mutasi Stok
                        </div";
                    }
                })
                ->rawColumns(['status'])
                ->make(true);
        }
    }
    public function getMedicines(Request $request)
    {
        if ($request->ajax()) {

            $data = Medicines::with([
                'composition',
                'category',
                'factory',
                'creditor'
            ])
                ->select('medicines.*')
                ->where('status', 1)
                ->orderBy('id', 'DESC');

            return DataTables::of($data)
                ->addIndexColumn()
                ->filter(function ($query) use ($request) {
                    if ($search = $request->get('search')['value']) {
                        $query->where(function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('unit', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                    }
                })
                ->addColumn('name', function ($row) {
                    return $row->name;
                })
                ->addColumn('unit', function ($row) {
                    return $row->unit;
                })

                ->rawColumns(['status_label'])
                ->make(true);
        }
    }
    public function medicineStockLog(Request $request)
    {
        if ($request->ajax()) {
            $items = ItemsLog::with(['medicines']);
            if ($request->filled('searchMedicine')) {
                $items->whereHas('medicines', function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->searchMedicine}%")
                        ->orWhere('code', 'like', "%{$request->searchMedicine}%");
                });
            }

            if ($request->filled('start_date')) {
                $items->whereDate('date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $items->whereDate('date', '<=', $request->end_date);
            }
            $total_sales = (clone $items)->where('status', 1)->sum('qty');
            $total_orders = (clone $items)->where('status', 2)->sum('qty');
            $stock_start = (clone $items)->where('date', 'asc')->first();
            return DataTables::eloquent($items)
                ->addIndexColumn()
                ->addColumn('date', function ($row) {
                    return $row->date;
                })
                ->addColumn('transaction_code', function ($row) {
                    return $row->transaction_code;
                })
                ->addColumn('code', function ($row) {
                    return $row->code;
                })
                ->addColumn('type', function ($row) {
                    return $row->type;
                })
                ->addColumn('name', function ($row) {
                    return $row->medicines->name;
                })
                ->addColumn('stock', function ($row) {
                    if ($row->status == 1) {
                        return "
                        <div style='color:#16a34a;font-weight:bold;'>
                            <span>-</span>
                            <b'>" . $row->qty . "</b>
                        </div>";
                    } else if ($row->status == 2) {
                        return "
                        <div style='color:#4173d3;font-weight:bold;'>
                            <span>+</span>
                            <b'>" . $row->qty . "</b>
                        </div>";
                    } else if ($row->status == 3) {
                        return " 
                    <div style='color:#d34163;font-weight:bold;'>
                        <span>+</span>
                        <b'>" . $row->qty . "</b>
                    </div>";
                    } else if ($row->status == 4) {
                        return "   <div style='color:#d34163;font-weight:bold;'>
                        <span>-</span>
                        <b'>" . $row->qty . "</b>
                    </div>";
                    } else if ($row->status == 5) {
                        if ($row->qty < 0) {
                            return "<div style='color:#d34163;font-weight:bold;'>
                                        <span></span>
                                        <b'>" . $row->qty . "</b>
                                    </div>";
                        } else if ($row->qty > 0) {
                            return "<div style='color:#d34163;font-weight:bold;'>
                                        <span>+</span>
                                        <b'>" . $row->qty . "</b>
                                    </div>";
                        } else {
                            return "<div style='color:#d34163;font-weight:bold;'>
                                        <span></span>
                                        <b'>" . $row->qty . "</b>
                                    </div>";
                        }
                    } else if ($row->status == 7) {
                        return "   <div style='color:#d34163;font-weight:bold;'>
                        <span>-</span>
                        <b'>" . $row->qty . "</b>
                    </div>";
                    }
                })
                ->addColumn('qty_before', function ($row) {
                    return "
                        <div style='color:#000000;font-weight:bold;'>
                            <span></span>
                            <b'>" . $row->qty_before . "</b>
                        </div>";
                })
                ->addColumn('qty_after', function ($row) {
                    return "
                    <div style='color:#000000;font-weight:bold;'>
                        <span></span>
                        <b'>" . $row->qty_after . "</b>
                    </div>";
                })
                ->addColumn('total_sales', function ($row) use ($total_sales) {
                    return  $total_sales;
                })
                ->addColumn('total_orders', function ($row) use ($total_orders) {
                    return  $total_orders;
                })
                ->addColumn('stock_start', function ($row) use ($stock_start) {
                    return $stock_start;
                })
                ->addColumn('qty_after_number', function ($row) {
                    return  $row->qty_after;
                })
                ->addColumn('supply', function ($row) {
                    return $row->medicines->stock;
                })
                ->addColumn('status', function ($row) {
                    if ($row->status == 1) {
                        return "<div style='
                        text-align:center;
                        font-weight:bold;
                        text-transform:uppercase;
                        background-color:rgba(34,197,94,0.2);
                        color:#16a34a;
                        padding:6px 4px;
                        font-size:12px;
                        font-family: Poppins;
                        border-radius:25px;'>
                        Penjualan
                        </div";
                    } else if ($row->status == 2) {
                        return "<div style='text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: #d6e8ff94;
                        color: #7f8eff;
                        padding: 6px 4px;
                        font-size: 12px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Pembelian
                        </div>";
                    } else if ($row->status == 3) {
                        return "<div style='
                        text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: rgb(255 0 0 / 17%);
                        color: #a31616;
                        padding: 6px 4px;
                        font-size: 12px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Retur Jual
                        </div";
                    } else if ($row->status == 4) {
                        return "<div style='
                        text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: rgb(255 177 0 / 31%);
                        color: #c17800;
                        padding: 6px 4px;
                        font-size: 12px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Retur Beli
                        </div";
                    } else if ($row->status == 5) {
                        return "<div style='
                        text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: #fff035;
                        color: #7a7817;
                        padding: 7px 6px;
                        font-size: 10px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Stock Opname
                        </div";
                    } else if ($row->status == 7) {
                        return "<div style='
                        text-align: center;
                        font-weight: bold;
                        text-transform: uppercase;
                        background-color: #aeffeaad;
                        color: #238787;
                        padding: 7px 6px;
                        font-size: 10px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Mutasi Stok
                        </div";
                    }
                })
                ->rawColumns(['status', 'stock', 'qty_before', 'qty_after'])
                ->make(true);
        }
    }
    public function getBatchesByMedicine(Request $request)
    {
        $batches = Batches::where('medicine_id', $request->medicine_id)
            ->orderBy('expired_date', 'asc') // FEFO
            ->get(['id', 'name', 'expired_date', 'stock']);

        return response()->json($batches);
    }
    public function generateOpnameCode()
    {
        $now = Carbon::now();

        $year  = $now->format('y');
        $month = $now->format('m');
        $prefix = "SO-{$year}{$month}";

        $lastCode = ItemsLog::where('code', 'like', "{$prefix}%")
            ->where('status', 5)
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
    public function stockOpname()
    {
        return view('supply.stockOpname');
    }
    public function printStockOpname(Request $request)
    {
        $now = Carbon::now()->format('dmY');
        return Excel::download(new PrintStockOpnameExport($request), 'stock_opname-' . $now . '.xlsx');
    }
    // public function Opname(Request $request)
    // {
    //     $request->validate([
    //         'medicine_id'       => 'required|exists:medicines,id',
    //         'stock_physic'      => 'nullable|integer',  
    //         'stock_system'      => 'nullable|integer',
    //         'stock_discrepancy' => 'required|integer',
    //     ]);

    //     try {
    //         DB::transaction(function () use ($request) {
    //             $medicine = Medicines::findOrFail($request->medicine_id);

    //             // ── 1. Correct batches (storage) ──────────────────────────────
    //             if ($request->filled('batches_id')) {
    //                 $batch = Batches::findOrFail($request->batches_id);
    //             } else {
    //                 // FEFO — earliest expiry first
    //                 $batch = Batches::where('medicine_id', $medicine->id)
    //                     ->orderBy('expired_date', 'asc')
    //                     ->lockForUpdate()
    //                     ->first();
    //             }

    //             if ($batch) {
    //                 // Set directly to physical count instead of increment/decrement
    //                 $batch->stock = $request->stock_physic;
    //                 $batch->save();
    //             }

    //             // ── 2. Correct medicine_transfers (counter) ───────────────────
    //             // If client selects a specific transfer, correct that one
    //             // Otherwise correct the latest transfer for this medicine
    //             if ($request->filled('transfer_id')) {
    //                 $transfer = MedicineTransfers::findOrFail($request->transfer_id);
    //                 $transfer->stock = $request->counter_stock_physic;
    //                 $transfer->save();
    //             }

    //             // ── 3. Log the correction ─────────────────────────────────────
    //             $discrepancy = $request->stock_discrepancy;
    //             $status = $discrepancy < 0 ? 6 : 5;

    //             ItemsLog::create([
    //                 'transaction_code' => $this->generateOpnameCode(),
    //                 'code'             => $this->generateItemsLogCode(),
    //                 'type'             => 'SO',
    //                 'medicine_id'      => $medicine->id,
    //                 'batches_id'       => $batch?->id,
    //                 'qty'              => abs($discrepancy),
    //                 'qty_before'       => $request->stock_system,
    //                 'qty_after'        => $request->stock_physic,
    //                 'total'            => '-',
    //                 'date'             => now(),
    //                 'status'           => $status,
    //             ]);

    //             // ── 4. Update medicine master stock ───────────────────────────
    //             $medicine->update(['stock' => $request->stock_physic]);
    //         });

    //         return response()->json(['success' => true, 'message' => 'Stok berhasil dikoreksi!']);
    //     } catch (\Exception $e) {
    //         return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    //     }
    // }
    public function batches(Request $request)
    {
        $batches = Batches::where('medicine_id', $request->medicine_id)
            ->where('pharmacy_id', auth()->user()->pharmacy_id) // scope to current pharmacy
            ->orderBy('expired_date', 'asc')                    // FEFO
            ->get(['id', 'name', 'expired_date', 'stock']);

        return response()->json($batches);
    }

    public function opname(Request $request)
    {
        $request->validate([
            'medicine_id'          => 'required|exists:medicines,id',
            'stock_physic'         => 'required|integer',
            'counter_stock_physic' => 'nullable|integer',
            'batches_id'           => 'nullable|exists:batches,id',
        ]);

        DB::beginTransaction();

        try {
            // 1. Resolve which batch to use (provided or FEFO default)
            if ($request->filled('batches_id')) {
                $batch = Batches::lockForUpdate()->findOrFail($request->batches_id);
            } else {
                $batch = Batches::lockForUpdate()
                    ->where('medicine_id', $request->medicine_id)
                    ->where('pharmacy_id', auth()->user()->pharmacy_id)
                    ->orderBy('expired_date', 'asc')
                    ->firstOrFail();
            }

            $stockBefore  = (int) $batch->stock;
            $stockPhysic  = (int) $request->stock_physic;
            $discrepancy  = $stockPhysic - $stockBefore;

            // status 5 = surplus or equal, status 6 = deficit
            $status = $discrepancy >= 0 ? 5 : 6;

            // 2. Update batch stock to the physical count
            $batch->stock = $stockPhysic;
            $batch->save();

            // 3. Write to items_log
            // Generate a transaction code — adjust the format to your convention
            StockOpname::create([
                'users_id'          => auth()->id(),
                'batches_id'        => $batch->id,
                'stock_physical'    => $stockPhysic,
                'stock_discrepancy' => $discrepancy,          // signed: positive = surplus, negative = deficit
                'stock_total'       => $stockPhysic,          // final stock after correction
                'date'              => now()->toDateString(),
                'status'            => $status,               // 5 = surplus/sama, 6 = defisit
            ]);

            ItemsLog::create([
                'batches_id'       => $batch->id,
                'transaction_code' => $this->generateOpnameCode(),
                'code'             => $this->generateItemsLogCode(),
                'type'             => "SO",
                'medicine_id'      => $request->medicine_id,
                'qty'              => abs($discrepancy),
                'qty_before'       => $stockBefore,
                'qty_after'        => $stockPhysic,
                'total'            => $discrepancy,
                'date'             => now()->toDateString(),
                'status'           => $status,
            ]);

            DB::commit();

            return response()->json([
                'message'      => 'Stock opname berhasil disimpan.',
                'batch'        => $batch->fresh(),
                'qty_before'   => $stockBefore,
                'qty_after'    => $stockPhysic,
                'discrepancy'  => $discrepancy,
                'status'       => $status,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
    // Stock Detail
    public function stockDetail()
    {
        return view('supply.stockDetail');
    }
    public function getStockDetail(Request $request)
    {

        $query = MedicineTransfers::with([
            'batches.medicines',
            'batches.pharmacy',
            'etalases'
        ])
            ->whereHas('batches', function ($q) {
                $q->where('pharmacy_id', auth()->user()->pharmacy_id);
            })
            ->select('medicine_transfers.*');
        if ($request->status !== null && $request->status !== '') {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $search = $request->search;
            $query->whereHas('batches.medicines', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $data = $query->get()->map(function ($item, $index) {
            $batch = $item->batches;

            return [
                'DT_RowIndex'    => $index + 1,
                'medicine_name'  => $batch->medicines->name ?? '-',
                'batch_name'     => $batch->name ?? '-',
                'stock'          => $item->stock ?? 0,
                'expired_date'   => $batch->expired_date ?? '-',
                'etalase'        => $item->etalases->name ?? '-',
                'pharmacy'       => $batch->pharmacy->name ?? '-',
                'status'         => $item->status,
                'code'           => $item->code ?? '-',
            ];
        });

        return response()->json(['data' => $data]);
    }
}
