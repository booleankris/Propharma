<?php

namespace App\Http\Controllers;

use App\Exports\Stocks\PrintStockOpnameExport;
use App\Exports\Stocks\StockDataExport;
use App\Models\Batches;
use App\Models\ItemsLog;
use App\Models\MedicineCart;
use App\Models\Medicines;
use App\Models\MedicineTransfers;
use App\Models\MedicineTransferItems;
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
    private function calculateRealtimeStock($medicineId, $pharmacyId, $type = 'total')
    {
        if (!$medicineId) return 0;

        $storageStock = (int) Batches::where('medicine_id', $medicineId)
            ->where('pharmacy_id', $pharmacyId)
            ->sum('stock');

        if ($type === 'storage') {
            return $storageStock;
        }

        $counterStock = (int) MedicineTransferItems::whereHas('batches', function ($b) use ($medicineId, $pharmacyId) {
                $b->where('medicine_id', $medicineId)
                  ->where('pharmacy_id', $pharmacyId);
            })
            ->where('status', 1)
            ->sum('qty');

        if ($type === 'counter') {
            return $counterStock;
        }

        return $storageStock + $counterStock;
    }

    // Stok Pelayanan
    public function supplies(Request $request)
    {
        return view('supply.history');
    }
    public function getSupplies(Request $request)
    {

        if ($request->ajax()) {

            $pharmacyId = getActivePharmacyId();

            $baseQuery = ItemsLog::query();


            $baseQuery->where(function ($q) use ($pharmacyId) {
                $q->where(function ($sub) use ($pharmacyId) {
                    $sub->where('status', 2)
                        ->whereHas('receiving', function ($r) use ($pharmacyId) {
                            $r->where('pharmacy_id', $pharmacyId)
                                ->whereIn('status', [1, 2, 3, 4]);
                        });
                })->orWhere(function ($sub) use ($pharmacyId) {
                    $sub->where('status', 7)
                        ->whereHas('batches', function ($b) use ($pharmacyId) {
                            $b->where('pharmacy_id', $pharmacyId);
                        });
                })->orWhere(function ($sub) use ($pharmacyId) {
                    $sub->whereNotIn('status', [2, 7])
                        ->whereHas('users', function ($u) use ($pharmacyId) {
                            $u->where('pharmacy_id', $pharmacyId);
                        });
                });
            });

            if ($request->filled('searchMedicine')) {
                $searchValue = $request->searchMedicine;

                $baseQuery->whereHas('medicines', function ($q) use ($searchValue) {
                    if (is_numeric($searchValue)) {
                        $q->where('id', $searchValue);
                    } else {
                        $q->where('name', 'like', "%{$searchValue}%")
                            ->orWhere('code', 'like', "%{$searchValue}%");
                    }
                });
            }

            if ($request->filled('start_date')) {
                $baseQuery->whereDate('date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $baseQuery->whereDate('date', '<=', $request->end_date);
            }

            // 2. STATS CALCULATION: Calculate all sums 
            $stats = (clone $baseQuery)->selectRaw("
                SUM(CASE WHEN status = 1 THEN qty ELSE 0 END) as qty_sold,
                SUM(CASE WHEN status = 2 THEN qty ELSE 0 END) as qty_bought,
                SUM(CASE WHEN status = 3 THEN qty ELSE 0 END) as qty_sold_rt,
                SUM(CASE WHEN status = 4 THEN qty ELSE 0 END) as qty_bought_rt
            ")->first();

            // 3. BALANCE CALCULATION: Get the very first and very last records for the balances
            $firstRecord = (clone $baseQuery)->orderBy('date', 'asc')->orderBy('id', 'asc')->first();
            $lastRecord = (clone $baseQuery)->orderBy('date', 'desc')->orderBy('id', 'desc')->first();

            // 4. TABLE QUERY: Eager load relations nested deep to prevent performance issues
            $items = (clone $baseQuery)->with([
                'medicines',
                'batches',                              // Fetch batches for batch name display
                'receiving.receiving_details.creditor', // Fetch creditor through receiving details
                'medicine_transaction.user'             // Fetch cashier user through transactions
            ])->whereNotIn('status', [5, 6])
              ->orderBy('updated_at', 'asc')
              ->orderBy('id', 'asc');

            // Balance calculation: Real-time stock if medicine is selected, otherwise last record's qty_after
            $balance = 0;
            if ($request->filled('searchMedicine')) {
                $searchValue = $request->searchMedicine;
                $med = is_numeric($searchValue)
                    ? Medicines::find($searchValue)
                    : Medicines::where('name', $searchValue)->orWhere('code', $searchValue)->first();

                if ($med) {
                    $balance = $this->calculateRealtimeStock($med->id, $pharmacyId, 'total');
                } else if ($lastRecord) {
                    $balance = $lastRecord->qty_after;
                }
            } else if ($lastRecord) {
                $balance = $lastRecord->qty_after;
            }

            // 5. RETURN DATATABLES RESPONSE
            return DataTables::eloquent($items)
                ->addIndexColumn()
                ->addColumn('date', function ($row) {
                    return $row->date;
                })
                ->addColumn('medicine_name', function ($row) {
                    return $row->medicines->name;
                })
                ->addColumn('batch_name', function ($row) {
                    if (in_array($row->status, [2, 7]) && $row->batches) {
                        return $row->batches->name;
                    }
                    return '-';
                })
                ->addColumn('transaction_code', function ($row) {
                    if ($row->status == 2) {
                        $codeStr = $row->receiving?->receiving_details?->first()?->receiving_details_code ?? $row->transaction_code;
                    } else {
                        $codeStr = $row->transaction_code;
                    }

                    if (!$codeStr)
                        return '-';
                    $code = e($codeStr);
                    return '
                    <div class="flex items-center gap-1.5">
                        <span class="font-mono text-[10px] font-medium text-slate-700 bg-slate-50 px-2 py-0.5 rounded border border-slate-200">' . $code . '</span>
                        <button type="button" onclick="navigator.clipboard.writeText(\'' . $code . '\'); iziToast.success({title: \'Tersalin\', message: \'Kode berhasil disalin\', position: \'topRight\'})" class="p-1 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="Salin kode">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        </button>
                    </div>';
                })
                ->addColumn('code', function ($row) {
                    return $row->code;
                })
                ->addColumn('type', function ($row) {
                    return $row->type;
                })
                // ----- UPDATED 'NAME' COLUMN LOGIC -----
                ->addColumn('name', function ($row) {
                    // Case 1: Purchase / Pembelian (Status = 2) -> Show Creditor Name
                    if ($row->status == 2) {
                        return $row->receiving?->receiving_details?->first()?->creditor?->name ?? '-';
                    }

                    // Case 2: From medicine transaction cashier user
                    if ($row->medicine_transaction?->user) {
                        return $row->medicine_transaction->user->name;
                    }

                    // Case 3: From users relation directly on items_log
                    if ($row->users) {
                        return $row->users->name;
                    }

                    // Fallback
                    return '-';
                })
                // ----------------------------------------
                ->addColumn('stock', function ($row) {
                    if ($row->status == 1) {
                        return "<div style='color:#16a34a;font-weight:bold;'><span>-</span><b>" . $row->qty . "</b></div>";
                    } else if ($row->status == 2) {

                        return "<div style='color:#4173d3;font-weight:bold;'><span>+</span><b>" . $row->qty . "</b></div>";
                    } else if ($row->status == 3) {
                        return "<div style='color:#d34163;font-weight:bold;'><span>+</span><b>" . $row->qty . "</b></div>";
                    } else if ($row->status == 4) {
                        return "<div style='color:#d34163;font-weight:bold;'><span>-</span><b>" . $row->qty . "</b></div>";
                    } else if ($row->status == 5) {
                        if ($row->qty < 0) {
                            return "<div style='color:#d34163;font-weight:bold;'><span></span><b>" . $row->qty . "</b></div>";
                        } else if ($row->qty > 0) {
                            return "<div style='color:#d34163;font-weight:bold;'><span>+</span><b>" . $row->qty . "</b></div>";
                        } else {
                            return "<div style='color:#d34163;font-weight:bold;'><span></span><b>" . $row->qty . "</b></div>";
                        }
                    } else if ($row->status == 7) {
                        if ($row->qty_after < $row->qty_before) {
                            // Pengurangan (dari gudang/sumber)
                            return "<div style='color:#d34163;font-weight:bold;'><span>-</span><b>" . abs($row->qty) . "</b></div>";
                        } else {
                            // Penambahan (ke etalase/tujuan)
                            return "<div style='color:#248787;font-weight:bold;'><span>+</span><b>" . abs($row->qty) . "</b></div>";
                        }
                    }
                    return "";
                })
                ->addColumn('qty_before', function ($row) {
                    return "<div style='color:#000000;font-weight:bold;'><span></span><b>" . $row->qty_before . "</b></div>";
                })
                ->addColumn('qty_after', function ($row) {
                    return "<div style='color:#000000;font-weight:bold;'><span></span><b>" . $row->qty_after . "</b></div>";
                })
                ->addColumn('qty_before_number', function ($row) {
                    return $row->qty_before;
                })
                ->addColumn('qty_after_number', function ($row) {
                    return $row->qty_after;
                })
                ->addColumn('supply', function ($row) use ($pharmacyId) {
                    static $stockCache = [];
                    $medId = $row->medicine_id;
                    if (!$medId) return '-';

                    if (!isset($stockCache[$medId])) {
                        $stockCache[$medId] = $this->calculateRealtimeStock($medId, $pharmacyId, 'total');
                    }

                    return $stockCache[$medId];
                })
                ->addColumn('status', function ($row) {
                    if ($row->status == 1) {
                        return "<div style='text-align:center; font-weight:bold; text-transform:uppercase; background-color:rgba(34,197,94,0.2); color:#16a34a; padding: 6px 4px; width:100px; font-size:9px; font-family: Poppins; border-radius:25px;'>Penjualan</div>";
                    } else if ($row->status == 2) {
                        return "<div style='text-align: center; font-weight: bold; text-transform: uppercase; background-color: #d6e8ff94; color: #7f8eff; padding: 6px 4px; width:100px; font-size: 9px; font-family: Poppins; border-radius: 25px;'>Pembelian</div>";
                    } else if ($row->status == 3) {
                        return "<div style='text-align: center; font-weight: bold; text-transform: uppercase; background-color: rgb(255 0 0 / 17%); color: #a31616; padding: 6px 4px; width:100px; font-size: 9px; font-family: Poppins; border-radius: 25px;'>Retur Jual</div>";
                    } else if ($row->status == 4) {
                        return "<div style='text-align: center; font-weight: bold; text-transform: uppercase; background-color: rgb(255 177 0 / 31%); color: #c17800; padding: 6px 4px; width:100px; font-size: 9px; font-family: Poppins; border-radius: 25px;'>Retur Beli</div>";
                    } else if ($row->status == 5) {
                        return "<div style='text-align: center; font-weight: bold; text-transform: uppercase; background-color: #fff035; color: #7a7817; padding: 6px 4px; width:100px; font-size: 9px; font-family: Poppins; border-radius: 25px;'>Stock Opname</div>";
                    } else if ($row->status == 7) {
                        return "<div style='text-align: center; font-weight: bold; text-transform: uppercase; background-color: #aeffeaad; color: #238787; padding: 6px 4px; width:100px; font-size: 9px; font-family: Poppins; border-radius: 25px;'>Mutasi Stok</div>";
                    }
                    return "-";
                })
                ->rawColumns(['status', 'stock', 'qty_before', 'qty_after', 'transaction_code'])
                ->with([
                    'stats' => [
                        'stat_before' => $firstRecord ? $firstRecord->qty_before : 0,
                        'stat_bought' => $stats->qty_bought ?? 0,
                        'stat_bought_rt' => $stats->qty_bought_rt ?? 0,
                        'stat_sold' => $stats->qty_sold ?? 0,
                        'stat_sold_rt' => $stats->qty_sold_rt ?? 0,
                        'stat_balance' => $balance,
                    ]
                ])
                ->make(true);
        }
    }

    // Stok Gudang
    public function storageSupplies(Request $request)
    {
        return view('supply.storageStockData');
    }

    public function getStorageSupplies(Request $request)
    {
        if ($request->ajax()) {
            $items = ItemsLog::with('medicines')
                ->whereIn('status', [2, 5, 6, 7]);

            if ($request->filled('searchMedicine')) {
                $items->whereHas('medicines', function ($q) use ($request) {
                    $q->where(function($sq) use ($request) {
                        $sq->where('name', 'like', "%{$request->searchMedicine}%")
                           ->orWhere('code', 'like', "%{$request->searchMedicine}%");
                    });
                });
            }

            // Selalu filter berdasarkan apotek aktif
            $items->whereHas('batches', function ($q) {
                $q->where('pharmacy_id', getActivePharmacyId());
            });

            if ($request->filled('start_date')) {
                $items->whereDate('date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $items->whereDate('date', '<=', $request->end_date);
            }

            return DataTables::eloquent($items)
                ->addIndexColumn()
                ->addColumn('date', fn($r) => $r->date)
                ->addColumn('code', fn($r) => $r->code)
                ->addColumn('type', fn($r) => $r->type)
                ->addColumn('name', fn($r) => $r->medicines->name)
                ->addColumn('stock', function ($row) {

                    // status 2 — Pembelian ( Stok masuk gudang)
                    if ($row->status == 2) {
                        $sign = $row->qty > 0 ? '+' : '';
                        $color = $row->qty >= 0 ? '#854F0B' : '#A32D2D';
                        return "<div style='color:{$color};font-weight:600;'>{$sign}{$row->qty}</div>";
                    }
                    // status 5 — Stock Opname
                    if ($row->status == 5) {
                        $sign = $row->qty > 0 ? '+' : '';
                        $color = $row->qty >= 0 ? '#854F0B' : '#A32D2D';
                        return "<div style='color:{$color};font-weight:600;'>{$sign}{$row->qty}</div>";
                    }
                    // status 6 
                    if ($row->status == 6) {
                        $diff = $row->qty_after - $row->qty_before;
                        $sign = $diff > 0 ? '+' : '-';
                        return "<div style='color:#185FA5;font-weight:600;'>{$sign}" . abs($diff) . "</div>";
                    }
                    // status 7 — Mutasi Stok
                    if ($row->status == 7) {
                        $diff = $row->qty_after - $row->qty_before;
                        $sign = $diff > 0 ? '+' : '-';
                        $color = $diff > 0 ? '#854F0B' : '#0F6E56';
                        return "<div style='color:{$color};font-weight:600;'>{$sign}" . abs($diff) . "</div>";
                    }
                })
                ->addColumn('qty_before', fn($r) => "<b>{$r->qty_before}</b>")
                ->addColumn('qty_after', fn($r) => "<b>{$r->qty_after}</b>")
                // Fixed: these two were both returning qty_after
                ->addColumn('qty_before_number', fn($r) => $r->qty_before)
                ->addColumn('qty_after_number', fn($r) => $r->qty_after)
                ->addColumn('supply', function ($r) {
                    static $storageCache = [];
                    $medId = $r->medicine_id;
                    if (!$medId) return '-';

                    if (!isset($storageCache[$medId])) {
                        $storageCache[$medId] = $this->calculateRealtimeStock($medId, getActivePharmacyId(), 'storage');
                    }

                    return $storageCache[$medId];
                })
                ->addColumn('status', function ($row) {
                    $map = [
                        2 => ['label' => 'Pembelian', 'bg' => '#caffc5', 'color' => '#457b00'],
                        5 => ['label' => 'Stock Opname', 'bg' => '#FAEEDA', 'color' => '#633806'],
                        6 => ['label' => 'Adjustment', 'bg' => '#E6F1FB', 'color' => '#0C447C'],
                        7 => ['label' => 'Mutasi Stok', 'bg' => '#E1F5EE', 'color' => '#085041'],
                    ];
                    $s = $map[$row->status] ?? ['label' => '—', 'bg' => '#F1EFE8', 'color' => '#5F5E5A'];
                    return "
                    <div style='
                        text-align:center;font-weight:500;text-transform:uppercase;
                        background:{$s['bg']};color:{$s['color']};
                        padding:4px 10px;font-size:11px;font-family:inherit;
                        border-radius:20px;letter-spacing:0.04em;'>
                        {$s['label']}
                    </div>";
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
                ->withSum([
                    'items_log as qty_orders' => function ($q) {
                        $q->where('status', 2);
                    }
                ], 'qty')
                ->withSum([
                    'items_log as qty_sales' => function ($q) {
                        $q->where('status', 1);
                    }
                ], 'qty')
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
                        padding: 7px 6px;
                        font-size:9px;
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
                        padding: 7px 6px;
                        width:100px;
                        font-size: 9px;
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
                        padding: 7px 6px;
                        width:100px;
                        font-size: 9px;
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
                        padding: 7px 6px;
                        width:100px;
                        font-size: 9px;
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
                        font-size: 9px;
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
                        font-size: 9px;
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
            $items = ItemsLog::with(['medicines', 'batches'])->whereHas('batches', function ($batch) {
                $batch->where('pharmacy_id', getActivePharmacyId());
            });
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
            $stock_start = (clone $items)->orderBy('date', 'asc')->first();
            return DataTables::eloquent($items)
                ->addIndexColumn()
                ->addColumn('date', function ($row) {
                    return $row->date->format('d/m/Y');
                })
                ->addColumn('transaction_code', function ($row) {
                    if (!$row->transaction_code)
                        return '-';
                    $code = e($row->transaction_code);
                    return '
                    <div class="flex items-center gap-1.5">
                        <span style="font-size:10px" class="font-nunito-bold text-slate-700 bg-slate-50 px-2 py-0.5 rounded border border-slate-200">' . $code . '</span>
                        <button type="button" onclick="navigator.clipboard.writeText(\'' . $code . '\'); iziToast.success({title: \'Tersalin\', message: \'Kode berhasil disalin\', position: \'topRight\'})" class="p-1 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="Salin kode">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        </button>
                    </div>';
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
                    return $total_sales;
                })
                ->addColumn('total_orders', function ($row) use ($total_orders) {
                    return $total_orders;
                })
                ->addColumn('stock_start', function ($row) use ($stock_start) {
                    return $stock_start;
                })
                ->addColumn('qty_after_number', function ($row) {
                    return $row->qty_after;
                })
                ->addColumn('supply', function ($row) {
                    static $stockCache = [];
                    $medId = $row->medicine_id;
                    if (!$medId) return '-';

                    if (!isset($stockCache[$medId])) {
                        $stockCache[$medId] = $this->calculateRealtimeStock($medId, getActivePharmacyId(), 'total');
                    }

                    return $stockCache[$medId];
                })
                ->addColumn('status', function ($row) {
                    if ($row->status == 1) {
                        return "<div style='
                        text-align:center;
                        font-weight:bold;
                        text-transform:uppercase;
                        background-color:rgba(34,197,94,0.2);
                        color:#16a34a;
                        padding: 10px 4px;
                        font-size:9px;
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
                        width:100px;
                        font-size: 9px;
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
                        width:100px;
                        font-size: 9px;
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
                        width:100px;
                        font-size: 9px;
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
                        font-size: 9px;
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
                        font-size: 9px;
                        font-family: Poppins;
                        border-radius: 25px;'>
                        Mutasi Stok
                        </div";
                    }
                })
                ->rawColumns(['status', 'stock', 'qty_before', 'qty_after', 'transaction_code'])
                ->make(true);
        }
    }
    public function getBatchesByMedicine(Request $request)
    {
        $batches = Batches::where('medicine_id', $request->medicine_id)
            ->where('pharmacy_id', getActivePharmacyId())
            ->orderBy('expired_date', 'asc') // FEFO
            ->withSum(['medicine_transfer_items as counter_stock' => function ($q) {
                $q->where('status', 1);
            }], 'qty')
            ->get(['id', 'name', 'expired_date', 'stock']);

        return response()->json($batches);
    }
    public function generateOpnameCode()
    {
        $now = Carbon::now();

        $year = $now->format('y');
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
    public function stockOpname()
    {
        return view('supply.stockOpname');
    }
    public function printStockOpname(Request $request)
    {
        $now = Carbon::now()->format('dmY');
        return Excel::download(new PrintStockOpnameExport($request), 'stock_opname-' . $now . '.xlsx');
    }
    public function scanBarcode(Request $request)
    {
        $barcode = $request->query('barcode');

        $transfer = MedicineTransferItems::with(['batches.medicines'])
            ->whereHas('batches.medicines', function ($q) use ($barcode) {
                $q->where('barcode', $barcode);
            })
            ->first();

        if (!$transfer) {
            return response()->json(['found' => false], 404);
        }

        $medicine = $transfer->batches->medicines;

        return response()->json([
            'found' => true,
            'code' => $medicine->code,
            'name' => $medicine->name,
            'unit' => $medicine->unit,
            'raw_price' => $medicine->raw_price,
            'stock' => $transfer->qty,  // counter stock from medicine_transfer_items
        ]);
    }
    public function scannerPage()
    {
        return view('supply.scanner');
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
            ->where('pharmacy_id', getActivePharmacyId())
            ->orderBy('expired_date', 'asc')                    // FEFO
            ->get(['id', 'name', 'expired_date', 'stock']);

        return response()->json($batches);
    }

    public function opname(Request $request)
    {
        $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'stock_physic' => 'required|integer',
            'counter_stock_physic' => 'nullable|integer',
            'batches_id' => 'nullable|exists:batches,id',
        ]);

        DB::beginTransaction();

        try {
            // 1. Resolve which batch to use (provided or FEFO default)
            if ($request->filled('batches_id')) {
                $batch = Batches::lockForUpdate()->findOrFail($request->batches_id);
            } else {
                $batch = Batches::lockForUpdate()
                    ->where('medicine_id', $request->medicine_id)
                    ->where('pharmacy_id', getActivePharmacyId())
                    ->orderBy('expired_date', 'asc')
                    ->firstOrFail();
            }

            $storageBefore = (int) $batch->stock;
            $transfer = MedicineTransferItems::where('batches_id', $batch->id)
                ->where('status', 1)
                ->first();
            $counterBefore = $transfer ? (int) $transfer->qty : 0;

            $stockBeforeTotal = $storageBefore + $counterBefore;

            $storagePhysic = (int) $request->stock_physic;
            $hasCounterInput = $request->filled('counter_stock_physic');
            $counterPhysic = $hasCounterInput ? (int) $request->counter_stock_physic : $counterBefore;

            $stockAfterTotal = $storagePhysic + $counterPhysic;
            $discrepancy = $stockAfterTotal - $stockBeforeTotal;

            // status 5 = surplus or equal, status 6 = deficit
            $status = $discrepancy >= 0 ? 5 : 6;

            // 2. Update batch storage stock
            $batch->stock = $storagePhysic;
            $batch->save();

            // 3. Update counter stock if input provided
            if ($hasCounterInput) {
                if ($transfer) {
                    $transfer->qty = $counterPhysic;
                    $transfer->save();
                } else {
                    MedicineTransferItems::create([
                        'batches_id' => $batch->id,
                        'source_batches_id' => $batch->id,
                        'qty' => $counterPhysic,
                        'status' => 1,
                    ]);
                }
            }

            // 4. Write StockOpname and ItemsLog
            StockOpname::create([
                'users_id' => auth()->id(),
                'batches_id' => $batch->id,
                'stock_physical' => $stockAfterTotal,
                'stock_discrepancy' => $discrepancy,
                'stock_total' => $stockAfterTotal,
                'date' => now()->toDateString(),
                'status' => $status,
            ]);

            ItemsLog::create([
                'batches_id' => $batch->id,
                'transaction_code' => $this->generateOpnameCode(),
                'code' => $this->generateItemsLogCode(),
                'type' => "SO",
                'medicine_id' => $request->medicine_id,
                'qty' => abs($discrepancy),
                'qty_before' => $stockBeforeTotal,
                'qty_after' => $stockAfterTotal,
                'total' => $discrepancy,
                'date' => now()->toDateTimeString(),
                'status' => $status,
                'user_id' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Stock opname berhasil disimpan.',
                'batch' => $batch->fresh(),
                'qty_before' => $stockBeforeTotal,
                'qty_after' => $stockAfterTotal,
                'discrepancy' => $discrepancy,
                'status' => $status,
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
        ini_set('memory_limit', '512M');

        $query = MedicineTransferItems::query()
            ->with([
                'transfer:id,code',
                'batches.medicines:id,name,code',
                'batches.pharmacy:id,name',
                'etalases:id,name'
            ])
            ->whereHas('batches', function ($q) {
                $q->where('pharmacy_id', getActivePharmacyId());
            });

        return DataTables::eloquent($query)

            ->addIndexColumn()

            ->filter(function ($query) use ($request) {

                // Filter status
                if ($request->status !== null && $request->status !== '') {
                    $query->where('status', $request->status);
                }

                // Filter search
                if ($request->search) {
                    $search = $request->search;

                    $query->where(function ($sub) use ($search) {
                        $sub->whereHas('batches.medicines', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        })->orWhereHas('transfer', function ($q) use ($search) {
                            $q->where('code', 'like', "%{$search}%");
                        })->orWhereHas('batches', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    });
                }
            })

            ->addColumn('code', function ($item) {
                return $item->transfer->code ?? '-';
            })

            ->addColumn('medicine_name', function ($item) {
                return optional($item->batches->medicines)->name ?? '-';
            })

            ->addColumn('batch_name', function ($item) {
                return $item->batches->name ?? '-';
            })

            ->addColumn('expired_date', function ($item) {
                return $item->batches->expired_date ?? '-';
            })

            ->addColumn('etalase', function ($item) {
                return optional($item->etalases)->name ?? '-';
            })

            ->addColumn('pharmacy', function ($item) {
                return optional($item->batches->pharmacy)->name ?? '-';
            })

            ->editColumn('stock', function ($item) {
                return $item->qty ?? 0;
            })

            ->editColumn('status', function ($item) {
                return (int) $item->status;
            })

            ->rawColumns([])

            ->make(true);
    }
}
