<?php

namespace App\Http\Controllers;

use App\Models\Medicines;
use App\Models\MedicineTransferItems;
use App\Models\Batches;
use App\Models\Reject;
use App\Models\RejectFollowUpOption;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DataTables;
use App\Models\ExportJob;
use App\Jobs\ProcessRejectSalesExport;
use App\Exports\RejectSalesExport;
use Maatwebsite\Excel\Facades\Excel;

class RejectController extends Controller
{
    public function followUpIndex(Request $request)
    {
        RejectFollowUpOption::ensureTableExists();
        $pharmacyId = getActivePharmacyId();

        $filters = [
            'search' => trim((string) $request->get('search', '')),
            'start_date' => $request->get('start_date', ''),
            'end_date' => $request->get('end_date', ''),
            'location' => $request->get('location', ''),
            'stock_status' => $request->get('stock_status', ''),
            'follow_up' => $request->get('follow_up', ''),
            'follow_up_update' => $request->get('follow_up_update', ''),
        ];

        $baseQuery = Reject::where('pharmacy_id', $pharmacyId);

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'stock_status_filled' => (clone $baseQuery)->whereNotNull('stock_status')->where('stock_status', '!=', '')->count(),
            'follow_up_filled' => (clone $baseQuery)->whereNotNull('follow_up')->where('follow_up', '!=', '')->count(),
            'update_filled' => (clone $baseQuery)->whereNotNull('follow_up_update')->where('follow_up_update', '!=', '')->count(),
        ];

        $query = Reject::with(['medicines.composition'])
            ->where('pharmacy_id', $pharmacyId);

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('medicines', function ($mq) use ($search) {
                    $mq->where('name', 'like', "%{$search}%")
                       ->orWhere('code', 'like', "%{$search}%")
                       ->orWhereHas('composition', fn($cq) => $cq->where('name', 'like', "%{$search}%"));
                })
                ->orWhere('medicine_name', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")
                ->orWhere('reason', 'like', "%{$search}%")
                ->orWhere('follow_up_note', 'like', "%{$search}%")
                ->orWhere('stock_status', 'like', "%{$search}%")
                ->orWhere('follow_up', 'like', "%{$search}%")
                ->orWhere('follow_up_update', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('date', '<=', $filters['end_date']);
        }
        if ($filters['location'] !== '') {
            if ($filters['location'] === '__EMPTY__') {
                $query->where(fn($q) => $q->whereNull('location')->orWhere('location', ''));
            } else {
                $query->where('location', $filters['location']);
            }
        }
        if ($filters['stock_status'] !== '') {
            if ($filters['stock_status'] === '__EMPTY__') {
                $query->where(fn($q) => $q->whereNull('stock_status')->orWhere('stock_status', ''));
            } else {
                $query->where('stock_status', $filters['stock_status']);
            }
        }
        if ($filters['follow_up'] !== '') {
            if ($filters['follow_up'] === '__EMPTY__') {
                $query->where(fn($q) => $q->whereNull('follow_up')->orWhere('follow_up', ''));
            } else {
                $query->where('follow_up', $filters['follow_up']);
            }
        }
        if ($filters['follow_up_update'] !== '') {
            if ($filters['follow_up_update'] === '__EMPTY__') {
                $query->where(fn($q) => $q->whereNull('follow_up_update')->orWhere('follow_up_update', ''));
            } else {
                $query->where('follow_up_update', $filters['follow_up_update']);
            }
        }

        $rejects = $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $optionsByCategory = RejectFollowUpOption::getOptionsByCategory($pharmacyId);

        // Merge any existing values used in reject rows so old values remain selectable
        foreach (['stock_status', 'follow_up', 'follow_up_update'] as $col) {
            $existingMap = [];
            foreach ($optionsByCategory[$col] ?? [] as $opt) {
                $existingMap[mb_strtoupper(trim($opt['label']))] = true;
            }

            $usedVals = Reject::where('pharmacy_id', $pharmacyId)
                ->whereNotNull($col)
                ->where($col, '!=', '')
                ->distinct()
                ->pluck($col);

            foreach ($usedVals as $val) {
                $clean = trim((string) $val);
                $upper = mb_strtoupper($clean);
                if ($clean !== '' && !isset($existingMap[$upper])) {
                    $optionsByCategory[$col][] = [
                        'id' => null,
                        'label' => $clean,
                        'is_default' => false,
                    ];
                    $existingMap[$upper] = true;
                }
            }
        }

        return view('kasir.reject.follow_up', compact('rejects', 'optionsByCategory', 'filters', 'stats'));
    }

