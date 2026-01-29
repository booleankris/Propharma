<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\MedicineCart;
use App\Models\Medicines;
use App\Models\Order;
use App\Models\OrderItems;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DataTables;

class OrdersController extends Controller
{

    public function OrderItems(Request $request)
    {
        $query = OrderItems::select([
            'order_items.id as order_item_id',
            'order_items.order_id',
            'order_items.medicine_id',
            'order_items.quantity',
            'order_items.price',
            'order_items.total',
            'order_items.pack',
            'order_items.creditor_id',
        ])->with([
            'medicines.factory',
            'medicines.creditor',
            'orders'
        ])->whereHas('orders', function ($q) {
            $q->where('status', 0);
        });

        return DataTables::of($query)
         
            ->addColumn('item_total', function ($data) {
                $total = "Rp. " . number_format($data->total);
                return $total;
            })
            ->addColumn('item_price', function ($data) {
                $price = "Rp. " . number_format($data->price);
                return $price;
            })
            ->escapeColumns(['preview, action'])
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
            $total = OrderItems::where('order_id', $last->id)->where('status', '0')->sum('total');
            $order_id = $last->id;
            $order_code = $last->code;
            $now = $last->date;

            return view('orders.order', compact('order_code', 'now', 'order_id', 'total'));
        } else {
            // Generate Order COde
            $year   = now()->format('y');
            $month  = now()->format('m');
            $prefix = $year . $month . 'OR';
            $last = Order::where('pharmacy_id', Auth()->user()->pharmacy_id)
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
    public function addItemOrder(Request $request)
    {
        $validated = $request->validate([
            'order_id'    => 'required',
            'medicine_id' => 'required',
            'creditor_id' => 'nullable',
            'pack'        => 'required',
            'price'       => 'required',
            'quantity'    => 'required',
            'total'       => 'required',
        ]);

        $item = OrderItems::create([
            'order_id'    => $validated['order_id'],
            'medicine_id' => $validated['medicine_id'],
            'creditor_id' => $validated['creditor_id'],
            'pack'        => $validated['pack'],
            'price'       => $validated['price'],
            'quantity'    => $validated['quantity'],
            'total'       => $validated['total'],
            'status'      => 0,
        ]);

        return response()->json([
            'success' => true,
            'item' => $item
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
            'pack'        => $request->pack,
            'price'       => $request->price,
            'quantity' => $request->quantity,
            'total' => $request->total,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item updated successfully'
        ]);
    }
    public function deleteOrderItem(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:order_items,id',
        ]);

        $item = OrderItems::findOrFail($request->id);
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item deleted successfully'
        ]);
    }
    public function searchMedicine(Request $request)
    {
        $search = $request->search;
        $data = Medicines::query()
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
                'unit'         => $item->unit,
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
    public function completeOrder(Request $request) {
        $request->validate([
            'order_id' => 'required',
        ]);
    
        $item = Order::findOrFail($request->order_id);
        $item->update([
            'status' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order Completed'
        ]);
    }
}
