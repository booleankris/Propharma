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
                ->where('status', '!=', 2) // not yet completed — adjust value to match your status convention
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
            ->addColumn('date', function ($row) {
                return $row->date;
            })
            ->addColumn('code', function ($row) {
                return $row->code ?? 0;
            })
            ->addColumn('status_order', function ($row) {
                if ($row->status == 2) {
                    return '<div style="text-align:center;font-weight:bold;text-transform:uppercase;background-color:rgba(34,197,94,0.2);color:#16a34a;padding:6px 4px;font-size:12px;border-radius:6px;">DITERIMA</div>';
                }
                if ($row->status == 1) {
                    return '<div style="text-align:center;font-weight:bold;text-transform:uppercase;background-color:rgba(234,179,8,0.2);color:#ca8a04;padding:6px 4px;font-size:12px;border-radius:6px;">DIPESAN</div>';
                }
                return '<div style="text-align:center;font-weight:bold;text-transform:uppercase;background-color:rgba(239,68,68,0.2);color:#b91c1c;padding:6px 4px;font-size:12px;border-radius:6px;">PENDING</div>';
            })
            ->addColumn('action', function ($row) {
                if ($row->status == 0) {
                    $label = 'Lanjutkan';
                    $color = 'background:#2563eb;';
                    $icon = '<svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>';
                } elseif ($row->status == 1) {
                    $label = 'Terima';
                    $color = 'background:#16a34a;';
                    $icon = '<svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
                } else {
                    // FIX #3: Was duplicate status == 1, now correctly handles status == 2 (Cetak)
                    $label = 'Cetak SPB';
                    $color = 'background:#eab308;';
                    $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-printer">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2" />
                    <path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4" />
                    <path d="M7 15a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2l0 -4" />
                </svg>';
                }

                if ($row->status == 0) {
                    return '
                    <a href="/createorder">
                        <div class="flex gap-1">
                            <div class="w-full">
                                <button style="' . $color . ' color:white;" class="rounded-full px-2 py-2 font-semibold">
                                    <div class="flex gap-2 justify-center items-center">
                                        <span>' . $icon . '</span>
                                        <span class="text-xs pr-2">' . $label . '</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </a>';
                } elseif ($row->status == 1) {
                    return '
                    <a href="receive/' . $row->id . '">
                        <div class="flex gap-1">
                            <div class="w-full">
                                <button style="' . $color . ' color:white;" class="rounded-full px-2 py-2 font-semibold">
                                    <div class="flex gap-2 justify-center items-center">
                                        <span>' . $icon . '</span>
                                        <span class="text-xs pr-2">' . $label . '</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </a>';
                } else {
                    // FIX #3: Cetak button now actually renders for status == 2
                    return '
                    <a target="_blank" href="/receiving/' . $row->id . '/printspbfinal">
                        <div class="flex gap-1">
                            <div class="w-full">
                                <button style="' . $color . ' color:white;" class="rounded-full px-2 py-2 font-semibold">
                                    <div class="flex gap-2 justify-center items-center">
                                        <span>' . $icon . '</span>
                                        <span class="text-xs pr-2">' . $label . '</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </a>';
                }
            })
            ->addColumn('total', fn($row) => 'Rp ' . number_format($row->order_items_sum_total, 0, ',', '.'))
            ->addColumn('total_ppn', fn($row) => 'Rp ' . number_format(floor($row->order_items_sum_total * 1.11), 0, ',', '.'))
            ->rawColumns(['status_order', 'action'])
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
        $transaction = Receiving::where('status', 0)->first();

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

            $receiving_id = $transaction->id;
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
            $details = ReceivingDetails::updateOrCreate(
                [
                    'receiving_id'  => $request->receiving_id,
                    'creditor_code' => $request->creditor_code,
                ],
                [
                    'invoice_number'  => $request->invoice_number,
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
