<?php

namespace App\Http\Controllers;

use App\Models\Medicines;
use App\Models\Reject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use DataTables;

class RejectController extends Controller
{
    function generateRejectionCode()
    {
        $now = Carbon::now();

        $year  = $now->format('y'); // 25
        $month = $now->format('m'); // 11
        $prefix = "{$year}{$month}RJ";

        $lastCode = Reject::where('code', 'like', "{$prefix}%")
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
    public function searchMedicine(Request $request)
    {
        $search = $request->search;
        $orderid = $request->orderid;
        $data = Medicines::where('pharmacy_id', Auth()->user()->pharmacy_id)
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
    public function reject()
    {
        $now = Carbon::now()->format('d/m/Y');
        $rejection_code = $this->generateRejectionCode();
        $d_total = Reject::where('pharmacy_id', Auth()->user()->pharmacy_id)->sum('total');

        return view('kasir.reject.reject_sales', compact('rejection_code', 'now', 'd_total'));
    }
    public function addItemReject(Request $request)
    {
        $validated = $request->validate([
            'code'          => 'required',
            'date'          => 'required',
            'pharmacy_id'   => 'nullable',
            'medicine_id'   => 'required',
            'quantity'      => 'required',
            'total'         => 'required',
            'reason'        => 'required',

        ]);
        $rejectDate = Carbon::createFromFormat('d/m/Y', $request->date)->format('Y-m-d');

        $item = Reject::create([
            'code'          => $validated['code'],
            'date'          => $rejectDate,
            'pharmacy_id'   => Auth()->user()->pharmacy_id,
            'medicine_id'   => $validated['medicine_id'],
            'quantity'      => $validated['quantity'],
            'total'         => $validated['total'],
            'reason'        => $validated['reason'],
        ]);

        $price_total = Reject::where('id', $item->id)->sum('total') ?? '';

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
    public function getReject()
    {
        $query = Reject::with('medicines')->where('pharmacy_id', Auth()->user()->pharmacy_id);

        return DataTables::of($query)
            ->addColumn(
                'total',
                fn($data) =>
                "Rp. " . number_format($data->total)
            )
            ->escapeColumns([])
            ->make(true);
    }
    public function postRejection(Request $request) {}
}
