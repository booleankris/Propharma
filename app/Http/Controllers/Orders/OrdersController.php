<?php

namespace App\Http\Controllers\Orders;

use App\Exports\Export\OrdersExport as ExportOrdersExport;
use App\Exports\Orders\OrdersExport;
use App\Http\Controllers\Controller;
use App\Models\MedicineCart;
use App\Models\Medicines;
use App\Models\MedicineTransfers;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Receiving;
use App\Models\Transfers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DataTables;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\DotMatrixPrinter;
use App\Services\SuratPesananFormatter;

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
            'order_items.note',
        ])
            ->with([
                'medicines.factory',
                'medicines.creditors',
                'medicines.creditor',
                'orders',
                'creditors'
            ])
            ->whereHas('orders', function ($q) {
                $q->where('status', 0)->where('pharmacy_id', getActivePharmacyId());
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

            ->addColumn(
                'discount',
                function ($data) {
                    $mc = \App\Models\MedicineCreditor::where('medicine_id', $data->medicine_id)
                        ->where('creditor_code', $data->creditor_code)
                        ->first();
                    return $mc ? ($mc->discount ?? 0) . '%' : '0%';
                }
            )

            ->escapeColumns([])
            ->make(true);
    }
    public function createOrder(Request $request)
    {
        $now = Carbon::now()->format('d/m/Y');
        $check_transaction = Order::where('pharmacy_id', getActivePharmacyId())
            ->where('status', '0')->first();

        if ($check_transaction) {

            $last = Order::where('pharmacy_id', getActivePharmacyId())
                ->where('status', '0')
                ->first();
            
            $now = Carbon::now()->format('d/m/Y');
            $last->update(['date' => $now]);

            $d_price = OrderItems::where('order_id', $last->id)->where('status', '0')->sum('total') ?? '';
            $d_ppn = floor($d_price * 0.11) ?? '';
            $d_total = $d_price + $d_ppn ?? '';
            $order_id = $last->id;
            $order_code = $last->code;

            return view('orders.order', compact('order_code', 'now', 'd_price', 'd_ppn', 'd_total', 'order_id'));
        } else {
            // Generate Order COde
            $year = now()->format('y');
            $month = now()->format('m');
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
                    'pharmacy_id' => getActivePharmacyId(),
                    'user_id' => auth()->user()->id,
                    'code' => $transactionCode,
                    'date' => $now,
                    'status' => 0,
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
    public function getOrdersCode($medicineId, $orderId, $creditorCode = null)
    {
        $medicine = \App\Models\Medicines::findOrFail($medicineId);
        $order = \App\Models\Order::findOrFail($orderId);
        $pharmacyId = $order->pharmacy_id;

        $type = strtoupper($medicine->type);
        $code = '';
        if ($type == "NARKOTIKA") {
            $code = "N";
        } else if ($type == "PSIKOTROPIKA") {
            $code = "P";
        } else if ($type == "PREKURSOR") {
            $code = "PR";
        } else if ($type == "OBAT-OBAT TERTENTU (OOT)" || $type == "OBAT TERTENTU") {
            $code = "O";
        } else if ($type == "REGULER") {
            $code = "R";
        } else {
            $code = "R";
        }

        if ($type !== "NARKOTIKA") {
            $existingItem = \App\Models\OrderItems::where('order_id', $orderId)
                ->where('creditor_code', $creditorCode)
                ->whereHas('medicines', function ($query) use ($type) {
                    if ($type == "OBAT-OBAT TERTENTU (OOT)" || $type == "OBAT TERTENTU") {
                        $query->whereIn(\Illuminate\Support\Facades\DB::raw('UPPER(type)'), ["OBAT-OBAT TERTENTU (OOT)", "OBAT TERTENTU"]);
                    } else {
                        $query->where(\Illuminate\Support\Facades\DB::raw('UPPER(type)'), $type);
                    }
                })
                ->first();

            if ($existingItem && $existingItem->order_items_code) {
                return $existingItem->order_items_code;
            }
        }

        $year = now()->format('y');
        $month = now()->format('m');
        $prefix = "SP-{$code}-{$year}{$month}/";

        $lastItem = \App\Models\OrderItems::where('order_items_code', 'like', $prefix . '%')
            ->whereHas('orders', function ($query) use ($pharmacyId) {
                $query->where('pharmacy_id', $pharmacyId);
            })
            ->orderBy('order_items_code', 'desc')
            ->first();

        if ($lastItem && $lastItem->order_items_code) {
            $parts = explode('/', $lastItem->order_items_code);
            $lastPart = end($parts);
            $serialPart = explode('-', $lastPart)[0];
            $lastSerial = intval($serialPart);
            $nextSerial = $lastSerial + 1;
        } else {
            $nextSerial = 1;
        }

        return $prefix . str_pad($nextSerial, 6, '0', STR_PAD_LEFT) . '-' . $pharmacyId;
    }
    public function addItemOrder(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required',
            'medicine_id' => 'required',
            'creditor_code' => 'nullable',
            'pack' => 'required',
            'price' => 'required',
            'quantity' => 'required',
            'total' => 'required',
        ]);

        $itemCode = $this->getOrdersCode($validated['medicine_id'], $validated['order_id'], $validated['creditor_code'] ?? null);

        $item = OrderItems::create([
            'order_items_code' => $itemCode,
            'order_id' => $validated['order_id'],
            'medicine_id' => $validated['medicine_id'],
            'creditor_code' => $validated['creditor_code'] ?? null,
            'pack' => $validated['pack'],
            'price' => $validated['price'],
            'quantity' => $validated['quantity'],
            'total' => $validated['total'],
            'note' => $request->note ?? null,
            'status' => 0,
        ]);

        $price_total = OrderItems::where('order_id', $item->order_id)->where('status', '0')->sum('total') ?? '';

        $ppn = floor($price_total * 0.11);
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
            'pack' => 'required|',
            'price' => 'required|',
            'quantity' => 'required|',
            'total' => 'required|',
        ]);

        $item = OrderItems::findOrFail($request->order_id);

        $data = [
            'medicine_id' => $request->medicine_id,
            'creditor_code' => $request->creditor_code,
            'pack' => $request->pack,
            'price' => $request->price,
            'quantity' => $request->quantity,
            'total' => $request->total,
            'note' => $request->note ?? null,
        ];

        if ($item->medicine_id != $request->medicine_id || $item->creditor_code != $request->creditor_code) {
            $data['order_items_code'] = $this->getOrdersCode($request->medicine_id, $item->order_id, $request->creditor_code);
        }

        $item->update($data);

        $price_total = OrderItems::where('order_id', $item->order_id)->where('status', '0')->sum('total') ?? '';

        $ppn = floor($price_total * 0.11);
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

        $ppn = floor($price_total * 0.11);
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
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'factory_id' => $item->factory?->id,
                'factory_name' => $item->factory?->name,
                'packaging' => $item->packaging,
                'content' => $item->content,
                'raw_price' => $item->raw_price,
            ];
        });
        return response()->json($data);
    }

    function generateOrderCode()
    {
        $now = Carbon::now();

        $year = $now->format('y'); // 25
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

            $now = now()->format('d/m/Y');
            $order->update([
                'status' => 1,
                'date' => $now,
            ]);
            $year = now()->format('y');
            $month = now()->format('m');
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
                'pharmacy_id' => getActivePharmacyId(),
                'code' => $receiving_code,
                'date' => $now,
                'status' => 0,
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
                    'id' => $c->id,
                    'name' => $c->name,
                    'code' => $c->code,
                    'discount' => $c->pivot->discount ?? 0,
                ];
            })
        ]);
    }
    public function printSPB($orderId)
    {
        try {
            ini_set('memory_limit', '512M');
            $date = Carbon::now()->translatedFormat('d F Y');
            $order = Order::with([
                'pharmacy',
                'order_items.medicines',
                'order_items.medicines.creditors',
                'order_items.creditors',
                'order_items.medicines.factory',
                'order_items.medicines.category',
                'order_items.medicines.composition',
            ])->findOrFail($orderId);

            $pharmacy = $order->pharmacy;

            $grouped = $order->order_items->groupBy(function ($item) {
                $type = $item->medicines->type ?? "Kosong";
                if (strtoupper($type) === 'NARKOTIKA') {
                    return 'NARKOTIKA_' . $item->id;
                }
                return $type;
            })->map(function ($perCreditor) {
                return $perCreditor->groupBy('creditor_code') ?? "Kosong";
            });

            $pdf = Pdf::loadView('orders.printSPB', compact('order', 'date', 'grouped', 'pharmacy'))
                ->setPaper('A7', 'portrait');

            return $pdf->stream("SPB-{$order->code}.pdf");
        } catch (\Throwable $e) {
            dd($e->getMessage(), $e->getFile(), $e->getLine());
        }
    }

    public function printSPBDotMatrix($orderId)
    {
        try {
            ini_set('memory_limit', '512M');
            $date = Carbon::now()->translatedFormat('d F Y');
            $order = Order::with([
                'pharmacy',
                'order_items.medicines',
                'order_items.medicines.creditors',
                'order_items.creditors',
                'order_items.medicines.composition',
            ])->findOrFail($orderId);

            $pharmacy = $order->pharmacy;

            $grouped = $order->order_items->groupBy(function ($item) {
                $type = $item->medicines->type ?? "Kosong";
                if (strtoupper($type) === 'NARKOTIKA') {
                    return 'NARKOTIKA_' . $item->id;
                }
                return $type;
            })->map(function ($perCreditor) {
                return $perCreditor->groupBy('creditor_code') ?? "Kosong";
            });

            $text = SuratPesananFormatter::build(
                $order,
                $pharmacy,
                $grouped,
                $date,
                filter_var(env('DOTMATRIX_PRINTER_DRAFT', true), FILTER_VALIDATE_BOOLEAN)
            );

            if ($text === '') {
                return response()->json(['error' => 'Tidak ada item obat untuk dicetak.'], 422);
            }

            if (request()->boolean('preview')) {
                return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
            }

            $ip = env('DOTMATRIX_PRINTER_IP');
            $port = (int) env('DOTMATRIX_PRINTER_PORT', 9100);

            if (!$ip) {
                return response()->json([
                    'error' => 'Printer belum dikonfigurasi. Atur DOTMATRIX_PRINTER_IP di .env.',
                ], 400);
            }

            DotMatrixPrinter::send($text, $ip, $port);

            return response()->json(['message' => 'Surat Pesanan berhasil dikirim ke printer dot matrix.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
        $dateTo = $request->date_to ?? now()->format('Y-m-d');
        $search = $request->search;
        $orderId = $request->order_id;

        // Exclude medicines already in this order
        $existingIds = OrderItems::where('order_id', $orderId)->pluck('medicine_id');

        $results = MedicineCart::select(
            'medicine_cart.medicine_id',
            'medicines.code',
            'medicines.name',
            'medicines.packaging',
            'medicines.raw_price',
            'medicines.minimal_stock'
        )
            ->selectRaw('SUM(medicine_cart.quantity) as total_sold')
            ->join('medicine_transactions', 'medicine_transactions.id', '=', 'medicine_cart.transaction_id')
            ->join('medicines', 'medicines.id', '=', 'medicine_cart.medicine_id')
            ->whereBetween('medicine_transactions.created_at', ["{$dateFrom} 00:00:00", "{$dateTo} 23:59:59"])
            ->whereNotIn('medicine_cart.medicine_id', $existingIds)
            ->when($search, fn($q) => $q->where('medicines.name', 'like', "%{$search}%"))
            ->groupBy(
                'medicine_cart.medicine_id',
                'medicines.code',
                'medicines.name',
                'medicines.packaging',
                'medicines.raw_price',
                'medicines.minimal_stock'
            )
            ->orderByDesc('total_sold')
            ->paginate(20);

        $results->getCollection()->transform(function ($row) {

            $batchStock = \App\Models\Batches::where('medicine_id', $row->medicine_id)->sum('stock');

            $transferStock = \App\Models\MedicineTransferItems::whereHas('batches', function ($q) use ($row) {
                $q->where('medicine_id', $row->medicine_id);
            })->sum('qty');

            $totalStocks = $batchStock + $transferStock;

            return [
                'medicine_id' => $row->medicine_id,
                'code' => $row->code,
                'name' => $row->name,
                'packaging' => $row->packaging,
                'raw_price' => $row->raw_price,
                'total_sold' => (int) $row->total_sold,
                'min_stock' => $row->minimal_stock,
                'stocks' => $totalStocks,
            ];
        });

        return response()->json($results);
    }

    public function addItemsBulk(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->items as $item) {
                $medicine = Medicines::find($item['medicine_id']);
                $qty = $item['quantity'];
                $price = $medicine->raw_price;

                $itemCode = $this->getOrdersCode($item['medicine_id'], $request->order_id, null);

                OrderItems::create([
                    'order_items_code' => $itemCode,
                    'order_id' => $request->order_id,
                    'medicine_id' => $item['medicine_id'],
                    'creditor_code' => null,
                    'pack' => 0,
                    'price' => $price,
                    'quantity' => $qty,
                    'total' => $qty * $price,
                    'status' => 0,
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
        $ppn = floor($price_total * 0.11);

        return response()->json([
            'success' => true,
            'summary' => [
                'price_item' => $price_total,
                'price_ppn' => $ppn,
                'price_total' => $price_total + $ppn,
            ]
        ]);
    }
}
