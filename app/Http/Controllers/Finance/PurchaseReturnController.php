<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Batches;
use App\Models\Creditor;
use App\Models\FinanceAccount;
use App\Models\FinancePayment;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\Pharmacies;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\ReceivingDetails;
use App\Models\ReturnPolicy;
use App\Support\ReceivingInvoiceTotals;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            $allowedRoles = ['administrator', 'General Manager', 'Gudang PMI'];
            $hasAccess = $user && $user->hasAnyRole($allowedRoles);
            if (!$hasAccess) {
                abort(403, 'Akses ditolak: Input Data Retur / Monitoring Retur Beli hanya dapat diakses oleh Administrator, General Manager, dan Gudang PMI.');
            }
            return $next($request);
        });
    }

    protected function getBranchContext(): array
    {
        $activePharmacyId = function_exists('getActivePharmacyId') ? getActivePharmacyId() : 1;
        $activePharmacy = Pharmacies::find($activePharmacyId);
        if (!$activePharmacy) {
            $activePharmacy = Pharmacies::first();
        }

        $user = auth()->user();
        $isHoOrAdmin = $user && ($user->hasRole('HO') || $user->hasRole('administrator') || $user->hasRole('General Manager'));

        $branches = [];
        if ($isHoOrAdmin) {
            $branches = Pharmacies::whereIn('id', [1, 9, 2, 3, 4, 5])
                ->orderByRaw('FIELD(id, 1, 9, 2, 3, 4, 5)')
                ->get(['id', 'name']);
        }

        return [
            'activePharmacy' => $activePharmacy ? [
                'id' => $activePharmacy->id,
                'name' => $activePharmacy->name,
            ] : [
                'id' => $activePharmacyId,
                'name' => 'Apotek Cabang',
            ],
            'branches' => $branches,
            'canSwitchBranch' => $isHoOrAdmin,
        ];
    }

    /**
     * Normalisasi nama pabrik untuk pencocokan toleran terhadap simbol/spasi/tanda kurung
     */
    protected function normalizeFactoryName(?string $str): string
    {
        if (!$str) return '';
        $str = strtoupper(trim($str));
        $str = preg_replace('/\([^)]+\)/', '', $str);
        $str = preg_replace('/[^A-Z0-9]/', '', $str);
        return $str ?: '';
    }

    /**
     * Cari aturan ketentuan retur berdasarkan PBF dan Pabrik
     */
    protected function findReturnPolicy(?string $creditorCode, $factoryId, ?string $factoryName, $preloadedPolicies = null)
    {
        if (!$creditorCode) return null;

        $policies = $preloadedPolicies !== null
            ? $preloadedPolicies
            : ReturnPolicy::where('creditor_code', $creditorCode)->get();

        // 1. Cek pencocokan langsung berdasarkan factory_id
        if ($factoryId) {
            foreach ($policies as $p) {
                if ($p->factory_id == $factoryId) {
                    return $p;
                }
            }
        }

        // 2. Cek pencocokan nama ternormalisasi
        if ($factoryName) {
            $normF = $this->normalizeFactoryName($factoryName);
            if (!empty($normF)) {
                foreach ($policies as $p) {
                    $normP = $this->normalizeFactoryName($p->factory_name);
                    if (!empty($normP)) {
                        if ($normP === $normF || (strlen($normP) >= 4 && str_contains($normF, $normP)) || (strlen($normF) >= 4 && str_contains($normP, $normF))) {
                            return $p;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Hitung status kesesuaian ketentuan retur berdasarkan tanggal ED dan aturan Master
     */
    protected function evaluateReturnEligibility($policy, ?string $expiredDateStr): array
    {
        if (!$policy) {
            return [
                'status' => 'unknown',
                'badge' => 'bg-slate-100 text-slate-600 border-slate-200',
                'label' => 'Belum Ada Aturan',
                'message' => 'Kombinasi Pabrik & PBF ini belum diatur di Master Ketentuan Retur.',
                'is_returnable' => null,
                'policy' => null,
            ];
        }

        if (!$policy->is_returnable) {
            return [
                'status' => 'forbidden',
                'badge' => 'bg-rose-100 text-rose-800 border-rose-300',
                'label' => 'No Retur (X)',
                'message' => 'Ketentuan: TIDAK BISA DIRETUR (NO RETUR). ' . ($policy->notes ?: ''),
                'is_returnable' => false,
                'policy' => [
                    'id' => $policy->id,
                    'factory_name' => $policy->factory_name,
                    'is_returnable' => false,
                    'notes' => $policy->notes,
                    'raw_rule' => $policy->raw_rule,
                ],
            ];
        }

        $monthsBefore = $policy->months_before_ed ?? 0;
        $monthsAfter = $policy->months_after_ed ?? 0;

        if (empty($expiredDateStr)) {
            return [
                'status' => 'allowed',
                'badge' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                'label' => "Bisa Retur (H-{$monthsBefore})",
                'message' => "Ketentuan retur: " . ($policy->notes ?: "H-{$monthsBefore} Bulan sebelum ED"),
                'is_returnable' => true,
                'policy' => [
                    'id' => $policy->id,
                    'factory_name' => $policy->factory_name,
                    'is_returnable' => true,
                    'months_before_ed' => $monthsBefore,
                    'months_after_ed' => $monthsAfter,
                    'notes' => $policy->notes,
                    'raw_rule' => $policy->raw_rule,
                ],
            ];
        }

        try {
            $now = Carbon::now()->startOfMonth();
            $ed = Carbon::parse($expiredDateStr)->startOfMonth();
            $diffMonths = $now->diffInMonths($ed, false);

            $policyData = [
                'id' => $policy->id,
                'factory_name' => $policy->factory_name,
                'is_returnable' => true,
                'months_before_ed' => $monthsBefore,
                'months_after_ed' => $monthsAfter,
                'notes' => $policy->notes,
                'raw_rule' => $policy->raw_rule,
            ];

            if ($diffMonths <= $monthsBefore && $diffMonths >= (-1 * $monthsAfter)) {
                return [
                    'status' => 'eligible',
                    'badge' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                    'label' => "Sesuai Ketentuan (H-{$monthsBefore})",
                    'message' => "Memenuhi syarat retur: ED " . Carbon::parse($expiredDateStr)->format('m/Y') . " (Sisa {$diffMonths} bln, ketentuan H-{$monthsBefore})",
                    'is_returnable' => true,
                    'policy' => $policyData,
                ];
            } elseif ($diffMonths > $monthsBefore) {
                return [
                    'status' => 'too_early',
                    'badge' => 'bg-amber-100 text-amber-800 border-amber-300',
                    'label' => "Belum Periode (H-{$monthsBefore})",
                    'message' => "ED masih {$diffMonths} bulan lagi. Ketentuan retur adalah H-{$monthsBefore} bulan sebelum ED.",
                    'is_returnable' => true,
                    'policy' => $policyData,
                ];
            } else {
                return [
                    'status' => 'expired_passed',
                    'badge' => 'bg-rose-100 text-rose-800 border-rose-300',
                    'label' => 'Lewat Batas Retur',
                    'message' => "Sudah melewati batas waktu retur (H+{$monthsAfter} bulan setelah ED).",
                    'is_returnable' => true,
                    'policy' => $policyData,
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'allowed',
                'badge' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                'label' => "Bisa Retur",
                'message' => $policy->notes ?: "Bisa retur",
                'is_returnable' => true,
                'policy' => null,
            ];
        }
    }

    public function index(Request $request)
    {
        $search = trim((string) ($request->input('search') ?? ''));
        $creditorFilter = $request->input('creditor_code');
        $statusFilter = $request->input('status');
        $reasonFilter = $request->input('reason');
        $perPage = (int) $request->input('per_page', 25);

        // Query item-level rows to match exact format in Excel monitoring
        $itemsQuery = PurchaseReturnItem::with(['purchaseReturn.creditor', 'medicine.factory'])
            ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
            ->select('purchase_return_items.*')
            ->orderBy('purchase_returns.created_at', 'desc')
            ->orderBy('purchase_return_items.id', 'desc');

        if ($search !== '') {
            $itemsQuery->where(function ($q) use ($search) {
                $q->where('purchase_return_items.medicine_name', 'LIKE', "%{$search}%")
                    ->orWhere('purchase_return_items.batch_number', 'LIKE', "%{$search}%")
                    ->orWhere('purchase_returns.invoice_number', 'LIKE', "%{$search}%")
                    ->orWhere('purchase_returns.return_no', 'LIKE', "%{$search}%")
                    ->orWhereHas('purchaseReturn.creditor', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('code', 'LIKE', "%{$search}%");
                    });
            });
        }

        if (!empty($creditorFilter)) {
            $itemsQuery->where('purchase_returns.creditor_code', $creditorFilter);
        }

        if (!empty($statusFilter)) {
            if (in_array($statusFilter, ['diajukan', 'dikonfirmasi', 'sudah_diambil', 'sudah_potong', 'barang_kembali_pmi'], true)) {
                $itemsQuery->where('purchase_return_items.status', $statusFilter);
            } elseif ($statusFilter === 'belum_potong') {
                $itemsQuery->where('purchase_return_items.status', '!=', 'sudah_potong');
            } elseif ($statusFilter === 'sudah_ambil') {
                $itemsQuery->where(function ($q) {
                    $q->where('purchase_return_items.status', 'sudah_diambil')
                      ->orWhereNotNull('purchase_return_items.pickup_date');
                });
            } elseif ($statusFilter === 'belum_ambil') {
                $itemsQuery->where(function ($q) {
                    $q->where('purchase_return_items.status', '!=', 'sudah_diambil')
                      ->whereNull('purchase_return_items.pickup_date');
                });
            }
        }

        if (!empty($reasonFilter)) {
            $reasonAliases = [
                'ED DEKAT' => ['ED DEKAT', 'ED'],
                'Barang Rusak' => ['Barang Rusak', 'RUSAK'],
                'Salah Barang' => ['Salah Barang', 'SALAH KIRIM', 'SALAH BARANG'],
                'Salah Pesan' => ['Salah Pesan', 'SALAH PESAN'],
                'Recall' => ['Recall', 'RECALL'],
                'Retur Pabrik' => ['Retur Pabrik', 'RETUR PABRIK'],
                'Double' => ['Double', 'TERDOUBLE', 'DOUBLE'],
                'dlsbnya' => ['dlsbnya', 'OVERSTOCK', 'LAINNYA', 'LAIN-LAIN'],
            ];

            if (isset($reasonAliases[$reasonFilter])) {
                $itemsQuery->whereIn('purchase_return_items.reason', $reasonAliases[$reasonFilter]);
            } else {
                $itemsQuery->where('purchase_return_items.reason', $reasonFilter);
            }
        }

        $items = $itemsQuery->paginate($perPage)->withQueryString();

        // Preload policies for creditors in current page
        $usedCreditorCodes = $items->pluck('purchaseReturn.creditor_code')->filter()->unique()->values()->all();
        $creditorPolicies = ReturnPolicy::whereIn('creditor_code', $usedCreditorCodes)->get()->groupBy('creditor_code');

        $items->getCollection()->transform(function ($item) use ($creditorPolicies) {
            $creditorCode = $item->purchaseReturn ? $item->purchaseReturn->creditor_code : null;
            $factoryId = $item->medicine ? $item->medicine->factory_id : null;
            $factoryName = $item->medicine && $item->medicine->factory ? $item->medicine->factory->name : null;

            $policies = $creditorCode && isset($creditorPolicies[$creditorCode]) ? $creditorPolicies[$creditorCode] : collect();
            $policy = $this->findReturnPolicy($creditorCode, $factoryId, $factoryName, $policies);
            $evaluation = $this->evaluateReturnEligibility($policy, $item->expired_date);

            $item->factory_name = $factoryName;
            $item->return_policy_info = $evaluation;
            $item->formatted_expired_date = $item->expired_date ? Carbon::parse($item->expired_date)->format('d/m/Y') : '-';
            $item->formatted_invoice_date = ($item->purchaseReturn && $item->purchaseReturn->invoice_date) ? Carbon::parse($item->purchaseReturn->invoice_date)->format('d/m/Y') : '-';
            $item->formatted_status_date = $item->status_date ? Carbon::parse($item->status_date)->format('d/m/Y') : ($item->created_at ? $item->created_at->format('d/m/Y') : '-');
            $item->formatted_pickup_date = $item->pickup_date ? Carbon::parse($item->pickup_date)->format('d/m/Y') : null;
            $item->formatted_confirmed_date = $item->confirmed_date ? Carbon::parse($item->confirmed_date)->format('d/m/Y') : null;
            return $item;
        });

        // Metrik Monitoring
        $totalItemsCount = PurchaseReturnItem::count();
        $totalReturValue = (float) PurchaseReturnItem::sum('subtotal');
        $diajukanCount = PurchaseReturnItem::where(function ($q) {
            $q->where('status', 'diajukan')->orWhereNull('status');
        })->count();
        $dikonfirmasiCount = PurchaseReturnItem::where('status', 'dikonfirmasi')->count();
        $sudahDiambilCount = PurchaseReturnItem::where('status', 'sudah_diambil')->count();
        $sudahPotongCount = PurchaseReturnItem::where('status', 'sudah_potong')->count();
        $pmiCount = PurchaseReturnItem::where('status', 'barang_kembali_pmi')->count();

        $creditors = Creditor::select('code', 'name')
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('name')
            ->get();

        $activeTab = $request->query('tab', 'monitoring');

        $approachingFilters = [
            'search' => $request->query('approaching_search', ''),
            'creditor_code' => $request->query('approaching_creditor', ''),
            'urgency' => $request->query('approaching_urgency', ''),
        ];

        $alertService = new \App\Services\Finance\PurchaseReturnAlertService();
        $branchContext = $this->getBranchContext();
        $pharmacyId = $branchContext['activePharmacy']['id'] ?? null;
        $approachingResult = $alertService->getApproachingReturns($pharmacyId, $approachingFilters, 25);

        $viewData = [
            'activeTab' => $activeTab,
            'items' => $items,
            'filters' => [
                'search' => $search,
                'creditor_code' => $creditorFilter,
                'status' => $statusFilter,
                'reason' => $reasonFilter,
                'per_page' => $perPage,
            ],
            'stats' => [
                'total_items' => $totalItemsCount,
                'total_value' => $totalReturValue,
                'diajukan' => $diajukanCount,
                'dikonfirmasi' => $dikonfirmasiCount,
                'sudah_diambil' => $sudahDiambilCount,
                'sudah_potong' => $sudahPotongCount,
                'barang_kembali_pmi' => $pmiCount,
                'approaching_count' => $approachingResult['stats']['total_count'] ?? 0,
                'eligible_count' => $approachingResult['stats']['eligible_count'] ?? 0,
            ],
            'approachingItems' => $approachingResult['data'],
            'approachingStats' => $approachingResult['stats'],
            'approachingFilters' => $approachingFilters,
            'creditors' => $creditors,
            'branchContext' => $branchContext,
        ];

        if ($request->header('X-Inertia')) {
            return Inertia::render('Finance/PurchaseReturns', $viewData);
        }

        return view('orders.purchase_returns', $viewData);
    }

    /**
     * Cari faktur penerimaan yang sudah ada di sistem
     */
    public function searchInvoices(Request $request)
    {
        $q = trim($request->input('query', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $invoices = ReceivingDetails::with(['creditor'])
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->where(function ($query) use ($q) {
                $query->where('invoice_number', 'LIKE', "%{$q}%")
                    ->orWhere('receiving_details_code', 'LIKE', "%{$q}%")
                    ->orWhere('creditor_code', 'LIKE', "%{$q}%")
                    ->orWhereHas('creditor', function ($cq) use ($q) {
                        $cq->where('name', 'LIKE', "%{$q}%");
                    });
            })
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get();

        $results = $invoices->map(function ($inv) {
            return [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'receiving_code' => $inv->receiving_details_code,
                'invoice_date' => $inv->invoice_date,
                'creditor_code' => $inv->creditor_code,
                'creditor_name' => $inv->creditor ? $inv->creditor->name : $inv->creditor_code,
            ];
        });

        return response()->json($results);
    }

    /**
     * Ambil rincian obat dari faktur penerimaan
     * Mengunci obat yang sudah pernah diretur untuk faktur ini
     */
    public function getInvoiceItems($id)
    {
        $receivingDetail = ReceivingDetails::with(['creditor'])->findOrFail($id);

        $items = DB::table('receiving_items')
            ->leftJoin('batches', 'receiving_items.batches_id', '=', 'batches.id')
            ->leftJoin('medicines as mb', 'batches.medicine_id', '=', 'mb.id')
            ->leftJoin('factories as fb', 'mb.factory_id', '=', 'fb.id')
            ->leftJoin('order_items', 'receiving_items.order_items_id', '=', 'order_items.id')
            ->leftJoin('medicines as mo', 'order_items.medicine_id', '=', 'mo.id')
            ->leftJoin('factories as fo', 'mo.factory_id', '=', 'fo.id')
            ->where('receiving_items.receiving_details_id', $receivingDetail->id)
            ->select(
                'receiving_items.id as ri_id',
                'receiving_items.batch',
                'receiving_items.expired_date',
                'receiving_items.qty_received',
                'receiving_items.qty',
                'receiving_items.raw_price',
                'receiving_items.discount',
                'receiving_items.extra_discount',
                'receiving_items.total',
                'receiving_items.receipt_is_pack',
                'receiving_items.stock_unit_factor',
                'order_items.pack as oi_pack',
                'batches.id as batch_id',
                'batches.stock as current_stock',
                DB::raw('COALESCE(mb.id, mo.id) as medicine_id'),
                DB::raw('COALESCE(mb.name, mo.name) as medicine_name'),
                DB::raw('COALESCE(mb.packaging, mo.packaging) as packaging'),
                DB::raw('COALESCE(mb.unit, mo.unit, "PCS") as stock_unit'),
                DB::raw('COALESCE(NULLIF(mb.content, ""), NULLIF(mo.content, ""), 1) as content'),
                DB::raw('COALESCE(mb.factory_id, mo.factory_id) as factory_id'),
                DB::raw('COALESCE(fb.name, fo.name) as factory_name')
            )
            ->get();

        $policies = ReturnPolicy::where('creditor_code', $receivingDetail->creditor_code)->get();

        // Cari obat yang sudah pernah diretur pada faktur ini (kunci 1 faktur 1 obat hanya 1x retur)
        $existingReturns = DB::table('purchase_return_items')
            ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
            ->where(function ($q) use ($receivingDetail) {
                $q->where('purchase_returns.invoice_number', $receivingDetail->invoice_number)
                    ->orWhere('purchase_returns.receiving_id', $receivingDetail->id);
            })
            ->where('purchase_returns.status', '!=', 'dibatalkan')
            ->get(['purchase_return_items.medicine_id', 'purchase_return_items.medicine_name', 'purchase_returns.return_no']);

        $returnedMedicineIds = $existingReturns->pluck('medicine_id')->filter()->all();
        $returnedMedicineNames = $existingReturns->pluck('medicine_name')->map(fn($n) => strtoupper(trim($n)))->all();

        $itemsWithPolicy = $items->map(function ($it) use ($receivingDetail, $policies, $returnedMedicineIds, $returnedMedicineNames) {
            $policy = $this->findReturnPolicy($receivingDetail->creditor_code, $it->factory_id, $it->factory_name, $policies);
            $evaluation = $this->evaluateReturnEligibility($policy, $it->expired_date);

            $isAlreadyReturned = ($it->medicine_id && in_array($it->medicine_id, $returnedMedicineIds))
                || in_array(strtoupper(trim($it->medicine_name)), $returnedMedicineNames);

            // Tentukan satuan stok eceran dan kemasan utuh
            $stockUnit = $it->stock_unit ?: 'PCS';
            $content = (int) ($it->stock_unit_factor ?: $it->content ?: 1);
            if ($content < 1) $content = 1;
            $packaging = $it->packaging ?: ($content > 1 ? 'BOX' : $stockUnit);
            $hasPack = ($content > 1 && !empty($packaging));

            // Cek apakah saat penerimaan diterima dalam bentuk kemasan (pack)
            $isReceivedPack = $it->receipt_is_pack !== null ? (bool) $it->receipt_is_pack : (bool) $it->oi_pack;

            // Hitung harga satuan faktur penerimaan (total baris dibagi qty baris)
            $qtyReceived = (float) ($it->qty_received ?: $it->qty ?: 1);
            $lineAmounts = ReceivingInvoiceTotals::lineAmounts($it, $receivingDetail->invoice_ppn);
            $consistentUnitPrice = $qtyReceived > 0 ? round($lineAmounts['total'] / $qtyReceived, 2) : (float) $it->raw_price;

            // Konversi harga & qty untuk Kemasan vs Eceran
            if ($isReceivedPack && $content > 1) {
                $packPrice = $consistentUnitPrice;
                $pricePerUnit = round($packPrice / $content, 4);
                $qtyReceivedPack = $qtyReceived;
                $qtyReceivedUnit = round($qtyReceived * $content, 2);
                $defaultReturType = 'packaging';
                $defaultUnit = $packaging;
                $defaultPrice = $packPrice;
                $defaultQty = $qtyReceivedPack;
            } else {
                $pricePerUnit = $consistentUnitPrice;
                $packPrice = round($pricePerUnit * $content, 2);
                $qtyReceivedUnit = $qtyReceived;
                $qtyReceivedPack = $content > 1 ? round($qtyReceived / $content, 2) : $qtyReceived;
                $defaultReturType = 'unit';
                $defaultUnit = $stockUnit;
                $defaultPrice = $pricePerUnit;
                $defaultQty = $qtyReceivedUnit;
            }

            // Stok batch saat ini (stok fisik selalu dalam satuan eceran/stockUnit)
            $currentStockUnit = (float) ($it->current_stock ?? 0);
            $currentStockPack = $content > 1 ? round($currentStockUnit / $content, 2) : $currentStockUnit;

            return [
                'ri_id' => $it->ri_id,
                'batch' => $it->batch,
                'expired_date' => $it->expired_date,
                'qty_received' => $it->qty_received,
                'qty' => $defaultQty,
                'raw_price' => $it->raw_price,
                'unit_price' => $defaultPrice,
                'total' => $lineAmounts['total'],
                'batch_id' => $it->batch_id,
                'current_stock' => $currentStockUnit,
                'current_stock_unit' => $currentStockUnit,
                'current_stock_pack' => $currentStockPack,
                'medicine_id' => $it->medicine_id,
                'medicine_name' => $it->medicine_name,
                'unit' => $defaultUnit,
                'retur_type' => $defaultReturType,
                'stock_unit_factor' => $defaultReturType === 'packaging' ? $content : 1,
                'packaging' => $packaging,
                'stock_unit' => $stockUnit,
                'content' => $content,
                'has_pack' => $hasPack,
                'pack_price' => $packPrice,
                'price_per_unit' => $pricePerUnit,
                'qty_received_pack' => $qtyReceivedPack,
                'qty_received_unit' => $qtyReceivedUnit,
                'factory_id' => $it->factory_id,
                'factory_name' => $it->factory_name,
                'return_policy' => $evaluation,
                'is_already_returned' => $isAlreadyReturned,
            ];
        });

        return response()->json([
            'invoice' => [
                'id' => $receivingDetail->id,
                'invoice_number' => $receivingDetail->invoice_number,
                'invoice_date' => $receivingDetail->invoice_date,
                'creditor_code' => $receivingDetail->creditor_code,
                'creditor_name' => $receivingDetail->creditor ? $receivingDetail->creditor->name : $receivingDetail->creditor_code,
            ],
            'items' => $itemsWithPolicy,
        ]);
    }

    /**
     * Cari obat dari master medicines untuk custom/manual return
     */
    public function searchMedicines(Request $request)
    {
        $q = trim($request->input('query', ''));
        $creditorCode = $request->input('creditor_code', '');

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $medicines = Medicines::with(['factory'])
            ->where(function ($query) use ($q) {
                $query->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('code', 'LIKE', "%{$q}%")
                    ->orWhere('barcode', 'LIKE', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        $policies = $creditorCode ? ReturnPolicy::where('creditor_code', $creditorCode)->get() : collect();

        $results = $medicines->map(function ($m) use ($creditorCode, $policies) {
            $factoryName = $m->factory ? $m->factory->name : null;
            $policy = $creditorCode ? $this->findReturnPolicy($creditorCode, $m->factory_id, $factoryName, $policies) : null;
            $evaluation = $this->evaluateReturnEligibility($policy, null);

            $stockUnit = $m->unit ?: 'PCS';
            $content = (int) ($m->content ?: 1);
            if ($content < 1) $content = 1;
            $packaging = $m->packaging ?: ($content > 1 ? 'BOX' : $stockUnit);
            $hasPack = ($content > 1);

            $rawPrice = (float) $m->raw_price;
            $packPrice = $rawPrice;
            $pricePerUnit = $hasPack ? round($packPrice / $content, 4) : $packPrice;

            $currentStockUnit = (float) ($m->stock ?? 0);
            $currentStockPack = $hasPack ? round($currentStockUnit / $content, 2) : $currentStockUnit;

            return [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'unit' => $hasPack ? $packaging : $stockUnit,
                'retur_type' => $hasPack ? 'packaging' : 'unit',
                'stock_unit_factor' => $hasPack ? $content : 1,
                'packaging' => $packaging,
                'stock_unit' => $stockUnit,
                'content' => $content,
                'has_pack' => $hasPack,
                'raw_price' => $rawPrice,
                'pack_price' => $packPrice,
                'price_per_unit' => $pricePerUnit,
                'current_stock' => $currentStockUnit,
                'current_stock_unit' => $currentStockUnit,
                'current_stock_pack' => $currentStockPack,
                'factory_id' => $m->factory_id,
                'factory_name' => $factoryName,
                'return_policy' => $evaluation,
            ];
        });

        return response()->json($results);
    }

    /**
     * Simpan input retur pembelian baru (Bisa custom/manual atau dari faktur sistem)
     * Mengunci 1 faktur 1 obat hanya bisa dikembalikan 1 kali
     * Retur dari faktur maupun manual langsung mengurangi stok saat disimpan.
     * Retur manual tetap berstatus 'diajukan' sampai dikonfirmasi.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|max:100',
            'invoice_date' => 'nullable|date',
            'received_date' => 'nullable|date',
            'creditor_code' => 'required|exists:creditors,code',
            'receiving_id' => 'nullable|integer',
            'source' => 'nullable|string|in:invoice,manual',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'nullable|integer',
            'items.*.batch_id' => 'nullable|integer',
            'items.*.medicine_name' => 'required|string|max:255',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.retur_type' => 'nullable|string|in:packaging,unit',
            'items.*.stock_unit_factor' => 'nullable|integer|min:1',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expired_date' => 'nullable|date',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string|max:100',
            'items.*.pickup_date' => 'nullable|date',
            'items.*.deduction_note' => 'nullable|string|max:255',
        ]);

        $invoiceNumber = trim($validated['invoice_number']);

        // Kunci jika obat sudah pernah diajukan retur pada faktur tersebut
        $existingReturns = DB::table('purchase_return_items')
            ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
            ->where(function ($q) use ($invoiceNumber, $validated) {
                $q->where('purchase_returns.invoice_number', $invoiceNumber);
                if (!empty($validated['receiving_id'])) {
                    $q->orWhere('purchase_returns.receiving_id', $validated['receiving_id']);
                }
            })
            ->where('purchase_returns.status', '!=', 'dibatalkan')
            ->get(['purchase_return_items.medicine_id', 'purchase_return_items.medicine_name']);

        $existingMedIds = $existingReturns->pluck('medicine_id')->filter()->all();
        $existingMedNames = $existingReturns->pluck('medicine_name')->map(fn($n) => strtoupper(trim($n)))->all();

        foreach ($validated['items'] as $it) {
            $mId = $it['medicine_id'] ?? null;
            $mName = strtoupper(trim($it['medicine_name']));
            if (($mId && in_array($mId, $existingMedIds)) || in_array($mName, $existingMedNames)) {
                return back()->withErrors([
                    'items' => "Obat '{$it['medicine_name']}' sudah pernah di-input retur pada faktur '{$invoiceNumber}'. Dalam 1 faktur, 1 obat hanya bisa dikembalikan 1 kali."
                ]);
            }
        }

        // Cek apakah retur ini berasal dari penerimaan (faktur penerimaan) vs manual
        $source = $validated['source'] ?? null;
        $receivingId = $validated['receiving_id'] ?? null;
        $isFromReceiving = false;
        if ($source === 'invoice') {
            $isFromReceiving = true;
        } elseif ($source === 'manual') {
            $isFromReceiving = false;
        } else {
            // Fallback jika flag source tidak dikirim secara eksplisit
            $isFromReceiving = !empty($receivingId);
        }

        $activePharmacyId = function_exists('getActivePharmacyId') ? getActivePharmacyId() : 1;
        $yearMonth = date('ym');
        $latest = PurchaseReturn::where('return_no', 'LIKE', "RET-{$yearMonth}/%")->count();
        $nextSeq = str_pad($latest + 1, 4, '0', STR_PAD_LEFT);
        $returnNo = "RET-{$yearMonth}/{$nextSeq}";
        $todayDate = now()->toDateString();

        // Status awal berbeda menurut sumber, tetapi stok langsung dipotong untuk keduanya.
        $initialStatus = $isFromReceiving ? 'dikonfirmasi' : 'diajukan';
        $confirmedDate = $isFromReceiving ? $todayDate : null;

        DB::transaction(function () use ($validated, $activePharmacyId, $returnNo, $todayDate, $isFromReceiving, $initialStatus, $confirmedDate) {
            $totalAmount = 0;
            foreach ($validated['items'] as $it) {
                $subtotal = (float)$it['qty'] * (float)$it['unit_price'];
                $totalAmount += $subtotal;
            }

            $purchaseReturn = PurchaseReturn::create([
                'return_no' => $returnNo,
                'invoice_number' => $validated['invoice_number'],
                'invoice_date' => $validated['invoice_date'] ?? null,
                'received_date' => $validated['received_date'] ?? null,
                'creditor_code' => $validated['creditor_code'],
                'pharmacy_id' => $activePharmacyId,
                'user_id' => auth()->id(),
                'receiving_id' => $validated['receiving_id'] ?? null,
                'total_amount' => $totalAmount,
                'status' => $initialStatus,
                'status_date' => $todayDate,
                'confirmed_date' => $confirmedDate,
                'notes' => $validated['notes'] ?? null,
                'is_manual' => !$isFromReceiving,
            ]);

            foreach ($validated['items'] as $it) {
                $subtotal = (float)$it['qty'] * (float)$it['unit_price'];
                $returType = $it['retur_type'] ?? 'unit';
                $factor = (int) ($it['stock_unit_factor'] ?? 1);
                if ($returType === 'unit') {
                    $factor = 1;
                }

                $returnItem = PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'medicine_id' => $it['medicine_id'] ?? null,
                    'batch_id' => $it['batch_id'] ?? null,
                    'medicine_name' => $it['medicine_name'],
                    'unit' => $it['unit'] ?? 'PCS',
                    'retur_type' => $returType,
                    'stock_unit_factor' => max(1, $factor),
                    'batch_number' => $it['batch_number'] ?? '-',
                    'expired_date' => $it['expired_date'] ?? null,
                    'qty' => $it['qty'],
                    'unit_price' => $it['unit_price'],
                    'subtotal' => $subtotal,
                    'status' => $initialStatus,
                    'status_date' => $todayDate,
                    'confirmed_date' => $confirmedDate,
                    'stock_deducted' => false,
                    'reason' => $it['reason'] ?? 'ED DEKAT',
                    'pickup_date' => $it['pickup_date'] ?? null,
                    'deduction_note' => $it['deduction_note'] ?? null,
                ]);

                // Pengajuan manual juga langsung mengurangi stok agar barang tidak
                // tetap tersedia untuk dijual selama menunggu proses retur ke PBF.
                $returnItem->setRelation('purchaseReturn', $purchaseReturn);
                $this->deductStockForItem($returnItem, $todayDate);
                $returnItem->save();
            }
        });

        $successMsg = $isFromReceiving
            ? 'Data Retur Pembelian dari faktur berhasil disimpan dan stok otomatis dipotong.'
            : 'Pengajuan Retur Manual berhasil disimpan. Stok langsung dipotong dan status menunggu konfirmasi.';

        if ($request->wantsJson() || $request->ajax() || $request->isJson() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
            ]);
        }

        return redirect()->back()->with('success', $successMsg);
    }

    /**
     * Generate Kode Items Log (Kartu Stok)
     */
    protected function generateItemsLogCode(): string
    {
        $now = Carbon::now();
        $year = $now->format('y');
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

    /**
     * Potong stok obat dan catat ItemsLog (Kartu Stok) saat retur dikonfirmasi
     */
    protected function deductStockForItem(PurchaseReturnItem $item, string $actionDate): void
    {
        if ($item->stock_deducted) {
            return;
        }

        if ($item->medicine_id) {
            $med = Medicines::find($item->medicine_id);
            if ($med) {
                // Konversi qty retur ke satuan stok fisik dasar (jika retur kemasan utuh, kalikan stock_unit_factor)
                $factor = ($item->retur_type === 'packaging' && (int)$item->stock_unit_factor > 1)
                    ? (int)$item->stock_unit_factor
                    : 1;
                $qtyDeduct = (float) $item->qty * $factor;
                $qtyBefore = (float) $med->stock;

                // Kurangi stok fisik obat (diperbolehkan minus stok)
                $med->decrement('stock', $qtyDeduct);
                $qtyAfter = $qtyBefore - $qtyDeduct;

                // Kurangi stok batch jika ada
                $batch = null;
                if (!empty($item->batch_id)) {
                    $batch = Batches::find($item->batch_id);
                } elseif (!empty($item->batch_number) && $item->batch_number !== '-') {
                    $batch = Batches::where('medicine_id', $med->id)
                        ->where('name', $item->batch_number)
                        ->first();
                }

                if ($batch) {
                    $batch->decrement('stock', $qtyDeduct);
                }

                // Catat ke kartu stok / ItemsLog (status 4 = Retur Pembelian)
                ItemsLog::create([
                    'transaction_code' => $item->purchaseReturn?->return_no ?? ('RET-' . date('ymd')),
                    'code' => $this->generateItemsLogCode(),
                    'type' => 'RT',
                    'medicine_id' => $med->id,
                    'qty' => $qtyDeduct,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'total' => $item->subtotal,
                    'date' => $actionDate,
                    'status' => 4,
                    'batches_id' => $batch?->id ?? null,
                    'user_id' => auth()->id(),
                ]);
            }
        }

        $item->stock_deducted = true;
    }

    /**
     * Kembalikan stok obat jika status retur dibatalkan / dikembalikan ke 'diajukan'
     */
    protected function restoreStockForItem(PurchaseReturnItem $item, string $actionDate): void
    {
        if (!$item->stock_deducted) {
            return;
        }

        if ($item->medicine_id) {
            $med = Medicines::find($item->medicine_id);
            if ($med) {
                // Konversi qty retur ke satuan stok fisik dasar
                $factor = ($item->retur_type === 'packaging' && (int)$item->stock_unit_factor > 1)
                    ? (int)$item->stock_unit_factor
                    : 1;
                $qtyRestore = (float) $item->qty * $factor;
                $qtyBefore = (float) $med->stock;

                $med->increment('stock', $qtyRestore);
                $qtyAfter = $qtyBefore + $qtyRestore;

                $batch = null;
                if (!empty($item->batch_id)) {
                    $batch = Batches::find($item->batch_id);
                } elseif (!empty($item->batch_number) && $item->batch_number !== '-') {
                    $batch = Batches::where('medicine_id', $med->id)
                        ->where('name', $item->batch_number)
                        ->first();
                }

                if ($batch) {
                    $batch->increment('stock', $qtyRestore);
                }

                ItemsLog::create([
                    'transaction_code' => ($item->purchaseReturn?->return_no ?? ('RET-' . date('ymd'))) . '-BATAL',
                    'code' => $this->generateItemsLogCode(),
                    'type' => 'RT_BATAL',
                    'medicine_id' => $med->id,
                    'qty' => $qtyRestore,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'total' => $item->subtotal,
                    'date' => $actionDate,
                    // Status 6 memakai delta qty_after - qty_before pada kartu stok,
                    // sehingga pemulihan stok tercatat sebagai penambahan.
                    'status' => 6,
                    'batches_id' => $batch?->id ?? null,
                    'user_id' => auth()->id(),
                ]);
            }
        }

        $item->stock_deducted = false;
        $item->confirmed_date = null;
    }

    /**
     * Konfirmasi Retur: Ubah status ke 'dikonfirmasi' dan kurangi stok barang (perbolehkan minus stok)
     * NOTE: Wajib mencatat tanggal konfirmasi retur
     */
    public function confirmItem(Request $request, $id)
    {
        $validated = $request->validate([
            'confirmed_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $item = PurchaseReturnItem::with(['purchaseReturn', 'medicine'])->findOrFail($id);
        $confirmedDate = $validated['confirmed_date'];

        DB::transaction(function () use ($item, $confirmedDate, $validated) {
            $this->deductStockForItem($item, $confirmedDate);

            $item->status = 'dikonfirmasi';
            $item->status_date = $confirmedDate;
            $item->confirmed_date = $confirmedDate;
            if (!empty($validated['notes'])) {
                $item->notes = $validated['notes'];
            }
            $item->save();

            $p = $item->purchaseReturn;
            if ($p) {
                $p->update([
                    'status' => 'dikonfirmasi',
                    'status_date' => $confirmedDate,
                    'confirmed_date' => $confirmedDate,
                ]);
            }
        });

        $msg = "Retur {$item->medicine_name} berhasil dikonfirmasi. Stok barang telah dikurangi.";

        if ($request->wantsJson() || $request->ajax() || $request->isJson() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'item' => $item,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Ubah Status Retur setelah dikonfirmasi:
     * - 'diajukan' (Kembalikan ke status diajukan & pulihkan stok)
     * - 'dikonfirmasi' (Sudah Dikonfirmasi)
     * - 'sudah_potong' (Sudah Potong Tagihan -> kurangi tagihan hutang dagang pada receiving terkait secara konsisten)
     * - 'barang_kembali_pmi' (Barang Kembali PMI)
     * NOTE: TIAP PERUBAHAN STATUS, WAJIB CATAT TANGGALNYA
     */
    public function changeItemStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:diajukan,dikonfirmasi,sudah_diambil,sudah_potong,barang_kembali_pmi',
            'status_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $item = PurchaseReturnItem::with(['purchaseReturn', 'medicine'])->findOrFail($id);
        $oldStatus = $item->status;
        $newStatus = $validated['status'];
        $statusDate = $validated['status_date'];

        DB::transaction(function () use ($item, $oldStatus, $newStatus, $statusDate, $validated) {
            $p = $item->purchaseReturn;
            $refNo = ($p ? $p->return_no : 'RET') . '-ITEM-' . $item->id;

            // Cari receiving detail terkait jika ada
            $receivingDetail = null;
            if ($p) {
                if ($p->receiving_id) {
                    $receivingDetail = ReceivingDetails::with(['receiving', 'receiving_items'])->find($p->receiving_id);
                }
                if (!$receivingDetail && $p->invoice_number) {
                    $receivingDetail = ReceivingDetails::with(['receiving', 'receiving_items'])
                        ->where('invoice_number', $p->invoice_number)
                        ->first();
                }
            }

            if ($newStatus === 'sudah_potong') {
                $this->deductStockForItem($item, $statusDate);
                $item->deduction_date = $statusDate;
                if (empty($item->pickup_date)) {
                    $item->pickup_date = $statusDate;
                }
                $formattedDate = Carbon::parse($statusDate)->format('d/m/Y');
                $item->deduction_note = "SUDAH POTONG TAGIHAN ({$formattedDate})";

                // Potong tagihan hutang dagang pada receiving terkait
                if ($receivingDetail) {
                    $pharmacyId = $receivingDetail->receiving?->pharmacy_id ?? $p?->pharmacy_id ?? 1;
                    $account = FinanceAccount::where('pharmacy_id', $pharmacyId)->where('category', 'Kas & Bank')->first()
                        ?? FinanceAccount::where('category', 'Kas & Bank')->first()
                        ?? FinanceAccount::first();

                    $payment = FinancePayment::firstOrNew(['reference_number' => $refNo]);
                    $payment->pharmacy_id = $pharmacyId;
                    $payment->receiving_id = $receivingDetail->receiving_id;
                    $payment->receiving_detail_id = $receivingDetail->id;
                    $payment->account_id = $account?->id ?? 1;
                    $payment->payment_type = 'RETUR';
                    $payment->payment_date = $statusDate;
                    $payment->amount = (float) $item->subtotal;
                    $payment->reference_number = $refNo;
                    $payment->notes = 'Potong Tagihan Retur Beli: ' . $item->medicine_name . ' (' . ($p->return_no ?? '-') . ')';
                    $payment->created_by = auth()->id();
                    $payment->save();

                    // Cek jika seluruh faktur penerimaan sudah lunas
                    $ppnType = strtoupper(trim($receivingDetail->invoice_ppn ?? $receivingDetail->creditor?->ppn_type ?? 'TANPA'));
                    $totalInvoice = ReceivingInvoiceTotals::calculate($receivingDetail->receiving_items, $ppnType)['total'];
                    $totalPaid = (float) FinancePayment::where('receiving_detail_id', $receivingDetail->id)->sum('amount');
                    if (($totalPaid >= $totalInvoice || ($totalInvoice - $totalPaid) < 0.005) && $receivingDetail->receiving) {
                        $receivingDetail->receiving->update(['status' => 3]);
                    }
                }
            } elseif ($newStatus === 'sudah_diambil') {
                // Status Baru: Sudah Diambil oleh PBF
                $this->deductStockForItem($item, $statusDate);
                $item->pickup_date = $statusDate; // Tanggal diambil wajib sama dan konsisten dengan tanggal status
                if (!$item->confirmed_date) {
                    $item->confirmed_date = $statusDate;
                }

                // Hapus potongan tagihan jika sebelumnya sudah_potong
                if ($oldStatus === 'sudah_potong') {
                    FinancePayment::where('reference_number', $refNo)->delete();
                    $item->deduction_date = null;
                    $item->deduction_note = null;
                }
            } elseif ($newStatus === 'dikonfirmasi') {
                $this->deductStockForItem($item, $statusDate);
                $item->confirmed_date = $statusDate;

                // Hapus potongan tagihan jika sebelumnya sudah_potong
                if ($oldStatus === 'sudah_potong') {
                    FinancePayment::where('reference_number', $refNo)->delete();
                    $item->deduction_date = null;
                    $item->deduction_note = null;
                }
            } elseif ($newStatus === 'barang_kembali_pmi') {
                $this->deductStockForItem($item, $statusDate);
                $item->pickup_date = $statusDate;

                // Hapus potongan tagihan jika sebelumnya sudah_potong
                if ($oldStatus === 'sudah_potong') {
                    FinancePayment::where('reference_number', $refNo)->delete();
                    $item->deduction_date = null;
                    $item->deduction_note = null;
                }
            } elseif ($newStatus === 'diajukan') {
                // Retur manual yang kembali ke status menunggu tetap menahan stok;
                // retur dari faktur mengikuti perilaku lama dan memulihkan stok.
                if (!$p?->is_manual) {
                    $this->restoreStockForItem($item, $statusDate);
                }
                FinancePayment::where('reference_number', $refNo)->delete();
                $item->pickup_date = null;
                $item->confirmed_date = null;
                $item->deduction_date = null;
                $item->deduction_note = null;
            }

            // Update status receiving jika pembayaran berubah
            if ($oldStatus === 'sudah_potong' && $newStatus !== 'sudah_potong' && $receivingDetail && $receivingDetail->receiving && (int)$receivingDetail->receiving->status === 3) {
                $ppnType = strtoupper(trim($receivingDetail->invoice_ppn ?? $receivingDetail->creditor?->ppn_type ?? 'TANPA'));
                $totalInvoice = ReceivingInvoiceTotals::calculate($receivingDetail->receiving_items, $ppnType)['total'];
                $totalPaid = (float) FinancePayment::where('receiving_detail_id', $receivingDetail->id)->sum('amount');
                if ($totalPaid < $totalInvoice) {
                    $receivingDetail->receiving->update(['status' => $totalPaid > 0 ? 2 : 1]);
                }
            }

            $item->status = $newStatus;
            $item->status_date = $statusDate;
            if (isset($validated['notes'])) {
                $item->notes = $validated['notes'];
            }
            $item->save();

            if ($p) {
                $p->update([
                    'status' => $newStatus,
                    'status_date' => $statusDate,
                ]);
            }
        });

        $statusLabels = [
            'diajukan' => 'Diajukan Retur',
            'dikonfirmasi' => 'Sudah Dikonfirmasi',
            'sudah_diambil' => 'Sudah Diambil',
            'sudah_potong' => 'Sudah Potong Tagihan',
            'barang_kembali_pmi' => 'Barang Kembali PMI',
        ];
        $label = $statusLabels[$newStatus] ?? $newStatus;
        $msg = "Status retur {$item->medicine_name} berhasil diubah menjadi: {$label}.";

        if ($request->wantsJson() || $request->ajax() || $request->isJson() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'item' => $item,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Update status item retur (misal update tanggal diambil atau potong tagihan langsung di tabel)
     */
    public function updateItem(Request $request, $id)
    {
        $item = PurchaseReturnItem::findOrFail($id);

        $validated = $request->validate([
            'pickup_date' => 'nullable|date',
            'deduction_note' => 'nullable|string|max:255',
            'reason' => 'nullable|string|max:100',
            'qty' => 'nullable|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0',
        ]);

        if (isset($validated['qty']) || isset($validated['unit_price'])) {
            $qty = $validated['qty'] ?? $item->qty;
            $unitPrice = $validated['unit_price'] ?? $item->unit_price;
            $validated['subtotal'] = (float)$qty * (float)$unitPrice;
        }

        if (!empty($validated['pickup_date'])) {
            // Jika tanggal diambil diisi, pastikan status dan tanggal konsisten
            if (in_array($item->status, ['diajukan', 'dikonfirmasi'])) {
                $validated['status'] = 'sudah_diambil';
                $validated['status_date'] = $validated['pickup_date'];
                $this->deductStockForItem($item, $validated['pickup_date']);
            } elseif ($item->status === 'sudah_diambil') {
                $validated['status_date'] = $validated['pickup_date'];
            }
        }

        $item->update($validated);

        // Recalculate total header amount
        $parent = $item->purchaseReturn;
        if ($parent) {
            $newTotal = $parent->items()->sum('subtotal');
            $parent->update(['total_amount' => $newTotal]);
        }

        $msg = 'Item retur berhasil diperbarui.';

        if ($request->wantsJson() || $request->ajax() || $request->isJson() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'item' => $item,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Hapus item retur
     */
    public function destroyItem(Request $request, $id)
    {
        $item = PurchaseReturnItem::with('purchaseReturn')->findOrFail($id);
        $parent = $item->purchaseReturn;

        if ($item->status === 'sudah_potong') {
            return response()->json([
                'success' => false,
                'message' => 'Retur yang sudah dipotong ke tagihan tidak dapat dihapus. Ubah status potong tagihan terlebih dahulu.',
            ], 422);
        }

        DB::transaction(function () use ($item, $parent) {
            // Penghapusan membatalkan retur, jadi pulihkan stok yang pernah dipotong.
            $this->restoreStockForItem($item, now()->toDateString());
            $item->delete();

            if ($parent) {
                if ($parent->items()->count() === 0) {
                    $parent->delete();
                } else {
                    $parent->update(['total_amount' => $parent->items()->sum('subtotal')]);
                }
            }
        });

        $msg = 'Item retur berhasil dihapus.';

        if ($request->wantsJson() || $request->ajax() || $request->isJson() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Export Laporan Monitoring Retur Beli ke Excel
     */
    public function export(Request $request)
    {
        $items = PurchaseReturnItem::with(['purchaseReturn.creditor', 'medicine.factory'])
            ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
            ->select('purchase_return_items.*')
            ->orderBy('purchase_returns.created_at', 'desc')
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monitoring Retur Beli');

        $headers = [
            'NO', 'NAMA OBAT', 'JUMLAH', 'SATUAN', 'BATCH', 'NO FAKTUR',
            'ED', 'KETENTUAN RETUR', 'PBF', 'TANGGAL ENTRI / FAKTUR', 'KETERANGAN', 'TGL BRG DI AMBIL', 'STATUS POTONG TAGIHAN', 'TOTAL HARGA'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $usedCreditorCodes = $items->pluck('purchaseReturn.creditor_code')->filter()->unique()->values()->all();
        $creditorPolicies = ReturnPolicy::whereIn('creditor_code', $usedCreditorCodes)->get()->groupBy('creditor_code');

        $rows = [];
        foreach ($items as $idx => $it) {
            $p = $it->purchaseReturn;
            $creditorCode = $p ? $p->creditor_code : null;
            $factoryId = $it->medicine ? $it->medicine->factory_id : null;
            $factoryName = $it->medicine && $it->medicine->factory ? $it->medicine->factory->name : null;

            $policies = $creditorCode && isset($creditorPolicies[$creditorCode]) ? $creditorPolicies[$creditorCode] : collect();
            $policy = $this->findReturnPolicy($creditorCode, $factoryId, $factoryName, $policies);
            $eval = $this->evaluateReturnEligibility($policy, $it->expired_date);

            $rows[] = [
                $idx + 1,
                $it->medicine_name . ($factoryName ? " ({$factoryName})" : ''),
                (float) $it->qty,
                $it->unit,
                $it->batch_number,
                $p ? $p->invoice_number : '',
                $it->expired_date ? Carbon::parse($it->expired_date)->format('d/m/Y') : '-',
                $eval['label'] . ($eval['policy']['notes'] ?? '' ? ' - ' . $eval['policy']['notes'] : ''),
                $p && $p->creditor ? $p->creditor->name : ($p->creditor_code ?? ''),
                $p && $p->invoice_date ? Carbon::parse($p->invoice_date)->format('d/m/Y') : ($it->created_at->format('d/m/Y')),
                $it->reason ?: ($p->reason ?? 'ED DEKAT'),
                $it->pickup_date ? Carbon::parse($it->pickup_date)->format('d/m/Y') : '-',
                $it->deduction_note ?: '-',
                (float) $it->subtotal,
            ];
        }

        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = 'Monitoring_Retur_Beli_' . date('Ymd_His') . '.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