    public function exportFollowUp(Request $request)
    {
        RejectFollowUpOption::ensureTableExists();
        $pharmacyId = getActivePharmacyId();

        $filters = [
            'search' => trim((string) $request->get('search', '')),
            'location' => $request->get('location', ''),
            'stock_status' => $request->get('stock_status', ''),
            'follow_up' => $request->get('follow_up', ''),
            'follow_up_update' => $request->get('follow_up_update', ''),
        ];

        $startDate = $request->get('start_date') ?: null;
        $endDate = $request->get('end_date') ?: null;

        $fileName = 'Tindak_Lanjut_Penolakan_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new RejectSalesExport($pharmacyId, $startDate, $endDate, $filters),
            $fileName
        );
    }

    public function updateFollowUp(Request $request, $id)
    {
        RejectFollowUpOption::ensureTableExists();

        $validated = $request->validate([
            'location' => 'nullable|in:Online,Offline',
            'stock_status' => 'nullable|string|max:255',
            'follow_up' => 'nullable|string|max:2000',
            'follow_up_note' => 'nullable|string|max:2000',
            'follow_up_update' => 'nullable|string|max:2000',
        ]);

        $reject = Reject::where('pharmacy_id', getActivePharmacyId())->findOrFail($id);

        $updateData = [];
        foreach (['location', 'stock_status', 'follow_up', 'follow_up_note', 'follow_up_update'] as $field) {
            if ($request->exists($field)) {
                $val = $validated[$field] ?? null;
                $updateData[$field] = is_string($val) ? trim($val) : null;
                if ($updateData[$field] === '') {
                    $updateData[$field] = null;
                }
            }
        }

        if (!empty($updateData)) {
            $reject->update($updateData);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data tindak lanjut penolakan berhasil diperbarui.',
                'reject' => $reject->fresh(['medicines.composition']),
            ]);
        }

        return redirect()->route('sales.reject.follow-up')->with('success', 'Tindak lanjut penolakan berhasil disimpan.');
    }

    public function storeFollowUpOption(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|in:stock_status,follow_up,follow_up_update',
            'label' => 'required|string|max:255',
            'reject_id' => 'nullable|integer',
        ]);

        RejectFollowUpOption::ensureTableExists();

        $pharmacyId = getActivePharmacyId();
        $label = trim($validated['label']);
        $category = $validated['category'];

        $existing = RejectFollowUpOption::where('category', $category)
            ->where(function ($q) use ($pharmacyId) {
                $q->whereNull('pharmacy_id')->orWhere('pharmacy_id', $pharmacyId);
            })
            ->whereRaw('UPPER(TRIM(label)) = ?', [mb_strtoupper($label)])
            ->first();

        if (!$existing) {
            $existing = RejectFollowUpOption::create([
                'pharmacy_id' => $pharmacyId,
                'category' => $category,
                'label' => $label,
            ]);
        }

        $updatedReject = null;
        if (!empty($validated['reject_id'])) {
            $updatedReject = Reject::where('pharmacy_id', $pharmacyId)->find($validated['reject_id']);
            if ($updatedReject) {
                $updatedReject->update([
                    $category => $existing->label,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Opsi baru berhasil ditambahkan.',
            'option' => [
                'id' => $existing->id,
                'category' => $existing->category,
                'label' => $existing->label,
                'is_default' => false,
            ],
            'reject' => $updatedReject,
        ]);
    }

    public function deleteFollowUpOption($id)
    {
        RejectFollowUpOption::ensureTableExists();

        $option = RejectFollowUpOption::where(function ($q) {
            $q->whereNull('pharmacy_id')->orWhere('pharmacy_id', getActivePharmacyId());
        })->findOrFail($id);

        $option->delete();

        return response()->json([
            'success' => true,
            'message' => 'Opsi berhasil dihapus.',
        ]);
    }

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

        $activePharmacyId = getActivePharmacyId();
        $counterPharmacyId = isWarehousePharmacy($activePharmacyId) ? 1 : $activePharmacyId;
        $medIds = $data->getCollection()->pluck('id')->filter()->all();
        $stockMap = [];
        if (!empty($medIds)) {
            $stockMap = MedicineTransferItems::join('batches', 'batches.id', '=', 'medicine_transfer_items.batches_id')
                ->where('batches.pharmacy_id', $counterPharmacyId)
                ->where('medicine_transfer_items.status', 1)
                ->where(function ($q) {
                    $q->whereNull('medicine_transfer_items.source_type')
                        ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                })
                ->whereIn('batches.medicine_id', $medIds)
                ->groupBy('batches.medicine_id')
                ->selectRaw('batches.medicine_id, SUM(medicine_transfer_items.qty) as total_qty')
                ->pluck('total_qty', 'medicine_id')
                ->all();
        }

        $data->getCollection()->transform(function ($item) use ($stockMap) {
            $rawQty = (float) ($stockMap[$item->id] ?? 0);
            $formattedTotal = ($rawQty == (int) $rawQty) ? (int) $rawQty : round($rawQty, 2);

            return [
                'id'           => $item->id,
                'code'         => $item->code,
                'name'         => $item->name,
                'factory_id'   => $item->factory?->id,
                'factory_name' => $item->factory?->name,
                'unit'         => $item->unit,
                'content'      => $item->content,
                'raw_price'    => $item->raw_price,
                'total'        => $formattedTotal,
                'total_stock'  => $formattedTotal,
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
        RejectFollowUpOption::ensureTableExists();

        $validated = $request->validate([
            'code'           => 'required',
            'date'           => 'required',
            'pharmacy_id'    => 'nullable',
            'medicine_id'    => 'nullable',
            'medicine_name'  => 'nullable|string',
            'quantity'       => 'required',
            'unit'           => 'nullable|string',
            'location'       => 'nullable|in:Online,Offline',
            'total'          => 'nullable|numeric',
            'reason'         => 'required',
            'follow_up_note' => 'nullable|string|max:2000',
        ]);

        $rejectDate = Carbon::createFromFormat('d/m/Y', $request->date)->format('Y-m-d');

        $item = Reject::create([
            'code'           => $validated['code'],
            'date'           => $rejectDate,
            'pharmacy_id'    => getActivePharmacyId(),
            'medicine_id'    => $validated['medicine_id'],
            'medicine_name'  => $validated['medicine_name'] ?? null,
            'quantity'       => $validated['quantity'],
            'unit'           => $validated['unit'] ?? $request->unit ?? null,
            'location'       => $validated['location'] ?? null,
            'total'          => $validated['total'] ?? 0,
            'reason'         => $validated['reason'],
            'follow_up_note' => isset($validated['follow_up_note']) ? trim($validated['follow_up_note']) : null,
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
    public function getReject(Request $request)
    {
        RejectFollowUpOption::ensureTableExists();

        $filters = $request->validate([
            'medicine_search' => 'nullable|string|max:255',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d' . ($request->filled('start_date') ? '|after_or_equal:start_date' : ''),
        ]);

        $query = Reject::with('medicines')->where('pharmacy_id', getActivePharmacyId());

        return DataTables::of($query)
            ->filter(function ($query) use ($filters) {
                $name = trim($filters['medicine_search'] ?? '');
                if ($name !== '') {
                    $query->where(function ($names) use ($name) {
                        $names->whereHas('medicines', fn($medicine) => $medicine->where('name', 'like', '%' . $name . '%'))
                            ->orWhere(function ($custom) use ($name) {
                                $custom->whereDoesntHave('medicines')->where('medicine_name', 'like', '%' . $name . '%');
                            });
                    });
                }
                if (!empty($filters['start_date'])) {
                    $query->whereDate('date', '>=', $filters['start_date']);
                }
                if (!empty($filters['end_date'])) {
                    $query->whereDate('date', '<=', $filters['end_date']);
                }
            })
            ->addColumn(
                'total',
                fn($data) =>
                "Rp. " . number_format($data->total)
            )
            ->addColumn('raw_total', fn($data) => $data->total)
            ->addColumn('raw_price', fn($data) => $data->quantity > 0 ? $data->total / $data->quantity : 0)
            ->addColumn('unit', fn($data) => $data->unit ?? $data->medicines?->unit ?? '')
            ->addColumn('location', fn($data) => $data->location ?? '')
            ->addColumn('follow_up_note', fn($data) => $data->follow_up_note ?? '')
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
        RejectFollowUpOption::ensureTableExists();

        $validated = $request->validate([
            'medicine_id'    => 'nullable',
            'medicine_name'  => 'nullable|string',
            'quantity'       => 'required',
            'unit'           => 'nullable|string',
            'location'       => 'nullable|in:Online,Offline',
            'total'          => 'nullable|numeric',
            'reason'         => 'required',
            'follow_up_note' => 'nullable|string|max:2000',
        ]);

        $item = Reject::findOrFail($id);
        $item->update([
            'medicine_id'    => $validated['medicine_id'],
            'medicine_name'  => $validated['medicine_name'] ?? $item->medicine_name,
            'quantity'       => $validated['quantity'],
            'unit'           => $validated['unit'] ?? $request->unit ?? $item->unit,
            'location'       => $request->exists('location') ? ($validated['location'] ?: null) : $item->location,
            'total'          => $validated['total'] ?? 0,
            'reason'         => $validated['reason'],
            'follow_up_note' => $request->exists('follow_up_note') ? (isset($validated['follow_up_note']) ? trim($validated['follow_up_note']) : null) : $item->follow_up_note,
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

    public function exportReject(Request $request)
    {
        $user = auth()->user();
        if ($user && $user->hasRole('Kasir') && !$user->hasAnyRole(['administrator', 'General Manager', 'Manager', 'manager', 'Koordinator', 'HO', 'operator'])) {
            return response()->json([
                'message' => 'Role Kasir tidak diizinkan untuk melakukan export Excel data penolakan barang.',
            ], 403);
        }

        $pharmacyId = getActivePharmacyId();
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $job = ExportJob::create([
            'type' => 'rejects',
            'status' => 'pending',
            'progress' => 0
        ]);

        dispatch(new ProcessRejectSalesExport(
            $job->id, 
            $pharmacyId, 
            $startDate, 
            $endDate
        ));

        return response()->json([
            'job_id' => $job->id,
            'message' => 'Export started.'
        ]);
    }

    public function exportStatus($id)
    {
        $user = auth()->user();
        if ($user && $user->hasRole('Kasir') && !$user->hasAnyRole(['administrator', 'General Manager', 'Manager', 'manager', 'Koordinator', 'HO', 'operator'])) {
            return response()->json([
                'message' => 'Role Kasir tidak diizinkan untuk mengakses status export.',
            ], 403);
        }

        $job = ExportJob::findOrFail($id);

        return response()->json([
            'status' => $job->status,
            'progress' => $job->progress,
            'file' => $job->file_path ? asset('storage/' . $job->file_path) : null
        ]);
    }
}
