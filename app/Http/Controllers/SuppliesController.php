<?php

namespace App\Http\Controllers;

use App\Exports\Stocks\PrintStockOpnameExport;
use App\Exports\Stocks\StockDataExport;
use App\Jobs\ProcessStockDataExport;
use App\Models\Batches;
use App\Models\ExportJob;
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

        $warehouseId = getWarehousePharmacyId();
        $canSeeWarehouse = canAccessWarehouseStock($pharmacyId);

        $storageStock = $canSeeWarehouse
            ? (int) Batches::where('medicine_id', $medicineId)
                ->where('pharmacy_id', $warehouseId)
                ->sum('stock')
            : 0;

        if ($type === 'storage') {
            return $storageStock;
        }

        // Jika farmasi adalah Gudang (id 9), stok etalase/pelayanan diambil dari SAHABAT PMI (id 1)
        $counterPharmacyId = isWarehousePharmacy($pharmacyId) ? 1 : $pharmacyId;

        $counterStock = (int) MedicineTransferItems::whereHas('batches', function ($b) use ($medicineId, $counterPharmacyId) {
                $b->where('medicine_id', $medicineId)
                  ->where('pharmacy_id', $counterPharmacyId);
            })
            ->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
            })
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

            $activePharmacyId = getActivePharmacyId();
            $warehouseId = getWarehousePharmacyId();
            $isWarehouse = isWarehousePharmacy($activePharmacyId);
            $pharmacyId = $isWarehouse ? 1 : $activePharmacyId;
            $canSeeWarehouse = canAccessWarehouseStock($activePharmacyId);

            // Jika akun gudang PMI, sertakan pharmacy_id Gudang PMI (9) dan Apotek PMI (1)
            $targetPharmacyIds = $isWarehouse
                ? array_unique([$activePharmacyId, $warehouseId, 1])
                : [$activePharmacyId];

            $baseQuery = ItemsLog::query();

            $baseQuery->where(function ($q) use ($targetPharmacyIds, $isWarehouse, $activePharmacyId) {
                $q->where(function ($sub) use ($targetPharmacyIds) {
                    $sub->where('status', 2)
                        ->whereHas('receiving', function ($r) use ($targetPharmacyIds) {
                            $r->whereIn('pharmacy_id', $targetPharmacyIds)
                                ->whereIn('status', [1, 2, 3, 4]);
                        });
                })->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->where('status', 7)
                        ->whereHas('batches', function ($b) use ($targetPharmacyIds) {
                            $b->whereIn('pharmacy_id', $targetPharmacyIds);
                        });
                })->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->whereNotIn('status', [2, 7])
                        ->whereHas('users', function ($u) use ($targetPharmacyIds) {
                            $u->whereIn('pharmacy_id', $targetPharmacyIds);
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
            $storageStock = 0;
            $counterStock = 0;

            $med = null;
            if ($request->filled('searchMedicine')) {
                $searchValue = $request->searchMedicine;
                $med = is_numeric($searchValue)
                    ? Medicines::find($searchValue)
                    : Medicines::where('name', $searchValue)->orWhere('code', $searchValue)->first();
            }

            if ($med) {
                $storageStock = $canSeeWarehouse ? $this->calculateRealtimeStock($med->id, $pharmacyId, 'storage') : 0;
                $counterStock = $this->calculateRealtimeStock($med->id, $pharmacyId, 'counter');
                $balance = $storageStock + $counterStock;
            } else if ($lastRecord) {
                $storageStock = $canSeeWarehouse ? (int) Batches::where('pharmacy_id', $warehouseId)->sum('stock') : 0;
                $counterStock = (int) MedicineTransferItems::whereHas('batches', function ($b) use ($pharmacyId) {
                    $b->where('pharmacy_id', $pharmacyId);
                })->where('status', 1)->where(function ($q) {
                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                })->sum('qty');
                $balance = $storageStock + $counterStock;
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
                        'stat_storage' => $storageStock,
                        'stat_counter' => $counterStock,
                        'stat_balance' => $balance,
                    ]
                ])
                ->make(true);
        }
    }

    // Stok Gudang
    public function storageSupplies(Request $request)
    {
        if (!canAccessWarehouseStock()) {
            return redirect()->route('home')->with('error', 'Akses stok gudang hanya untuk PMI dan Gudang.');
        }
        return view('supply.storageStockData');
    }

    public function getStorageSupplies(Request $request)
    {
        if (!canAccessWarehouseStock()) {
            return response()->json(['data' => []]);
        }

        if ($request->ajax()) {
            $warehouseId = getWarehousePharmacyId();

            $items = ItemsLog::with('medicines')
                ->whereIn('status', [2, 5, 6, 7])
                ->whereHas('batches', function ($q) use ($warehouseId) {
                    $q->where('pharmacy_id', $warehouseId);
                });

            $totalStockGudang = 0;
            if ($request->filled('medicine_id')) {
                $items->where('medicine_id', $request->medicine_id);
                $totalStockGudang = $this->calculateRealtimeStock($request->medicine_id, $warehouseId, 'storage');
            } else {
                $totalStockGudang = (int) Batches::where('pharmacy_id', $warehouseId)->sum('stock');
            }

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
                ->addColumn('name', fn($r) => $r->medicines->name ?? '-')
                ->addColumn('stock', function ($row) {

                    // status 2 — Pembelian (Stok masuk gudang)
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
                    return $row->qty;
                })
                ->addColumn('qty_before', fn($r) => "<b>{$r->qty_before}</b>")
                ->addColumn('qty_after', fn($r) => "<b>{$r->qty_after}</b>")
                ->addColumn('qty_before_number', fn($r) => $r->qty_before)
                ->addColumn('qty_after_number', fn($r) => $r->qty_after)
                ->addColumn('supply', function ($r) use ($warehouseId) {
                    static $storageCache = [];
                    $medId = $r->medicine_id;
                    if (!$medId) return '-';

                    if (!isset($storageCache[$medId])) {
                        $storageCache[$medId] = $this->calculateRealtimeStock($medId, $warehouseId, 'storage');
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
                ->with('total_stock_gudang', $totalStockGudang)
                ->make(true);
        }
    }

    // Data Stok
    public function stockData()
    {
        if (!canAccessWarehouseStock() && !isWarehousePharmacy()) {
            return redirect()->route('dashboard')->with('error', 'Halaman Data Stok hanya dapat diakses oleh Gudang PMI.');
        }

        return view('supply.stockData');
    }
    public function getStockData(Request $request)
    {
        if ($request->ajax()) {
            if (!canAccessWarehouseStock() && !isWarehousePharmacy()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $warehouseId = getWarehousePharmacyId(); // 9 (Gudang PMI)
            $pmiPharmacyId = 1; // SAHABAT PMI

            $medicines = Medicines::query()
                ->select([
                    'medicines.id',
                    'medicines.code',
                    'medicines.name',
                    'medicines.unit',
                ])
                ->addSelect([
                    // Qty Beli dari Gudang PMI (pharmacy_id = 9)
                    'qty_orders' => ItemsLog::select(DB::raw('COALESCE(SUM(CAST(items_log.qty AS UNSIGNED)), 0)'))
                        ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                        ->whereColumn('items_log.medicine_id', 'medicines.id')
                        ->where('items_log.status', 2)
                        ->where('batches.pharmacy_id', $warehouseId)
                        ->when($request->filled('start_date'), fn($q) => $q->whereDate('items_log.date', '>=', $request->start_date))
                        ->when($request->filled('end_date'), fn($q) => $q->whereDate('items_log.date', '<=', $request->end_date)),

                    // Qty Jual dari SAHABAT PMI (pharmacy_id = 1)
                    'qty_sales' => ItemsLog::select(DB::raw('COALESCE(SUM(CAST(items_log.qty AS UNSIGNED)), 0)'))
                        ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                        ->whereColumn('items_log.medicine_id', 'medicines.id')
                        ->where('items_log.status', 1)
                        ->where('batches.pharmacy_id', $pmiPharmacyId)
                        ->when($request->filled('start_date'), fn($q) => $q->whereDate('items_log.date', '>=', $request->start_date))
                        ->when($request->filled('end_date'), fn($q) => $q->whereDate('items_log.date', '<=', $request->end_date)),

                    // Qty Awal
                    'qty_start' => ItemsLog::select('qty_before')
                        ->whereColumn('medicine_id', 'medicines.id')
                        ->when($request->filled('start_date'), fn($q) => $q->whereDate('date', '>=', $request->start_date))
                        ->when($request->filled('end_date'), fn($q) => $q->whereDate('date', '<=', $request->end_date))
                        ->orderBy('date')
                        ->orderBy('id')
                        ->limit(1),

                    // Stok Gudang (pharmacy_id = 9)
                    'qty_storage' => Batches::select(DB::raw('COALESCE(SUM(stock), 0)'))
                        ->whereColumn('medicine_id', 'medicines.id')
                        ->where('pharmacy_id', $warehouseId),

                    // Stok Pelayanan PMI (pharmacy_id = 1)
                    'qty_counter' => MedicineTransferItems::select(DB::raw('COALESCE(SUM(medicine_transfer_items.qty), 0)'))
                        ->join('batches', 'batches.id', '=', 'medicine_transfer_items.batches_id')
                        ->whereColumn('batches.medicine_id', 'medicines.id')
                        ->where('batches.pharmacy_id', $pmiPharmacyId)
                        ->where('medicine_transfer_items.status', 1)
                        ->where(function ($q) {
                            $q->whereNull('medicine_transfer_items.source_type')
                              ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                        }),
                ]);

            if ($request->filled('medicine_id')) {
                $medicines->where('medicines.id', $request->medicine_id);
            }

            return DataTables::of($medicines)
                ->addIndexColumn()
                ->editColumn('qty_start', fn($m) => (int) ($m->qty_start ?? 0))
                ->editColumn('qty_orders', fn($m) => (int) ($m->qty_orders ?? 0))
                ->editColumn('qty_sales', fn($m) => (int) ($m->qty_sales ?? 0))
                ->editColumn('qty_storage', fn($m) => (int) ($m->qty_storage ?? 0))
                ->editColumn('qty_counter', fn($m) => (int) ($m->qty_counter ?? 0))
                ->addColumn('qty_now', function ($m) {
                    $storage = (int) ($m->qty_storage ?? 0);
                    $counter = (int) ($m->qty_counter ?? 0);
                    return $storage + $counter;
                })
                ->make(true);
        }
    }
    public function medicineSelect(Request $request)
    {
        $search = $request->q;

        $result = Medicines::where('status', 1)
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%');
            })
            ->select('id', 'code', 'name', 'unit')
            ->limit(20)
            ->get();

        return response()->json($result);
    }
    public function printStockData(Request $request)
    {
        if (!canAccessWarehouseStock() && !isWarehousePharmacy()) {
            abort(403, 'Unauthorized');
        }
        return Excel::download(new StockDataExport($request), 'stock_data_gudang.xlsx');
    }

    public function exportStockData(Request $request)
    {
        if (!canAccessWarehouseStock() && !isWarehousePharmacy()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $job = ExportJob::create([
            'type' => 'stock_data',
            'status' => 'pending',
            'progress' => 0,
        ]);

        dispatch(new ProcessStockDataExport($job->id, $request->only(['start_date', 'end_date', 'medicine_id'])));

        return response()->json([
            'job_id' => $job->id,
            'message' => 'Export dimulai.',
        ]);
    }

    public function exportStockDataStatus($id)
    {
        $job = ExportJob::findOrFail($id);

        return response()->json([
            'status' => $job->status,
            'progress' => (int) $job->progress,
            'file' => $job->file_path ? asset('storage/' . $job->file_path) : null,
        ]);
    }

    // Stock Opname

    public function getMedicineLogs(Request $request)
    {
        if ($request->ajax()) {

            $logs = ItemsLog::query()
                ->with(['medicines', 'batches'])
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

            $stats = (clone $logs)->selectRaw("
                SUM(CASE WHEN status = 1 THEN qty ELSE 0 END) as qty_sold,
                SUM(CASE WHEN status = 2 THEN qty ELSE 0 END) as qty_bought
            ")->first();

            $firstRecord = (clone $logs)->orderBy('date', 'asc')->orderBy('id', 'asc')->first();

            return DataTables::of($logs)
                ->addIndexColumn()

                ->addColumn('name', function ($log) {
                    return $log->medicines->name ?? '-';
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
                        </div>";
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
                        </div>";
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
                        </div>";
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
                        </div>";
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
                        </div>";
                    }
                    return "-";
                })
                ->rawColumns(['status'])
                ->with([
                    'qty_awal' => $firstRecord ? (int) $firstRecord->qty_before : 0,
                    'qty_beli' => (int) ($stats->qty_bought ?? 0),
                    'qty_jual' => (int) ($stats->qty_sold ?? 0),
                ])
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
            $activePharmacyId = getActivePharmacyId();
            $items = ItemsLog::with(['medicines', 'batches'])->whereHas('batches', function ($batch) use ($activePharmacyId) {
                if (isWarehousePharmacy($activePharmacyId)) {
                    $batch->whereIn('pharmacy_id', [getWarehousePharmacyId(), 1]);
                } else {
                    $batch->where('pharmacy_id', $activePharmacyId);
                }
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
        $pharmacyId = getActivePharmacyId();
        $warehouseId = getWarehousePharmacyId();
        $canSeeWarehouse = canAccessWarehouseStock($pharmacyId);
        $counterPharmacyId = isWarehousePharmacy($pharmacyId) ? 1 : $pharmacyId;

        $batches = Batches::where('medicine_id', $request->medicine_id)
            ->where(function ($q) use ($pharmacyId, $warehouseId, $canSeeWarehouse) {
                if (isWarehousePharmacy($pharmacyId)) {
                    $q->where('pharmacy_id', $warehouseId)
                      ->orWhere('pharmacy_id', 1);
                } else {
                    $q->where('pharmacy_id', $pharmacyId);
                    if ($canSeeWarehouse) {
                        $q->orWhere('pharmacy_id', $warehouseId);
                    }
                }
            })
            ->orderBy('expired_date', 'asc') // FEFO
            ->withSum(['medicine_transfer_items as counter_stock' => function ($q) use ($counterPharmacyId) {
                $q->where('status', 1)
                  ->whereHas('batches', fn($b) => $b->where('pharmacy_id', $counterPharmacyId))
                  ->where(function ($sub) {
                      $sub->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                  });
            }], 'qty')
            ->get(['id', 'name', 'expired_date', 'stock', 'pharmacy_id']);

        $batches->each(function ($b) use ($warehouseId) {
            // Hanya batch milik Gudang PMI (pharmacy_id = 9) yang memiliki stok gudang (storage stock).
            // Batch milik apotek pelayanan (pharmacy_id != 9) stok fisiknya berada di etalase (counter_stock).
            if ($b->pharmacy_id != $warehouseId) {
                $b->stock = 0;
            }
        });

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

    public function generateTransfersCode()
    {
        $now = Carbon::now();

        $year = $now->format('y');
        $month = $now->format('m');
        $prefix = "{$year}{$month}MUT";

        $lastCode = MedicineTransfers::where('code', 'like', "{$prefix}%")
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
            'id' => $medicine->id,
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
        $pharmacyId = getActivePharmacyId();
        $warehouseId = getWarehousePharmacyId();
        $canSeeWarehouse = canAccessWarehouseStock($pharmacyId);
        $counterPharmacyId = isWarehousePharmacy($pharmacyId) ? 1 : $pharmacyId;

        $batches = Batches::where('medicine_id', $request->medicine_id)
            ->where(function ($q) use ($pharmacyId, $warehouseId, $canSeeWarehouse) {
                if (isWarehousePharmacy($pharmacyId)) {
                    $q->where('pharmacy_id', $warehouseId)
                      ->orWhere('pharmacy_id', 1);
                } else {
                    $q->where('pharmacy_id', $pharmacyId);
                    if ($canSeeWarehouse) {
                        $q->orWhere('pharmacy_id', $warehouseId);
                    }
                }
            })
            ->withSum(['medicine_transfer_items as counter_stock' => function ($q) use ($counterPharmacyId) {
                $q->where('status', 1)
                  ->whereHas('batches', fn($b) => $b->where('pharmacy_id', $counterPharmacyId))
                  ->where(function ($sub) {
                      $sub->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                  });
            }], 'qty')
            ->orderBy('expired_date', 'asc')
            ->get(['id', 'name', 'expired_date', 'stock', 'pharmacy_id']);

        $batches->each(function ($b) use ($warehouseId) {
            // Hanya batch milik Gudang PMI (pharmacy_id = 9) yang memiliki stok gudang (storage stock).
            // Batch milik apotek pelayanan (pharmacy_id != 9) stok fisiknya berada di etalase (counter_stock).
            if ($b->pharmacy_id != $warehouseId) {
                $b->stock = 0;
            }
        });

        return response()->json($batches);
    }

    public function opname(Request $request)
    {
        $pharmacyId = getActivePharmacyId();
        $warehouseId = getWarehousePharmacyId();
        $canSeeWarehouse = canAccessWarehouseStock($pharmacyId);
        $counterPharmacyId = isWarehousePharmacy($pharmacyId) ? 1 : $pharmacyId;

        $rules = [
            'medicine_id' => 'required|exists:medicines,id',
            'counter_stock_physic' => 'nullable|integer|min:0',
            'batches_id' => 'nullable|exists:batches,id',
        ];

        if ($canSeeWarehouse) {
            $rules['stock_physic'] = 'required|integer|min:0';
        } else {
            $rules['stock_physic'] = 'nullable|integer|min:0';
        }

        $request->validate($rules);

        DB::beginTransaction();

        try {
            // 1. Resolve which batch to use (Custom batch if provided, selected batch, or FEFO default)
            if ($request->filled('custom_batch_name')) {
                $customName = trim($request->custom_batch_name);
                $customEd = $request->filled('custom_expired_date')
                    ? Carbon::parse(str_replace('/', '-', $request->custom_expired_date))->toDateString()
                    : now()->addYears(2)->toDateString();

                $batch = Batches::lockForUpdate()->firstOrCreate([
                    'medicine_id' => $request->medicine_id,
                    'pharmacy_id' => $canSeeWarehouse ? $warehouseId : $pharmacyId,
                    'name' => $customName,
                ], [
                    'expired_date' => $customEd,
                    'stock' => 0,
                ]);

                if ($request->filled('custom_expired_date')) {
                    $batch->expired_date = $customEd;
                    $batch->save();
                }
            } elseif ($request->filled('batches_id')) {
                $batch = Batches::lockForUpdate()->findOrFail($request->batches_id);
            } else {
                $batch = Batches::lockForUpdate()
                    ->where('medicine_id', $request->medicine_id)
                    ->where(function ($q) use ($pharmacyId, $warehouseId, $canSeeWarehouse) {
                        $q->where('pharmacy_id', $pharmacyId);
                        if ($canSeeWarehouse) {
                            $q->orWhere('pharmacy_id', $warehouseId);
                            $q->orWhere('pharmacy_id', 1);
                        }
                    })
                    ->orderBy('expired_date', 'asc')
                    ->first();

                if (!$batch) {
                    $batch = Batches::create([
                        'medicine_id' => $request->medicine_id,
                        'pharmacy_id' => $canSeeWarehouse ? $warehouseId : $pharmacyId,
                        'name' => 'OPN-' . date('Ymd'),
                        'expired_date' => now()->addYears(2)->toDateString(),
                        'stock' => 0,
                    ]);
                }
            }

            // Resolusi batch khusus Gudang PMI (pharmacy_id = 9) untuk stok gudang
            $storageBatch = null;
            if ($canSeeWarehouse) {
                if ($batch->pharmacy_id == $warehouseId) {
                    $storageBatch = $batch;
                } else {
                    $storageBatch = Batches::firstOrCreate([
                        'medicine_id' => $request->medicine_id,
                        'pharmacy_id' => $warehouseId,
                        'name' => $batch->name,
                    ], [
                        'expired_date' => $batch->expired_date ?? now()->addYears(2)->toDateString(),
                        'stock' => 0,
                    ]);
                }
                $storageBefore = (int) $storageBatch->stock;
            } else {
                $storageBefore = 0;
            }

            // Resolusi batch counter / pelayanan (pharmacy_id = 1 untuk PMI, atau pharmacy_id cabang)
            $counterBatch = null;
            if ($batch->pharmacy_id == $counterPharmacyId) {
                $counterBatch = $batch;
            } else {
                $counterBatch = Batches::firstOrCreate([
                    'medicine_id' => $request->medicine_id,
                    'pharmacy_id' => $counterPharmacyId,
                    'name' => $batch->name,
                ], [
                    'expired_date' => $batch->expired_date ?? now()->addYears(2)->toDateString(),
                    'stock' => 0,
                ]);
            }

            $transfer = MedicineTransferItems::where('batches_id', $counterBatch->id)
                ->where('status', 1)
                ->where(function ($q) {
                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                })
                ->first();

            $counterBefore = $transfer ? (int) $transfer->qty : 0;
            $stockBeforeTotal = $storageBefore + $counterBefore;

            $storagePhysic = $canSeeWarehouse ? (int) ($request->stock_physic ?? 0) : $storageBefore;
            $hasCounterInput = $request->filled('counter_stock_physic') || !$canSeeWarehouse;
            $counterPhysic = $request->filled('counter_stock_physic')
                ? (int) $request->counter_stock_physic
                : ($canSeeWarehouse ? $counterBefore : (int) ($request->stock_physic ?? 0));

            $stockAfterTotal = $storagePhysic + $counterPhysic;
            $discrepancy = $stockAfterTotal - $stockBeforeTotal;

            // status 5 = surplus or equal, status 6 = deficit
            $status = $discrepancy >= 0 ? 5 : 6;

            // 2. Update batch storage stock (hanya di batch Gudang PMI pharmacy_id = 9)
            if ($canSeeWarehouse && $storageBatch) {
                $storageBatch->stock = $storagePhysic;
                $storageBatch->save();

                // Jika $batch awal bukan batch gudang (misal pharmacy_id = 1), pastikan batches.stock di apotek bernilai 0
                if ($batch->id != $storageBatch->id && $batch->pharmacy_id != $warehouseId) {
                    $batch->stock = 0;
                    $batch->save();
                }
            }

            // 3. Update counter stock if input provided (hanya di medicine_transfer_items dengan batches_id = counterBatch)
            if ($hasCounterInput) {
                if ($transfer) {
                    $transfer->qty = $counterPhysic;
                    $transfer->save();
                } else {
                    $transferHeader = MedicineTransfers::create([
                        'code' => $this->generateTransfersCode(),
                        'status' => 1,
                        'user_id' => auth()->id(),
                    ]);

                    MedicineTransferItems::create([
                        'medicine_transfer_id' => $transferHeader->id,
                        'batches_id' => $counterBatch->id,
                        'source_batches_id' => $storageBatch ? $storageBatch->id : $counterBatch->id,
                        'qty' => $counterPhysic,
                        'status' => 1,
                        'source_type' => 'pelayanan',
                        'etalases_id' => 99,
                    ]);
                }
            }

            // 4. Update master medicine stock
            $medicine = Medicines::find($request->medicine_id);
            if ($medicine) {
                $totalRealStock = $this->calculateRealtimeStock($medicine->id, $pharmacyId, 'total');
                $medicine->update(['stock' => $totalRealStock]);
            }

            // 5. Write StockOpname and ItemsLog
            StockOpname::create([
                'users_id' => auth()->id(),
                'batches_id' => $storageBatch ? $storageBatch->id : $batch->id,
                'stock_physical' => $stockAfterTotal,
                'stock_discrepancy' => $discrepancy,
                'stock_total' => $stockAfterTotal,
                'date' => now()->toDateString(),
                'status' => $status,
            ]);

            ItemsLog::create([
                'batches_id' => $storageBatch ? $storageBatch->id : $batch->id,
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
                'batch' => ($storageBatch ?? $batch)->fresh(),
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

        $pharmacyId = getActivePharmacyId();

        $query = MedicineTransferItems::query()
            ->with([
                'transfer:id,code',
                'batches.medicines:id,name,code',
                'batches.pharmacy:id,name',
                'etalases:id,name'
            ])
            ->whereHas('batches', function ($q) use ($pharmacyId) {
                $q->where('pharmacy_id', $pharmacyId);
            })
            ->where(function ($q) {
                $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
            });

        $totalStockPelayanan = 0;
        if ($request->medicine_id) {
            $totalStockPelayanan = $this->calculateRealtimeStock($request->medicine_id, $pharmacyId, 'counter');
        } else {
            $totalStockPelayanan = (int) MedicineTransferItems::whereHas('batches', fn($q) => $q->where('pharmacy_id', $pharmacyId))
                ->where('status', 1)
                ->where(function ($q) {
                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                })
                ->sum('qty');
        }

        return DataTables::eloquent($query)

            ->addIndexColumn()

            ->filter(function ($query) use ($request) {

                // Filter status
                if ($request->status !== null && $request->status !== '') {
                    $query->where('status', $request->status);
                }

                // Filter medicine_id
                if ($request->medicine_id) {
                    $query->whereHas('batches', function ($q) use ($request) {
                        $q->where('medicine_id', $request->medicine_id);
                    });
                }

                // Filter search
                $searchValue = is_array($request->search) ? ($request->search['value'] ?? '') : $request->search;
                if (!empty($searchValue)) {
                    $query->where(function ($sub) use ($searchValue) {
                        $sub->whereHas('batches.medicines', function ($q) use ($searchValue) {
                            $q->where('name', 'like', "%{$searchValue}%")
                                ->orWhere('code', 'like', "%{$searchValue}%");
                        })->orWhereHas('transfer', function ($q) use ($searchValue) {
                            $q->where('code', 'like', "%{$searchValue}%");
                        })->orWhereHas('batches', function ($q) use ($searchValue) {
                            $q->where('name', 'like', "%{$searchValue}%");
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

            ->with('total_stock_pelayanan', $totalStockPelayanan ?? 0)
            ->make(true);
    }
}
