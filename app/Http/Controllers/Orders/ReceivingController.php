<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Batches;
use App\Models\Creditor;
use App\Models\ItemsLog;
use App\Models\MedicinePriceHistory;
use App\Models\Medicines;
use App\Models\MedicineTransfers;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Receiving;
use App\Models\ReceivingDetails;
use App\Models\ReceivingItems;
use App\Models\Transfers;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DataTables;
use Form;

class ReceivingController extends Controller
{

    public function createReceiving(Request $request)
    {
        $now = Carbon::now()->format('d/m/Y');

        $transaction = Receiving::where('pharmacy_id', auth()->user()->pharmacy_id)
            ->where('status', 0)
            ->first();

        if ($transaction) {
            $receiving_id = $transaction->id;
            $order_code   = $transaction->code;

            /*
         * Check if this in-progress receiving already has items
         * linked to a purchase order, traversing:
         * receiving → receiving_details → receiving_items → order_items → orders
         */
            $order_exist = Order::whereHas('order_items.receivingItems.receiving_details.receiving', function ($q) use ($transaction) {
                $q->where('id', $transaction->id);
            })
                ->where('status', '!=', 2) // not yet completed order
                ->first();

            return view('orders.receiving', compact('order_code', 'transaction', 'now', 'order_exist', 'receiving_id'));
        } else {
            $year   = now()->format('y');
            $month  = now()->format('m');
            $prefix = $year . $month . 'RE';

            $last = Receiving::where('pharmacy_id', auth()->user()->pharmacy_id)
                ->where('code', 'like', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            $nextNumber = $last ? intval(substr($last->code, -4)) + 1 : 0;
            $serial     = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            try {
                DB::beginTransaction();

                $transaction = Receiving::create([
                    'pharmacy_id' => auth()->user()->pharmacy_id,
                    'code'        => $prefix . $serial,
                    'date'        => $now,
                    'status'      => 0,
                ]);

                DB::commit();
                return redirect()->route('receiving.create');
            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()->back()->with('message', 'Gagal Menyimpan! ' . $e->getMessage());
            }
        }
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

    public function searchBPBA(Request $request)
    {
        $search = $request->search;

        $data = Order::with([
            'order_items.medicines.factory'
        ])
            ->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
                $q->where('status', '!=', 2);
            })
            ->paginate(10);

        $data->getCollection()->transform(function ($order) {
            return [
                'id'   => $order->id,
                'code' => $order->code,
                'items' => $order->order_items,
            ];
        });

        return response()->json($data);
    }

    public function searchReceiving(Request $request)
    {
        $search = $request->search;

        $order = Order::with('receiving')->where('code', $search)->first();
        if (!$order) {
            return response()->json(null);
        }

        return response()->json($order->receiving);
    }

    public function getOrderItems(Request $request)
    {
        $ordersid = $request->order_id;
        $creditorCode = $request->creditor_code;

        \Illuminate\Support\Facades\Log::info('ORDER CODE', [$request->order_id]);

        if (!$ordersid || !$creditorCode) {
            return DataTables::of(collect())->make(true);
        }

        $items = OrderItems::query()
            ->with(['medicines', 'receiving_items', 'receiving_items.locations', 'receiving_items.etalases', 'receiving_items.receiving_details'])
            ->withSum('receivingItems as qty_received', 'qty_received')
            ->whereHas('orders', function ($q) use ($ordersid) {
                $q->where('id', $ordersid);
            });

        if ($creditorCode) {
            $items->where('creditor_code', $creditorCode);
        }

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('qty_received', function ($row) {
                return $row->qty_received ?? 0;
            })
            ->addColumn('qty_remaining', function ($row) {
                return max(0, $row->quantity - ($row->qty_received ?? 0));
            })
            ->addColumn('price', fn($row) => 'Rp ' . number_format($row->price, 0, ',', '.'))
            ->addColumn('price_ppn', fn($row) => 'Rp ' . number_format(floor($row->price * 1.11), 0, ',', '.'))
            ->addColumn('total', fn($row) => 'Rp ' . number_format($row->total, 0, ',', '.'))
            ->make(true);
    }

