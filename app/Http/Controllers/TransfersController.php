<?php

namespace App\Http\Controllers;

use App\Models\Batches;
use App\Models\Items;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\MedicineTransferItems;
use App\Models\MedicineTransfers;
use App\Models\Pharmacies;
use App\Models\Transfers;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TransfersExport;
use App\Models\ExportJob;
use App\Jobs\ProcessTransfersExport;
use App\Services\MedicineTransferCodeGenerator;

class TransfersController extends Controller
{
    /**
     * User yang terverifikasi lewat PIN pribadi (secret_pin) untuk aksi mutasi saat ini.
     * Jika null, fallback ke user yang sedang login.
     */
    protected ?int $actorUserId = null;

    /**
     * ID user pelaku mutasi: user PIN jika ada, jika tidak user login.
     */
    protected function actorId(): ?int
    {
        return $this->actorUserId ?: auth()->id();
    }

    /**
     * Cari user berdasarkan PIN pribadi (secret_pin). Return null jika PIN kosong / salah.
     */
    protected function findUserByPin($pin): ?User
    {
        $pin = trim((string) $pin);
        if ($pin === '' || !preg_match('/^\d{4}$/', $pin)) {
            return null;
        }

        return User::where('secret_pin', $pin)->first();
    }

    /**
     * Verifikasi PIN dari request dan set pelaku mutasi.
     */
    protected function resolvePinActor(Request $request): ?User
    {
        $user = $this->findUserByPin($request->input('pin'));
        if ($user) {
            $this->actorUserId = (int) $user->id;
        }

        return $user;
    }

