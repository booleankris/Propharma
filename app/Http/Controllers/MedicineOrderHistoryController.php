<?php

namespace App\Http\Controllers;

use App\Models\ReceivingItems;
use App\Models\Medicines;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class MedicineOrderHistoryController extends Controller
{
    public function index()
    {
        return view('orders.medicineorderhistory');
    }

    // Autocomplete dropdown source — only medicines that have actually
    // been received by this pharmacy, so the dropdown stays relevant.
    public function searchMedicine(Request $request)
    {
        $keyword = $request->get('search', '');
        $page    = (int) $request->get('page', 1);
        $perPage = 10;

        $pharmacyId = auth()->user()->pharmacy_id;

        $query = Medicines::query()
            ->where('pharmacy_id', $pharmacyId)
            ->whereHas('order_items.receiving_items') // only medicines actually received
            ->when($keyword, function ($q) use ($keyword) {
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('name', 'like', '%' . $keyword . '%')
                        ->orWhere('code', 'like', '%' . $keyword . '%');
                });
            })
            ->select('id', 'code', 'name')
            ->orderBy('name');

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data'         => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'per_page'     => $perPage,
        ]);
    }

    public function data(Request $request)
    {
        $items = ReceivingItems::query()
            ->with([
                'order_items.medicines',
                'receiving_details.creditor',
                'receiving_details.receiving',
            ])
            ->whereHas('receiving_details.receiving', function ($q) {
                $q->where('pharmacy_id', auth()->user()->pharmacy_id);
            });

        // searchMedicine can be either a medicine id (selected from dropdown)
        // or free text (typed and Enter pressed without picking a row)
        if ($request->filled('searchMedicine')) {
            $value = $request->searchMedicine;

            $items->whereHas('order_items.medicines', function ($q) use ($value) {
                if (is_numeric($value)) {
                    $q->where('id', $value);
                } else {
                    $q->where('name', 'like', '%' . $value . '%');
                }
            });
        }

        // Filter by when the item itself was received, not when the parent
        // order/receiving record was created — items can trickle in over
        // time onto the same receiving code.
        if ($request->filled('start_date')) {
            $items->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $items->whereDate('created_at', '<=', $request->end_date);
        }

        $items->latest('id');

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('date', fn($row) => $row->created_at?->format('d/m/Y') ?? '—')
            ->addColumn('receiving_code', fn($row) => $row->receiving_details?->receiving?->code ?? '—')
            ->addColumn('invoice_number', fn($row) => $row->receiving_details?->invoice_number ?? '—')
            ->addColumn('medicine_name', fn($row) => $row->order_items?->medicines?->name ?? '—')
            ->addColumn('qty_received', fn($row) => $row->qty_received)
            ->addColumn('total', fn($row) => 'Rp ' . number_format($row->total, 0, ',', '.'))
            ->addColumn('creditor_name', fn($row) => $row->receiving_details?->creditor?->name ?? '—')
            ->make(true);
    }
}