    public function searchReceivingDetails(Request $request)
    {

        $query = ReceivingDetails::where('id', $request->detail_id)->first();
        $creditor_code = $query->creditor_code;

        // $query = ReceivingDetails::whereHas('receiving_items.order_items', function ($query) use ($orderid) {
        //     $query->where('order_id', $orderid);
        // })->where('creditor_code', $creditor_code)->first();
        $creditor = Creditor::where('code', $creditor_code)->first();
        return response()->json([
            'query' => $query,
            'creditor' => $creditor
        ]);
    }

    public function selectCreditors(Request $request)
    {
        $orderid = $request->orderid;
        $creditor_code = $request->creditor_code;

        $query = ReceivingDetails::whereHas('receiving_items.order_items', function ($query) use ($orderid) {
            $query->where('order_id', $orderid);
        })->where('creditor_code', $creditor_code)->first();
        $creditor = Creditor::where('code', $creditor_code)->first();
        return response()->json([
            'query' => $query,
            'creditor' => $creditor
        ]);
    }

    public function history(Request $request)
    {
        return view('orders.history');
    }

    public function orderhistory(Request $request)
    {
        return view('orders.orderhistory');
    }
    public function printSPBFinal($orderId)
    {
        $date = Carbon::now()->translatedFormat('d F Y');
        $order = Order::with(['order_items.receiving_items', 'order_items.medicines', 'order_items.creditors', 'order_items.medicines.factory', 'order_items.medicines.category'])
            ->findOrFail($orderId);
        $grouped = $order->order_items->groupBy(function ($item) {
            return $item->medicines->type ?? "Kosong";
        })->map(function ($perCreditor) {
            return $perCreditor->groupBy('creditor_code') ?? "Kosong";
        });

        $pdf = Pdf::loadView('orders.printSPBFinal', compact('order', 'date', 'grouped'))
            ->setPaper('A4', 'portrait');

        return $pdf->stream("SPBFINAL-{$order->code}.pdf");
    }
    public function printOrders($orderId)
    {
        $order = Order::findOrFail($orderId);

        $receivingItem = ReceivingItems::whereHas('order_items', function ($q) use ($orderId) {
            $q->where('order_id', $orderId);
        })
            ->with('receiving_details.receiving')
            ->first();

        abort_if(!$receivingItem, 404, 'Data receiving tidak ditemukan untuk order ini.');

        $receivingId = $receivingItem->receiving_details->receiving_id;

        $receiving = Receiving::with([
            'pharmacy',
            'receiving_details' => function ($q) use ($orderId) {
                $q->whereHas('receiving_items.order_items', function ($sub) use ($orderId) {
                    $sub->where('order_id', $orderId);
                });
            },
            'receiving_details.creditor',
            'receiving_details.receiving_items' => function ($q) use ($orderId) {
                $q->whereHas('order_items', function ($sub) use ($orderId) {
                    $sub->where('order_id', $orderId);
                });
            },
            'receiving_details.receiving_items.order_items.medicines',
        ])
            ->findOrFail($receivingId);

        return \PDF::loadView('orders.printOrders', compact('receiving'))
            ->setPaper('a4', 'landscape')
            ->stream('tanda-penerimaan-barang-' . $receiving->code . '.pdf');
    }
    public function gethistory(Request $request)
    {
        $query = MedicinePriceHistory::with(['medicines', 'user'])
            ->select('medicine_price_history.*');

        if ($request->filled('search_medicine')) {
            $kw = $request->search_medicine;
            $query->whereHas('medicines', function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                    ->orWhere('code', 'like', "%{$kw}%");
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $query->orderByDesc('created_at');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('medicine_code', fn($row) => $row->medicines?->code ?? '-')
            ->addColumn('medicine_name', fn($row) => $row->medicines?->name ?? '-')
            ->addColumn('medicine_unit', fn($row) => $row->medicines?->unit ?? '-')
            ->addColumn('new_price_fmt', function ($row) {
                return 'Rp ' . number_format($row->new_price, 0, ',', '.');
            })
            ->addColumn('changed_by', fn($row) => $row->user?->name ?? '-')
            ->addColumn('changed_at', fn($row) => $row->created_at?->format('d/m/Y H:i') ?? '-')
            ->addColumn('direction', function ($row) {
                $current = $row->medicines?->net_price ?? 0;
                $new     = $row->new_price;

                if ($new > $current) {
                    return '<span class="badge-up">▲ Naik</span>';
                } elseif ($new < $current) {
                    return '<span class="badge-down">▼ Turun</span>';
                }
                return '<span class="badge-same">— Sama</span>';
            })
            ->rawColumns(['direction'])
            ->make(true);
    }

    public function getorderhistory(Request $request)
    {
        $items = ReceivingDetails::with([
            'receiving',
            'creditor',
            'receiving_items.order_items',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $items->where(function ($query) use ($search) {
                $query->where('invoice_date', $search)
                    ->orWhere('invoice_number', $search)
                    ->orWhereHas('creditor', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%");
                    });
            });
        }

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('date', function ($row) {
                return Carbon::parse($row->created_at)->format('d/m/Y');
            })
            ->addColumn('invoice_payment', function ($row) {
                return $row->invoice_payment;
            })
            ->addColumn('invoice_date', function ($row) {
                return Carbon::parse($row->invoice_date)->format('d/m/Y');
            })
            ->addColumn('invoice_number', function ($row) {
                return $row->invoice_number ?? "-";
            })
            ->addColumn('creditor', function ($row) {
                return $row->creditor->name;
            })
            ->addColumn('action', function ($row) {
                return ' <a target="_blank" href="../invoice/print/' . $row->id . '">
                            <div class="flex gap-1">
                                <div class="w-full">
                                    <button style="background-color:#eab308;color:white;" class="rounded-full px-2 py-2 font-semibold">
                                        <div class="flex gap-2 justify-center items-center">
                                            <span>
                                            <svg 
                                            xmlns="http://www.w3.org/2000/svg" 
                                            viewBox="0 0 24 24" 
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            class="w-6 h-6 text-[#fff] hover:text-blue-600 transition cursor-pointer"
                                        >
                                            <path d="M6 9V3H18V9" />
                                            <rect x="6" y="14" width="12" height="7" rx="1" />
                                            <path d="M6 18H5A2 2 0 0 1 3 16V11A2 2 0 0 1 5 9H19A2 2 0 0 1 21 11V16A2 2 0 0 1 19 18H18" />
                                        </svg>
                                            </span>
                                            <span class="text-xs pr-2">Cetak</span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </a>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function orderList(Request $request)
    {
        $items = Order::query()
            ->where('pharmacy_id', auth()->user()->pharmacy_id)
            ->with(['order_items'])
            ->withSum('order_items', 'total')
            ->orderByDesc('id');

        if ($request->filled('order_code')) {
            $items->where('code', 'like', '%' . $request->order_code . '%');
        }
        if ($request->filled('start_date')) {
            $items->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $items->whereDate('created_at', '<=', $request->end_date);
        }

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('date', fn($row) => $row->date ? date('d M Y', strtotime($row->updated_at)) : '-')
            ->addColumn('code', function ($row) {
                $code = e($row->code ?? '0');
                return '<span class="inline-flex items-center px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 font-mono text-xs font-semibold tracking-wide border border-slate-200">' . $code . '</span>';
            })
            ->addColumn('status_order', function ($row) {
                if ($row->status == 2) {
                    // DITERIMA
                    return '<div style="text-align:center;">
                        <span style="display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:9999px; font-size:11px; font-weight:700; color:#047857; background-color:#ecfdf5; border:1px solid #a7f3d0; box-shadow:0 0 10px rgb(16 185 129 / 17%);">
                            <span style="position:relative; display:flex; width:8px; height:8px;">
                                <span style="position:absolute; width:100%; height:100%; border-radius:50%; background-color:#10b981; box-shadow:0 0 6px #10b981;"></span>
                            </span>
                            DITERIMA
                        </span>
                    </div>';
                } elseif ($row->status == 1) {
                    // DIPESAN
                    return '<div style="text-align:center;">
                        <span style="display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:9999px; font-size:11px; font-weight:700; color:#b45309; background-color:#fffbeb; border:1px solid #fde68a; box-shadow:0 0 10px rgb(245 158 11 / 18%);">
                            <span style="position:relative; display:flex; width:8px; height:8px;">
                                <span style="position:absolute; width:100%; height:100%; border-radius:50%; background-color:#f59e0b; box-shadow:0 0 6px #f59e0b;"></span>
                            </span>
                            DIPESAN
                        </span>
                    </div>';
                } else {
                    // PENDING
                    return '<div style="text-align:center;">
                        <span style="display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:9999px; font-size:11px; font-weight:700; color:#be123c; background-color:#fff1f2; border:1px solid #fecdd3; box-shadow:0 0 10px rgb(244 63 94 / 15%);">
                            <span style="position:relative; display:flex; width:8px; height:8px;">
                                <span style="position:absolute; width:100%; height:100%; border-radius:50%; background-color:#f43f5e; box-shadow:0 0 6px #f43f5e;"></span>
                            </span>
                            PENDING
                        </span>
                    </div>';
                }
            })
            ->addColumn('action', function ($row) {
                if ($row->status == 0) {
                    return '
                    <a href="/createorder" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm hover:shadow transition-all duration-150">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                        <span>Lanjutkan</span>
                    </a>';
                }

                if ($row->status == 1) {
                    return '
                    <a href="/receive/' . $row->id . '" style="background: #e6ffe8; border: solid 1px #00bd5a; color: #078f03; box-shadow: 0 0 10px #2d8c056b;" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm hover:shadow transition-all duration-150">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Terima</span>
                    </a>';
                }

                return '
                <div class="flex items-center gap-2">
                    <a target="_blank" href="/receiving/' . $row->id . '/printspbfinal" style="box-shadow:0 0 10px rgb(255 236 159 / 47%);" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-lg shadow-xs transition-all duration-150">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656"/></svg>
                        <span>Cetak SPB</span>
                    </a>
                    <a target="_blank" href="/receiving/' . $row->id . '/printorders" style="box-shadow:0 0 10px rgb(245 11 11 / 18%);" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-pink-700 bg-pink-50 hover:bg-pink-100 border border-pink-200 rounded-lg shadow-xs transition-all duration-150">
                        <svg class="w-4 h-4 text-pink-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.25-2.142V8.25"/></svg>
                        <span>List Pesanan</span>
                    </a>
                </div>';
            })
            ->addColumn('total', fn($row) => '<span class="font-semibold text-slate-700">Rp ' . number_format($row->order_items_sum_total ?? 0, 0, ',', '.') . '</span>')
            ->addColumn('total_ppn', fn($row) => '<span class="font-bold text-slate-900">Rp ' . number_format(floor(($row->order_items_sum_total ?? 0) * 1.11), 0, ',', '.') . '</span>')
            ->rawColumns(['code', 'status_order', 'action', 'total', 'total_ppn'])
            ->make(true);
    }

    function generateReceivingCode()
    {
        $now = Carbon::now();

        $year  = $now->format('y');
        $month = $now->format('m');
        $prefix = "{$year}{$month}OI";

        $lastCode = Receiving::where('code', 'like', "{$prefix}%")
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

    public function index()
    {
        $now = Carbon::now()->format('d/m/Y');
        $receiving_code = $this->generateReceivingCode();
        return view('orders.index', compact('receiving_code', 'now'));
    }

    public function receive($id)
    {
        $now = Carbon::now()->format('d/m/Y');
        $datenow = Carbon::now()->format('Y-m-d');

        // FIX #1: Added order_id filter so we only get the receiving for THIS order
        $transaction = Receiving::where('status', 0)->where(
            'pharmacy_id',
            auth()->user()->pharmacy_id
        )->first();

        // FIX #2: Guard against null $check_order
        $check_order = OrderItems::with('orders')->whereHas('orders', function ($q) use ($id) {
            $q->where('id', $id);
        })->first();

        if (!$check_order || !$check_order->orders) {
            abort(404, 'Order not found.');
        }

        if ($check_order->orders->status == 2) {
            return redirect()->route('receiving.index')->with('success', "Pesanan Berhasil Diterima");
        }

        $creditorOption = OrderItems::where('order_id', $id)
            ->select('creditor_code')
            ->distinct()
            ->with('creditors:id,code,name')
            ->get()
            ->pluck('creditors')
            ->unique('code')
            ->values();

        if ($transaction) {
            $getOrder = Order::findOrFail($id);

            $receiving_id = $getOrder->receiving_id;
            $receiving_code = $transaction->code;
            $order_id = $getOrder->id;
            $order_code = $getOrder->code;
            $getOrderItems = OrderItems::where('order_id', $order_id)->first();
            $getOrderId = $getOrderItems->id;
            $d_price = ReceivingItems::with('order_items')
                ->whereHas('order_items', function ($q) use ($getOrderId) {
                    $q->where('id', $getOrderId);
                })
                ->sum('total') ?? '0';
            $d_ppn = $d_price * 0.11 ?? '0';
            $d_total = $d_price + $d_ppn ?? '0';

            return view('orders.receiving', compact('order_id', 'd_price', 'd_ppn', 'd_total', 'order_code', 'creditorOption', 'receiving_code', 'transaction', 'now', 'datenow', 'receiving_id'));
        } else {
            $year   = now()->format('y');
            $month  = now()->format('m');
            $prefix = $year . $month . 'RE';
            $last = Receiving::where('pharmacy_id', Auth()->user()->pharmacy_id)
                ->where('code', 'like', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            if ($last) {
                $lastNumber = intval(substr($last->code, -4));
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 0;
            }

            $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $receiving_code = $prefix . $serial;

            try {
                DB::beginTransaction();

                $transaction = Receiving::create([
                    'order_id'     => $id,
                    'creditors_id' => NULL,
                    'pharmacy_id'  => Auth()->user()->pharmacy_id,
                    'code'         => $receiving_code,
                    'date'         => $now,
                    'status'       => 0,
                ]);

                DB::commit();
                return redirect()->route('receiving.receive', $id);
            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()->back()->with('message', "Gagal Menyimpan! " . $e->getMessage());
            }
        }
    }

    public function addReceivingItem(Request $request)
    {
        $request->validate([
            'receiving_id'     => 'required',
            'order_items_id'   => 'required',
            'qty_received'     => 'required|numeric|min:1',
            'discount'         => 'required',
            'extra_discount'   => 'required',
            'expired_date'     => 'required',
            'batch'            => 'required',
            'status'           => 'required',
            'invoice_date'     => 'required',
            'invoice_due'      => 'required',
            'invoice_number'   => 'required',
            'invoice_payment'  => 'required',
            'invoice_ppn'      => 'required',
            'invoice_times'    => 'required',
        ]);

        DB::beginTransaction();

        try {

            // $details = ReceivingDetails::where('receiving_id', $request->receiving_id)->findOrFail();
            // if($details == )


            // Check the creditor. If the combination of
            // creditor and receiving_id is already exist, update the data.
            // If not, create new data.
            $details = ReceivingDetails::updateOrCreate(

                [
                    'receiving_id'    => $request->receiving_id,
                    'invoice_number'  => $request->invoice_number,
                    'creditor_code' => $request->creditor_code,
                ],
                [
                    'invoice_date'    => $request->invoice_date,
                    'invoice_times'   => $request->invoice_times,
                    'invoice_due'     => $request->invoice_due,
                    'invoice_payment' => $request->invoice_payment,
                    'invoice_ppn'     => $request->invoice_ppn,
                ]
            );

            ReceivingItems::updateOrCreate(
                [
                    'receiving_details_id' => $details->id,
                    'order_items_id'       => $request->order_items_id,
                ],
                [
                    'batches_id'     => $request->id,
                    'qty_received'   => $request->qty_received,
                    'qty'            => $request->qty_received,
                    'discount'       => $request->discount,
                    'extra_discount' => $request->extra_discount,
                    'expired_date'   => $request->expired_date,
                    'batch'          => $request->batch,
                    'location'       => NULL,
                    'etalase'        => NULL,
                    'total'          => $request->total,
                    'status'         => $request->status,
                ]
            );

            DB::commit();

            $receiving = Receiving::findOrFail($request->receiving_id);
            $getOrderId = $request->order_items_id;

            $price_total = ReceivingItems::with('order_items')
                ->whereHas('order_items', function ($q) use ($getOrderId) {
                    $q->where('id', $getOrderId);
                })
                ->sum('total') ?? '0';
            $ppn = $price_total * 0.11;

            return response()->json([
                'success'   => true,
                'receiving' => $receiving,
                'summary' => [
                    'price_item'  => $price_total,
                    'price_ppn'   => $ppn,
                    'price_total' => $price_total + $ppn,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function printReceiving($id)
    {
        $receiving = Receiving::with([
            'receiving_details.receiving_items.order_items.medicines',
            'receiving_details.creditor'
        ])->findOrFail($id);

        $totalDiscount = 0;
        $extraDiscount = 0;
        $subtotal = 0;

        foreach ($receiving->receiving_details as $detail) {
            $totalDiscount += $detail->receiving_items->sum('discount');
            $extraDiscount += $detail->receiving_items->sum('extra_discount');
            $subtotal += $detail->receiving_items->sum('total');
        }

        $totaldiscount = $totalDiscount + $extraDiscount;
        $totalwithdiscount = $subtotal;
        $total_receiving = $subtotal - $totaldiscount;

        return view('orders.printReceiving', compact(
            'totaldiscount',
            'totalwithdiscount',
            'total_receiving',
            'receiving'
        ));
    }

    public function printInvoice($id)
    {
        $invoice = ReceivingDetails::with([
            'receiving',
            'receiving_items.order_items.medicines',
            'creditor'
        ])->findOrFail($id);

        $totalDiscount = $invoice->receiving_items->sum('discount');
        $extraDiscount = $invoice->receiving_items->sum('extra_discount');
        $subtotal = $invoice->receiving_items->sum('total');

        $totaldiscount = $totalDiscount + $extraDiscount;
        $totalwithdiscount = $subtotal;
        $total_receiving = $subtotal - $totaldiscount;

        return view('orders.printInvoice', compact(
            'totaldiscount',
            'totalwithdiscount',
            'total_receiving',
            'invoice'
        ));
    }

    function generateTransfersCode()
    {
        $now = Carbon::now();

        $year  = $now->format('y');
        $month = $now->format('m');
        $prefix = "{$year}{$month}MUT";

        $lastCode = Transfers::where('code', 'like', "{$prefix}%")
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

    public function completeOrder(Request $request)
    {

        $request->validate([
            'receivingid' => 'required',
            'orderid'     => 'required',
        ]);

        try {
            DB::beginTransaction();

            $receiving = Receiving::with([
                'receiving_details.receiving_items.order_items'
            ])->findOrFail($request->receivingid);

            $order = Order::findOrFail($request->orderid);

            $receivingItems = $receiving->receiving_details
                ->pluck('receiving_items')
                ->flatten();

            if ($receivingItems->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada item untuk diproses',
                ], 422);
            }

            $now        = Carbon::now()->format('Y-m-d');
            $pharmacyId = auth()->user()->pharmacy_id;


            // 1. Pre-fetch all medicines
            $medicineIds = $receivingItems->pluck('order_items.medicine_id')->unique()->values();
            $medicines   = Medicines::whereIn('id', $medicineIds)->get()->keyBy('id');

            // 2. Pre-fetch all matching batches
            $existingBatches = Batches::where('pharmacy_id', $pharmacyId)
                ->where(function ($q) use ($receivingItems) {
                    foreach ($receivingItems as $item) {
                        $q->orWhere(
                            fn($q2) => $q2
                                ->where('medicine_id',  $item->order_items->medicine_id)
                                ->where('name',         $item->batch)
                                ->where('expired_date', $item->expired_date)
                        );
                    }
                })
                ->get()
                ->keyBy(fn($b) => "{$b->medicine_id}|{$b->name}|{$b->expired_date}");

            // 3. Loop
            $itemsLogInserts      = [];
            $batchIncrements      = [];
            $medicineIncrements   = [];
            $receivingItemUpdates = [];

            foreach ($receivingItems as $item) {
                $medicineId = $item->order_items->medicine_id;
                $medicine   = $medicines->get($medicineId);

                if (!$medicine) {
                    throw new \Exception("Medicine ID {$medicineId} not found.");
                }

                $batchKey = "{$medicineId}|{$item->batch}|{$item->expired_date}";

                if (!isset($existingBatches[$batchKey])) {
                    $batch = Batches::create([
                        'medicine_id'  => $medicineId,
                        'name'         => $item->batch,
                        'expired_date' => $item->expired_date,
                        'status'       => 0,
                        'pharmacy_id'  => $pharmacyId,
                        'stock'        => 0,
                    ]);
                    $existingBatches[$batchKey] = $batch;
                }

                $batch     = $existingBatches[$batchKey];
                $qtyBefore = $medicine->stock;
                $medicine->stock += $item->qty_received;

                $batchIncrements[$batch->id]     = ($batchIncrements[$batch->id]     ?? 0) + $item->qty_received;
                $medicineIncrements[$medicineId] = ($medicineIncrements[$medicineId] ?? 0) + $item->qty_received;
                $receivingItemUpdates[$item->id] = $batch->id;

                $itemsLogInserts[] = [
                    'transaction_code' => $receiving->code,
                    'code'             => $this->generateItemsLogCode(),
                    'type'             => 'OR',
                    'medicine_id'      => $medicineId,
                    'qty'              => $item->qty_received,
                    'qty_before'       => $qtyBefore,
                    'qty_after'        => $medicine->stock,
                    'total'            => $item->order_items->total ?? 0,
                    'date'             => $now,
                    'status'           => 2,
                    'batches_id'       => $batch->id,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }

            // FIX #5: Removed nested DB::transaction() — using the outer beginTransaction instead
            foreach ($batchIncrements as $batchId => $qty) {
                if (auth()->user()->pharmacy_id != 1) {
                    Batches::where('id', $batchId)->update(['stock' => 0]);

                    $transfer = MedicineTransfers::create([
                        'batches_id'  => $batchId,
                        'etalases_id' => 99,
                        'code'        => $this->generateTransfersCode(),
                        'stock'       => $qty,
                        'status'      => 1,
                    ]);

                    $medicine = Medicines::findOrFail($transfer->batches->medicine_id);

                    ItemsLog::create([
                        'transaction_code' => $transfer->code,
                        'code'             => $this->generateItemsLogCode(),
                        'type'             => 'MU',
                        'medicine_id'      => $medicine->id,
                        'qty'              => $transfer->stock,
                        'qty_before'       => 0,
                        'qty_after'        => $transfer->batches->stock,
                        'total'            => 0,
                        'date'             => Carbon::now(),
                        'status'           => 7,
                        'batches_id'       => $transfer->batches_id,
                        'user_id'          => auth()->user()->id,
                    ]);
                } else {
                    Batches::where('id', $batchId)->increment('stock', $qty);
                }
            }

            foreach ($medicineIncrements as $medicineId => $qty) {
                Medicines::where('id', $medicineId)->increment('stock', $qty);
            }

            collect($receivingItemUpdates)
                ->chunk(500)
                ->each(function ($chunk) {
                    foreach ($chunk as $itemId => $batchId) {
                        ReceivingItems::where('id', $itemId)->update(['batches_id' => $batchId]);
                    }
                });

            collect($itemsLogInserts)
                ->chunk(500)
                ->each(fn($chunk) => ItemsLog::insert($chunk->values()->all()));

            // Finalize
            $order->update(['status' => 2]);
            $receiving->update(['status' => 1]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Received',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Error complete receiving', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyelesaikan receiving',
            ], 500);
        }
    }
}
