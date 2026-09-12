<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Medicines;
use App\Models\Patients;
use App\Models\MedicineTransactions;
use App\Models\MedicineCart;
use App\Models\MedicineTransferItems;
use App\Models\ItemsLog;
use App\Models\Batches;
use App\Models\Pharmacies;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MobileSyncController extends Controller
{
    /**
     * 1. [GET] /api/mobile/pharmacies
     * Daftar cabang apotek aktif untuk dropdown pilihan cabang di aplikasi mobile.
     */
    public function getPharmacies(Request $request)
    {
        $pharmacies = Pharmacies::select('id', 'name', 'city', 'address', 'phone', 'status')
            ->where('status', 1)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar cabang berhasil dimuat',
            'data'    => $pharmacies,
        ]);
    }

    /**
     * 2. [GET] /api/mobile/medicines/lookup?code=...&pharmacy_id=...
     * Lookup instan data obat berdasarkan SKU / Code / Barcode untuk auto-fill form master data mobile.
     */
    public function lookupMedicine(Request $request, $code = null)
    {
        $searchCode = trim($code ?: $request->input('code', $request->input('barcode', '')));
        $pharmacyId = $request->input('pharmacy_id');

        if (!$searchCode) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter "code" atau "barcode" (SKU) wajib diisi.',
            ], 422);
        }

        $medicine = Medicines::with(['category', 'factory'])
            ->where(function ($q) use ($searchCode) {
                $q->where('code', $searchCode)
                  ->orWhere('barcode', $searchCode);
            })
            ->first();

        if (!$medicine) {
            return response()->json([
                'success' => false,
                'message' => "Obat dengan SKU/Barcode '{$searchCode}' tidak ditemukan pada sistem Web.",
            ], 404);
        }

        $formatted = $this->formatMedicineResource($medicine, $pharmacyId);

        return response()->json([
            'success' => true,
            'message' => 'Data obat berhasil ditemukan',
            'data'    => $formatted,
        ]);
    }

    /**
     * Alias endpoint: [GET] /api/mobile/medicines/by-code/{code}?pharmacy_id=...
     */
    public function getMedicineByCode(Request $request, $code)
    {
        return $this->lookupMedicine($request, $code);
    }

    /**
     * 3. [GET] /api/mobile/medicines
     * List obat terpaginasi dengan pencarian, filter cabang, dan incremental sync (updated_since).
     */
    public function getMedicines(Request $request)
    {
        $limit = min((int) $request->input('limit', 50), 200);
        $pharmacyId = $request->input('pharmacy_id');
        $search = $request->input('search');
        $updatedSince = $request->input('updated_since');

        $query = Medicines::with(['category', 'factory'])->where('status', 1);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhere('generic', 'like', "%{$search}%");
            });
        }

        if ($updatedSince) {
            $query->where('updated_at', '>=', Carbon::parse($updatedSince));
        }

        $query->orderBy('name', 'asc');
        $paginated = $query->paginate($limit);

        $items = collect($paginated->items())->map(function ($medicine) use ($pharmacyId) {
            return $this->formatMedicineResource($medicine, $pharmacyId);
        });

        return response()->json([
            'success'    => true,
            'data'       => $items,
            'pagination' => [
                'current_page'  => $paginated->currentPage(),
                'last_page'     => $paginated->lastPage(),
                'per_page'      => $paginated->perPage(),
                'total'         => $paginated->total(),
                'has_more'      => $paginated->hasMorePages(),
                'next_page_url' => $paginated->nextPageUrl(),
            ]
        ]);
    }

    /**
     * Helper kalkulasi stok per cabang/pharmacy.
     */
    protected function calculateMedicineStockByPharmacy($medicineId, $pharmacyId)
    {
        $map = [
            14 => 1, // Sahabat PMI
            17 => 2, // Sahabat Mulawarman
            16 => 3, // Sahabat MIM
            15 => 5, // Sahabat Antasari
        ];
        $webPharmacyId = $map[$pharmacyId] ?? (int) $pharmacyId;

        $warehouseId = function_exists('getWarehousePharmacyId') ? getWarehousePharmacyId() : 9;
        $isWarehouse = function_exists('isWarehousePharmacy') ? isWarehousePharmacy($webPharmacyId) : ($webPharmacyId === $warehouseId);
        $canSeeWarehouse = function_exists('canAccessWarehouseStock') ? canAccessWarehouseStock($webPharmacyId) : in_array($webPharmacyId, [1, 6, 9]);

        // 1. Storage Stock (Gudang PMI)
        $storageStock = ($isWarehouse || $canSeeWarehouse)
            ? (int) Batches::where('medicine_id', $medicineId)
                ->where('pharmacy_id', $warehouseId)
                ->sum('stock')
            : 0;

        // 2. Counter Stock (Pelayanan / Etalase)
        $counterPharmacyId = $isWarehouse ? 1 : $webPharmacyId;
        $counterStock = (int) MedicineTransferItems::whereHas('batches', function ($b) use ($medicineId, $counterPharmacyId) {
                $b->where('medicine_id', $medicineId)
                  ->where('pharmacy_id', $counterPharmacyId);
            })
            ->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
            })
            ->sum('qty');

        // 3. Direct Batch Stock pada cabang terkait
        $directBatchStock = 0;
        if (!$isWarehouse && $webPharmacyId != $warehouseId) {
            $directBatchStock = (int) Batches::where('medicine_id', $medicineId)
                ->where('pharmacy_id', $webPharmacyId)
                ->sum('stock');
        }

        $totalBranchStock = $counterStock + $directBatchStock + ($isWarehouse ? $storageStock : 0);

        return [
            'pharmacy_id'        => $webPharmacyId,
            'total_stock'        => $totalBranchStock,
            'counter_stock'      => $counterStock,
            'storage_stock'      => $storageStock,
            'direct_batch_stock' => $directBatchStock,
        ];
    }

    /**
     * Helper standardisasi format output JSON obat.
     */
    protected function formatMedicineResource(Medicines $medicine, $pharmacyId = null)
    {
        $rawPrice = (float) ($medicine->raw_price ?: ($medicine->net_price ? round($medicine->net_price / 1.11, 2) : 0));
        $netPrice = (float) ($medicine->net_price ?: ($medicine->raw_price ? round($medicine->raw_price * 1.11, 2) : 0));
        $hetPrice = (float) ($medicine->het_price ?? 0);
        $pharmacyNetPrice = (float) ($medicine->pharmacy_net_price ?? $netPrice);

        // Perhitungan margin HET vs Net Price
        $marginPercentage = ($netPrice > 0 && $hetPrice > 0)
            ? round((($hetPrice - $netPrice) / $netPrice) * 100, 2)
            : 0;

        $stockData = [];
        if ($pharmacyId) {
            $branchStock = $this->calculateMedicineStockByPharmacy($medicine->id, $pharmacyId);
            $pharmacy = Pharmacies::find($branchStock['pharmacy_id']);

            $nearestBatch = Batches::where('medicine_id', $medicine->id)
                ->where('pharmacy_id', $branchStock['pharmacy_id'])
                ->where('expired_date', '>=', now()->toDateString())
                ->orderBy('expired_date', 'asc')
                ->first();

            $stockData = [
                'pharmacy_id'          => $branchStock['pharmacy_id'],
                'pharmacy_name'        => $pharmacy?->name ?? 'Cabang ' . $branchStock['pharmacy_id'],
                'stock'                => $branchStock['total_stock'],
                'counter_stock'        => $branchStock['counter_stock'],
                'storage_stock'        => $branchStock['storage_stock'],
                'minimal_stock'        => (int) ($medicine->minimal_stock ?? 0),
                'is_low_stock'         => $branchStock['total_stock'] <= ($medicine->minimal_stock ?? 0),
                'is_out_of_stock'      => $branchStock['total_stock'] <= 0,
                'nearest_expired_date' => $nearestBatch?->expired_date ? Carbon::parse($nearestBatch->expired_date)->format('Y-m-d') : null,
            ];
        } else {
            $pharmacies = Pharmacies::where('status', 1)->get();
            $stockByPharmacy = [];
            $accumulatedStock = 0;

            foreach ($pharmacies as $p) {
                $bStock = $this->calculateMedicineStockByPharmacy($medicine->id, $p->id);
                if ($bStock['total_stock'] > 0 || in_array($p->id, [1, 2, 3, 5, 9])) {
                    $stockByPharmacy[] = [
                        'pharmacy_id'   => $p->id,
                        'pharmacy_name' => $p->name,
                        'stock'         => $bStock['total_stock'],
                    ];
                }
                $accumulatedStock += $bStock['total_stock'];
            }

            $stockData = [
                'total_stock'       => $accumulatedStock,
                'minimal_stock'     => (int) ($medicine->minimal_stock ?? 0),
                'is_low_stock'      => $accumulatedStock <= ($medicine->minimal_stock ?? 0),
                'is_out_of_stock'   => $accumulatedStock <= 0,
                'stock_by_pharmacy' => $stockByPharmacy,
            ];
        }

        return [
            'id'                    => $medicine->id,
            'code'                  => $medicine->code,
            'barcode'               => $medicine->barcode,
            'name'                  => $medicine->name,
            'generic_name'          => $medicine->generic,
            'unit'                  => $medicine->unit,
            'packaging'             => $medicine->packaging,
            'content'               => $medicine->content,
            'strip'                 => $medicine->strip,
            'dosage'                => $medicine->dosage,
            'category'              => $medicine->category?->name ?? null,
            'factory'               => $medicine->factory?->name ?? null,
            'type'                  => $medicine->type,

            // Flat price attributes (langsung auto-fill di form mobile)
            'raw_price'             => $rawPrice,
            'net_price'             => $netPrice,
            'het_price'             => $hetPrice,
            'pharmacy_net_price'    => $pharmacyNetPrice,
            'stock'                 => $pharmacyId ? ($stockData['stock'] ?? 0) : ($stockData['total_stock'] ?? 0),

            // Structured Pricing Object
            'pricing' => [
                'raw_price'          => $rawPrice,
                'net_price'          => $netPrice,
                'het_price'          => $hetPrice,
                'pharmacy_net_price' => $pharmacyNetPrice,
                'tax_percentage'     => 11,
                'margin_percentage'  => $marginPercentage,
                'formatted' => [
                    'raw_price' => 'Rp ' . number_format($rawPrice, 0, ',', '.'),
                    'net_price' => 'Rp ' . number_format($netPrice, 0, ',', '.'),
                    'het_price' => 'Rp ' . number_format($hetPrice, 0, ',', '.'),
                ],
            ],

            // Stock & Branch Object
            'stock_info' => $stockData,

            // Compliance & Regulations
            'compliance' => [
                'is_psychotropic'       => (bool) $medicine->psychotropic,
                'is_precursor'          => (bool) $medicine->precursor,
                'requires_prescription' => (bool) $medicine->receipt,
                'is_active'             => (bool) ($medicine->status == 1),
            ],

            'updated_at' => $medicine->updated_at ? $medicine->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }

    public function getProducts(Request $request)
    {
        $limit = $request->input('limit', 100); // Default 100 item per request
        $medicines = Medicines::select('code', 'name', DB::raw('het_price as price'), 'unit', 'stock')
            ->where('status', 1)
            ->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $medicines->items(),
            'pagination' => [
                'current_page' => $medicines->currentPage(),
                'last_page' => $medicines->lastPage(),
                'per_page' => $medicines->perPage(),
                'total' => $medicines->total(),
                'next_page_url' => $medicines->nextPageUrl(),
            ]
        ]);
    }

    // 2. [POST] /api/mobile/members/check
    public function checkMember(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'name' => 'required|string',
        ]);

        $patient = Patients::updateOrCreate(
            ['phone' => $request->phone],
            [
                'name' => $request->name,
                'status' => 1,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Member tersinkronisasi sukses',
            'data' => $patient
        ]);
    }

    // 3. [POST] /api/mobile/members/checkout
    public function checkoutPoints(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $patient = Patients::where('phone', $request->phone)->first();
        if (!$patient) {
            return response()->json(['success' => false, 'message' => 'Member tidak terdaftar di sistem Web POS']);
        }

        // Cari transaksi terbaru member ini (dalam 24 jam terakhir agar wajar)
        $latestTransaction = MedicineTransactions::where('patient_id', $patient->id)
            ->where('status', 1)
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latestTransaction) {
            return response()->json(['success' => false, 'message' => 'Tidak ada transaksi baru dalam 24 jam terakhir']);
        }

        return response()->json([
            'success' => true,
            'transaction_code' => $latestTransaction->transaction_code,
            'transaction_date' => $latestTransaction->created_at->format('Y-m-d H:i:s'),
            'total_transaction' => $latestTransaction->subtotal,
            'phone' => $patient->phone,
        ]);
    }

    // 4. [GET] /api/mobile/members/{phone}/history
    public function memberHistory(Request $request, $phone)
    {
        $patient = Patients::where('phone', $phone)->first();
        if (!$patient) {
            return response()->json(['success' => false, 'message' => 'Member tidak ditemukan']);
        }

        $history = MedicineTransactions::with('transactions.medicine')
            ->where('patient_id', $patient->id)
            ->where('status', 1)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($trans) {
                return [
                    'transaction_code' => $trans->transaction_code,
                    'date' => $trans->created_at->format('Y-m-d H:i:s'),
                    'total' => $trans->subtotal,
                    'items' => $trans->transactions->map(function($item) {
                        return [
                            'medicine_name' => $item->medicine ? $item->medicine->name : '-',
                            'qty' => $item->quantity,
                            'price' => $item->item_price,
                            'total' => $item->final_price,
                        ];
                    })
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    // 5. [POST] /api/mobile/transactions/checkout
    public function transactionCheckout(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|integer',
            'payment_type' => 'required|string',
            'transaction_type' => 'required|string',
            'phone' => 'required|string',
            'name' => 'required|string',
            'discount' => 'nullable|numeric',
            'total_transaction' => 'required|numeric',
            'items' => 'required|array',
            'items.*.code' => 'required|string',
            'items.*.qty' => 'required|numeric',
            'items.*.price' => 'required|numeric',
            'items.*.discount' => 'nullable|numeric',
        ]);

        // Mapping Pharmacy ID
        $mobilePharmacyId = $request->pharmacy_id;
        $map = [
            14 => 1, // Sahabat PMI
            17 => 2, // Sahabat Mulawarman
            16 => 3, // Sahabat MIM
            15 => 5, // Sahabat Antasari
        ];
        $webPharmacyId = $map[$mobilePharmacyId] ?? $mobilePharmacyId;

        $patient = Patients::firstOrCreate(
            ['phone' => $request->phone],
            ['name' => $request->name, 'status' => 1]
        );

        DB::beginTransaction();
        try {
            // Generate Transaction Code (Mobile)
            $prefix = "OL-" . date('Ymd') . "-";
            $lastTrans = MedicineTransactions::where('transaction_code', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
            $num = $lastTrans ? intval(substr($lastTrans->transaction_code, -4)) + 1 : 1;
            $code = $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);

            $medTransaction = MedicineTransactions::create([
                'pharmacy_id' => $webPharmacyId,
                'patient_id' => $patient->id,
                'user_id' => 1,
                'transaction_code' => $code,
                'transaction_type' => $request->transaction_type, // Misal: ONLINE
                'subtotal' => $request->total_transaction,
                'discount' => $request->discount ?? 0,
                'paid' => $request->total_transaction,
                'changes' => 0,
                'payment_method' => $request->payment_type,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($request->items as $item) {
                $medicine = Medicines::where('code', $item['code'])->first();
                if (!$medicine) {
                    throw new \Exception("Obat dengan kode {$item['code']} tidak ditemukan.");
                }

                $totalPrice = ($item['qty'] * $item['price']) - ($item['discount'] ?? 0);

                MedicineCart::create([
                    'transaction_id' => $medTransaction->id,
                    'medicine_id' => $medicine->id,
                    'user_id' => 1, // Default user
                    'quantity' => $item['qty'],
                    'item_price' => $item['price'],
                    'discount' => $item['discount'] ?? 0,
                    'total_price' => $totalPrice,
                    'final_price' => $totalPrice,
                    'cart_type' => $request->transaction_type, // Misal ONLINE
                    'status' => 1,
                ]);

                // Pemotongan Stok Serupa dengan Kasir Web POS
                $qty_bought = $item['qty'];
                $qty_before = $medicine->stock;

                while ($qty_bought > 0) {
                    $transfer = MedicineTransferItems::join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
                        ->where('batches.medicine_id', $medicine->id)
                        ->where('batches.pharmacy_id', $webPharmacyId)
                        ->where('medicine_transfer_items.qty', '>', 0)
                        ->orderBy('batches.expired_date', 'asc')
                        ->lockForUpdate()
                        ->select('medicine_transfer_items.*')
                        ->first();

                    if (!$transfer) {
                        $transfer = MedicineTransferItems::join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
                            ->where('batches.medicine_id', $medicine->id)
                            ->where('batches.pharmacy_id', $webPharmacyId)
                            ->orderBy('batches.expired_date', 'desc')
                            ->lockForUpdate()
                            ->select('medicine_transfer_items.*')
                            ->first();

                        if (!$transfer) {
                            throw new \Exception("Stok counter tidak ditemukan untuk obat: {$medicine->name}.");
                        }
                    }

                    if ($transfer->qty >= $qty_bought) {
                        $transfer->qty -= $qty_bought;
                        $transfer->save();
                        $qty_bought = 0;
                    } else {
                        $qty_bought -= $transfer->qty;
                        $transfer->qty = 0;
                        $transfer->save();
                    }
                }

                $medicine->stock -= $item['qty'];
                $medicine->save();

                // Items Log untuk LIPH
                $prefixLog = "LOG-" . date('Ymd') . "-";
                $lastLog = ItemsLog::where('code', 'like', $prefixLog . '%')->orderBy('id', 'desc')->first();
                $numLog = $lastLog ? intval(substr($lastLog->code, -4)) + 1 : 1;
                $logCode = $prefixLog . str_pad($numLog, 4, '0', STR_PAD_LEFT);

                ItemsLog::create([
                    'transaction_code' => $code,
                    'code' => $logCode,
                    'type' => $request->transaction_type, // "ONLINE"
                    'medicine_id' => $medicine->id,
                    'qty' => $item['qty'],
                    'qty_before' => $qty_before,
                    'qty_after' => $medicine->stock,
                    'total' => $totalPrice,
                    'date' => now()->format('Y-m-d H:i:s'),
                    'status' => 1,
                    'batches_id' => $transfer->batches_id ?? null,
                    'user_id' => 1,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil disimpan dan stok terpotong',
                'transaction_code' => $code,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