    /**
     * Endpoint verifikasi PIN untuk modal mutasi (keluar / terima).
     */
    public function verifyTransferPin(Request $request)
    {
        $user = $this->findUserByPin($request->input('pin'));

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'PIN salah, silahkan coba lagi.',
            ]);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name ?: ($user->username ?? 'User'),
            ],
        ]);
    }

    /**
     * Cek ketersediaan kolom audit di tabel etalases.
     */
    protected function hasEtalaseAuditColumns(): bool
    {
        static $cached = null;
        if ($cached === null) {
            $cached = Schema::hasColumn('etalases', 'created_by') && Schema::hasColumn('etalases', 'updated_by');
        }

        return $cached;
    }

    /**
     * Cek ketersediaan kolom audit etalase di tabel medicine_transfer_items.
     */
    protected function hasTransferItemEtalaseAuditColumns(): bool
    {
        static $cached = null;
        if ($cached === null) {
            $cached = Schema::hasColumn('medicine_transfer_items', 'etalase_updated_by')
                && Schema::hasColumn('medicine_transfer_items', 'etalase_updated_at');
        }

        return $cached;
    }

    /**
     * Format data etalase beserta info audit pembuat/pengubah.
     */
    protected function formatEtalasePayload(Items $etalase): array
    {
        return [
            'id' => $etalase->id,
            'name' => $etalase->name,
            'pharmacy_id' => $etalase->pharmacy_id,
            'updated_by_name' => $this->hasEtalaseAuditColumns() ? ($etalase->updated_by_name ?? null) : null,
            'updated_at_formatted' => $etalase->updated_at ? $etalase->updated_at->format('d M Y H:i') : null,
        ];
    }

    /**
     * Ambil daftar etalase yang tersedia untuk cabang tertentu.
     */
    protected function getEtalasesForPharmacy(int $pharmacyId)
    {
        $query = Items::query();
        if ($this->hasEtalaseAuditColumns()) {
            $query->with(['creator:id,name,username', 'updater:id,name,username']);
        }
        if ($pharmacyId > 0) {
            $hasSpecific = Items::where('pharmacy_id', $pharmacyId)->exists();
            if ($hasSpecific) {
                $query->where(function ($q) use ($pharmacyId) {
                    $q->where('pharmacy_id', $pharmacyId)
                        ->orWhere(function ($sub) use ($pharmacyId) {
                            $sub->whereNull('pharmacy_id')
                                ->whereIn('id', function ($mti) use ($pharmacyId) {
                                    $mti->select('mti.etalases_id')
                                        ->from('medicine_transfer_items as mti')
                                        ->join('batches as b', 'b.id', '=', 'mti.batches_id')
                                        ->where('b.pharmacy_id', $pharmacyId)
                                        ->whereNotNull('mti.etalases_id');
                                });
                        });
                });
            } else {
                $query->where(function ($q) use ($pharmacyId) {
                    $q->where('pharmacy_id', $pharmacyId)
                      ->orWhereNull('pharmacy_id');
                });
            }
        }

        return $query->orderBy('name')->get()->map(function (Items $e) {
            return (object) $this->formatEtalasePayload($e);
        })->values();
    }

    /**
     * Validasi bahwa etalase tujuan milik cabang penerima (atau global NULL).
     */
    protected function resolveDestinationEtalaseId($etalaseId, int $pharmacyId): ?int
    {
        if (empty($etalaseId)) {
            return null;
        }

        $id = (int) $etalaseId;
        $exists = Items::whereKey($id)
            ->where(function ($q) use ($pharmacyId) {
                $q->where('pharmacy_id', $pharmacyId)
                  ->orWhereNull('pharmacy_id');
            })
            ->exists();

        return $exists ? $id : null;
    }

    /**
     * Tentukan etalase default item berdasarkan etalases_id sebelumnya pada medicine_transfer_items
     * untuk obat & cabang penerima yang sama; jika tidak ditemukan, pilih secara acak dari etalase cabang.
     *
     * @return array{id: ?int, name: ?string, source: ?string}
     */
    protected function resolveDefaultEtalaseForItem(
        MedicineTransferItems $item,
        int $pharmacyId,
        $branchEtalases = null
    ): array {
        if ($pharmacyId <= 0 || isWarehousePharmacy($pharmacyId)) {
            return ['id' => null, 'name' => null, 'source' => null];
        }

        $etalasesCollection = $branchEtalases !== null
            ? collect($branchEtalases)
            : $this->getEtalasesForPharmacy($pharmacyId);

        if ($etalasesCollection->isEmpty()) {
            return ['id' => null, 'name' => null, 'source' => null];
        }

        $etalasesById = $etalasesCollection->keyBy(fn($e) => (int) $e->id);
        $validIds = $etalasesById->keys()->map(fn($id) => (int) $id)->values()->all();

        $item->loadMissing('batches');
        $medicineId = $item->batches?->medicine_id ?? $item->requested_medicine_id;

        $foundId = null;
        if ($medicineId && !empty($validIds)) {
            $orderExpr = $this->hasTransferItemEtalaseAuditColumns()
                ? 'COALESCE(medicine_transfer_items.etalase_updated_at, medicine_transfer_items.updated_at, medicine_transfer_items.created_at) DESC, medicine_transfer_items.id DESC'
                : 'COALESCE(medicine_transfer_items.updated_at, medicine_transfer_items.created_at) DESC, medicine_transfer_items.id DESC';

            // Prioritaskan riwayat item yang sudah diterima (status = 1) di cabang penerima
            $foundId = MedicineTransferItems::query()
                ->join('batches as b', 'b.id', '=', 'medicine_transfer_items.batches_id')
                ->where('b.pharmacy_id', $pharmacyId)
                ->where('b.medicine_id', $medicineId)
                ->where('medicine_transfer_items.id', '!=', $item->id)
                ->whereNotNull('medicine_transfer_items.etalases_id')
                ->whereIn('medicine_transfer_items.etalases_id', $validIds)
                ->where('medicine_transfer_items.status', 1)
                ->orderByRaw($orderExpr)
                ->value('medicine_transfer_items.etalases_id');

            // Fallback ke riwayat item non-ditolak sebelumnya jika belum ada yang diterima di cabang penerima
            if (!$foundId) {
                $foundId = MedicineTransferItems::query()
                    ->join('batches as b', 'b.id', '=', 'medicine_transfer_items.batches_id')
                    ->where('b.pharmacy_id', $pharmacyId)
                    ->where('b.medicine_id', $medicineId)
                    ->where('medicine_transfer_items.id', '!=', $item->id)
                    ->whereNotNull('medicine_transfer_items.etalases_id')
                    ->whereIn('medicine_transfer_items.etalases_id', $validIds)
                    ->where('medicine_transfer_items.status', '!=', 2)
                    ->orderByRaw($orderExpr)
                    ->value('medicine_transfer_items.etalases_id');
            }

            // Fallback ke riwayat medicine_transfer_items obat yang sama yang memiliki etalases_id valid milik cabang ini
            if (!$foundId) {
                $foundId = MedicineTransferItems::query()
                    ->join('batches as b', 'b.id', '=', 'medicine_transfer_items.batches_id')
                    ->where('b.medicine_id', $medicineId)
                    ->where('medicine_transfer_items.id', '!=', $item->id)
                    ->whereNotNull('medicine_transfer_items.etalases_id')
                    ->whereIn('medicine_transfer_items.etalases_id', $validIds)
                    ->where('medicine_transfer_items.status', '!=', 2)
                    ->orderByRaw($orderExpr)
                    ->value('medicine_transfer_items.etalases_id');
            }
        }

        if ($foundId) {
            $resolvedId = (int) $foundId;
            return [
                'id' => $resolvedId,
                'name' => $etalasesById->get($resolvedId)?->name,
                'source' => 'previous',
            ];
        }

        $randomId = (int) $validIds[array_rand($validIds)];
        return [
            'id' => $randomId,
            'name' => $etalasesById->get($randomId)?->name,
            'source' => 'random',
        ];
    }

    function generateItemsLogCode()
    {
        $now = Carbon::now();

        $year = $now->format('y');
        $month = $now->format('m');
        $prefix = "{$year}{$month}LOG-";

        $lastCode = ItemsLog::where('code', 'like', "{$prefix}%")
            ->orderByRaw('LENGTH(code) DESC, code DESC')
            ->lockForUpdate()
            ->value('code');

        if ($lastCode) {
            $numericPart = substr((string) $lastCode, strlen($prefix));
            $lastNumber = (int) preg_replace('/\D/', '', $numericPart);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        $code = $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        while (ItemsLog::where('code', $code)->exists()) {
            $nextNumber++;
            $code = $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $code;
    }
    function generateTransfersCode()
    {
        return MedicineTransferCodeGenerator::generate();
    }
    public function searchBatches(Request $request)
    {
        $search = trim($request->input('search', ''));

        if (blank($search)) {
            return response()->json(['data' => []]);
        }

        $pharmacyId = getActivePharmacyId();

        $query = Batches::query()
            ->with(['medicines', 'medicine_transfer_items' => function ($q) {
                $q->where('qty', '>', 0)->where('status', 1);
            }])
            ->where('pharmacy_id', $pharmacyId)
            ->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhereHas(
                        'medicines',
                        fn($qMed) =>
                        $qMed->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('code', 'LIKE', "%{$search}%")
                    );
            });

        // Warehouse: stock is directly in batches.stock (already deducted on create)
        // Cabang / Pelayanan: stock is sum of accepted MTI records
        $isWarehouse = isWarehousePharmacy($pharmacyId);

        if ($isWarehouse) {
            $query->where('stock', '>', 0);
        } else {
            $query->whereRaw('(
                SELECT COALESCE(SUM(mti1.qty), 0)
                FROM medicine_transfer_items mti1
                WHERE mti1.batches_id = batches.id
                  AND mti1.status = 1
                  AND (mti1.source_type IS NULL OR mti1.source_type != "retur_gudang")
            ) > 0');
        }

        // Show stock that expires first at the top. Missing/blank EDs remain searchable,
        // but follow all batches with a usable expiration date.
        $query
            ->orderByRaw("CASE WHEN expired_date IS NULL OR TRIM(expired_date) = '' OR expired_date = '0000-00-00' THEN 1 ELSE 0 END ASC")
            ->orderByRaw("CASE
                WHEN expired_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}' THEN STR_TO_DATE(expired_date, '%Y-%m-%d')
                WHEN expired_date REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}' THEN STR_TO_DATE(expired_date, '%d/%m/%Y')
                WHEN expired_date REGEXP '^[0-9]{2}-[0-9]{2}-[0-9]{4}' THEN STR_TO_DATE(expired_date, '%d-%m-%Y')
                ELSE NULL
            END ASC")
            ->orderBy('id');

        $data = $query->paginate(20);

        // Transform
        $results = collect();
        $data->getCollection()->each(function ($item) use ($isWarehouse, &$results) {
            if ($isWarehouse) {
                $availStock = max(0, (int) $item->stock);

                if ($availStock > 0) {
                    $results->push([
                        'id' => $item->id,
                        'batches_name' => $item->name,
                        'name' => $item->medicines?->name ?? '??',
                        'stock' => $availStock,
                        'unit' => $item->medicines?->unit ?? '??',
                        'expired_date' => safeDateFormat($item->expired_date),
                        'source_type' => 'gudang',
                    ]);
                }
            } else {
                $currentPelayanan = (int) $item->medicine_transfer_items
                    ->filter(function ($mti) {
                        return (int) $mti->status === 1 && (empty($mti->source_type) || $mti->source_type !== 'retur_gudang');
                    })
                    ->sum('qty');
                $availStock = max(0, $currentPelayanan);

                if ($availStock > 0) {
                    $results->push([
                        'id' => $item->id,
                        'batches_name' => $item->name,
                        'name' => $item->medicines?->name ?? '??',
                        'stock' => $availStock,
                        'unit' => $item->medicines?->unit ?? '??',
                        'expired_date' => safeDateFormat($item->expired_date),
                        'source_type' => 'pelayanan',
                    ]);
                }
            }
        });

        $data->setCollection($results);

        return response()->json($data);
    }
    public function transfersCreate()
    {
        $now = Carbon::now();
        $code = $this->generateTransfersCode();
        $currentPharmacyId = getActivePharmacyId();

        $pharmaciesQuery = Pharmacies::where('status', 1);

        if (isWarehousePharmacy($currentPharmacyId)) {
            // Stok gudang PMI dapat dimutasi ke cabang pelayanan.
            $pharmaciesQuery->whereIn('id', [1, 2, 3, 4, 5]);
        } else {
            // Cabang / Pelayanan bisa transfer ke Gudang PMI (id = 9) dan ke cabang fisik lain
            $pharmaciesQuery->whereIn('id', [1, 2, 3, 4, 5, 9])
                ->where('id', '!=', $currentPharmacyId);
        }

        $pharmacies = $pharmaciesQuery->get();
        return view('kasir.transfers.create_transfers', compact('now', 'pharmacies', 'code'));
    }

    public function createTransferRequest()
    {
        $currentPharmacyId = getActivePharmacyId();
        $pharmacies = Pharmacies::where('status', 1)
            ->whereIn('id', [1, 2, 3, 4, 5, 9])
            ->where('id', '!=', $currentPharmacyId)
            ->orderBy('name')
            ->get();

        return view('kasir.transfers.create_request', [
            'pharmacies' => $pharmacies,
            'code' => $this->generateTransfersCode(),
            'now' => Carbon::now(),
        ]);
    }

    public function searchRequestMedicines(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $sourcePharmacyId = (int) $request->query('source_pharmacy_id');
        if (mb_strlen($search) < 1 || !in_array($sourcePharmacyId, [1, 2, 3, 4, 5, 9], true)) {
            return response()->json(['data' => []]);
        }

        $sourceType = isWarehousePharmacy($sourcePharmacyId) ? 'gudang' : 'pelayanan';
        $batches = Batches::with('medicines')
            ->where('pharmacy_id', $sourcePharmacyId)
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhereHas('medicines', function ($medicine) use ($search) {
                        $medicine->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            })
            ->orderByRaw('expired_date IS NULL, expired_date ASC')
            ->orderBy('id')
            ->limit(60)
            ->get();

        $results = $batches->map(function ($batch) use ($sourceType) {
            $available = $sourceType === 'gudang'
                ? max(0, (int) $batch->stock)
                : (int) MedicineTransferItems::where('batches_id', $batch->id)
                    ->where('qty', '>', 0)->where('status', 1)
                    ->where(function ($query) {
                        $query->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                    })->sum('qty');

            if ($available < 1) return null;

            return [
                'id' => $batch->id,
                'batches_name' => $batch->name,
                'name' => $batch->medicines?->name ?? '—',
                'medicine_id' => $batch->medicine_id,
                'medicine_code' => $batch->medicines?->code ?? '—',
                'stock' => $available,
                'unit' => $batch->medicines?->unit ?? '—',
                'expired_date' => safeDateFormat($batch->expired_date),
                'source_type' => $sourceType,
            ];
        })->filter()->values();

        return response()->json(['data' => $results]);
    }

    public function storeTransferRequest(Request $request)
    {
        $validated = $request->validate([
            'source_pharmacy_id' => 'required|exists:pharmacies,id',
            'items' => 'required|array|min:1',
            'items.*.batches_id' => 'required|exists:batches,id',
            'items.*.etalases_id' => 'nullable|exists:etalases,id',
            'items.*.source_type' => 'required|in:gudang,pelayanan',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        $requesterPharmacyId = getActivePharmacyId();
        if ((int) $validated['source_pharmacy_id'] === $requesterPharmacyId) {
            return back()->withInput()->withErrors(['source_pharmacy_id' => 'Cabang pengirim harus berbeda dengan cabang Anda.']);
        }
        if (!in_array((int) $validated['source_pharmacy_id'], [1, 2, 3, 4, 5, 9], true)
            || !Pharmacies::whereKey($validated['source_pharmacy_id'])->where('status', 1)->exists()) {
            return back()->withInput()->withErrors(['source_pharmacy_id' => 'Cabang pengirim tidak aktif.']);
        }

        $transfer = DB::transaction(function () use ($validated, $requesterPharmacyId) {
            $sourcePharmacyId = (int) $validated['source_pharmacy_id'];
            if (!in_array($sourcePharmacyId, [1, 2, 3, 4, 5, 9], true)
                || !Pharmacies::whereKey($sourcePharmacyId)->where('status', 1)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['source_pharmacy_id' => 'Cabang pengirim tidak aktif.']);
            }

            foreach ($validated['items'] as $line) {
                $sourceBatch = Batches::whereKey($line['batches_id'])
                    ->where('pharmacy_id', $sourcePharmacyId)
                    ->lockForUpdate()
                    ->first();
                if (!$sourceBatch) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Batch tidak tersedia di cabang pengirim yang dipilih.']);
                }
                $expectedSourceType = isWarehousePharmacy($sourcePharmacyId) ? 'gudang' : 'pelayanan';
                if ($line['source_type'] !== $expectedSourceType) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Jenis stok pada permintaan tidak sesuai dengan cabang pengirim.']);
                }
                $available = $expectedSourceType === 'gudang'
                    ? max(0, (int) $sourceBatch->stock)
                    : (int) MedicineTransferItems::where('batches_id', $sourceBatch->id)
                        ->where('qty', '>', 0)->where('status', 1)
                        ->where(function ($query) {
                            $query->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                        })->sum('qty');
                if ((int) $line['qty'] > $available) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => "Stok {$sourceBatch->name} berubah. Stok tersedia sekarang {$available}."]);
                }
                if (!empty($line['etalases_id'])) {
                    $etalaseBelongsToReceiver = Items::whereKey($line['etalases_id'])
                        ->where(function ($query) use ($requesterPharmacyId) {
                            $query->where('pharmacy_id', $requesterPharmacyId)->orWhereNull('pharmacy_id');
                        })->exists();
                    if (!$etalaseBelongsToReceiver) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Etalase harus berasal dari cabang penerima.']);
                    }
                }
            }

            $transfer = MedicineTransfers::create([
                'code' => $this->generateTransfersCode(),
                'user_id' => auth()->id(),
                'requested_by' => auth()->id(),
                'sent_by' => null,
                'received_by' => null,
                'status' => 0,
                'is_request' => true,
                'source_pharmacy_id' => $validated['source_pharmacy_id'],
                'destination_pharmacy_id' => $requesterPharmacyId,
                'request_status' => 0,
            ]);

            foreach ($validated['items'] as $line) {
                MedicineTransferItems::create([
                    'medicine_transfer_id' => $transfer->id,
                    'requested_medicine_id' => Batches::find($line['batches_id'])->medicine_id,
                    'source_batches_id' => $line['batches_id'],
                    'source_type' => $line['source_type'],
                    'etalases_id' => $line['etalases_id'] ?? null,
                    'qty' => $line['qty'],
                    'status' => 0,
                ]);
            }

            return $transfer;
        });

        return redirect()->route('transfers.incoming', ['tab' => 'requests'])
            ->with('success', "Permintaan mutasi {$transfer->code} berhasil dikirim.");
    }

    public function approveTransferRequest(Request $request, MedicineTransfers $transfer)
    {
        $pharmacyId = getActivePharmacyId();
        if (!$transfer->is_request || (int) $transfer->request_status !== 0 || (int) $transfer->source_pharmacy_id !== $pharmacyId) {
            return back()->with('message', 'Permintaan ini tidak tersedia untuk disetujui oleh cabang Anda.');
        }

        if (!$this->resolvePinActor($request)) {
            return back()->with('message', 'PIN pribadi salah atau belum diisi. Mutasi keluar dibatalkan.');
        }

        try {
            DB::transaction(function () use ($transfer, $pharmacyId) {
                $transfer = MedicineTransfers::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if (!$transfer->is_request || (int) $transfer->request_status !== 0 || (int) $transfer->source_pharmacy_id !== $pharmacyId) {
                    throw new \RuntimeException('Status permintaan sudah berubah. Muat ulang halaman.');
                }

                $now = Carbon::now();
                $requestItems = $transfer->items()->whereNull('batches_id')->lockForUpdate()->get();
                if ($requestItems->isEmpty()) {
                    throw new \RuntimeException('Tidak ada item permintaan yang dapat diproses.');
                }

                foreach ($requestItems as $requestedItem) {
                    $remaining = (int) $requestedItem->qty;
                    $medicineId = (int) $requestedItem->requested_medicine_id;
                    $batches = Batches::where('pharmacy_id', $pharmacyId)
                        ->where('medicine_id', $medicineId)
                        ->when($requestedItem->source_batches_id, fn($query, $batchId) => $query->whereKey($batchId))
                        ->orderByRaw('expired_date IS NULL, expired_date ASC')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    foreach ($batches as $sourceBatch) {
                        if ($remaining <= 0) break;
                        $sourceType = isWarehousePharmacy($pharmacyId) ? 'gudang' : 'pelayanan';
                        $available = $sourceType === 'gudang'
                            ? max(0, (int) $sourceBatch->stock)
                            : (int) MedicineTransferItems::where('batches_id', $sourceBatch->id)
                                ->where('qty', '>', 0)->where('status', 1)
                                ->where(function ($query) {
                                    $query->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                                })->sum('qty');

                        if ($available <= 0) continue;
                        $qty = min($remaining, $available);
                        $destinationBatch = Batches::firstOrCreate(
                            [
                                'pharmacy_id' => $transfer->destination_pharmacy_id,
                                'medicine_id' => $sourceBatch->medicine_id,
                                'name' => $sourceBatch->name,
                                'expired_date' => $sourceBatch->expired_date,
                            ],
                            ['status' => 0, 'stock' => 0]
                        );

                        if ($sourceType === 'gudang') {
                            $qtyBefore = (int) $sourceBatch->stock;
                            $sourceBatch->decrement('stock', $qty);
                            $qtyAfter = (int) $sourceBatch->fresh()->stock;
                        } else {
                            $qtyBefore = $available;
                            $toDeduct = $qty;
                            $sourceRows = MedicineTransferItems::where('batches_id', $sourceBatch->id)
                                ->where('qty', '>', 0)->where('status', 1)
                                ->where(function ($query) {
                                    $query->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                                })->orderBy('id')->lockForUpdate()->get();
                            foreach ($sourceRows as $sourceRow) {
                                if ($toDeduct <= 0) break;
                                $deduct = min((int) $sourceRow->qty, $toDeduct);
                                $sourceRow->decrement('qty', $deduct);
                                $toDeduct -= $deduct;
                            }
                            $qtyAfter = $qtyBefore - $qty;
                        }

                        $itemData = [
                            'batches_id' => $destinationBatch->id,
                            'source_batches_id' => $sourceBatch->id,
                            'source_type' => $sourceType,
                            'etalases_id' => $requestedItem->etalases_id ?: null,
                            'qty' => $qty,
                            'original_qty' => $qty,
                            'status' => 0,
                            'stock_deducted_at' => $now,
                        ];
                        if ($requestedItem->batches_id === null) {
                            $requestedItem->update($itemData);
                        } else {
                            $itemData['medicine_transfer_id'] = $transfer->id;
                            $itemData['requested_medicine_id'] = $medicineId;
                            MedicineTransferItems::create($itemData);
                        }

                        Medicines::whereKey($medicineId)->decrement('stock', $qty);
                        ItemsLog::create([
                            'transaction_code' => $transfer->code,
                            'code' => $this->generateItemsLogCode(),
                            'type' => 'MU',
                            'medicine_id' => $medicineId,
                            'qty' => $qty,
                            'qty_before' => $qtyBefore,
                            'qty_after' => $qtyAfter,
                            'total' => 0,
                            'date' => $now,
                            'status' => 7,
                            'batches_id' => $sourceBatch->id,
                            'user_id' => $this->actorId(),
                        ]);
                        $remaining -= $qty;
                    }

                    if ($remaining > 0) {
                        throw new \RuntimeException('Stok ' . (Medicines::find($medicineId)?->name ?? 'obat') . " tidak mencukupi. Kekurangan {$remaining}. Tidak ada stok yang dikurangi.");
                    }
                }

                $transfer->update([
                    'request_status' => 1,
                    'user_id' => auth()->id(),
                    'sent_by' => $this->actorId(),
                ]);
            });

            return back()->with('success', 'Permintaan disetujui. Mutasi keluar tercatat dan stok sumber telah dikurangi.');
        } catch (\Throwable $e) {
            return back()->with('message', 'Permintaan gagal diproses: ' . $e->getMessage());
        }
    }

    public function denyTransferRequest(MedicineTransfers $transfer)
    {
        if (!$transfer->is_request || (int) $transfer->request_status !== 0 || (int) $transfer->source_pharmacy_id !== getActivePharmacyId()) {
            return back()->with('message', 'Permintaan ini tidak tersedia untuk ditolak oleh cabang Anda.');
        }

        DB::transaction(function () use ($transfer) {
            $transfer->items()->where('status', 0)->update(['status' => 2]);
            $transfer->update(['request_status' => 2, 'status' => 2]);
        });

        return back()->with('success', 'Permintaan mutasi ditolak. Stok tidak berubah.');
    }

    public function transfer(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'submission_key' => 'required|uuid',
            'pharmacy' => 'required|exists:pharmacies,id',
            'items' => 'required|array|min:1',
            'items.*.batches_id' => 'required|exists:batches,id',
            'items.*.etalases_id' => 'nullable|exists:etalases,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.source_type' => 'required|in:gudang,pelayanan',
            'pin' => 'nullable|string',
        ]);

        if (!$this->resolvePinActor($request)) {
            return response()->json(['success' => false, 'message' => 'PIN pribadi salah atau belum diisi. Mutasi keluar dibatalkan.'], 422);
        }

        $submissionKey = $request->input('submission_key');
        $existingTransfer = MedicineTransfers::where('submission_key', $submissionKey)->first();
        if ($existingTransfer) {
            $sameSubmission = (int) $existingTransfer->user_id === (int) auth()->id()
                && (int) $existingTransfer->source_pharmacy_id === (int) getActivePharmacyId()
                && (int) $existingTransfer->destination_pharmacy_id === (int) $request->pharmacy;

            return $sameSubmission
                ? response()->json(['success' => true, 'message' => 'Transfer ini sudah tersimpan.'])
                : response()->json(['success' => false, 'message' => 'Kunci pengiriman sudah digunakan. Muat ulang halaman dan periksa data transfer.'], 409);
        }

        try {
            DB::transaction(function () use ($request) {
                $now = Carbon::now();
                $transfer = MedicineTransfers::create([
                    'code' => $request->code,
                    'submission_key' => $request->submission_key,
                    'user_id' => auth()->id(),
                    'sent_by' => $this->actorId(),
                    'status' => 0,
                    'source_pharmacy_id' => getActivePharmacyId(),
                    'destination_pharmacy_id' => (int) $request->pharmacy,
                ]);

                $pharmacyId = getActivePharmacyId();

                if (isWarehousePharmacy($pharmacyId)) {
                    if (!in_array((int) $request->pharmacy, [1, 2, 3, 4, 5], true)) {
                        throw new \Exception("Gudang PMI hanya dapat melakukan mutasi ke cabang pelayanan.");
                    }
                } else {
                    if ((int) $request->pharmacy === (int) $pharmacyId || !in_array((int) $request->pharmacy, [1, 2, 3, 4, 5, 9], true)) {
                        throw new \Exception("Apotek tujuan tidak valid.");
                    }
                }

                // Aggregate items with identical batches_id and source_type to prevent duplicate entries
                $aggregatedItems = [];
                foreach ($request->items as $line) {
                    $key = $line['batches_id'] . '_' . ($line['source_type'] ?? 'gudang');
                    if (isset($aggregatedItems[$key])) {
                        $aggregatedItems[$key]['qty'] += (int) $line['qty'];
                    } else {
                        $aggregatedItems[$key] = [
                            'batches_id' => $line['batches_id'],
                            'source_type' => $line['source_type'] ?? 'gudang',
                            'etalases_id' => $line['etalases_id'] ?? null,
                            'qty' => (int) $line['qty'],
                        ];
                    }
                }

                foreach ($aggregatedItems as $line) {
                    $sourceBatch = Batches::lockForUpdate()->findOrFail($line['batches_id']);
                    if ((int) $sourceBatch->pharmacy_id !== $pharmacyId) {
                        throw new \Exception("Batch sumber bukan milik cabang Anda.");
                    }
                    $sourceType = $line['source_type'];
                    $qty = (int) $line['qty'];
                    $medicine = $sourceBatch->medicines;

                    // Validate source_type vs pharmacy role
                    if ($sourceType === 'gudang' && !isWarehousePharmacy($pharmacyId)) {
                        throw new \Exception("Hanya gudang yang bisa transfer dari stok gudang.");
                    }
                    if ($sourceType === 'pelayanan' && isWarehousePharmacy($pharmacyId)) {
                        throw new \Exception("Gudang hanya dapat mentransfer dari stok gudang.");
                    }

                    // ── Check & Immediately Deduct Source Stock ───────────────
                    if ($sourceType === 'gudang') {
                        // Gudang: deduct directly from batches.stock
                        $availStock = (int) $sourceBatch->stock;
                        if ($qty > $availStock) {
                            throw new \Exception("Qty untuk {$sourceBatch->name} ({$medicine?->name}) melebihi stok gudang tersedia ({$availStock}).");
                        }
                        $srcQtyBefore = $sourceBatch->stock;
                        $sourceBatch->decrement('stock', $qty);
                        $srcQtyAfter = $sourceBatch->fresh()->stock;
                    } else {
                        // Pelayanan: deduct from MTI records (FIFO)
                        $mtiList = MedicineTransferItems::where('batches_id', $sourceBatch->id)
                            ->where('qty', '>', 0)
                            ->where('status', 1)
                            ->where(function ($q) {
                                $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                            })
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->get();
                        $totalMtiQty = $mtiList->sum('qty');

                        if ($qty > $totalMtiQty) {
                            throw new \Exception("Qty untuk {$sourceBatch->name} ({$medicine?->name}) melebihi stok pelayanan tersedia ({$totalMtiQty}).");
                        }

                        $srcQtyBefore = $totalMtiQty;
                        $toDeduct = $qty;
                        foreach ($mtiList as $mtiRow) {
                            if ($toDeduct <= 0) break;
                            $deduct = min($mtiRow->qty, $toDeduct);
                            $mtiRow->decrement('qty', $deduct);
                            $toDeduct -= $deduct;
                        }
                        $srcQtyAfter = max(0, $srcQtyBefore - $qty);
                    }

                    // ── Find or create destination batch ──────────────────────
                    $destBatch = Batches::firstOrCreate(
                        [
                            'pharmacy_id' => $request->pharmacy,
                            'medicine_id' => $sourceBatch->medicine_id,
                            'name' => $sourceBatch->name,
                            'expired_date' => $sourceBatch->expired_date,
                        ],
                        ['status' => 0, 'stock' => 0]
                    );

                    // ── Create transfer item with stock_deducted_at ───────────
                    MedicineTransferItems::create([
                        'medicine_transfer_id' => $transfer->id,
                        'batches_id' => $destBatch->id,
                        'source_batches_id' => $sourceBatch->id,
                        'source_type' => $sourceType,
                        'etalases_id' => $line['etalases_id'] ?? null,
                        'qty' => $qty,
                        'original_qty' => $qty,
                        'status' => 0,
                        'stock_deducted_at' => $now,
                    ]);

                    // Sync medicines master stock
                    $medRecord = Medicines::find($sourceBatch->medicine_id);
                    if ($medRecord) {
                        $medRecord->decrement('stock', $qty);
                    }

                    // ── Log source outgoing ───────────────────────────────────
                    ItemsLog::create([
                        'transaction_code' => $transfer->code,
                        'code' => $this->generateItemsLogCode(),
                        'type' => 'MU',
                        'medicine_id' => $sourceBatch->medicine_id,
                        'qty' => $qty,
                        'qty_before' => $srcQtyBefore,
                        'qty_after' => $srcQtyAfter,
                        'total' => 0,
                        'date' => $now,
                        'status' => 7,
                        'batches_id' => $sourceBatch->id,
                        'user_id' => $this->actorId(),
                    ]);
                }
            });

            return response()->json(['success' => true, 'message' => 'Transfer disimpan. Stok telah dikurangi.']);
        } catch (\Throwable $e) {
            // A concurrent retry can race the initial lookup. The unique index
            // makes the second request roll back; return the first result.
            $existingTransfer = MedicineTransfers::where('submission_key', $submissionKey)->first();
            if ($existingTransfer
                && (int) $existingTransfer->user_id === (int) auth()->id()
                && (int) $existingTransfer->source_pharmacy_id === (int) getActivePharmacyId()
                && (int) $existingTransfer->destination_pharmacy_id === (int) $request->pharmacy) {
                return response()->json(['success' => true, 'message' => 'Transfer ini sudah tersimpan.']);
            }

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function printReceipt($id)
    {
        $transfer = MedicineTransfers::with([
            'items.batches.medicines',
            'items.batches.pharmacy',
            'items.sourceBatch.medicines',
            'items.sourceBatch.pharmacy',
            'items.requestedMedicine',
            'users.pharmacy',
            'sender.pharmacy',
            'receiver.pharmacy',
            'requester.pharmacy',
            'sourcePharmacy',
            'destinationPharmacy',
        ])->findOrFail($id);
        $this->authorizeTransferParticipant($transfer);

        // Urut abjad berdasarkan nama item obat
        $sortedItems = $transfer->items->sort(function ($a, $b) {
            $nameA = trim((string) ($a->batches?->medicines?->name ?? $a->requestedMedicine?->name ?? ''));
            $nameB = trim((string) ($b->batches?->medicines?->name ?? $b->requestedMedicine?->name ?? ''));
            $cmp = strcasecmp($nameA, $nameB);
            if ($cmp === 0) {
                $batchA = trim((string) ($a->batches?->name ?? $a->sourceBatch?->name ?? ''));
                $batchB = trim((string) ($b->batches?->name ?? $b->sourceBatch?->name ?? ''));
                return strcasecmp($batchA, $batchB);
            }
            return $cmp;
        })->values();

        $transfer->setRelation('items', $sortedItems);

        // Akun pengeluar stok (Yang Menyerahkan) - BUKAN akun yang sedang login
        $senderUser = null;
        if ($transfer->sent_by) {
            $senderUser = $transfer->sender ?: \App\Models\User::find($transfer->sent_by);
        } elseif ($transfer->is_request) {
            // Untuk Permintaan Mutasi:
            // Hanya ada pengirim jika permintaan sudah disetujui/dikeluarkan stoknya (request_status == 1)
            if ((int) $transfer->request_status === 1) {
                $outgoingLog = \App\Models\ItemsLog::where('transaction_code', $transfer->code)
                    ->where('type', 'MU')
                    ->whereRaw('qty_before > qty_after')
                    ->first();
                if ($outgoingLog && $outgoingLog->user_id) {
                    $senderUser = \App\Models\User::find($outgoingLog->user_id);
                } elseif ($transfer->user_id && (int) $transfer->user_id !== (int) $transfer->requested_by) {
                    $senderUser = $transfer->users ?: \App\Models\User::find($transfer->user_id);
                }
            }
        } else {
            // Mutasi Langsung: pembuat mutasi adalah pengirim barang
            $senderUser = $transfer->users ?: ($transfer->user_id ? \App\Models\User::find($transfer->user_id) : null);
        }
        $senderName = $senderUser?->name ?? ($senderUser?->username ?? null);

        // Akun penerima (Yang Menerima) - HANYA orang yang benar-benar menekan tombol terima
        $receiverName = null;
        if ((int) $transfer->status === 1) {
            $receiverUser = null;
            if ($transfer->received_by) {
                $receiverUser = $transfer->receiver ?: \App\Models\User::find($transfer->received_by);
            } else {
                // Fallback transfer riwayat: ambil user_id dari log penerimaan MU
                $receiverLog = \App\Models\ItemsLog::where('transaction_code', $transfer->code)
                    ->where('type', 'MU')
                    ->whereRaw('qty_after > qty_before')
                    ->first();
                if ($receiverLog && $receiverLog->user_id) {
                    $receiverUser = \App\Models\User::find($receiverLog->user_id);
                }
            }
            $receiverName = $receiverUser?->name ?? ($receiverUser?->username ?? null);
        }

        $pdf = Pdf::loadView('kasir.transfers.receipt', compact('transfer', 'senderName', 'receiverName'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('tanda-terima-barang-' . $transfer->code . '.pdf');
        // use ->download(...) instead of ->stream(...) if you want a forced download
    }

    public function index(Request $request)
    {
        $pharmacyId = (int) ($request->query('pharmacy_id') ?: getActivePharmacyId());

        return response()->json(
            $this->getEtalasesForPharmacy($pharmacyId)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'pharmacy_id' => 'nullable|integer',
            'pin' => 'nullable|string',
        ]);

        if ($request->filled('pin') && !$this->resolvePinActor($request)) {
            return response()->json(['message' => 'PIN pribadi salah.'], 422);
        }

        $actorId = $this->actorId();
        $pharmacyId = !empty($validated['pharmacy_id']) ? (int) $validated['pharmacy_id'] : (getActivePharmacyId() ?: null);
        $name = trim($validated['name']);

        $duplicate = Items::where('pharmacy_id', $pharmacyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->exists();
        if ($duplicate) {
            return response()->json(['message' => 'Nama etalase sudah ada di cabang ini.'], 422);
        }

        $payload = [
            'name' => $name,
            'pharmacy_id' => $pharmacyId,
        ];
        if ($this->hasEtalaseAuditColumns()) {
            $payload['created_by'] = $actorId;
            $payload['updated_by'] = $actorId;
        }

        $etalase = Items::create($payload);
        if ($this->hasEtalaseAuditColumns()) {
            $etalase->load(['creator:id,name,username', 'updater:id,name,username']);
        }

        return response()->json($this->formatEtalasePayload($etalase), 201);
    }

    public function update(Request $request, Items $etalase)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'pin' => 'nullable|string',
        ]);

        if ($request->filled('pin') && !$this->resolvePinActor($request)) {
            return response()->json(['message' => 'PIN pribadi salah.'], 422);
        }

        $activePharmacyId = (int) getActivePharmacyId();
        if ($etalase->pharmacy_id !== null && (int) $etalase->pharmacy_id !== $activePharmacyId) {
            return response()->json(['message' => 'Etalase bukan milik cabang aktif.'], 403);
        }

        $name = trim($validated['name']);
        $duplicate = Items::where('pharmacy_id', $etalase->pharmacy_id)
            ->where('id', '!=', $etalase->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->exists();
        if ($duplicate) {
            return response()->json(['message' => 'Nama etalase sudah ada di cabang ini.'], 422);
        }

        $payload = ['name' => $name];
        if ($this->hasEtalaseAuditColumns()) {
            $payload['updated_by'] = $this->actorId();
        }

        $etalase->update($payload);
        if ($this->hasEtalaseAuditColumns()) {
            $etalase->load(['creator:id,name,username', 'updater:id,name,username']);
        }

        return response()->json($this->formatEtalasePayload($etalase));
    }

    public function destroy(Request $request, Items $etalase)
    {
        if ($request->filled('pin') && !$this->resolvePinActor($request)) {
            return response()->json(['message' => 'PIN pribadi salah.'], 422);
        }

        $activePharmacyId = (int) getActivePharmacyId();
        if ($etalase->pharmacy_id !== null && (int) $etalase->pharmacy_id !== $activePharmacyId) {
            return response()->json(['message' => 'Etalase bukan milik cabang aktif.'], 403);
        }

        $id = $etalase->id;
        $actorId = $this->actorId();
        $now = Carbon::now();

        DB::transaction(function () use ($etalase, $actorId, $now) {
            $itemUpdate = ['etalases_id' => null];
            if ($this->hasTransferItemEtalaseAuditColumns()) {
                $itemUpdate['etalase_updated_by'] = $actorId;
                $itemUpdate['etalase_updated_at'] = $now;
            }
            MedicineTransferItems::where('etalases_id', $etalase->id)->update($itemUpdate);
            $etalase->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Etalase berhasil dihapus.',
            'deleted_id' => $id,
        ]);
    }

    public function updateItemEtalase(Request $request, MedicineTransferItems $item)
    {
        $currentPharmacyId = (int) getActivePharmacyId();
        $item->loadMissing(['batches', 'transfer']);
        $isReceiver = (int) ($item->batches?->pharmacy_id) === $currentPharmacyId
            || (int) ($item->transfer?->destination_pharmacy_id) === $currentPharmacyId;
        if (!$isReceiver) {
            return response()->json(['message' => 'Hanya cabang penerima yang dapat mengubah etalase item ini.'], 403);
        }

        if (!$this->resolvePinActor($request)) {
            return response()->json(['message' => 'PIN pribadi salah atau belum diisi.'], 422);
        }

        $rawEtalaseId = $request->input('etalases_id');
        if ($rawEtalaseId === 'default') {
            $chosenEtalaseId = $this->resolveDefaultEtalaseForItem($item, $currentPharmacyId)['id'];
            if (!$chosenEtalaseId) {
                return response()->json(['message' => 'Tidak ada etalase tujuan yang tersedia untuk cabang ini.'], 422);
            }
        } elseif ($rawEtalaseId === null || $rawEtalaseId === '') {
            $chosenEtalaseId = null;
        } else {
            $chosenEtalaseId = $this->resolveDestinationEtalaseId($rawEtalaseId, $currentPharmacyId);
            if (!$chosenEtalaseId) {
                return response()->json(['message' => 'Etalase tujuan tidak valid untuk cabang ini.'], 422);
            }
        }

        $actorId = $this->actorId();
        $now = Carbon::now();
        $updateData = ['etalases_id' => $chosenEtalaseId];
        if ($this->hasTransferItemEtalaseAuditColumns()) {
            $updateData['etalase_updated_by'] = $actorId;
            $updateData['etalase_updated_at'] = $now;
        }
        $item->update($updateData);

        $actorUser = $actorId ? User::find($actorId) : null;
        $actorName = $actorUser ? ($actorUser->name ?: ($actorUser->username ?? 'User')) : 'User';

        return response()->json([
            'success' => true,
            'message' => 'Etalase obat berhasil diperbarui.',
            'etalases_id' => $chosenEtalaseId,
            'etalase_name' => $item->fresh('etalases')?->etalases?->name ?? 'Belum dipilih',
            'updated_by_name' => $actorName,
            'updated_at_formatted' => $now->format('d M Y H:i'),
        ]);
    }
    public function incomingTransfers()
    {
        $pharmacyId = getActivePharmacyId();
        $search = request('search');
        $startDate = request('start_date');
        $endDate = request('end_date');
        $expiredDate = request('expired_date');
        $status = request('status'); // '', 'unreceived', 'received'

        $applyFilters = function ($query) use ($search, $startDate, $endDate, $expiredDate, $status) {
            $query->whereDoesntHave('items', function ($q) {
                $q->whereNotNull('receiving_items_id');
            });

            if ($search) {
                $query->where(function ($sq) use ($search) {
                    $sq->where('code', 'like', "%{$search}%")
                        ->orWhereHas('items.batches.medicines', function ($subQ) use ($search) {
                            $subQ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items.sourceBatch.medicines', function ($subQ) use ($search) {
                            $subQ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            }

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }

            if ($expiredDate) {
                $query->whereHas('items.batches', function ($subQ) use ($expiredDate) {
                    $subQ->whereDate('expired_date', $expiredDate);
                });
            }

            if ($status === 'unreceived') {
                $query->where('status', '!=', 2)
                    ->where(function ($sq) {
                        $sq->where('status', 0)
                            ->orWhereHas('items', fn($i) => $i->where('status', 0));
                    });
            } elseif ($status === 'received') {
                $query->where('status', 1)
                    ->whereDoesntHave('items', fn($i) => $i->where('status', 0));
            }
        };

        // ── Mutasi Keluar: transfers WHERE source batch belongs to this pharmacy ──
        $pendingRequests = MedicineTransfers::with(['users.pharmacy', 'sourcePharmacy', 'destinationPharmacy', 'sender', 'receiver', 'requester', 'items.requestedMedicine', 'items.sourceBatch'])
            ->where('is_request', true)->where('request_status', 0)
            ->where('source_pharmacy_id', $pharmacyId)->latest()->get();
        $submittedRequests = MedicineTransfers::with(['users.pharmacy', 'sourcePharmacy', 'destinationPharmacy', 'sender', 'receiver', 'requester', 'items.requestedMedicine', 'items.sourceBatch'])
            ->where('is_request', true)->where('destination_pharmacy_id', $pharmacyId)->latest()->get();

        $pendingQuery = MedicineTransfers::with(['users.pharmacy', 'sourcePharmacy', 'destinationPharmacy', 'sender', 'receiver', 'requester'])
            ->where(function ($query) {
                $query->where('is_request', false)->orWhere('request_status', 1);
            })
            ->where(function ($q) use ($pharmacyId) {
                $q->where('source_pharmacy_id', $pharmacyId)
                    ->orWhereHas('items.sourceBatch', fn($sb) => $sb->where('pharmacy_id', $pharmacyId))
                    ->orWhere(function ($q2) use ($pharmacyId) {
                        $q2->whereDoesntHave('items.sourceBatch')
                            ->whereHas('users', fn($u) => $u->where('pharmacy_id', $pharmacyId));
                    });
            })
            ->latest();
        $applyFilters($pendingQuery);
        $pending = $pendingQuery
            ->paginate(10, ['*'], 'pending_page')
            ->fragment('pending')
            ->withQueryString();

        // ── Mutasi Masuk: transfers WHERE destination batch belongs to this pharmacy ──
        $acceptedQuery = MedicineTransfers::with(['users.pharmacy', 'sourcePharmacy', 'destinationPharmacy', 'sender', 'receiver', 'requester'])
            ->where(function ($query) {
                $query->where('is_request', false)->orWhere('request_status', 1);
            })
            ->where(function ($q) use ($pharmacyId) {
                $q->where('destination_pharmacy_id', $pharmacyId)
                    ->orWhereHas('items.batches', fn($b) => $b->where('pharmacy_id', $pharmacyId));
            })
            ->whereIn('status', [0, 1])
            ->latest();
        $applyFilters($acceptedQuery);
        $accepted = $acceptedQuery
            ->paginate(10, ['*'], 'accepted_page')
            ->fragment('accepted')
            ->withQueryString();

        // ── Ditolak: transfers with denied status involving this pharmacy ──
        $deniedQuery = MedicineTransfers::with(['users.pharmacy', 'sourcePharmacy', 'destinationPharmacy', 'sender', 'receiver', 'requester'])
            ->where(function ($q) {
                $q->where('status', 2)
                    ->orWhereHas('items', fn($i) => $i->where('status', 2));
            })
            ->where(function ($q) use ($pharmacyId) {
                $q->where('source_pharmacy_id', $pharmacyId)
                    ->orWhere('destination_pharmacy_id', $pharmacyId)
                    ->orWhereHas('items.sourceBatch', fn($sb) => $sb->where('pharmacy_id', $pharmacyId))
                    ->orWhereHas('items.batches', fn($b) => $b->where('pharmacy_id', $pharmacyId));
            })
            ->latest();
        $applyFilters($deniedQuery);
        $denied = $deniedQuery
            ->paginate(10, ['*'], 'denied_page')
            ->fragment('denied')
            ->withQueryString();

        $etalases = $this->getEtalasesForPharmacy((int) $pharmacyId);

        return view('kasir.transfers.transfers', compact('pending', 'accepted', 'denied', 'pendingRequests', 'submittedRequests', 'etalases'));
    }

    public function exportTransfers(Request $request)
    {
        $pharmacyId = getActivePharmacyId();
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $search = $request->search;
        $type = $request->type ?? 'semua'; // pending, accepted, denied, semua
        $expiredDate = $request->expired_date;
        $status = $request->status;

        $job = ExportJob::create([
            'type' => 'transfers',
            'status' => 'pending',
            'progress' => 0
        ]);

        dispatch(new ProcessTransfersExport(
            $job->id,
            $pharmacyId,
            $startDate,
            $endDate,
            $search,
            $type,
            $expiredDate,
            $status
        ));

        return response()->json([
            'job_id' => $job->id,
            'message' => 'Export started.'
        ]);
    }

    public function exportStatus($id)
    {
        $job = ExportJob::findOrFail($id);

        return response()->json([
            'status' => $job->status,
            'progress' => $job->progress,
            'file' => $job->file_path ? asset('storage/' . $job->file_path) : null
        ]);
    }
    /**
     * Process item acceptance: ONLY increment destination stock.
     * Source stock was already deducted at transfer creation time.
     */
    private function processItemTransferStock($item, $transfer, $now)
    {
        // 1. Strict guard: If item is not in pending status (0), skip processing to prevent duplicate stock addition
        if ((int) $item->status !== 0) {
            return;
        }

        $destBatch = $item->batches;
        $srcBatch = $item->sourceBatch;
        if (!$srcBatch) {
            $srcBatch = Batches::where('medicine_id', $destBatch->medicine_id)
                ->where('pharmacy_id', 9)
                ->first() ?? $destBatch;
        }
        $medicine = Medicines::findOrFail($destBatch->medicine_id);
        $sourceType = $item->source_type ?? 'gudang';

        $destPharmacyId = $destBatch->pharmacy_id;
        $srcPharmacyId = $srcBatch->pharmacy_id;

        // 2. Strict idempotency guard: check if an incoming log for this transfer code and dest batch already exists
        $hasIncomingLog = ItemsLog::where('transaction_code', $transfer->code)
            ->where('batches_id', $destBatch->id)
            ->where('status', 7)
            ->where('type', 'MU')
            ->where('medicine_id', $medicine->id)
            ->where('qty', $item->qty)
            ->whereRaw('qty_after > qty_before')
            ->exists();

        if ($hasIncomingLog) {
            $item->update(['status' => 1]);
            return;
        }

        // Determine if this is a "return to gudang" (pelayanan → gudang destination)
        $isReturnToGudang = ($sourceType === 'pelayanan' && (isWarehousePharmacy($destPharmacyId) || ($srcPharmacyId == $destPharmacyId && $destPharmacyId == 1)));

        // ── Increment destination ONLY (source already deducted on create) ──
        if ($isReturnToGudang) {
            // Return to gudang: increment batches.stock
            $destQtyBefore = $destBatch->stock;
            $destBatch->increment('stock', $item->qty);
            $destQtyAfter = $destBatch->fresh()->stock;

            $itemUpdate = [
                'status' => 1,
                'source_type' => 'retur_gudang',
                'etalases_id' => $item->etalases_id,
            ];
            if ($this->hasTransferItemEtalaseAuditColumns() && $item->etalases_id) {
                $itemUpdate['etalase_updated_by'] = $this->actorId();
                $itemUpdate['etalase_updated_at'] = $now;
            }
            $item->update($itemUpdate);
        } else {
            // Add to pelayanan: $item itself becomes the stock record at destination
            $destQtyBefore = (int) MedicineTransferItems::where('batches_id', $destBatch->id)
                ->where('status', 1)
                ->where('id', '!=', $item->id)
                ->where(function ($q) {
                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                })
                ->sum('qty');

            $itemUpdate = [
                'status' => 1,
                'etalases_id' => $item->etalases_id,
            ];
            if ($this->hasTransferItemEtalaseAuditColumns() && $item->etalases_id) {
                $itemUpdate['etalase_updated_by'] = $this->actorId();
                $itemUpdate['etalase_updated_at'] = $now;
            }
            $item->update($itemUpdate);

            $destQtyAfter = $destQtyBefore + $item->qty;
        }

        // ── Log destination (incoming) ────────────────────────────
        ItemsLog::create([
            'transaction_code' => $transfer->code,
            'code' => $this->generateItemsLogCode(),
            'type' => 'MU',
            'medicine_id' => $medicine->id,
            'qty' => $item->qty,
            'qty_before' => $destQtyBefore,
            'qty_after' => $destQtyAfter,
            'total' => 0,
            'date' => $now,
            'status' => 7,
            'batches_id' => $destBatch->id,
            'user_id' => $this->actorId(),
        ]);
    }

    public function acceptTransfer(Request $request, MedicineTransfers $transfer)
    {
        $currentPharmacyId = getActivePharmacyId();
        if ($transfer->is_request && (int) $transfer->request_status !== 1) {
            return redirect(url()->previous())->with('message', 'Permintaan mutasi harus disetujui pengirim terlebih dahulu.');
        }
        if ($transfer->destination_pharmacy_id && (int) $transfer->destination_pharmacy_id !== $currentPharmacyId) {
            return redirect(url()->previous())->with('message', 'Hanya cabang penerima yang berhak menerima mutasi ini.');
        }
        if ((int) $transfer->status === 1) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Mutasi ini sudah pernah diterima sebelumnya.');
        }
        $firstItem = $transfer->items()->first();
        $destinationPharmacyId = $firstItem?->batches?->pharmacy_id;

        if ($destinationPharmacyId && $destinationPharmacyId != $currentPharmacyId) {
            return redirect(url()->previous())->with('message', 'Hanya apotek tujuan yang berhak menerima mutasi ini.');
        }

        if (!$this->resolvePinActor($request)) {
            return redirect(url()->previous() . '#accepted')->with('message', 'PIN pribadi salah atau belum diisi. Penerimaan mutasi dibatalkan.');
        }

        $rawBulkEtalase = $request->input('etalases_id');
        $isBulkDefault = ($rawBulkEtalase === 'default');
        $defaultEtalaseId = $isBulkDefault ? null : $this->resolveDestinationEtalaseId($rawBulkEtalase, (int) $currentPharmacyId);
        $itemEtalases = (array) $request->input('item_etalases', []);
        $branchEtalases = isWarehousePharmacy($currentPharmacyId)
            ? collect()
            : $this->getEtalasesForPharmacy((int) $currentPharmacyId);

        try {
            DB::transaction(function () use ($transfer, $currentPharmacyId, $defaultEtalaseId, $isBulkDefault, $itemEtalases, $branchEtalases) {
                $now = Carbon::now();
                $lockedTransfer = MedicineTransfers::whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();
                if ((int) $lockedTransfer->status === 1) {
                    return;
                }

                $pendingItems = $lockedTransfer->items()->with('batches')->where('status', 0)->lockForUpdate()->get();

                foreach ($pendingItems as $item) {
                    $rawItemChoice = $itemEtalases[$item->id] ?? null;
                    if ($rawItemChoice === 'default' || (($rawItemChoice === null || $rawItemChoice === '') && $isBulkDefault)) {
                        $chosenEtalaseId = $this->resolveDefaultEtalaseForItem($item, (int) $currentPharmacyId, $branchEtalases)['id'];
                    } else {
                        $chosenEtalaseId = $this->resolveDestinationEtalaseId($rawItemChoice, (int) $currentPharmacyId)
                            ?: $defaultEtalaseId
                            ?: $this->resolveDestinationEtalaseId($item->etalases_id, (int) $currentPharmacyId);
                    }

                    if (!isWarehousePharmacy($currentPharmacyId) && !$chosenEtalaseId) {
                        throw new \RuntimeException('Pilih etalase tujuan terlebih dahulu untuk semua obat sebelum menerima mutasi.');
                    }

                    if ($chosenEtalaseId) {
                        $item->etalases_id = $chosenEtalaseId;
                    }

                    $this->processItemTransferStock($item, $lockedTransfer, $now);
                }

                // ── Update parent transfer status ─────────────────────────
                if ($lockedTransfer->items()->where('status', 0)->doesntExist()) {
                    $hasAccepted = $lockedTransfer->items()->where('status', 1)->exists();
                    $lockedTransfer->update([
                        'status' => $hasAccepted ? 1 : 2,
                        'received_by' => $hasAccepted ? $this->actorId() : null,
                    ]);
                }
            });

            return redirect(url()->previous() . '#accepted')->with('success', 'Semua item diterima.');
        } catch (\Throwable $e) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Gagal: ' . $e->getMessage());
        }
    }

    public function getTransferItems($id)
    {
        $relations = ['users.pharmacy', 'receiver', 'items.batches.medicines', 'items.batches.pharmacy', 'items.etalases'];
        if ($this->hasTransferItemEtalaseAuditColumns()) {
            $relations[] = 'items.etalaseUpdater';
        }
        $transfer = MedicineTransfers::with($relations)->findOrFail($id);
        $currentPharmacyId = getActivePharmacyId();
        $isParticipant = (int) $transfer->source_pharmacy_id === $currentPharmacyId
            || (int) $transfer->destination_pharmacy_id === $currentPharmacyId
            || $transfer->items()->whereHas('sourceBatch', fn($query) => $query->where('pharmacy_id', $currentPharmacyId))->exists()
            || $transfer->items()->whereHas('batches', fn($query) => $query->where('pharmacy_id', $currentPharmacyId))->exists();
        if (!$isParticipant) abort(403);

        $statusMap = [
            0 => ['Menunggu', 'bg-amber-50 text-amber-700 border-amber-200'],
            1 => ['Diterima', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
            2 => ['Ditolak', 'bg-rose-50 text-rose-700 border-rose-200'],
        ];

        $etalases = $this->getEtalasesForPharmacy((int) $currentPharmacyId);
        $itemDefaultEtalases = [];
        if (!isWarehousePharmacy($currentPharmacyId) && $etalases->isNotEmpty()) {
            foreach ($transfer->items as $it) {
                if ((int) ($it->batches?->pharmacy_id) === (int) $currentPharmacyId && (int) $it->status !== 2) {
                    $itemDefaultEtalases[$it->id] = $this->resolveDefaultEtalaseForItem($it, (int) $currentPharmacyId, $etalases);
                }
            }
        }

        return view('kasir.transfers.partials.items_table', compact('transfer', 'statusMap', 'etalases', 'itemDefaultEtalases'));
    }

    public function acceptItem(Request $request, MedicineTransferItems $item)
    {
        $currentPharmacyId = getActivePharmacyId();
        $transfer = $item->transfer;
        if ($transfer?->is_request && (int) $transfer->request_status !== 1) {
            return redirect(url()->previous())->with('message', 'Permintaan mutasi harus disetujui pengirim terlebih dahulu.');
        }
        if ($transfer?->destination_pharmacy_id && (int) $transfer->destination_pharmacy_id !== $currentPharmacyId) {
            return redirect(url()->previous())->with('message', 'Hanya cabang penerima yang berhak menerima mutasi ini.');
        }
        if ((int) $item->status !== 0) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Item ini sudah pernah diproses.');
        }
        $destinationPharmacyId = $item->batches?->pharmacy_id;

        if ($destinationPharmacyId && $destinationPharmacyId != $currentPharmacyId) {
            return redirect(url()->previous())->with('message', 'Hanya apotek tujuan yang berhak menerima obat ini.');
        }

        if (!$this->resolvePinActor($request)) {
            return redirect(url()->previous() . '#accepted')->with('message', 'PIN pribadi salah atau belum diisi. Penerimaan item dibatalkan.');
        }

        $rawChoice = $request->input('etalases_id');
        if ($rawChoice === 'default') {
            $chosenEtalaseId = $this->resolveDefaultEtalaseForItem($item, (int) $currentPharmacyId)['id'];
        } else {
            $chosenEtalaseId = $this->resolveDestinationEtalaseId($rawChoice, (int) $currentPharmacyId)
                ?: $this->resolveDestinationEtalaseId($item->etalases_id, (int) $currentPharmacyId);
        }

        if (!isWarehousePharmacy($currentPharmacyId) && !$chosenEtalaseId) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Pilih etalase tujuan terlebih dahulu sebelum menerima obat.');
        }

        try {
            DB::transaction(function () use ($item, $chosenEtalaseId) {
                $now = Carbon::now();
                $transfer = MedicineTransfers::whereKey($item->medicine_transfer_id)->lockForUpdate()->firstOrFail();
                $lockedItem = MedicineTransferItems::whereKey($item->getKey())->lockForUpdate()->firstOrFail();
                if ((int) $lockedItem->status !== 0) {
                    return;
                }

                if ($chosenEtalaseId) {
                    $lockedItem->etalases_id = $chosenEtalaseId;
                }

                $this->processItemTransferStock($lockedItem, $transfer, $now);

                // ── Update parent transfer status ─────────────────────────
                if ($transfer->items()->where('status', 0)->doesntExist()) {
                    $hasAccepted = $transfer->items()->where('status', 1)->exists();
                    $transfer->update([
                        'status' => $hasAccepted ? 1 : 2,
                        'received_by' => $hasAccepted ? ($transfer->received_by ?: $this->actorId()) : null,
                    ]);
                } else {
                    if (!$transfer->received_by) {
                        $transfer->update([
                            'received_by' => $this->actorId(),
                        ]);
                    }
                }
            });

            return redirect(url()->previous() . '#accepted')->with('success', 'Item diterima.');
        } catch (\Throwable $e) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Gagal: ' . $e->getMessage());
        }
    }
    public function denyItem(MedicineTransferItems $item)
    {
        $transfer = $item->transfer;
        if ($transfer?->is_request && (int) $transfer->request_status !== 1) {
            return redirect(url()->previous())->with('message', 'Permintaan hanya dapat ditolak dari bagian Permintaan Masuk.');
        }
        $currentPharmacyId = getActivePharmacyId();
        $isParticipant = (int) $transfer?->source_pharmacy_id === $currentPharmacyId
            || (int) $transfer?->destination_pharmacy_id === $currentPharmacyId
            || (int) $item->sourceBatch?->pharmacy_id === $currentPharmacyId
            || (int) $item->batches?->pharmacy_id === $currentPharmacyId;
        if (!$isParticipant) abort(403);

        if ((int) $item->status !== 0) {
            return redirect(url()->previous() . '#denied')->with('message', 'Item ini sudah pernah diproses.');
        }

        try {
            DB::transaction(function () use ($item) {
                $now = Carbon::now();
                $transfer = MedicineTransfers::whereKey($item->medicine_transfer_id)->lockForUpdate()->firstOrFail();
                $lockedItem = MedicineTransferItems::whereKey($item->getKey())->lockForUpdate()->firstOrFail();

                if ((int) $lockedItem->status !== 0) {
                    return;
                }

                // Rollback source stock if it was deducted on create
                if ($lockedItem->stock_deducted_at) {
                    $this->rollbackSourceStock($lockedItem, $transfer, $now);
                }

                $lockedItem->update(['status' => 2]);

                if ($transfer->items()->where('status', 0)->doesntExist()) {
                    $hasAccepted = $transfer->items()->where('status', 1)->exists();
                    $transfer->update(['status' => $hasAccepted ? 1 : 2]);
                }
            });

            $transfer = $item->transfer->fresh();
            if ($transfer->status == 2) {
                return redirect(url()->previous() . '#denied')->with('success', 'Semua item ditolak. Stok dikembalikan.');
            }

            return redirect(url()->previous() . '#accepted')->with('success', 'Item ditolak. Stok dikembalikan.');
        } catch (\Throwable $e) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Gagal: ' . $e->getMessage());
        }
    }

    public function denyTransfer(MedicineTransfers $transfer)
    {
        if ($transfer->is_request && (int) $transfer->request_status !== 1) {
            return redirect(url()->previous())->with('message', 'Gunakan aksi pada bagian Permintaan Masuk untuk menolak permintaan.');
        }
        $currentPharmacyId = getActivePharmacyId();
        if ((int) $transfer->source_pharmacy_id !== $currentPharmacyId && (int) $transfer->destination_pharmacy_id !== $currentPharmacyId) {
            $isParticipant = $transfer->items()
                ->where(function ($query) use ($currentPharmacyId) {
                    $query->whereHas('sourceBatch', fn($batch) => $batch->where('pharmacy_id', $currentPharmacyId))
                        ->orWhereHas('batches', fn($batch) => $batch->where('pharmacy_id', $currentPharmacyId));
                })->exists();
            if (!$isParticipant) abort(403);
        }
        if ((int) $transfer->status === 2) {
            return redirect(url()->previous() . '#denied')->with('message', 'Mutasi ini sudah pernah ditolak.');
        }
        try {
            DB::transaction(function () use ($transfer) {
                $now = Carbon::now();
                $lockedTransfer = MedicineTransfers::whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();
                if ((int) $lockedTransfer->status === 2) {
                    return;
                }

                $pendingItems = $lockedTransfer->items()->where('status', 0)->lockForUpdate()->get();

                foreach ($pendingItems as $item) {
                    // Rollback source stock if it was deducted on create
                    if ($item->stock_deducted_at && (int) $item->status === 0) {
                        $this->rollbackSourceStock($item, $lockedTransfer, $now);
                    }
                    $item->update(['status' => 2]);
                }

                $hasAccepted = $lockedTransfer->items()->where('status', 1)->exists();
                $lockedTransfer->update(['status' => $hasAccepted ? 1 : 2]);
            });

            return redirect(url()->previous() . '#denied')->with('success', 'Mutasi ditolak. Stok telah dikembalikan.');
        } catch (\Throwable $e) {
            return redirect(url()->previous() . '#accepted')->with('message', 'Gagal: ' . $e->getMessage());
        }
    }

    /**
     * Rollback source stock that was deducted at transfer creation time.
     */
    private function rollbackSourceStock($item, $transfer, $now)
    {
        $srcBatch = $item->sourceBatch;
        if (!$srcBatch) return;

        $medicine = Medicines::find($srcBatch->medicine_id);
        $sourceType = $item->source_type ?? 'gudang';

        if ($sourceType === 'gudang') {
            // Return stock to batches.stock
            $srcQtyBefore = $srcBatch->stock;
            $srcBatch->increment('stock', $item->qty);
            $srcQtyAfter = $srcBatch->fresh()->stock;
        } else {
            // Return stock to pelayanan MTI records:
            // Create a new accepted MTI record to restore the pelayanan stock
            $srcQtyBefore = (int) MedicineTransferItems::where('batches_id', $srcBatch->id)
                ->where('qty', '>', 0)
                ->where('status', 1)
                ->where(function ($q) {
                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                })
                ->sum('qty');

            // Find an existing accepted MTI for this batch and increment it back
            $existingMti = MedicineTransferItems::where('batches_id', $srcBatch->id)
                ->where('status', 1)
                ->where(function ($q) {
                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                })
                ->orderBy('id', 'desc')
                ->first();

            if ($existingMti) {
                $existingMti->increment('qty', $item->qty);
            } else {
                // Edge case: no existing MTI record, create a restoration record
                MedicineTransferItems::create([
                    'medicine_transfer_id' => $transfer->id,
                    'batches_id' => $srcBatch->id,
                    'source_batches_id' => $srcBatch->id,
                    'source_type' => 'pelayanan',
                    'etalases_id' => $item->etalases_id,
                    'qty' => $item->qty,
                    'status' => 1, // accepted = stok tersedia
                ]);
            }

            $srcQtyAfter = $srcQtyBefore + $item->qty;
        }

        // Restore medicines master stock
        $medRecord = Medicines::find($srcBatch->medicine_id);
        if ($medRecord) {
            $medRecord->increment('stock', $item->qty);
        }

        // ── Log rollback (stock returned to source) ───────────────
        ItemsLog::create([
            'transaction_code' => $transfer->code,
            'code' => $this->generateItemsLogCode(),
            'type' => 'MU',
            'medicine_id' => $srcBatch->medicine_id,
            'qty' => $item->qty,
            'qty_before' => $srcQtyBefore,
            'qty_after' => $srcQtyAfter,
            'total' => 0,
            'date' => $now,
            'status' => 7,
            'batches_id' => $srcBatch->id,
            'user_id' => $this->actorId(),
        ]);
    }

    private function authorizeTransferParticipant(MedicineTransfers $transfer): void
    {
        $currentPharmacyId = getActivePharmacyId();
        $isParticipant = (int) $transfer->source_pharmacy_id === $currentPharmacyId
            || (int) $transfer->destination_pharmacy_id === $currentPharmacyId
            || $transfer->items()->whereHas('sourceBatch', fn($query) => $query->where('pharmacy_id', $currentPharmacyId))->exists()
            || $transfer->items()->whereHas('batches', fn($query) => $query->where('pharmacy_id', $currentPharmacyId))->exists();
        abort_unless($isParticipant, 403);
    }
}
