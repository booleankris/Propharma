<?php

namespace App\Http\Controllers\Orders;

use App\Exports\Export\OrdersExport as ExportOrdersExport;
use App\Exports\Orders\OrdersExport;
use App\Http\Controllers\Controller;
use App\Models\MedicineCart;
use App\Models\Medicines;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Receiving;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DataTables;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class OrdersController extends Controller
{

    public function OrderItems(Request $request)
    {
        $creditorId = $request->creditor_id;

        $query = OrderItems::select([
            'order_items.id as order_item_id',
            'order_items.order_id',
            'order_items.medicine_id',
            'order_items.quantity',
            'order_items.price',
            'order_items.total',
            'order_items.pack',
            'order_items.creditor_code',
        ])
            ->with([
                'medicines.factory',
                'medicines.creditors',
                'medicines.creditor',
                'orders',
                'creditors'
            ])
            ->whereHas('orders', function ($q) {
                $q->where('status', 0)->where('pharmacy_id', auth()->user()->pharmacy_id);
            })

            ->when($creditorId, function ($q) use ($creditorId) {
                $q->where('creditor_code', $creditorId);
            });

        return DataTables::of($query)

            ->addColumn(
                'item_total',
                fn($data) =>
                "Rp. " . number_format($data->total)
            )

            ->addColumn(
                'item_price',
                fn($data) =>
                "Rp. " . number_format($data->price)
            )

            ->addColumn(
                'creditors',
                fn($data) =>
                $data->creditors->name ?? 'Belum Dipilih'
            )

            ->escapeColumns([])
            ->make(true);
    }
    public function createOrder(Request $request)
    {
        $now = Carbon::now()->format('d/m/Y');
        $check_transaction = Order::where('pharmacy_id', Auth()->user()->pharmacy_id)
            ->where('status', '0')->first();

        if ($check_transaction) {

            $last = Order::where('pharmacy_id', Auth()->user()->pharmacy_id)
                ->where('status', '0')
                ->first();
            $d_price = OrderItems::where('order_id', $last->id)->where('status', '0')->sum('total') ?? '';
            $d_ppn = $d_price * 0.11 ?? '';
            $d_total = $d_price + $d_ppn ?? '';
            $order_id = $last->id;
            $order_code = $last->code;
            $now = $last->date;

            return view('orders.order', compact('order_code', 'now', 'd_price', 'd_ppn', 'd_total', 'order_id'));
        } else {
            // Generate Order COde
            $year   = now()->format('y');
            $month  = now()->format('m');
            $prefix = $year . $month . 'OR';

            $last = Order::where('code', 'like', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            if ($last) {
                $lastNumber = intval(substr($last->code, -4));
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 0;
            }

            $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            $transactionCode = $prefix . $serial;

            try {
                DB::beginTransaction();

                $transaction = Order::create([
                    'pharmacy_id'       => Auth()->user()->pharmacy_id,
                    'user_id'           => Auth()->user()->id,
                    'code'              => $transactionCode,
                    'date'              => $now,
                    'status'            => 0,
                ]);
                DB::commit();
                return redirect()->back()->with('message', "Berhasil Menyimpan! ");
            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()->back()->with('message', "Gagal Menyimpan! " . $e->getMessage());
            }
        }


        // if ($check_transaction == 0) {
        // }
    }
    public function orderList(Request $request)
    {
        $orderCode = $request->order_id;
        \Illuminate\Support\Facades\Log::info('ORDER CODE', [$request->order_id]);

        if (!$orderCode) {
            return DataTables::of(collect())->make(true);
        }

        $items = OrderItems::query()
            ->with(['medicines', 'receiving_items'])
            ->withSum('receivingItems as qty_received', 'qty_received')
            ->whereHas('orders', function ($q) use ($orderCode) {
                $q->where('code', $orderCode);
            });

        return DataTables::of($items)
            ->addIndexColumn()

            ->addColumn('qty_received', function ($row) {
                return $row->qty_received ?? 0;
            })

            ->addColumn('qty_remaining', function ($row) {
                return max(0, $row->quantity - ($row->qty_received ?? 0));
            })

            ->addColumn(
                'price',
                fn($row) =>
                'Rp ' . number_format($row->price, 0, ',', '.')
            )
            ->addColumn(
                'price_ppn',
                fn($row) =>

                'Rp ' . number_format(floor($row->price * 1.11), 0, ',', '.')
            )
            ->addColumn(
                'total',
                fn($row) =>
                'Rp ' . number_format($row->total, 0, ',', '.')
            )

            ->make(true);
    }
    public function printOrder($id)
    {
        $order = Order::where('id', $id)->first();
        return Excel::download(
            new ExportOrdersExport($id),
            $order->code . '.xlsx'
        );
    }
    public function addItemOrder(Request $request)
    {
        $validated = $request->validate([
            'order_id'      => 'required',
            'medicine_id'   => 'required',
            'creditor_code' => 'nullable',
            'pack'          => 'required',
            'price'         => 'required',
            'quantity'      => 'required',
            'total'         => 'required',
        ]);

        $item = OrderItems::create([
            'order_id'      => $validated['order_id'],
            'medicine_id'   => $validated['medicine_id'],
            'creditor_code' => $validated['creditor_code'] ?? null,
            'pack'          => $validated['pack'],
            'price'         => $validated['price'],
            'quantity'      => $validated['quantity'],
            'total'         => $validated['total'],
            'status'        => 0,
        ]);

        $price_total = OrderItems::where('order_id', $item->order_id)->where('status', '0')->sum('total') ?? '';

        $ppn = $price_total * 0.11;
        return response()->json([
            'success' => true,
            'item' => $item,
            'summary' => [
                'price_item' => $price_total,
                'price_ppn' => $ppn,
                'price_total' => $price_total + $ppn
            ]
        ]);
    }
    public function updateOrderItem(Request $request)
    {
        $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'pack'        => 'required|',
            'price'       => 'required|',
            'quantity'    => 'required|',
            'total'       => 'required|',
        ]);

        $item = OrderItems::findOrFail($request->order_id);
        $item->update([
            'medicine_id' => $request->medicine_id,
            'creditor_code' => $request->creditor_code,
            'pack'        => $request->pack,
            'price'       => $request->price,
            'quantity' => $request->quantity,
            'total' => $request->total,
        ]);

        $price_total = OrderItems::where('order_id', $item->order_id)->where('status', '0')->sum('total') ?? '';

        $ppn = $price_total * 0.11;
        return response()->json([
            'success' => true,
            'item' => $item,
            'summary' => [
                'price_item' => $price_total,
                'price_ppn' => $ppn,
                'price_total' => $price_total + $ppn
            ]
        ]);
    }
    public function deleteOrderItem(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:order_items,id',
        ]);

        $item = OrderItems::findOrFail($request->id);
        $item->delete();

        $price_total = OrderItems::where('order_id', $item->order_id)->where('status', '0')->sum('total') ?? '';

        $ppn = $price_total * 0.11;
        return response()->json([
            'success' => true,
            'item' => $item,
            'message' => 'Item deleted successfully',
            'summary' => [
                'price_item' => $price_total,
                'price_ppn' => $ppn,
                'price_total' => $price_total + $ppn
            ]
        ]);
    }
    public function searchMedicine(Request $request)
    {
        $search = $request->search;
        $orderid = $request->orderid;
        $filterExist = OrderItems::where('order_id', $orderid)->pluck('medicine_id');
        $data = Medicines::whereNotIn('id', $filterExist)
            ->with([
                'composition',
                'category',
                'factory',
                'creditor'
            ])


            ->where('medicines.name', 'LIKE', "%{$search}%")
            ->orWhere('medicines.code', 'LIKE', "%{$search}%")
            ->paginate(10);

        // format response for frontend
        $data->getCollection()->transform(function ($item) {
            return [
                'id'           => $item->id,
                'code'         => $item->code,
                'name'         => $item->name,
                'factory_id'   => $item->factory?->id,
                'factory_name' => $item->factory?->name,
                'packaging'    => $item->packaging,
                'content'      => $item->content,
                'raw_price'    => $item->raw_price,
            ];
        });
        return response()->json($data);
    }

    function generateOrderCode()
    {
        $now = Carbon::now();

        $year  = $now->format('y'); // 25
        $month = $now->format('m'); // 11
        $prefix = "{$year}{$month}OI";

        $lastCode = Order::where('code', 'like', "{$prefix}%")
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
    public function order()
    {
        $now = Carbon::now()->format('d/m/Y');
        $order_code = $this->generateOrderCode();

        return view('orders.order', compact('order_code', 'now'));
    }
    public function completeOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        try {
            DB::beginTransaction();

            $order = Order::findOrFail($request->order_id);

            if ($order->order_items()->count() === 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order harus memiliki minimal 1 item!'
                ], 422);
            }

            $order->update([
                'status' => 1,
            ]);

            $now    = now()->format('d/m/Y');
            $year   = now()->format('y');
            $month  = now()->format('m');
            $prefix = $year . $month . 'RE';

            $last = Receiving::where('code', 'like', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            $nextNumber = $last
                ? intval(substr($last->code, -4)) + 1
                : 1;

            $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $receiving_code = $prefix . $serial;

            $transaction = Receiving::create([
                'pharmacy_id'  => auth()->user()->pharmacy_id,
                'code'         => $receiving_code,
                'date'         => $now,
                'status'       => 0,
            ]);

            // 26 Juli 2026 - Edit receiving_id from orders
            $order->update([
                'receiving_id' => $transaction->id,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Pemesanan Berhasil!',
                'redirect' => route('receiving.index')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal Menyimpan! ' . $e->getMessage()
            ], 500);
        }
    }
    public function getCreditors($id)
    {
        $medicine = Medicines::with('creditors')->findOrFail($id);

        return response()->json([
            'medicine' => $medicine,
            'creditors' => $medicine->creditors->map(function ($c) {
                return [
                    'id'       => $c->id,
                    'name'     => $c->name,
                    'code'     => $c->code,
                    'discount' => $c->pivot->discount ?? 0,
                ];
            })
        ]);
    }
    public function printSPB($orderId)
    {
        $date = Carbon::now()->translatedFormat('d F Y');
        $order = Order::with([
            'pharmacy',
            'order_items.medicines',
            'order_items.creditors',
            'order_items.medicines.factory',
            'order_items.medicines.category',
            'order_items.medicines.composition',
        ])->findOrFail($orderId);

        $pharmacy = $order->pharmacy;

        $grouped = $order->order_items->groupBy(function ($item) {
            return $item->medicines->type ?? "Kosong";
        })->map(function ($perCreditor) {
            return $perCreditor->groupBy('creditor_code') ?? "Kosong";
        });
        $pharmacy = $order->pharmacy;
        // TEMPORARY DEBUG - remove after fixing
        $logoFile = $pharmacy->logo;
        $logoPath = public_path('img/' . $logoFile);
        return response(
            "Logo field value: [{$logoFile}]<br>" .
                "Full path: [{$logoPath}]<br>" .
                "file_exists: " . (file_exists($logoPath) ? 'YES' : 'NO') . "<br>" .
                "is_readable: " . (is_readable($logoPath) ? 'YES' : 'NO') . "<br>" .
                "filesize: " . (file_exists($logoPath) ? filesize($logoPath) : 'N/A') . " bytes"
        );
        // $pdf = Pdf::loadView('orders.printSPB', compact('order', 'date', 'grouped', 'pharmacy'))
        //     ->setPaper('A7', 'portrait');

        // return $pdf->stream("SPB-{$order->code}.pdf");
    }
    public function printPreview($order_id)
    {
        $order = Order::with(['order_items.medicines', 'order_items.medicines.factory'])
            ->findOrFail($order_id);
        $grouped = $order->order_items->groupBy('creditor_code');


        return view('orders.printSPB', compact('grouped'));
    }
    public function smartMedicines(Request $request)
    {
        $dateFrom = $request->date_from ?? now()->subDays(30)->format('Y-m-d');
        $dateTo   = $request->date_to ?? now()->format('Y-m-d');
        $search   = $request->search;
        $orderId  = $request->order_id;

        // exclude medicines already in this order, so Smart Order doesn't duplicate rows
        $existingIds = OrderItems::where('order_id', $orderId)->pluck('medicine_id');

        $query = MedicineCart::select('medicine_cart.medicine_id')
            ->selectRaw('SUM(medicine_cart.quantity) as total_sold')
            ->join('medicine_transactions', 'medicine_transactions.id', '=', 'medicine_cart.transaction_id')
            ->join('medicines', 'medicines.id', '=', 'medicine_cart.medicine_id')
            ->whereBetween('medicine_transactions.created_at', ["{$dateFrom} 00:00:00", "{$dateTo} 23:59:59"])
            ->whereNotIn('medicine_cart.medicine_id', $existingIds)
            ->when($search, fn($q) => $q->where('medicines.name', 'like', "%{$search}%"))
            ->groupBy('medicine_cart.medicine_id')
            ->orderByDesc('total_sold');

        $results = $query->paginate(20);

        $results->getCollection()->transform(function ($row) {
            $medicine = Medicines::find($row->medicine_id);
            return [
                'medicine_id' => $row->medicine_id,
                'code'        => $medicine->code,
                'name'        => $medicine->name,
                'packaging'   => $medicine->packaging,
                'raw_price'   => $medicine->raw_price,
                'total_sold'  => (int) $row->total_sold,
            ];
        });

        return response()->json($results);
    }

    public function addItemsBulk(Request $request)
    {
        $request->validate([
            'order_id'              => 'required|exists:orders,id',
            'items'                 => 'required|array|min:1',
            'items.*.medicine_id'   => 'required|exists:medicines,id',
            'items.*.quantity'      => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->items as $item) {
                $medicine = Medicines::find($item['medicine_id']);
                $qty   = $item['quantity'];
                $price = $medicine->raw_price;

                OrderItems::create([
                    'order_id'      => $request->order_id,
                    'medicine_id'   => $item['medicine_id'],
                    'creditor_code' => null,
                    'pack'          => 0,
                    'price'         => $price,
                    'quantity'      => $qty,
                    'total'         => $qty * $price,
                    'status'        => 0,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan item! ' . $e->getMessage()
            ], 500);
        }

        $price_total = OrderItems::where('order_id', $request->order_id)->where('status', '0')->sum('total') ?? 0;
        $ppn = $price_total * 0.11;

        return response()->json([
            'success' => true,
            'summary' => [
                'price_item'  => $price_total,
                'price_ppn'   => $ppn,
                'price_total' => $price_total + $ppn,
            ]
        ]);
    }
}
