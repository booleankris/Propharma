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

        $year  = $now->format('y');
        $month = $now->format('m');
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
        $data = Medicines::with([
            'composition',
            'category',
            'factory',
            'creditor'
        ])


            ->where('medicines.name', 'LIKE', "%{$search}%")
            ->orWhere('medicines.code', 'LIKE', "%{$search}%")
            ->paginate(10);

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
        $d_total = Reject::where('pharmacy_id', getActivePharmacyId())->sum('total');

        return view('kasir.reject.reject_sales', compact('rejection_code', 'now', 'd_total'));
    }
    public function addItemReject(Request $request)
    {
        $validated = $request->validate([
            'code'          => 'required',
            'date'          => 'required',
            'pharmacy_id'   => 'nullable',
            'medicine_id'   => 'nullable', // <-- Changed to nullable
            'medicine_name' => 'nullable|string', // <-- New field
            'quantity'      => 'required',
            'total'         => 'nullable|numeric', // <-- Allow null or 0
            'reason'        => 'required',
        ]);

        $rejectDate = Carbon::createFromFormat('d/m/Y', $request->date)->format('Y-m-d');

        $item = Reject::create([
            'code'          => $validated['code'],
            'date'          => $rejectDate,
            'pharmacy_id'   => getActivePharmacyId(),
            'medicine_id'   => $validated['medicine_id'],
            'medicine_name' => $validated['medicine_name'] ?? null,
            'quantity'      => $validated['quantity'],
            'total'         => $validated['total'] ?? 0, // <-- Set to 0 if null
            'reason'        => $validated['reason'],
        ]);

        // Calculate sum correctly in case of 0 totals
        $price_total = Reject::where('id', $item->id)->sum('total') ?? 0;
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
        $query = Reject::with('medicines')->where('pharmacy_id', getActivePharmacyId());

        return DataTables::of($query)
            ->addColumn(
                'total',
                fn($data) =>
                "Rp. " . number_format($data->total)
            )
            ->addColumn('raw_total', fn($data) => $data->total)
            ->addColumn('raw_price', fn($data) => $data->quantity > 0 ? $data->total / $data->quantity : 0)
            ->addColumn('action', function ($data) {
                return '
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="editReject(' . $data->id . ')"
                            class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2.5 py-1.5 text-[11px] font-semibold text-blue-600 hover:bg-blue-100 transition-colors">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4l10.5-10.5a1.5 1.5 0 0 0-4-4l-10.5 10.5z"/><path d="M13.5 6.5l4 4"/></svg>
                            Edit
                        </button>
                        <button type="button" onclick="deleteReject(' . $data->id . ')"
                            class="inline-flex items-center gap-1 rounded-lg bg-rose-50 px-2.5 py-1.5 text-[11px] font-semibold text-rose-600 hover:bg-rose-100 transition-colors">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12"/><path d="M9 7v-2a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                            Hapus
                        </button>
                    </div>';
            })
            ->rawColumns(['action'])
            ->escapeColumns([])
            ->make(true);
    }
    public function updateItemReject(Request $request, $id)
    {
        $validated = $request->validate([
            'medicine_id'   => 'nullable',
            'medicine_name' => 'nullable|string',
            'quantity'      => 'required',
            'total'         => 'nullable|numeric',
            'reason'        => 'required',
        ]);

        $item = Reject::findOrFail($id);
        $item->update([
            'medicine_id'   => $validated['medicine_id'],
            'medicine_name' => $validated['medicine_name'] ?? $item->medicine_name,
            'quantity'      => $validated['quantity'],
            'total'         => $validated['total'] ?? 0,
            'reason'        => $validated['reason'],
        ]);

        $total = Reject::where('pharmacy_id', getActivePharmacyId())->sum('total');

        return response()->json([
            'success' => true,
            'item'    => $item,
            'total'   => $total,
        ]);
    }
    public function deleteItemReject($id)
    {
        $item = Reject::findOrFail($id);
        $item->delete();

        $total = Reject::where('pharmacy_id', getActivePharmacyId())->sum('total');

        return response()->json([
            'success' => true,
            'total'   => $total,
        ]);
    }
    public function postRejection(Request $request) {}
}
