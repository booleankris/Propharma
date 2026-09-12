<?php

namespace App\Http\Controllers;

use App\Exports\Stocks\PrintStockOpnameExport;
use App\Exports\Stocks\StockDataExport;
use App\Exports\Stocks\StockOpnameTemplateExport;
use App\Jobs\ProcessStockDataExport;
use App\Jobs\ProcessStockOpnameImport;
use App\Models\Batches;
use App\Models\ExportJob;
use App\Models\ItemsLog;
use App\Models\MedicineCart;
use App\Models\Medicines;
use App\Models\MedicineTransfers;
use App\Models\MedicineTransferItems;
use App\Models\ReceivingItems;
use App\Models\StockOpname;
use App\Services\StockOpnameImportService;
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

            // Jika akun gudang PMI atau HO/PMI central, sertakan pharmacy_id Gudang PMI (9) dan Apotek PMI (1)
            $targetPharmacyIds = ($isWarehouse || $canSeeWarehouse || in_array($activePharmacyId, [1, 6, 9]))
                ? array_unique([$activePharmacyId, $warehouseId, 1])
                : [$activePharmacyId];

            $baseQuery = ItemsLog::query();

            $baseQuery->where(function ($q) use ($targetPharmacyIds, $isWarehouse, $activePharmacyId) {
                // 1. Pembelian (Status 2)
                $q->where(function ($sub) use ($targetPharmacyIds) {
                    $sub->where('status', 2)
                        ->whereHas('receiving', function ($r) use ($targetPharmacyIds) {
                            $r->whereIn('pharmacy_id', $targetPharmacyIds)
                                ->whereIn('status', [1, 2, 3, 4]);
                        });
                })
                // 2. Retur Beli (Status 4)
                ->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->where('status', 4)
                        ->where(function ($returQ) use ($targetPharmacyIds) {
                            $returQ->whereHas('receiving', function ($r) use ($targetPharmacyIds) {
                                $r->whereIn('pharmacy_id', $targetPharmacyIds);
                            })->orWhereHas('batches', function ($b) use ($targetPharmacyIds) {
                                $b->whereIn('pharmacy_id', $targetPharmacyIds);
                            })->orWhereHas('users', function ($u) use ($targetPharmacyIds) {
                                $u->whereIn('pharmacy_id', $targetPharmacyIds);
                                if (in_array(9, $targetPharmacyIds)) {
                                    $u->orWhereHas('roles', fn($rq) => $rq->where('name', 'Gudang PMI'));
                                }
                            });
                        });
                })
                // 3. Mutasi Stok (Status 7)
                ->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->where('status', 7)
                        ->whereHas('batches', function ($b) use ($targetPharmacyIds) {
                            $b->whereIn('pharmacy_id', $targetPharmacyIds);
                        });
                })
                // 4. Status lainnya (Penjualan = 1, Retur Jual = 3, dll)
                ->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->whereNotIn('status', [2, 4, 7])
                        ->whereHas('users', function ($u) use ($targetPharmacyIds) {
                            $u->whereIn('pharmacy_id', $targetPharmacyIds);
                            if (in_array(9, $targetPharmacyIds)) {
                                $u->orWhereHas('roles', fn($rq) => $rq->where('name', 'Gudang PMI'));
                            }
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
            $itemsQuery = (clone $baseQuery)->with([
                'medicines',
                'batches',                              // Fetch batches for batch name display
                'receiving.receiving_details.creditor', // Fetch creditor through receiving details
                'medicine_transaction.user',            // Fetch cashier user through transactions
                'users'                                 // Fetch user for creator display
            ])->whereNotIn('status', [5, 6])
              ->orderBy('updated_at', 'asc')
              ->orderBy('id', 'asc');

            // Hitung running balance sekuensial jika ada filter spesifik obat
            $runningMap = [];
            $initialStartBalance = 0;
            $allRows = (clone $itemsQuery)->get();
            $firstRow = $allRows->first();

            if ($firstRow) {
                $prevRecord = (clone $baseQuery)
                    ->where(function ($q) use ($firstRow) {
                        $q->where('updated_at', '<', $firstRow->updated_at)
                          ->orWhere(function ($sq) use ($firstRow) {
                              $sq->where('updated_at', '=', $firstRow->updated_at)
                                ->where('id', '<', $firstRow->id);
                          });
                    })
                    ->orderBy('updated_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                $initialStartBalance = $prevRecord ? (int) $prevRecord->qty_after : (int) $firstRow->qty_before;

                $currRunning = $initialStartBalance;
                foreach ($allRows as $r) {
                    $delta = 0;
                    if ($r->status == 1) { // Penjualan (-)
                        $delta = -$r->qty;
                    } else if ($r->status == 2) { // Pembelian (+)
                        $delta = $r->qty;
                    } else if ($r->status == 3) { // Retur Jual (+)
                        $delta = $r->qty;
                    } else if ($r->status == 4) { // Retur Beli (-)
                        $delta = -$r->qty;
                    } else if ($r->status == 7) { // Mutasi
                        $isOutgoing = (int) $r->qty_after < (int) $r->qty_before;
                        $delta = $isOutgoing ? -$r->qty : $r->qty;
                    }

                    $rowStart = $currRunning;
                    $rowEnd = $rowStart + $delta;
                    $currRunning = $rowEnd;

                    $runningMap[$r->id] = [
                        'start' => $rowStart,
                        'end'   => $rowEnd,
                    ];
                }
            }

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
            return DataTables::eloquent($itemsQuery)
                ->addIndexColumn()
                ->addColumn('date', function ($row) {
                    return $row->date;
                })
                ->addColumn('medicine_name', function ($row) {
                    return $row->medicines->name;
                })
                ->addColumn('batch_name', function ($row) {
                    if (in_array($row->status, [2, 4, 7]) && $row->batches) {
                        return $row->batches->name;
                    }
                    return '-';
                })
                ->addColumn('transaction_code', function ($row) {
                    if (in_array($row->status, [2, 4])) {
                        $detail = $row->receiving?->receiving_details?->first();
                        $codeStr = $detail?->receiving_details_code ?: ($detail?->invoice_number ?: $row->transaction_code);
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
                    // Case 1: Purchase (Status = 2) or Retur Beli (Status = 4) -> Show Creditor / PBF Name
                    if (in_array($row->status, [2, 4])) {
                        $creditorName = $row->receiving?->receiving_details?->first()?->creditor?->name;
                        if ($creditorName) {
                            return $creditorName;
                        }
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
                ->addColumn('qty_before', function ($row) use (&$runningMap) {
                    $val = isset($runningMap[$row->id]) ? $runningMap[$row->id]['start'] : $row->qty_before;
                    return "<div style='color:#000000;font-weight:bold;'><span></span><b>" . $val . "</b></div>";
                })
                ->addColumn('qty_after', function ($row) use (&$runningMap) {
                    $val = isset($runningMap[$row->id]) ? $runningMap[$row->id]['end'] : $row->qty_after;
                    return "<div style='color:#000000;font-weight:bold;'><span></span><b>" . $val . "</b></div>";
                })
                ->addColumn('qty_before_number', function ($row) use (&$runningMap) {
                    return isset($runningMap[$row->id]) ? $runningMap[$row->id]['start'] : $row->qty_before;
                })
                ->addColumn('qty_after_number', function ($row) use (&$runningMap) {
                    return isset($runningMap[$row->id]) ? $runningMap[$row->id]['end'] : $row->qty_after;
                })
                ->addColumn('supply', function ($row) use ($pharmacyId, &$runningMap) {
                    if (isset($runningMap[$row->id])) {
                        return $runningMap[$row->id]['end'];
                    }

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
                        'stat_before' => !empty($runningMap) ? $initialStartBalance : ($firstRecord ? $firstRecord->qty_before : 0),
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
                ->whereIn('status', [2, 4, 5, 6, 7])
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
                    // status 4 — Retur Beli (Stok keluar gudang kembali ke supplier)
                    if ($row->status == 4) {
                        return "<div style='color:#A32D2D;font-weight:600;'>-{$row->qty}</div>";
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
                        4 => ['label' => 'Retur Beli', 'bg' => '#FFE4D6', 'color' => '#C17800'],
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
        $pharmacyId = getActivePharmacyId();
        $canSeeWarehouse = canAccessWarehouseStock($pharmacyId);
        $pharmacy = \App\Models\Pharmacies::find($pharmacyId);

        return view('supply.stockData', compact('canSeeWarehouse', 'pharmacy'));
    }

    public function getStockData(Request $request)
    {
        if ($request->ajax()) {
            $pharmacyId = getActivePharmacyId();
            $warehouseId = getWarehousePharmacyId(); // 9 (Gudang PMI)
            $pmiPharmacyId = 1; // SAHABAT PMI
            $canSeeWarehouse = canAccessWarehouseStock($pharmacyId);

            $startDate = $request->filled('start_date') ? Carbon::parse($request->start_date)->toDateString() : null;
            $endDate = $request->filled('end_date') ? Carbon::parse($request->end_date)->toDateString() : null;

            // Tentukan target pharmacy_id untuk filter pembelian, penjualan, saldo awal, dan stok counter
            $ordersPharmacyId = $canSeeWarehouse ? $warehouseId : $pharmacyId;
            $salesPharmacyId = $canSeeWarehouse ? $pmiPharmacyId : $pharmacyId;
            $startPharmacyIds = $canSeeWarehouse ? [$warehouseId, $pmiPharmacyId] : [$pharmacyId];
            $counterPharmacyId = $canSeeWarehouse ? $pmiPharmacyId : $pharmacyId;

            $medicines = Medicines::query()
                ->select([
                    'medicines.id',
                    'medicines.code',
                    'medicines.name',
                    'medicines.unit',
                ])
                ->addSelect([
                    // Qty Beli: Gudang PMI jika ada akses gudang, atau Pembelian cabang
                    'qty_orders' => ItemsLog::select(DB::raw('COALESCE(SUM(CAST(items_log.qty AS UNSIGNED)), 0)'))
                        ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                        ->whereColumn('items_log.medicine_id', 'medicines.id')
                        ->where('items_log.status', 2)
                        ->where('batches.pharmacy_id', $ordersPharmacyId)
                        ->when($request->filled('start_date'), fn($q) => $q->whereDate('items_log.date', '>=', $request->start_date))
                        ->when($request->filled('end_date'), fn($q) => $q->whereDate('items_log.date', '<=', $request->end_date)),

                    // Qty Jual: Sahabat PMI jika ada akses gudang, atau Penjualan cabang
                    'qty_sales' => ItemsLog::select(DB::raw('COALESCE(SUM(CAST(items_log.qty AS UNSIGNED)), 0)'))
                        ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                        ->whereColumn('items_log.medicine_id', 'medicines.id')
                        ->where('items_log.status', 1)
                        ->where('batches.pharmacy_id', $salesPharmacyId)
                        ->when($request->filled('start_date'), fn($q) => $q->whereDate('items_log.date', '>=', $request->start_date))
                        ->when($request->filled('end_date'), fn($q) => $q->whereDate('items_log.date', '<=', $request->end_date)),

                    // Qty Retur Beli cabang
                    'qty_orders_rt' => ItemsLog::select(DB::raw('COALESCE(SUM(CAST(items_log.qty AS UNSIGNED)), 0)'))
                        ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                        ->whereColumn('items_log.medicine_id', 'medicines.id')
                        ->where('items_log.status', 4)
                        ->where('batches.pharmacy_id', $ordersPharmacyId)
                        ->when($request->filled('start_date'), fn($q) => $q->whereDate('items_log.date', '>=', $request->start_date))
                        ->when($request->filled('end_date'), fn($q) => $q->whereDate('items_log.date', '<=', $request->end_date)),

                    // Qty Retur Jual cabang
                    'qty_sales_rt' => ItemsLog::select(DB::raw('COALESCE(SUM(CAST(items_log.qty AS UNSIGNED)), 0)'))
                        ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                        ->whereColumn('items_log.medicine_id', 'medicines.id')
                        ->where('items_log.status', 3)
                        ->where('batches.pharmacy_id', $salesPharmacyId)
                        ->when($request->filled('start_date'), fn($q) => $q->whereDate('items_log.date', '>=', $request->start_date))
                        ->when($request->filled('end_date'), fn($q) => $q->whereDate('items_log.date', '<=', $request->end_date)),

                    // Qty Awal untuk Gudang PMI (Opname/Log awal)
                    'qty_start' => $canSeeWarehouse
                        ? ItemsLog::select(DB::raw("CASE WHEN items_log.type = 'SO' THEN items_log.qty_after ELSE items_log.qty_before END"))
                            ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                            ->whereColumn('items_log.medicine_id', 'medicines.id')
                            ->whereIn('batches.pharmacy_id', $startPharmacyIds)
                            ->when($request->filled('start_date'), fn($q) => $q->whereDate('items_log.date', '>=', $request->start_date))
                            ->when($request->filled('end_date'), fn($q) => $q->whereDate('items_log.date', '<=', $request->end_date))
                            ->orderBy('items_log.date', 'asc')
                            ->orderBy('items_log.id', 'asc')
                            ->limit(1)
                        : DB::raw('0'),

                    // Stok Gudang (hanya jika cabang memiliki/mengakses Gudang PMI)
                    'qty_storage' => $canSeeWarehouse
                        ? Batches::select(DB::raw('COALESCE(SUM(stock), 0)'))
                            ->whereColumn('medicine_id', 'medicines.id')
                            ->where('pharmacy_id', $warehouseId)
                        : DB::raw('0'),

                    // Stok Pelayanan / Etalase (Cabang atau Sahabat PMI)
                    'qty_counter' => MedicineTransferItems::select(DB::raw('COALESCE(SUM(medicine_transfer_items.qty), 0)'))
                        ->join('batches', 'batches.id', '=', 'medicine_transfer_items.batches_id')
                        ->whereColumn('batches.medicine_id', 'medicines.id')
                        ->where('batches.pharmacy_id', $counterPharmacyId)
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
                ->editColumn('qty_start', function ($m) use ($canSeeWarehouse) {
                    if ($canSeeWarehouse) {
                        return (int) ($m->qty_start ?? 0);
                    }
                    $counter = (int) ($m->qty_counter ?? 0);
                    $netIn = (int) ($m->qty_orders ?? 0) - (int) ($m->qty_orders_rt ?? 0);
                    $netOut = (int) ($m->qty_sales ?? 0) - (int) ($m->qty_sales_rt ?? 0);
                    return $counter - $netIn + $netOut;
                })
                ->editColumn('qty_orders', fn($m) => (int) ($m->qty_orders ?? 0))
                ->editColumn('qty_sales', fn($m) => (int) ($m->qty_sales ?? 0))
                ->editColumn('qty_storage', fn($m) => (int) ($m->qty_storage ?? 0))
                ->editColumn('qty_counter', fn($m) => (int) ($m->qty_counter ?? 0))
                ->addColumn('qty_now', function ($m) use ($canSeeWarehouse) {
                    $storage = $canSeeWarehouse ? (int) ($m->qty_storage ?? 0) : 0;
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
        return Excel::download(new StockDataExport($request), 'stock_data.xlsx');
    }

    public function exportStockData(Request $request)
    {
        $job = ExportJob::create([
            'type' => 'stock_data',
            'status' => 'pending',
            'progress' => 0,
        ]);

        $pharmacyId = getActivePharmacyId();
        dispatch(new ProcessStockDataExport($job->id, array_merge(
            $request->only(['start_date', 'end_date', 'medicine_id']),
            ['active_pharmacy_id' => $pharmacyId]
        )));

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

            $baseMedicineLogQuery = function () use ($activePharmacyId, $request) {
                $warehouseId = getWarehousePharmacyId();
                $canSeeWarehouse = canAccessWarehouseStock($activePharmacyId);

                $q = ItemsLog::whereHas('batches', function ($batch) use ($activePharmacyId, $warehouseId, $canSeeWarehouse) {
                    if (isWarehousePharmacy($activePharmacyId)) {
                        $batch->whereIn('pharmacy_id', [$warehouseId, 1]);
                    } else {
                        $batch->where(function ($sub) use ($activePharmacyId, $warehouseId, $canSeeWarehouse) {
                            $sub->where('pharmacy_id', $activePharmacyId);
                            if ($canSeeWarehouse) {
                                $sub->orWhere('pharmacy_id', $warehouseId)
                                    ->orWhere('pharmacy_id', 1);
                            }
                        });
                    }
                });

                if ($request->filled('searchMedicine')) {
                    $q->whereHas('medicines', function ($sub) use ($request) {
                        $sub->where('name', 'like', "%{$request->searchMedicine}%")
                            ->orWhere('code', 'like', "%{$request->searchMedicine}%");
                    });
                }

                return $q;
            };

            // Calculate Period Summaries (unfiltered by quick type chip)
            $periodQuery = $baseMedicineLogQuery();
            if ($request->filled('start_date')) {
                $periodQuery->whereDate('date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $periodQuery->whereDate('date', '<=', $request->end_date);
            }

            $total_sales = (clone $periodQuery)->where('status', 1)->sum('qty');
            $total_orders = (clone $periodQuery)->where('status', 2)->sum('qty');

            // Calculate Saldo Awal for period
            if ($request->filled('start_date')) {
                $lastLogBefore = $baseMedicineLogQuery()
                    ->whereDate('date', '<', $request->start_date)
                    ->orderBy('date', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($lastLogBefore) {
                    $qty_awal = (int) $lastLogBefore->qty_after;
                } else {
                    $firstLogInPeriod = (clone $periodQuery)->orderBy('date', 'asc')->orderBy('id', 'asc')->first();
                    $qty_awal = $firstLogInPeriod ? (int) $firstLogInPeriod->qty_before : 0;
                }
            } else {
                $firstLog = $baseMedicineLogQuery()->orderBy('date', 'asc')->orderBy('id', 'asc')->first();
                $qty_awal = $firstLog ? (int) $firstLog->qty_before : 0;
            }

            // Main items query for DataTable with eager loaded relationships
            $items = $baseMedicineLogQuery()->with(['medicines', 'batches', 'users']);

            if ($request->filled('start_date')) {
                $items->whereDate('date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $items->whereDate('date', '<=', $request->end_date);
            }

            // Quick type filter chips
            if ($request->filled('filter_type')) {
                $ft = strtolower($request->filter_type);
                if ($ft === 'so') {
                    $items->where(function ($q) {
                        $q->where('status', 5)->orWhere('type', 'SO');
                    });
                } elseif ($ft === 'sales') {
                    $items->where('status', 1);
                } elseif ($ft === 'purchase') {
                    $items->where('status', 2);
                } elseif ($ft === 'mutation') {
                    $items->where('status', 7);
                } elseif ($ft === 'retur') {
                    $items->whereIn('status', [3, 4]);
                }
            }

            $items->orderBy('date', 'desc')->orderBy('id', 'desc');

            return DataTables::eloquent($items)
                ->addIndexColumn()
                ->addColumn('date', function ($row) {
                    $d = $row->date ? \Carbon\Carbon::parse($row->date) : null;
                    if (!$d) return '<span class="text-slate-400 text-xs">—</span>';
                    $dateStr = $d->format('d/m/Y');
                    $timeStr = $d->format('H:i');
                    return '<div class="whitespace-nowrap leading-tight">
                        <span class="font-semibold text-slate-700 text-xs">' . $dateStr . '</span>' .
                        ($timeStr && $timeStr !== '00:00' ? '<span class="text-[10px] text-slate-400 block">' . $timeStr . '</span>' : '') .
                    '</div>';
                })
                ->addColumn('transaction_code', function ($row) {
                    if (!$row->transaction_code)
                        return '<span class="text-slate-400 text-xs">—</span>';
                    $code = e($row->transaction_code);
                    return '
                    <div class="inline-flex items-center gap-1.5 whitespace-nowrap">
                        <span class="text-[11px] font-mono font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">' . $code . '</span>
                        <button type="button" onclick="navigator.clipboard.writeText(\'' . $code . '\'); iziToast.success({title: \'Tersalin\', message: \'Kode ' . $code . ' berhasil disalin\', position: \'topRight\'})" class="p-1 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Salin kode">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        </button>
                    </div>';
                })
                ->addColumn('type_badge', function ($row) {
                    switch ((int) $row->status) {
                        case 1:
                            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200 whitespace-nowrap">Penjualan</span>';
                        case 2:
                            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200 whitespace-nowrap">Pembelian</span>';
                        case 3:
                            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 whitespace-nowrap">Retur Jual</span>';
                        case 4:
                            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-orange-50 text-orange-700 border border-orange-200 whitespace-nowrap">Retur Beli</span>';
                        case 5:
                            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200 whitespace-nowrap">Stock Opname</span>';
                        case 6:
                            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-50 text-sky-700 border border-sky-200 whitespace-nowrap">Penyesuaian</span>';
                        case 7:
                            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200 whitespace-nowrap">Mutasi Stok</span>';
                        default:
                            $lbl = e($row->type ?: 'Lainnya');
                            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 whitespace-nowrap">' . $lbl . '</span>';
                    }
                })
                ->addColumn('batch_info', function ($row) {
                    $batch = $row->batches;
                    if (!$batch) return '<span class="text-slate-400 text-xs">—</span>';
                    $batchName = e($batch->batch_1 ?: '-');
                    $ed = $batch->expired_date ? \Carbon\Carbon::parse($batch->expired_date)->format('d/m/Y') : '-';
                    return '<div class="flex flex-col whitespace-nowrap leading-tight">
                        <span class="font-bold text-slate-700 text-xs">' . $batchName . '</span>
                        <span class="text-[10px] text-slate-400">ED: ' . $ed . '</span>
                    </div>';
                })
                ->addColumn('qty_before', function ($row) {
                    return '<span class="font-semibold text-slate-600 text-xs">' . number_format($row->qty_before) . '</span>';
                })
                ->addColumn('stock', function ($row) {
                    $status = (int) $row->status;
                    $delta = 0;
                    if ($status === 1) {
                        // Penjualan -> outflow
                        $delta = - abs($row->qty);
                    } elseif ($status === 2) {
                        // Pembelian -> inflow
                        $delta = abs($row->qty);
                    } elseif ($status === 3) {
                        // Retur Jual -> inflow
                        $delta = abs($row->qty);
                    } elseif ($status === 4) {
                        // Retur Beli -> outflow
                        $delta = - abs($row->qty);
                    } elseif ($status === 5) {
                        // Stock Opname -> difference between after and before
                        $delta = $row->qty_after - $row->qty_before;
                        if ($delta == 0 && $row->total != 0) {
                            $delta = (int) $row->total;
                        }
                    } else {
                        $delta = $row->qty_after - $row->qty_before;
                    }

                    if ($delta > 0) {
                        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 whitespace-nowrap">+ ' . number_format($delta) . '</span>';
                    } elseif ($delta < 0) {
                        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold text-rose-700 bg-rose-50 border border-rose-200 whitespace-nowrap">- ' . number_format(abs($delta)) . '</span>';
                    } else {
                        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium text-slate-500 bg-slate-50 border border-slate-200 whitespace-nowrap">0</span>';
                    }
                })
                ->addColumn('qty_after', function ($row) {
                    return '<span class="font-bold text-slate-800 text-xs">' . number_format($row->qty_after) . '</span>';
                })
                ->addColumn('user_name', function ($row) {
                    $name = $row->users?->name;
                    if (!$name) return '<span class="text-slate-400 text-xs">—</span>';
                    $safeName = e($name);
                    return '<span class="text-xs font-medium text-slate-600 truncate max-w-[110px] block" title="' . $safeName . '">' . $safeName . '</span>';
                })
                // Legacy aliases for backward compatibility
                ->addColumn('code', fn($row) => $row->code)
                ->addColumn('type', fn($row) => $row->type)
                ->addColumn('name', fn($row) => $row->medicines?->name ?? '-')
                ->addColumn('supply', function ($row) {
                    static $stockCache = [];
                    $medId = $row->medicine_id;
                    if (!$medId) return '-';
                    if (!isset($stockCache[$medId])) {
                        $stockCache[$medId] = $this->calculateRealtimeStock($medId, getActivePharmacyId(), 'total');
                    }
                    return $stockCache[$medId];
                })
                ->addColumn('status', fn($row) => $row->status)
                ->rawColumns(['date', 'transaction_code', 'type_badge', 'batch_info', 'qty_before', 'stock', 'qty_after', 'user_name'])
                ->with([
                    'qty_awal' => (int) $qty_awal,
                    'qty_beli' => (int) $total_orders,
                    'qty_jual' => (int) $total_sales,
                ])
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

    public function downloadStockOpnameTemplate(Request $request)
    {
        $pharmacyId = getActivePharmacyId();
        $mode = $request->input('mode', 'pelayanan');
        $includeMedicines = $request->boolean('include_medicines', true);
        $filename = 'Format_Stock_Opname_' . ($mode === 'gudang' ? 'Gudang' : 'Pelayanan') . '_' . date('Ymd_His') . '.xlsx';
        return Excel::download(new StockOpnameTemplateExport($pharmacyId, $mode, $includeMedicines), $filename);
    }

    public function analyzeStockOpnameImport(Request $request, StockOpnameImportService $importService)
    {
        $request->validate([
            'file'        => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'target_mode' => 'nullable|in:pelayanan,gudang',
        ]);

        $pharmacyId = getActivePharmacyId();
        $canSeeWarehouse = canAccessWarehouseStock($pharmacyId);
        $targetMode = $request->input('target_mode', 'pelayanan');
        if (!$canSeeWarehouse && $targetMode === 'gudang') {
            $targetMode = 'pelayanan';
        }

        $file = $request->file('file');
        $filePath = $file->getRealPath();

        try {
            $result = $importService->analyze($filePath, $pharmacyId, $targetMode);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca file Excel: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function executeStockOpnameImport(Request $request, StockOpnameImportService $importService)
    {
        $request->validate([
            'token'       => 'required|string',
            'target_mode' => 'nullable|in:pelayanan,gudang',
            'is_async'    => 'nullable|boolean',
        ]);

        $pharmacyId = getActivePharmacyId();
        $canSeeWarehouse = canAccessWarehouseStock($pharmacyId);
        $targetMode = $request->input('target_mode', 'pelayanan');
        if (!$canSeeWarehouse && $targetMode === 'gudang') {
            $targetMode = 'pelayanan';
        }

        $token = $request->input('token');
        $isAsync = $request->boolean('is_async', false);
        $userId = auth()->id();

        if ($isAsync) {
            $job = ExportJob::create([
                'type'      => 'import_stock_opname',
                'status'    => ExportJob::STATUS_PENDING,
                'progress'  => 0,
                'file_path' => null,
            ]);

            ProcessStockOpnameImport::dispatch($job->id, $token, $pharmacyId, $targetMode, $userId);

            return response()->json([
                'success'   => true,
                'is_async'  => true,
                'job_id'    => $job->id,
                'message'   => 'Impor sedang diproses di latar belakang.',
            ]);
        }

        try {
            $result = $importService->execute($token, $pharmacyId, $targetMode, $userId);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses impor: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function stockOpnameImportStatus($id)
    {
        $job = ExportJob::findOrFail($id);
        return response()->json([
            'id'        => $job->id,
            'status'    => $job->status,
            'progress'  => (int) $job->progress,
            'finished'  => $job->isFinished(),
            'failed'    => $job->status === ExportJob::STATUS_FAILED,
        ]);
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

        // target_mode: 'pelayanan' (default) or 'gudang'
        $targetMode = $request->input('target_mode', 'pelayanan');
        if (!$canSeeWarehouse && $targetMode === 'gudang') {
            $targetMode = 'pelayanan';
        }

        $rules = [
            'medicine_id'          => 'required|exists:medicines,id',
            'batches_id'           => 'nullable|exists:batches,id',
            'custom_batch_name'    => 'nullable|string|max:255',
            'custom_expired_date'  => 'nullable|string',
            'etalases_id'          => 'nullable|exists:etalases,id',
            'target_mode'          => 'nullable|in:pelayanan,gudang',
        ];

        if ($targetMode === 'gudang') {
            $rules['stock_physic'] = 'required|integer|min:0';
        } else {
            $rules['counter_stock_physic'] = 'required|integer|min:0';
        }

        $request->validate($rules);

        DB::beginTransaction();

        try {
            // 1. Resolve target batch
            if ($request->filled('custom_batch_name')) {
                $customName = trim($request->custom_batch_name);
                $customEd = $request->filled('custom_expired_date')
                    ? Carbon::parse(str_replace('/', '-', $request->custom_expired_date))->toDateString()
                    : now()->addYears(2)->toDateString();

                $batchTargetPharmacyId = ($targetMode === 'gudang') ? $warehouseId : $counterPharmacyId;

                $batch = Batches::lockForUpdate()->firstOrCreate([
                    'medicine_id' => $request->medicine_id,
                    'pharmacy_id' => $batchTargetPharmacyId,
                    'name'        => $customName,
                ], [
                    'expired_date' => $customEd,
                    'stock'        => 0,
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
                    ->where(function ($q) use ($pharmacyId, $warehouseId, $counterPharmacyId, $targetMode) {
                        if ($targetMode === 'gudang') {
                            $q->where('pharmacy_id', $warehouseId);
                        } else {
                            $q->where('pharmacy_id', $counterPharmacyId);
                        }
                    })
                    ->orderBy('expired_date', 'asc')
                    ->first();

                if (!$batch) {
                    $batchTargetPharmacyId = ($targetMode === 'gudang') ? $warehouseId : $counterPharmacyId;
                    $batch = Batches::create([
                        'medicine_id'  => $request->medicine_id,
                        'pharmacy_id'  => $batchTargetPharmacyId,
                        'name'         => 'OPN-' . date('Ymd'),
                        'expired_date' => now()->addYears(2)->toDateString(),
                        'stock'        => 0,
                    ]);
                }
            }

            // 2. Resolve etalases_id for Pelayanan
            $etalasesId = $request->etalases_id;
            if ($targetMode === 'pelayanan' && empty($etalasesId)) {
                $defaultEtalase = Etalases::where('pharmacy_id', $counterPharmacyId)->first();
                $etalasesId = $defaultEtalase ? $defaultEtalase->id : 99;
            }

            // 3. Process according to target_mode
            if ($targetMode === 'gudang') {
                // Resolusi batch khusus Gudang PMI (pharmacy_id = 9)
                $storageBatch = null;
                if ($batch->pharmacy_id == $warehouseId) {
                    $storageBatch = $batch;
                } else {
                    $storageBatch = Batches::firstOrCreate([
                        'medicine_id' => $request->medicine_id,
                        'pharmacy_id' => $warehouseId,
                        'name'        => $batch->name,
                    ], [
                        'expired_date' => $batch->expired_date ?? now()->addYears(2)->toDateString(),
                        'stock'        => 0,
                    ]);
                }

                $storageBefore = (int) $storageBatch->stock;
                $storagePhysic = (int) $request->stock_physic;
                $discrepancy   = $storagePhysic - $storageBefore;
                $status        = $discrepancy >= 0 ? 5 : 6;

                // Update gudang batch stock
                $storageBatch->stock = $storagePhysic;
                $storageBatch->save();

                // Update master medicine stock
                $medicine = Medicines::find($request->medicine_id);
                if ($medicine) {
                    $totalRealStock = $this->calculateRealtimeStock($medicine->id, $pharmacyId, 'total');
                    $medicine->update(['stock' => $totalRealStock]);
                }

                // Log Opname
                StockOpname::create([
                    'users_id'          => auth()->id(),
                    'batches_id'        => $storageBatch->id,
                    'stock_physical'    => $storagePhysic,
                    'stock_discrepancy' => $discrepancy,
                    'stock_total'       => $storagePhysic,
                    'date'              => now()->toDateString(),
                    'status'            => $status,
                ]);

                ItemsLog::create([
                    'batches_id'       => $storageBatch->id,
                    'transaction_code' => $this->generateOpnameCode(),
                    'code'             => $this->generateItemsLogCode(),
                    'type'             => "SO",
                    'medicine_id'      => $request->medicine_id,
                    'qty'              => abs($discrepancy),
                    'qty_before'       => $storageBefore,
                    'qty_after'        => $storagePhysic,
                    'total'            => $discrepancy,
                    'date'             => now()->toDateTimeString(),
                    'status'           => $status,
                    'user_id'          => auth()->id(),
                ]);

                DB::commit();

                return response()->json([
                    'message'     => 'Stock opname Gudang berhasil disimpan.',
                    'target_mode' => 'gudang',
                    'batch'       => $storageBatch->fresh(),
                    'qty_before'  => $storageBefore,
                    'qty_after'   => $storagePhysic,
                    'discrepancy' => $discrepancy,
                    'status'      => $status,
                ]);
            } else {
                // Mode: Pelayanan
                $counterBatch = null;
                if ($batch->pharmacy_id == $counterPharmacyId) {
                    $counterBatch = $batch;
                } else {
                    $counterBatch = Batches::firstOrCreate([
                        'medicine_id' => $request->medicine_id,
                        'pharmacy_id' => $counterPharmacyId,
                        'name'        => $batch->name,
                    ], [
                        'expired_date' => $batch->expired_date ?? now()->addYears(2)->toDateString(),
                        'stock'        => 0,
                    ]);
                }

                $transfers = MedicineTransferItems::where('batches_id', $counterBatch->id)
                    ->where('status', 1)
                    ->where(function ($q) {
                        $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                    })
                    ->get();

                $counterBefore = (int) $transfers->sum('qty');
                $counterPhysic = (int) $request->counter_stock_physic;
                $discrepancy   = $counterPhysic - $counterBefore;
                $status        = $discrepancy >= 0 ? 5 : 6;

                if ($transfers->isNotEmpty()) {
                    $primary = $transfers->first();
                    $primary->qty = $counterPhysic;
                    if ($etalasesId) {
                        $primary->etalases_id = $etalasesId;
                    }
                    $primary->save();

                    // Set secondary records to 0
                    foreach ($transfers->slice(1) as $secondary) {
                        if ($secondary->qty != 0) {
                            $secondary->qty = 0;
                            $secondary->save();
                        }
                    }
                } else {
                    $transferHeader = MedicineTransfers::create([
                        'code'    => $this->generateTransfersCode(),
                        'status'  => 1,
                        'user_id' => auth()->id(),
                    ]);

                    MedicineTransferItems::create([
                        'medicine_transfer_id' => $transferHeader->id,
                        'batches_id'           => $counterBatch->id,
                        'source_batches_id'    => $counterBatch->id,
                        'qty'                  => $counterPhysic,
                        'status'               => 1,
                        'source_type'          => 'pelayanan',
                        'etalases_id'          => $etalasesId,
                    ]);
                }

                // Update master medicine stock
                $medicine = Medicines::find($request->medicine_id);
                if ($medicine) {
                    $totalRealStock = $this->calculateRealtimeStock($medicine->id, $pharmacyId, 'total');
                    $medicine->update(['stock' => $totalRealStock]);
                }

                // Log Opname
                StockOpname::create([
                    'users_id'          => auth()->id(),
                    'batches_id'        => $counterBatch->id,
                    'stock_physical'    => $counterPhysic,
                    'stock_discrepancy' => $discrepancy,
                    'stock_total'       => $counterPhysic,
                    'date'              => now()->toDateString(),
                    'status'            => $status,
                ]);

                ItemsLog::create([
                    'batches_id'       => $counterBatch->id,
                    'transaction_code' => $this->generateOpnameCode(),
                    'code'             => $this->generateItemsLogCode(),
                    'type'             => "SO",
                    'medicine_id'      => $request->medicine_id,
                    'qty'              => abs($discrepancy),
                    'qty_before'       => $counterBefore,
                    'qty_after'        => $counterPhysic,
                    'total'            => $discrepancy,
                    'date'             => now()->toDateTimeString(),
                    'status'           => $status,
                    'user_id'          => auth()->id(),
                ]);

                DB::commit();

                return response()->json([
                    'message'     => 'Stock opname Pelayanan berhasil disimpan.',
                    'target_mode' => 'pelayanan',
                    'batch'       => $counterBatch->fresh(),
                    'qty_before'  => $counterBefore,
                    'qty_after'   => $counterPhysic,
                    'discrepancy' => $discrepancy,
                    'status'      => $status,
                ]);
            }
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
