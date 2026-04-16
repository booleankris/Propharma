<?php

namespace App\Http\Controllers;

use App\Models\Batches;
use App\Models\Items;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\MedicineTransfers;
use App\Models\Pharmacies;
use App\Models\Transfers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransfersController extends Controller
{
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
    public function searchBatches(Request $request)
    {
        $search = trim($request->input('search', ''));

        if (blank($search)) {
            return response()->json(['data' => []]);
        }

        $data = Batches::query()
            ->with('medicines')
            ->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhereHas(
                        'medicines',
                        fn($qMed) =>
                        $qMed->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('code', 'LIKE', "%{$search}%")
                    );
            })
            ->where('stock', '>', 0)
            ->where('pharmacy_id', auth()->user()->pharmacy_id)
            ->paginate(10);

        $data->getCollection()->transform(function ($item) {
            return [
                'id'           => $item->id,
                'batches_name' => $item->name,
                'name'         => $item->medicines?->name ?? "??",
                'stock'         => $item?->stock ?? "??",
                'unit'         => $item?->medicines?->unit ?? "??",

            ];
        });

        return response()->json($data);
    }
    public function transfersCreate()
    {
        $now = Carbon::now();
        $code = $this->generateTransfersCode();
        $pharmacies = Pharmacies::all();
        return view('kasir.transfers.create_transfers', compact('now', 'pharmacies', 'code'));
    }
    public function transfer(Request $request)
    {
        $request->validate([
            'batches_id' => 'required|exists:batches,id',
            'etalases_id' => 'required|exists:etalases,id',
            'code' => 'required|string',
            'qty' => 'required|string',
        ]);
        try {
            DB::transaction(function () use ($request) {
                $now         = Carbon::now()->format('Y-m-d');
                $pharmacyId  = $request->pharmacy;

                // ───────────────────── 1. Find The Batch & Medicine Detail ─────────────────────
                $item        = Batches::findOrFail($request->batches_id);
                $medicines   = Medicines::where('id', $item->medicine_id)->get();

                // ───────────────────── 2. Check Existing Batches ─────────────────────
                $existingBatches = Batches::where('pharmacy_id', $pharmacyId)
                    ->where('medicine_id',  $item->medicine_id)
                    ->where('name',         $item->batch)
                    ->where('expired_date', $item->expired_date)
                    ->first();

                // ───────────────────── 3. Create New Batch If Not Exist ─────────────────────

                if (!$existingBatches) {
                    $batch = Batches::create([
                        'medicine_id'  => $item->medicine_id,
                        'name'         => $item->name,
                        'expired_date' => $item->expired_date,
                        'status'       => 0,
                        'pharmacy_id'  => $pharmacyId,
                        'stock'        => 0,
                    ]);
                }

                $batch->increment('stock', $request->qty);

                $insert = MedicineTransfers::create([
                    'batches_id' => $batch->id,
                    'etalases_id' => $request->etalases_id,
                    'code'       => $request->code,
                    'stock'        => $request->qty,
                    'status'     => 0,

                ]);
            });
            return redirect()->back()->with('success', "Berhasil Menyimpan! ");
        } catch (\Throwable $e) {
            return redirect()->back()->with('message', "Gagal Menyimpan! " . $e->getMessage());
        }
    }

    public function index()
    {
        return response()->json(
            Items::orderBy('name')->get(['id', 'name'])
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|',
        ]);

        $etalase = Items::create($validated);

        return response()->json($etalase, 201);
    }

    public function update(Request $request, Items $etalase)
    {
        $validated = $request->validate([
            'name'        => 'required|string|',
        ]);

        $etalase->update($validated);

        return response()->json($etalase);
    }
    public function incomingTransfers()
    {
        $transfers = MedicineTransfers::with(['batches.medicines', 'etalases'])
            ->whereHas('batches', function ($getpid) {
                $getpid->where('pharmacy_id', auth()->user()->pharmacy_id);
            })
            ->latest()
            ->get();

        $pending  = $transfers->where('status', 0);
        $accepted = $transfers->where('status', 1);
        $denied   = $transfers->where('status', 2);

        $transferData = $transfers->keyBy('id')->map(fn($t) => [
            'id'       => $t->id,
            'code'     => $t->code ?? '—',
            'med_name' => $t->batches?->medicines?->name ?? '—',
            'batch'    => $t->batches?->name ?? '—',
            'etalase'  => $t->etalases?->name ?? '—',
            'stock'    => $t->stock ?? 0,
            'date'     => $t->created_at?->format('d M Y, H:i') ?? '—',
        ]);

        return view('kasir.transfers.transfers', compact('pending', 'accepted', 'denied', 'transferData'));
    }

    public function acceptTransfer(MedicineTransfers $transfer)
    {

        try {

            DB::transaction(function () use ($transfer) {
                $now = Carbon::now();
                $medicine = Medicines::findOrFail($transfer->batches->medicine_id);
                $qtybefore = $transfer->batches->stock;


                $transfer->batches->decrement('stock', $transfer->stock);
                $transfer->update(['status' => 1]);

                // Transfers status     = 7
                ItemsLog::create([
                    'transaction_code' => $transfer->code,
                    'code'             => $this->generateItemsLogCode(),
                    'type'             => 'MU',
                    'medicine_id'      => $medicine->id,
                    'qty'              => $transfer->stock,
                    'qty_before'       => $qtybefore,
                    'qty_after'        => $transfer->batches->stock,
                    'total'            => 0,
                    'date'             => $now,
                    'status'           => 7,
                    'batches_id'       => $transfer->batches_id,

                ]);
            });


            return redirect()->back()->with('success', 'Mutasi Stock diterima!');
        } catch (\Throwable $e) {
            return redirect()->back()->with('message', 'Gagal menerima: ' . $e->getMessage());
        }
    }

    public function denyTransfer(MedicineTransfers $transfer)
    {
        try {
            $transfer->update(['status' => 2]);
            return redirect()->back()->with('success', 'Transfer ditolak.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('message', 'Gagal menolak: ' . $e->getMessage());
        }
    }
}
