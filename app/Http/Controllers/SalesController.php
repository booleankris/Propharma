<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use App\Models\Debtors;
use App\Models\FinanceAccount;
use App\Models\FinancePayment;
use App\Models\Batches;
use App\Models\Doctors;
use App\Models\Item;
use App\Models\ItemCart;
use App\Models\ItemsLog;
use App\Models\MedicineCart;
use App\Models\Medicines;
use App\Models\ReceivingItems;
use App\Models\MedicineTransactions;
use App\Models\Patients;
use App\Models\PaymentParameters;
use App\Models\Sales;
use App\Models\TicketPayment;
use App\Models\TicketTransaction;
use App\Models\MedicineTransfers;
use App\Models\MedicineTransferItems;
use App\Services\MedicineTransactionCodeGenerator;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class SalesController extends Controller
{
    public function index($type, $id = null)
    {
        $pharmacy_id = Auth::user()->pharmacy_id;
        $user_id = Auth::user()->id;

        // 1. Resolve display-type → DB-type + parameter key 
        $typeMap = [
            'resep' => ['db' => 'RESEP TUNAI', 'param_key' => 'receipt', 'code' => '1'],
            'kredit' => ['db' => 'KREDIT', 'param_key' => null, 'code' => '4'],
            'upds' => ['db' => 'UPDS', 'param_key' => 'pdu', 'code' => '2'],
            'hv' => ['db' => 'HV/OTC', 'param_key' => 'otc', 'code' => '3'],
        ];

        abort_if(!array_key_exists($type, $typeMap), 404, 'Invalid transaction type.');

        $meta = $typeMap[$type];
        $dbType = $meta['db'];
        $paramKey = $meta['param_key'];

        // 2. Load payment parameters once
        $paymentParams = PaymentParameters::findOrFail(1);
        $parameterHV = $paymentParams->otc;
        $parameterUP = $paymentParams->pdu;
        $parameterRT = $paymentParams->receipt;
        $service = $paymentParams->embalas;
        $ChangeFakturParameters = $paymentParams->pdu;
        $ChangeFakturRounding = $paymentParams->rounding;
        $rounding = $dbType === 'KREDIT' ? '0' : $paymentParams->rounding;
        $parameters = $dbType === 'KREDIT' ? '0' : $paymentParams->{$paramKey};
        // 3. Resolve which transaction to use
        if ($id !== null) {
            // Tab already has a specific transaction, load it, guard status
            $transaction = MedicineTransactions::with(['patients', 'doctors', 'debtors'])
                ->where('pharmacy_id', $pharmacy_id)
                ->where('id', $id)
                ->where('status', 0)
                ->first();

            if (!$transaction) {
                // Transaction finished or doesn't belong to this pharmacy
                // Fall back to opening a fresh transaction of the requested type
                return redirect()->route('transaction', ['type' => $type])->with('message', 'Transaksi sudah selesai atau tidak ditemukan. Membuka transaksi baru.');
            }


            // If another cashier resumes this pending transaction in the same pharmacy, take over user_id so subsequent cart operations and checkout work cleanly
            if ($transaction->user_id !== Auth()->user()->id) {
                $transaction->update(['user_id' => Auth()->user()->id]);
                MedicineCart::where('transaction_id', $transaction->id)
                    ->update(['user_id' => Auth()->user()->id]);
            }

            $trx_id = $transaction->id;
        } else {
            // No ID supplied — DO NOT look for ANY pending transaction.
            // Create a NEW transaction unconditionally to isolate tabs.
            try {
                DB::beginTransaction();

                $transaction = MedicineTransactions::create([
                    'pharmacy_id' => $pharmacy_id,
                    'user_id' => Auth()->user()->id,
                    'debtor_id' => null,
                    'transaction_type' => $dbType,
                    'transaction_code' => null,
                    'subtotal' => null,
                    'discount' => null,
                    'status' => 0,
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()->back()->with('message', 'Gagal membuat transaksi: ' . $e->getMessage());
            }

            // Redirect WITH the new id so the URL is stable for this tab
            return redirect()->route('transaction', [
                'type' => $type,
                'id' => $transaction->id,
            ]);
        }

        // 4. Handle type switch on an existing transaction ────────────────────
        // If the user navigates to a different type on the same transaction,
        // update the type and clear the cart (prices differ per type).

        if ($transaction->transaction_type !== $dbType) {
            $transaction->update(['transaction_type' => $dbType, 'transaction_code' => null]);
            MedicineCart::where('transaction_id', $transaction->id)->delete();
        }

        $trx_id = $transaction->id;

        if ($dbType === 'KREDIT' && $transaction->debtor_id) {
            $transaction->load('debtors.parameters');
            if ($transaction->debtors && $transaction->debtors->parameters->isNotEmpty()) {
                $debtorParam = $transaction->debtors->parameters->first();
                $parameters = $debtorParam->receipt ?? '0';
                $rounding = $debtorParam->rounding ?? '0';
                $service = $debtorParam->embalas ?? '0';
            }
        }

        // Pending transactions do not reserve a receipt number; the final code
        // is allocated atomically during checkout.
        $transaction_code = $transaction->transaction_code;

        // 5. Collect view data───────────────
        $itemInCart = MedicineCart::with('medicine')
            ->where('transaction_id', $trx_id)
            ->where('status', 0)
            ->orderBy('id', 'asc')
            ->get();

        $activeCart = $itemInCart;
        $totaltransaction = (float) $activeCart->sum('final_price');

        $total_price = (object)[
            'total_price' => (float) $activeCart->sum('total_price'),
            'embalase' => (float) $activeCart->sum('embalase'),
        ];

        $existingpackage = $itemInCart->first(function ($cartItem) {
            return $cartItem->recipe_status == '0' && !empty($cartItem->package);
        });

        $discount_total = (float) $activeCart->sum('discount');

        $check_transaction = MedicineTransactions::where('pharmacy_id', $pharmacy_id)
            ->where('status', 0)
            ->count();

        return response()
            ->view('kasir.transaction', compact(
                'transaction_code',
                'check_transaction',
                'type',
                'trx_id',
                'transaction',
                'existingpackage',
                // 'priceTotal',
                'total_price',
                'parameters',
                'rounding',
                'itemInCart',
                'totaltransaction',
                'discount_total',
                'ChangeFakturParameters',
                'parameterRT',
                'parameterHV',
                'parameterUP',
                'service',
                'ChangeFakturRounding'
            ))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    // Helper: build authoritative cart state + totals from DB ──────────────
    private function buildCartSyncPayload(int $transactionId): array
    {
        $items = MedicineCart::with('medicine')
            ->where('transaction_id', $transactionId)
            ->where('status', 0)
            ->orderBy('id', 'asc')
            ->get();

        $totalTransaction = (float) $items->sum('final_price');
        $totalDiscount = (float) $items->sum('discount');
        $totalBought = (float) $items->sum('total_price') + (float) $items->sum('embalase');

        $cartItems = $items->map(function (MedicineCart $row) {
            return [
                'id' => $row->id,
                'medicine_id' => $row->medicine_id,
                'name' => $row->medicine->name ?? '-',
                'unit' => $row->medicine->unit ?? '',
                'packaging' => $row->medicine->packaging ?? '',
                'quantity' => (float) $row->quantity,
                'discount' => (float) $row->discount,
                'total_price' => (float) $row->total_price,
                'embalase' => (float) $row->embalase,
                'service_fee' => (float) ($row->service_fee ?? 0),
                'final_price' => (float) $row->final_price,
                'cart_type' => $row->cart_type,
                'recipe_status' => $row->recipe_status,
                'recipe_number' => $row->recipe_number,
            ];
        })->values()->all();

        return [
            'total_transaction' => $totalTransaction,
            'totaltransaction' => $totalTransaction,
            'total_discount' => $totalDiscount,
            'totalbought' => $totalBought,
            'cart_count' => count($cartItems),
            'cart_items' => $cartItems,
        ];
    }

    // Helper: map DB type string back to route slug ───────────────────────────
    private function dbTypeToRouteType(string $dbType): string
    {
        return match ($dbType) {
            'RESEP TUNAI' => 'resep',
            'KREDIT' => 'kredit',
            'UPDS' => 'upds',
            'HV/OTC' => 'hv',
            default => 'resep',
        };
    }
    function generateItemsLogCode()
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
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

    // Transaction Flow
    /*
    Transaction status code
    0 = active transaction
    1 = completed transaction

    Cart status code
    0 = In cart
    1 = Processed
    2 = Paid

    1. Create Transaction on transaction page( Status = 0 )
    2. If user close or go to other page and go back to transaction page, it check the transaction status, then get the transaction detail of where the transaction status = 0
    3. If transaction with status = 0 is not found, then create new transaction
    4. 
    */

    // Flow 1 Create Transaction

    // Flow 2 Search Item

    public function search(Request $request)
    {
        $q = trim($request->get('q', ''));
        $pharmacyId = getActivePharmacyId();
        $warehouseId = getWarehousePharmacyId();
        $canSeeWarehouse = canAccessWarehouseStock($pharmacyId);

        $query = Medicines::query()
            ->where('medicines.status', 1)
            ->select([
                'medicines.id',
                'medicines.code',
                'medicines.barcode',
                'medicines.name',
                'medicines.factory_id',
                'medicines.raw_price',
                'medicines.het_price',
                'medicines.net_price',
                'medicines.location',
                'medicines.etalase',
                'medicines.strip',
                'medicines.stock',
                'medicines.unit',
                'medicines.packaging',
                'medicines.content',
                'medicines.dosage',
            ])
            ->selectRaw('COALESCE(cs.counter_stock, 0) as counter_stock');

        if ($canSeeWarehouse) {
            $query->selectRaw('COALESCE(bs.storage_stock, 0) as storage_stock')
                ->leftJoinSub(
                    DB::table('batches')
                        ->select('medicine_id', DB::raw('COALESCE(SUM(stock), 0) as storage_stock'))
                        ->where('pharmacy_id', $warehouseId)
                        ->groupBy('medicine_id'),
                    'bs',
                    'bs.medicine_id',
                    '=',
                    'medicines.id'
                );
        } else {
            $query->selectRaw('0 as storage_stock');
        }

        $items = $query
            ->leftJoinSub(
                DB::table('medicine_transfer_items as mt')
                    ->join('batches as b', 'b.id', '=', 'mt.batches_id')
                    ->select('b.medicine_id', DB::raw('COALESCE(SUM(mt.qty), 0) as counter_stock'))
                    ->where('mt.status', 1)
                    ->where('b.pharmacy_id', $pharmacyId)
                    ->where(function ($q) {
                        $q->whereNull('mt.source_type')->orWhere('mt.source_type', '!=', 'retur_gudang');
                    })
                    ->groupBy('b.medicine_id'),
                'cs',
                'cs.medicine_id',
                '=',
                'medicines.id'
            )
            ->with(['etalases', 'locations', 'factory'])

            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($x) use ($q) {
                    $x->where('medicines.code', 'like', $q . '%')
                        ->orWhere('medicines.barcode', 'like', $q . '%')
                        ->orWhere('medicines.name', 'like', '%' . $q . '%');
                });
            })

            ->orderByRaw(
                "CASE WHEN medicines.code LIKE ? THEN 0 ELSE 1 END, medicines.code ASC",
                [$q . '%']
            )

            ->limit(40)
            ->get();

        $medicineIds = $items->pluck('id')->filter()->toArray();
        if (!empty($medicineIds)) {
            $branchStocks = DB::table('medicine_transfer_items as mt')
                ->join('batches as b', 'b.id', '=', 'mt.batches_id')
                ->select(
                    'b.medicine_id',
                    DB::raw('COALESCE(SUM(CASE WHEN b.pharmacy_id = 1 THEN mt.qty ELSE 0 END), 0) as stock_pmi'),
                    DB::raw('COALESCE(SUM(CASE WHEN b.pharmacy_id = 2 THEN mt.qty ELSE 0 END), 0) as stock_asm'),
                    DB::raw('COALESCE(SUM(CASE WHEN b.pharmacy_id = 3 THEN mt.qty ELSE 0 END), 0) as stock_mim'),
                    DB::raw('COALESCE(SUM(CASE WHEN b.pharmacy_id = 5 THEN mt.qty ELSE 0 END), 0) as stock_asa')
                )
                ->where('mt.status', 1)
                ->whereIn('b.medicine_id', $medicineIds)
                ->whereIn('b.pharmacy_id', [1, 2, 3, 5])
                ->where(function ($q) {
                    $q->whereNull('mt.source_type')->orWhere('mt.source_type', '!=', 'retur_gudang');
                })
                ->groupBy('b.medicine_id')
                ->get()
                ->keyBy('medicine_id');

            foreach ($items as $item) {
                $bs = $branchStocks->get($item->id);
                $item->counter_stock = (float) ($item->counter_stock ?? 0);
                $item->storage_stock = (float) ($item->storage_stock ?? 0);
                $item->stock_pmi = (float) ($bs?->stock_pmi ?? 0);
                $item->stock_asm = (float) ($bs?->stock_asm ?? 0);
                $item->stock_mim = (float) ($bs?->stock_mim ?? 0);
                $item->stock_asa = (float) ($bs?->stock_asa ?? 0);
                $item->active_pharmacy_id = (int) $pharmacyId;
            }
        } else {
            foreach ($items as $item) {
                $item->counter_stock = (float) ($item->counter_stock ?? 0);
                $item->storage_stock = (float) ($item->storage_stock ?? 0);
                $item->stock_pmi = 0;
                $item->stock_asm = 0;
                $item->stock_mim = 0;
                $item->stock_asa = 0;
                $item->active_pharmacy_id = (int) $pharmacyId;
            }
        }

        return response()->json($items);
    }
    // public function search(Request $request)
    // {
    //     $q = trim($request->get('q', ''));

    //     $items = Medicines::query()
    //         ->select([
    //             'id',
    //             'code',
    //             'barcode',
    //             'name',
    //             'raw_price',
    //             'het_price',
    //             'net_price',
    //             'location',
    //             'etalase',
    //             'stock',
    //             'unit',
    //             'packaging',
    //             'content',
    //             'dosage',
    //             DB::raw('(SELECT COALESCE(SUM(stock), 0) FROM batches WHERE medicine_id = medicines.id) as storage_stock'),
    //             DB::raw('(SELECT COALESCE(SUM(mt.stock), 0) FROM medicine_transfer_items mt JOIN batches b ON b.id = mt.batches_id WHERE b.medicine_id = medicines.id AND mt.status = 1) as counter_stock'),
    //         ])
    //         ->with(['etalases', 'locations'])
    //         ->when(
    //             $q !== '',
    //             fn($query) =>
    //             $query->where('code',      'like', $q . '%')
    //                 ->orWhere('barcode', 'like', $q . '%')
    //                 ->orWhere('name',    'like', '%' . $q . '%')
    //         )
    //         ->orderByRaw("CASE WHEN code LIKE ? THEN 0 ELSE 1 END, code ASC", [$q . '%'])
    //         ->limit(40)
    //         ->get();

    //     return response()->json($items);
    // }
    public function searchDebtors(Request $request)
    {
        $q = trim($request->get('q', ''));

        $items = Debtors::with('parameters')
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($x) use ($q) {
                    $x->where('code', 'like', '%' . $q . '%')
                        ->orWhere('name', 'like', '%' . $q . '%')
                        ->orWhere('phone', 'like', '%' . $q . '%');
                });
            })
            ->select(['id', 'code', 'name', 'address', 'city', 'phone', 'contact', 'email', 'status'])
            ->orderByRaw("CASE WHEN code LIKE ? THEN 0 ELSE 1 END, code ASC", [$q . '%'])
            ->limit(10)
            ->get();

        return response()->json($items);
    }
    // Transaction Type
    // 1 = Resep Tunai
    // 2 = UPDS
    // 3 = HV/OTC
    // 4 = Resep Kredit
    public function generateTransactionCode($code, ?int $pharmacyId = null)
    {
        return MedicineTransactionCodeGenerator::generate((string) $code, $pharmacyId);
    }
    public function regenerateTransactionCode($newTypeCode, $oldCode)
    {
        return $this->generateTransactionCode($newTypeCode);
    }
    public function searchPatients(Request $request)
    {
        $q = trim($request->get('q', ''));

        $items = Patients::select(['id', 'code', 'name', 'address', 'city', 'phone', 'birth', 'status'])
            ->where(function ($query) use ($q) {
                $query->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', '%' . $q . '%');
            })
            ->orderByRaw("CASE WHEN code LIKE ? THEN 0 ELSE 1 END, code ASC", [$q . '%'])
            ->limit(10)
            ->get();

        return response()->json($items);
    }

    public function searchDoctors(Request $request)
    {
        $q = trim($request->get('q', ''));

        $items = Doctors::select(['id', 'code', 'name', 'address', 'city', 'phone', 'status'])
            ->where(function ($query) use ($q) {
                $query->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            })
            ->orderByRaw("CASE WHEN code LIKE ? THEN 0 ELSE 1 END, code ASC", [$q . '%'])
            ->limit(10)
            ->get();

        return response()->json($items);
    }

    public function createTransaction(Request $request)
    {
        if ($request->get('type') == 'resep') {
            $typenew = "RESEP TUNAI";
        } else if ($request->get('type') == 'kredit') {
            $typenew = "KREDIT";
        } else if ($request->get('type') == 'upds') {
            $typenew = "UPDS";
        } else if ($request->get('type') == 'hv') {
            $typenew = "HV/OTC";
        }

        try {
            DB::beginTransaction();

            $transaction = MedicineTransactions::create([
                'pharmacy_id' => getActivePharmacyId(),
                'user_id' => Auth()->user()->id,
                'debtor_id' => NULL,
                'transaction_type' => $typenew,
                'transaction_code' => NULL,
                'subtotal' => NULL,
                'discount' => NULL,
                'status' => 0,
            ]);

            DB::commit();
            return redirect()->route('transaction', [
                'type' => $request->get('type'),
                'id' => $transaction->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', "Gagal Menyimpan! " . $e->getMessage());
        }
    }
    public function deleteTransaction(Request $request)
    {
        $item = MedicineTransactions::findOrFail($request->get('trxid'));

        if ($item) {
            $item->delete();
            if ($request->get('trxtype') == "UPDS") {
                $url = 'upds';
            } else if ($request->get('trxtype') == "HV/OTC") {
                $url = 'hv';
            } else if ($request->get('trxtype') == "RESEP TUNAI") {
                $url = 'resep';
            } else if ($request->get('trxtype') == "KREDIT") {
                $url = 'kredit';
            }
            $transaction = MedicineTransactions::where('pharmacy_id', getActivePharmacyId())->where('status', 0)->latest()->first();

            if ($transaction) {
                return redirect()->route('home');
            } else {
                return redirect()->route('home');
            }
        }
    }

    // Flow 4 Add to Cart

    public function addToCart(Request $request)
    {
        $transaction = DB::transaction(function () use ($request) {
            // Serialize cart writes on the parent transaction. This prevents two
            // tabs/devices from both selecting the same "next recipe" state.
            $parentTransaction = MedicineTransactions::where('id', $request->get('transaction_id'))
                ->where('user_id', auth()->id())
                ->where('status', 0)
                ->lockForUpdate()
                ->firstOrFail();

            $cartType = $request->get('cart_type');
            $medicineType = $request->get('medicine_type');
            $embalase = $request->get('embalase');
            if ($parentTransaction->transaction_type === 'KREDIT') {
                if (!in_array($cartType, ['UK', 'UP', 'HV'], true)) {
                    abort(422, 'Jenis item resep kredit tidak valid.');
                }
                // Keep the parent invoice as credit, but persist the selected
                // invoice type on each item for database/detail reporting.
                $medicineType = [
                    'UK' => 'KREDIT',
                    'UP' => 'UPDS',
                    'HV' => 'HV/OTC',
                ][$cartType];
                if ($cartType !== 'UK') {
                    $embalase = 0;
                }
            }

            $isRecipe = (int) $request->get('racikstatus') === 1;
            if ($isRecipe && !in_array($parentTransaction->transaction_type, ['RESEP TUNAI', 'KREDIT'], true)) {
                abort(422, 'Racikan hanya dapat ditambahkan pada resep tunai atau kredit.');
            }

            $recipeNumber = null;
            $recipeStatus = null;
            if ($isRecipe) {
                $startNewRecipe = $request->boolean('start_new_recipe');
                $latestRecipeItem = MedicineCart::where('transaction_id', $parentTransaction->id)
                    ->where('user_id', auth()->id())
                    ->where('status', 0)
                    ->whereNotNull('recipe_number')
                    ->latest('id')
                    ->first();

                if ($latestRecipeItem && (int) $latestRecipeItem->recipe_status === 0) {
                    $recipeNumber = (int) $latestRecipeItem->recipe_number;
                } elseif (!$latestRecipeItem && $startNewRecipe) {
                    $recipeNumber = 1;
                } elseif ($latestRecipeItem && (int) $latestRecipeItem->recipe_status === 1 && $startNewRecipe) {
                    $highestRecipeNumber = MedicineCart::where('transaction_id', $parentTransaction->id)
                        ->where('user_id', auth()->id())
                        ->where('status', 0)
                        ->whereNotNull('recipe_number')
                        ->pluck('recipe_number')
                        ->map(fn ($number) => (int) $number)
                        ->max();
                    $recipeNumber = ((int) $highestRecipeNumber) + 1;
                } else {
                    abort(409, 'Racikan sebelumnya sudah selesai. Matikan lalu aktifkan kembali mode racikan untuk memulai racikan baru.');
                }
                $recipeStatus = 0;
            }

            // Service Filter: only non-recipe UM/UK items may receive service fee.
            $service = $isRecipe
                ? 0
                : (in_array($cartType, ['UM', 'UK'], true) ? $request->get('service', 0) : 0);

            if ($request->has('debtor_id') && $request->filled('debtor_id') && $parentTransaction->transaction_type === 'KREDIT') {
                $parentTransaction->update(['debtor_id' => $request->get('debtor_id')]);
            }

            return MedicineCart::create([
                'user_id' => auth()->id(),
                'medicine_id' => $request->get('medicine_id'),
                'transaction_id' => $parentTransaction->id,
                'quantity' => $request->get('quantity'),
                'discount' => $request->get('discount'),
                'embalase' => $embalase,
                'cart_type' => $cartType,
                'package' => $request->get('package'),
                'dosage_r' => $request->get('dosage_r'),
                'item_price' => $request->get('price2'),
                'raw_total' => $request->get('raw_total'),
                'total_price' => $request->get('final_price'),
                'final_price' => $request->get('total_price'),
                'status' => 0,
                'recipe_status' => $recipeStatus,
                'recipe_number' => $recipeNumber,
                'medicine_type' => $medicineType,
                'service_fee' => $service,
            ]);
        });
        $transaction->load('medicine');
        $syncPayload = $this->buildCartSyncPayload((int) $transaction->transaction_id);

        return response()->json(array_merge([
            'id' => $transaction->id,
            'name' => $transaction->medicine->name,
            'unit' => $transaction->medicine->unit,
            'packaging' => $transaction->medicine->packaging,
            'price' => $transaction->medicine->net_price,
            'quantity' => $transaction->quantity,
            'discount' => $transaction->discount,
            'total_price' => $transaction->total_price,
            'embalase' => $transaction->embalase,
            'service_fee' => $transaction->service_fee,
            'final_price' => $transaction->final_price,
            'cart_type' => $transaction->cart_type,
            'remove_url' => route('sales.removeItem', $transaction->id),
        ], $syncPayload));
    }
    public function getCartItem($id)
    {
        $cart = MedicineCart::with('medicine')->findOrFail($id);
        return response()->json($cart);
    }
    public function deleteCartItem($id)
    {
        $cart = MedicineCart::findOrFail($id);
        $transactionId = (int) $cart->transaction_id;
        $cart->delete();

        $syncPayload = $this->buildCartSyncPayload($transactionId);
        return response()->json(array_merge([
            'success' => true,
            'item' => $cart,
        ], $syncPayload));
    }
    private function generatePatientCode()
    {
        $last = Patients::where('code', 'like', 'PT%')->orderByDesc('id')->first();

        if (!$last || !$last->code) {
            return 'PT0001';
        }

        $number = (int) substr($last->code, 2);
        return 'PT' . str_pad($number + 1, 4, '0', STR_PAD_LEFT);
    }

    public function addPatient(Request $request)
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $request->name));
        $phone = trim((string) $request->phone);

        if ($name === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nama pasien wajib diisi.',
            ], 422);
        }

        $matches = \App\Support\PatientMatcher::findMatches(
            $name,
            $phone,
            Patients::select(['id', 'code', 'name', 'address', 'city', 'phone', 'birth'])->get()
        );

        // Jika nama sama persis atau no telepon sama persis -> minta konfirmasi jika belum force
        if ($matches['exact'] && !$request->boolean('force')) {
            $suggestions = array_values(array_unique(array_merge([$matches['exact']], $matches['similar']), SORT_REGULAR));
            return response()->json([
                'success'            => false,
                'needs_confirmation' => true,
                'is_exact'           => true,
                'is_phone_match'     => $matches['is_phone_match'],
                'match_reason'       => $matches['match_reason'],
                'message'            => 'Data pasien "' . $matches['exact']->name . '" sudah ada di sistem (' . $matches['match_reason'] . ').',
                'existing_patient'   => $matches['exact'],
                'suggestions'        => $suggestions,
            ]);
        }

        // Jika nama mirip (kemungkinan typo) -> minta konfirmasi jika belum force
        if (!empty($matches['similar']) && !$request->boolean('force')) {
            return response()->json([
                'success'            => false,
                'needs_confirmation' => true,
                'is_exact'           => false,
                'is_phone_match'     => false,
                'match_reason'       => 'similar',
                'message'            => 'Pasien dengan nama serupa ditemukan di sistem.',
                'suggestions'        => $matches['similar'],
            ]);
        }

        $code = $this->generatePatientCode();

        $patient = Patients::create([
            'code'    => $code,
            'name'    => $name,
            'address' => $request->get('address') ?: '-',
            'phone'   => $phone ?: '-',
            'city'    => $request->get('city') ?: '-',
            'birth'   => $request->get('birth') ?: '-',
        ]);

        return response()->json([
            'success' => true,
            'patient' => $patient,
        ]);
    }
    private function generateDoctorCode()
    {
        $last = Doctors::orderBy('id', 'desc')->first();

        if (!$last || !$last->code) {
            return 'DR0001';
        }

        $number = (int) substr($last->code, 2);
        return 'DR' . str_pad($number + 1, 4, '0', STR_PAD_LEFT);
    }
    public function addDoctor(Request $request)
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $request->name));
        $specialist = trim((string) $request->specialist);

        if ($name === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nama dokter tidak boleh kosong.',
            ], 422);
        }

        if ($specialist === '') {
            return response()->json([
                'success' => false,
                'message' => 'Spesialis dokter tidak boleh kosong. Silakan isi spesialis (contoh: Umum, Sp.A, Sp.PD).',
            ], 422);
        }

        $matches = \App\Support\DoctorNameMatcher::findMatches(
            $name,
            Doctors::select(['id', 'code', 'name', 'specialist', 'address', 'city', 'phone'])->get()
        );

        // Nama sama persis → minta konfirmasi kasir kecuali jika dipaksa (force)
        if ($matches['exact'] && !$request->boolean('force')) {
            return response()->json([
                'success'            => false,
                'needs_confirmation' => true,
                'is_exact'           => true,
                'message'            => 'Data dokter "' . $matches['exact']->name . '" (' . $matches['exact']->code . ') sudah ada di dalam database.',
                'existing_doctor'    => $matches['exact'],
                'suggestions'        => [$matches['exact']],
            ]);
        }

        // Nama mirip (kemungkinan typo) → minta konfirmasi kasir, kecuali dipaksa
        if (!empty($matches['similar']) && !$request->boolean('force')) {
            return response()->json([
                'success'            => false,
                'needs_confirmation' => true,
                'is_exact'           => false,
                'suggestions'        => $matches['similar'],
            ]);
        }

        $code = $this->generateDoctorCode();

        $doctor = Doctors::create([
            'pharmacy_id' => "1",
            'code'        => $code,
            'name'        => $name,
            'specialist'  => $specialist ?: '-',
            'address'     => trim((string) $request->address) ?: '-',
            'city'        => trim((string) $request->city) ?: '-',
            'phone'       => trim((string) $request->phone) ?: '-',
        ]);

        return response()->json([
            'success' => true,
            'doctor'  => $doctor
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    // public function addToCart(Request $request)
    // {
    //     $items = Item::all();
    //     $carts = ItemCart::where('user_id', Auth()->id())->get();

    //     $item = Item::where('id', $request->get('item_id'))->first();
    //     $addtocart = ItemCart::create([
    //         'user_id' => Auth()->id(),
    //         'items_id' => $item->id,
    //         'quantity'   => 1,
    //     ]);
    //     return redirect()->route('sales.index')
    //         ->with('success', 'Produk berhasil dimasukkan kedalam keranjang');
    // }
    public function moreItem(Request $request)
    {
        $item = ItemCart::findOrFail($request->get('id'));
        $item->update([
            'quantity' => $item->quantity + 1,
        ]);
        return redirect()->route('sales.index');
    }
    public function lessItem(Request $request)
    {
        $item = ItemCart::findOrFail($request->get('id2'));
        if ($item->quantity - 1 == 0) {
            $item->delete();
        } else {
            $item->update([
                'quantity' => $item->quantity - 1,
            ]);
        }


        return redirect()->route('sales.index');
    }
    public function removeItem(Request $request)
    {
        $item = MedicineCart::findOrFail($request->id);
        $item->delete();
        return response()->json([
            'id' => $item->id,
            'name' => $item->medicine->name,
            'total_price' => $item->total_price,
            'remove_url' => route('sales.removeItem', $item->id),
        ]);
    }
    public function ticketPayment($url)
    {
        return redirect()->away($url);
    }
    public function invoice()
    {
        // $transaction = MedicineTransactions::where('id', $request->get('transaction_id'))->first();
        // $cart = MedicineCart::with('medicine')->where('transaction_id', $request->get('transaction_id'))->get();
        // try {
        //     DB::beginTransaction();
        //     // $change_transaction_status =          
        //     return redirect()->away($request->transaction_type);
        // } catch (\Exception $e) {
        //     DB::rollBack();
        //     return redirect()->back()->with('message', "Gagal Menyimpan! " . $e->getMessage());
        // }
    }

    // Flow 5 : Checkout
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'user_id' => Auth()->user()->id,
            'transaction_id' => 'required|integer|',
            'paid' => 'required|numeric|min:0',
            'discounsubtotalvalue' => 'nullable|numeric|min:0',
            'totaltransaction' => 'required|numeric|min:0',
            'changes' => 'required|numeric|min:0',
            'patient_id' => 'nullable|integer|exists:patients,id',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'debtor_id' => 'nullable|integer|exists:debtors,id',
            'paymentType' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'user_id' => 'nullable|integer|exists:users,id',
            'shift_logs_id' => 'nullable|integer|exists:shift_logs,id',
            'has_doctor_shipping_fee' => 'nullable',
            'doctor_shipping_fee' => 'nullable|numeric|min:0',
            'doctor_shipping_payment_method' => 'nullable|string',
            'doctor_shipping_bank_name' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $transaction = MedicineTransactions::findOrFail($validated['transaction_id']);
            $type = $transaction->transaction_type;
            $typeMap = [
                'RESEP TUNAI' => ['db' => 'RESEP TUNAI', 'param_key' => 'receipt', 'code' => '1'],
                'KREDIT' => ['db' => 'KREDIT', 'param_key' => null, 'code' => '4'],
                'UPDS' => ['db' => 'UPDS', 'param_key' => 'pdu', 'code' => '2'],
                'HV/OTC' => ['db' => 'HV/OTC', 'param_key' => 'otc', 'code' => '3'],
            ];


            $meta = $typeMap[$type];

            // ── Idempotency ──────────────────────────────────────────
            if ($transaction->status === 1) {
                DB::rollBack();

                $transaction->load('transactions.medicine', 'patients', 'doctors');

                return response()->json([
                    'success' => true,
                    'print_url' => route('sales.print', $transaction->id),
                    'print_resep_url' => route('salesrecipe.print', $transaction->id),
                    'commands' => $this->buildEscPos($transaction),
                ]);
            }

            // ── Cart Integrity & Desync Guard ────────────────────────
            $cartSync = $this->buildCartSyncPayload((int) $transaction->id);
            if ($cartSync['cart_count'] === 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Keranjang transaksi kosong. Tidak dapat melakukan checkout.',
                    'cart_sync' => $cartSync,
                ], 422);
            }

            $dbSubtotal = round((float) $cartSync['total_transaction'], 2);
            $clientSubtotal = round((float) $validated['totaltransaction'], 2);
            if (abs($dbSubtotal - $clientSubtotal) > 1) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'desync_detected' => true,
                    'message' => 'Total transaksi tidak sinkron dengan item di database (Rp ' . number_format($dbSubtotal, 0, ',', '.') . '). Tabel keranjang telah disinkronkan ulang, silakan periksa kembali sebelum pembayaran.',
                    'cart_sync' => $cartSync,
                ], 409);
            }

            $isCreditTransaction = strtoupper((string) ($transaction->transaction_type ?? '')) === 'KREDIT';
            $paymentMethod = $isCreditTransaction ? 'KREDIT' : strtoupper(trim($validated['paymentType'] ?? 'CASH'));
            $isUnpaid = in_array($paymentMethod, ['BELUM BAYAR', 'KREDIT'], true);
            $nonCashPaymentMethods = ['QRIS', 'DEBIT', 'TRANSFER'];
            $bankAccount = null;

            if (in_array($paymentMethod, $nonCashPaymentMethods, true)) {
                $bankName = trim((string) ($validated['bank_name'] ?? ''));
                $normalizeBankName = static fn ($value) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value));
                $bankKey = $normalizeBankName($bankName);
                $matches = FinanceAccount::where('pharmacy_id', $transaction->pharmacy_id)
                    ->where('category', 'Kas & Bank')
                    ->where('account_type', 'bank')
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->get()
                    ->filter(function ($account) use ($normalizeBankName, $bankKey) {
                        $accountName = $normalizeBankName($account->name);
                        return $bankKey !== '' && str_contains($accountName, $bankKey);
                    });

                if ($matches->count() !== 1) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => $matches->isEmpty()
                            ? "Rekening {$bankName} belum tersedia atau belum aktif di cabang ini. Hubungi admin untuk mengatur rekening Kas & Bank."
                            : "Ada lebih dari satu rekening {$bankName} aktif di cabang ini. Pastikan hanya satu rekening untuk setiap bank.",
                    ], 422);
                }

                $bankAccount = $matches->first();
            }

            $hasDoctorShippingFee = filter_var($request->input('has_doctor_shipping_fee', false), FILTER_VALIDATE_BOOLEAN);
            $doctorShippingFee = $hasDoctorShippingFee ? round((float) ($request->input('doctor_shipping_fee', 0)), 2) : 0;
            $doctorShippingPaymentMethod = ($hasDoctorShippingFee && $doctorShippingFee > 0)
                ? strtoupper(trim((string) $request->input('doctor_shipping_payment_method', 'CASH')))
                : null;
            $doctorShippingBankName = ($hasDoctorShippingFee && $doctorShippingFee > 0 && in_array($doctorShippingPaymentMethod, $nonCashPaymentMethods, true))
                ? trim((string) $request->input('doctor_shipping_bank_name', ''))
                : null;

            // created_at is intentionally not mass assignable on the model.
            // Set it explicitly at checkout so reports that fall back to the
            // transaction timestamp use the sale time, not when the cart began.
            $transaction->forceFill(['created_at' => Carbon::now()]);
            $transaction->update([
                'status' => 1,
                'paid' => $validated['paid'],
                'transaction_code' => $this->generateTransactionCode($meta['code'], (int) $transaction->pharmacy_id),
                'discount' => $validated['discounsubtotalvalue'],
                'subtotal' => $validated['totaltransaction'],
                'changes' => $validated['changes'],
                'patient_id' => $validated['patient_id'] ?? null,
                'doctor_id' => $validated['doctor_id'] ?? null,
                'debtor_id' => $validated['debtor_id'] ?? null,
                'payment_method' => $paymentMethod,
                'transfer_bank_name' => $isUnpaid ? null : ($validated['bank_name'] ?? null),
                'has_doctor_shipping_fee' => $hasDoctorShippingFee,
                'doctor_shipping_fee' => $doctorShippingFee,
                'doctor_shipping_payment_method' => $doctorShippingPaymentMethod,
                'doctor_shipping_bank_name' => $doctorShippingBankName,
                'user_id' => $validated['user_id'] ?? null,
                'shift_logs_id' => $validated['shift_logs_id'] ?? null,
            ]);

            // Penjualan tunai masuk otomatis ke akun Kas Apotek cabang transaksi.
            if ($paymentMethod === 'CASH' && strtoupper((string) $transaction->transaction_type) !== 'KREDIT') {
                $cashAccount = FinanceAccount::where('pharmacy_id', $transaction->pharmacy_id)
                    ->where('category', 'Kas & Bank')
                    ->where('account_type', 'kas_apotek')
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($cashAccount) {
                    $cashAmount = round((float) $validated['totaltransaction'], 2);
                    FinancePayment::create([
                        'pharmacy_id' => $transaction->pharmacy_id,
                        'medicine_transaction_id' => $transaction->id,
                        'account_id' => $cashAccount->id,
                        'payment_type' => 'PENJUALAN TUNAI',
                        'payment_date' => Carbon::now()->toDateString(),
                        'amount' => $cashAmount,
                        'reference_number' => $transaction->transaction_code,
                        'notes' => 'Penerimaan penjualan tunai kasir',
                        'created_by' => Auth::id(),
                    ]);
                    $cashAccount->increment('balance', $cashAmount);
                    Cache::forget('finance_stats_summary_branch_' . $transaction->pharmacy_id . '_' . $transaction->pharmacy_id);
                }
            }

            // Pembayaran non-tunai masuk ke rekening bank sesuai nama bank yang dipilih kasir.
            if ($bankAccount) {
                $bankAmount = round((float) $validated['totaltransaction'], 2);
                FinancePayment::create([
                    'pharmacy_id' => $transaction->pharmacy_id,
                    'medicine_transaction_id' => $transaction->id,
                    'account_id' => $bankAccount->id,
                    'payment_type' => 'PENJUALAN NON TUNAI',
                    'payment_date' => Carbon::now()->toDateString(),
                    'amount' => $bankAmount,
                    'reference_number' => $transaction->transaction_code,
                    'notes' => "Penerimaan penjualan {$paymentMethod} melalui {$validated['bank_name']}",
                    'created_by' => Auth::id(),
                ]);
                $bankAccount->increment('balance', $bankAmount);
                Cache::forget('finance_stats_summary_branch_' . $transaction->pharmacy_id . '_' . $transaction->pharmacy_id);
            }

            // Penerimaan talangan Jasa Dokter & Ongkir ke kas / rekening bank apotek
            if ($hasDoctorShippingFee && $doctorShippingFee > 0) {
                $normalizeBankName = static fn ($value) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value));
                if ($doctorShippingPaymentMethod === 'CASH') {
                    $shippingCashAccount = FinanceAccount::where('pharmacy_id', $transaction->pharmacy_id)
                        ->where('category', 'Kas & Bank')
                        ->where('account_type', 'kas_apotek')
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if ($shippingCashAccount) {
                        FinancePayment::create([
                            'pharmacy_id' => $transaction->pharmacy_id,
                            'medicine_transaction_id' => $transaction->id,
                            'account_id' => $shippingCashAccount->id,
                            'payment_type' => 'PENJUALAN TUNAI',
                            'payment_date' => Carbon::now()->toDateString(),
                            'amount' => $doctorShippingFee,
                            'reference_number' => $transaction->transaction_code,
                            'notes' => 'Penerimaan talangan ongkir & jasa dokter (Tunai)',
                            'created_by' => Auth::id(),
                        ]);
                        $shippingCashAccount->increment('balance', $doctorShippingFee);
                    }
                } elseif (in_array($doctorShippingPaymentMethod, $nonCashPaymentMethods, true) && !empty($doctorShippingBankName)) {
                    $shippingBankKey = $normalizeBankName($doctorShippingBankName);
                    $shippingBankMatches = FinanceAccount::where('pharmacy_id', $transaction->pharmacy_id)
                        ->where('category', 'Kas & Bank')
                        ->where('account_type', 'bank')
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->get()
                        ->filter(function ($account) use ($normalizeBankName, $shippingBankKey) {
                            $accountName = $normalizeBankName($account->name);
                            return $shippingBankKey !== '' && str_contains($accountName, $shippingBankKey);
                        });

                    if ($shippingBankMatches->count() === 1) {
                        $shippingBankAccount = $shippingBankMatches->first();
                        FinancePayment::create([
                            'pharmacy_id' => $transaction->pharmacy_id,
                            'medicine_transaction_id' => $transaction->id,
                            'account_id' => $shippingBankAccount->id,
                            'payment_type' => 'PENJUALAN NON TUNAI',
                            'payment_date' => Carbon::now()->toDateString(),
                            'amount' => $doctorShippingFee,
                            'reference_number' => $transaction->transaction_code,
                            'notes' => "Penerimaan talangan ongkir & jasa dokter ({$doctorShippingPaymentMethod} melalui {$doctorShippingBankName})",
                            'created_by' => Auth::id(),
                        ]);
                        $shippingBankAccount->increment('balance', $doctorShippingFee);
                    }
                }
                Cache::forget('finance_stats_summary_branch_' . $transaction->pharmacy_id . '_' . $transaction->pharmacy_id);
            }

            MedicineCart::where('transaction_id', $validated['transaction_id'])
                ->update(['status' => 1]);

            $txWithItems = MedicineTransactions::with('transactions.medicine')
                ->findOrFail($validated['transaction_id']);

            $now = Carbon::now()->format('Y-m-d H:i:s');

            foreach ($txWithItems->transactions as $cart) {
                $medicine = $cart->medicine;
                $medicine_id = $medicine->id;
                $qty_bought = $cart->quantity;
                $batchDeductions = [];
                $pharmacyId = getActivePharmacyId();
                $counterQtyBefore = (int) MedicineTransferItems::join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
                    ->where('batches.medicine_id', $medicine_id)
                    ->where('batches.pharmacy_id', $pharmacyId)
                    ->where('medicine_transfer_items.status', 1)
                    ->where(function ($q) {
                        $q->whereNull('medicine_transfer_items.source_type')
                            ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                    })
                    ->sum('medicine_transfer_items.qty');

                // Debug log removed to prevent disk & log flooding

                while ($qty_bought > 0) {
                    // 1. Cari batch counter dengan stok > 0 (FIFO berdasarkan expired date)
                    $transfer = MedicineTransferItems::join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
                        ->where('batches.medicine_id', $medicine_id)
                        ->where('batches.pharmacy_id', getActivePharmacyId())
                        ->where('medicine_transfer_items.status', 1)
                        ->where(function ($q) {
                            $q->whereNull('medicine_transfer_items.source_type')
                              ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                        })
                        ->where('medicine_transfer_items.qty', '>', 0)
                        ->orderBy('batches.expired_date', 'asc')
                        ->lockForUpdate()
                        ->select('medicine_transfer_items.*')
                        ->first();

                    if (!$transfer) {
                        // 2. Tidak ada stok > 0, cari batch counter manapun (izinkan minus)
                        $transfer = MedicineTransferItems::join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
                            ->where('batches.medicine_id', $medicine_id)
                            ->where('batches.pharmacy_id', getActivePharmacyId())
                            ->where('medicine_transfer_items.status', 1)
                            ->where(function ($q) {
                                $q->whereNull('medicine_transfer_items.source_type')
                                  ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                            })
                            ->orderBy('batches.expired_date', 'desc')
                            ->lockForUpdate()
                            ->select('medicine_transfer_items.*')
                            ->first();

                        if (!$transfer) {
                            // 3. Tidak ada transfer item sama sekali, skip deduction counter
                            \Log::warning("Stok counter tidak ditemukan untuk obat: {$medicine->name} (ID: {$medicine_id}). Penjualan tetap diproses dengan stok minus.");
                            break;
                        }

                        // Kurangi langsung semua sisa qty ke batch ini (akan jadi minus)
                        $deductedQty = (float) $qty_bought;
                        $transfer->qty -= $qty_bought;
                        $transfer->save();
                        $batchDeductions[$transfer->batches_id] = ($batchDeductions[$transfer->batches_id] ?? 0) + $deductedQty;
                        $qty_bought = 0;
                    } else {
                        if ($transfer->qty >= $qty_bought) {
                            $deductedQty = (float) $qty_bought;
                            $transfer->qty -= $qty_bought;
                            $transfer->save();
                            $batchDeductions[$transfer->batches_id] = ($batchDeductions[$transfer->batches_id] ?? 0) + $deductedQty;
                            $qty_bought = 0;
                        } else {
                            $deductedQty = (float) $transfer->qty;
                            $qty_bought -= $deductedQty;
                            $batchDeductions[$transfer->batches_id] = ($batchDeductions[$transfer->batches_id] ?? 0) + $deductedQty;
                            $transfer->qty = 0;
                            $transfer->save();
                        }
                    }
                }

                $medicine->stock -= $cart->quantity;
                $medicine->save();

                $trackedQty = array_sum($batchDeductions);
                if ($trackedQty < (float) $cart->quantity) {
                    $batchDeductions['untracked'] = (float) $cart->quantity - $trackedQty;
                }

                $runningCounterQty = $counterQtyBefore;
                foreach ($batchDeductions as $batchId => $deductedQty) {
                    $portion = (float) $cart->quantity > 0 ? $deductedQty / (float) $cart->quantity : 0;
                    $counterQtyAfter = $runningCounterQty - $deductedQty;
                    ItemsLog::create([
                        'transaction_code' => $txWithItems->transaction_code,
                        'code' => $this->generateItemsLogCode(),
                        'type' => $cart->cart_type,
                        'medicine_id' => $cart->medicine_id,
                        'qty' => $deductedQty,
                        'qty_before' => $runningCounterQty,
                        'qty_after' => $counterQtyAfter,
                        'total' => round((float) $cart->final_price * $portion, 2),
                        'date' => $now,
                        'status' => 1,
                        'batches_id' => $batchId === 'untracked' ? null : $batchId,
                        'user_id' => auth()->user()->id,
                    ]);
                    $runningCounterQty = $counterQtyAfter;
                }
            }

            DB::commit();

            // Load relations needed for receipt
            $txWithItems->load('patients', 'doctors');

            return response()->json([
                'success' => true,
                'print_url' => route('sales.print', $transaction->id),
                'print_resep_url' => route('salesrecipe.print', $transaction->id),
                'commands' => $this->buildEscPos($txWithItems),
            ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak ditemukan.',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout failed', [
                'transaction_id' => $request->transaction_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    private function buildEscPos(MedicineTransactions $tx): array
    {
        $width = 32; // 58mm paper = 32 chars
        $cmds = [];

        $cmds[] = "\x1B\x40";

        $cmds[] = "\x1B\x61\x01";          // center
        $cmds[] = "\x1B\x45\x01";          // bold on
        $cmds[] = "\x1B\x21\x30";          // double size
        $cmds[] = "APOTEK SAHABAT\n";
        $cmds[] = "\x1B\x21\x00";          // normal size
        $cmds[] = "\x1B\x45\x00";          // bold off
        $cmds[] = "Jl. Palang Merah Ind No.16 A-B-C\n";
        $cmds[] = "Telp: 081257586688\n";
        $cmds[] = "SAMARINDA\n";

        $cmds[] = str_repeat('-', $width) . "\n";

        $cmds[] = "\x1B\x61\x00";          // left
        $cmds[] = "Bukti Pembayaran\n";
        $cmds[] = $tx->updated_at->format('d/m/Y H:i:s') . "\n";
        $cmds[] = "\n";
        $cmds[] = "Nama   : " . ($tx->patients->name ?? '-') . "\n";
        $cmds[] = "Alamat : " . ($tx->patients->address ?? '-') . "\n";

        $cmds[] = str_repeat('-', $width) . "\n";
        $cmds[] = "Nama Dokter : " . ($tx->doctors->name ?? '-') . "\n";
        $cmds[] = str_repeat('-', $width) . "\n";

        // ── Items ────────────────────────────────────────────────────
        foreach ($tx->transactions as $cart) {
            $cmds[] = mb_substr($cart->medicine->name, 0, $width) . "\n";

            $left = str_pad(
                $cart->quantity . ' x ' . number_format($cart->item_price, 0, ',', '.'),
                $width - 12
            );
            $right = str_pad(
                number_format($cart->raw_total, 0, ',', '.'),
                12,
                ' ',
                STR_PAD_LEFT
            );
            $cmds[] = $left . $right . "\n";
        }

        $cmds[] = str_repeat('-', $width) . "\n";

        // ── Totals ───────────────────────────────────────────────────
        $row = fn($label, $value) =>
            str_pad($label, $width - 12) .
            str_pad(number_format($value, 0, ',', '.'), 12, ' ', STR_PAD_LEFT) . "\n";

        // Sub Total
        $totalRawTotal = $tx->transactions->sum('raw_total');
        $totaldiscount = $tx->discount ?? 0;
        $payment = $totalRawTotal - $totaldiscount;

        $cmds[] = $row('Sub Total', $totalRawTotal);
        $cmds[] = $row('Discount', -$totaldiscount);

        // Bold total
        $cmds[] = "\x1B\x45\x01";
        $cmds[] = $row('Jumlah', $payment);
        $cmds[] = "\x1B\x45\x00";

        $cmds[] = $row('Bayar', $tx->paid);
        $cmds[] = $row('Kembalian', $tx->changes);

        // ── Kasir ────────────────────────────────────────────────────
        $cmds[] = str_repeat('-', $width) . "\n";
        $cmds[] = "Kasir : " . auth()->user()->name . "\n";
        $cmds[] = str_repeat('-', $width) . "\n";

        // ── Footer ───────────────────────────────────────────────────
        $cmds[] = "\x1B\x61\x01";          // center
        $cmds[] = "Terima Kasih\n";
        $cmds[] = "Semoga Lekas Sembuh\n";
        $cmds[] = "\n\n\n";

        // ── Cut ──────────────────────────────────────────────────────
        $cmds[] = "\x1D\x56\x00";

        return $cmds;
    }
    public function getTransactionItem(Request $request)
    {
        $transactionId = (int) $request->get('transaction_id');
        $itemTransaction = MedicineCart::with('medicine', 'transactions', 'user')
            ->where('transaction_id', $transactionId)
            ->where('status', 0)
            ->orderBy('id', 'asc')
            ->get();
        $transaction = $itemTransaction->first();
        $syncPayload = $this->buildCartSyncPayload($transactionId);

        return response()->json([
            'success' => true,
            'transaction' => $transaction,
            'itemTransaction' => $itemTransaction,
            'cart_sync' => $syncPayload,
        ]);
    }



    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function sendEmbalase(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|integer',
            'jasaValue' => 'required|numeric|min:0',
        ]);
        $transactionId = (int) $validated['transaction_id'];
        $jasaValue = (int) $validated['jasaValue'];

        $cartItem = DB::transaction(function () use ($transactionId, $jasaValue) {
            $parentTransaction = MedicineTransactions::where('id', $transactionId)
                ->where('user_id', auth()->id())
                ->where('status', 0)
                ->lockForUpdate()
                ->firstOrFail();

            $openRecipeItem = MedicineCart::where('transaction_id', $parentTransaction->id)
                ->where('user_id', auth()->id())
                ->where('status', 0)
                ->whereNotNull('recipe_number')
                ->where('recipe_status', 0)
                ->latest('id')
                ->first();

            $targetRecipeItem = $openRecipeItem ?? MedicineCart::where('transaction_id', $parentTransaction->id)
                ->where('user_id', auth()->id())
                ->where('status', 0)
                ->whereNotNull('recipe_number')
                ->latest('id')
                ->first();

            if (!$targetRecipeItem) {
                abort(422, 'Tidak ada racikan aktif yang dapat diselesaikan.');
            }

            $recipeNumber = $targetRecipeItem->recipe_number;
            $recipeItems = MedicineCart::where('transaction_id', $parentTransaction->id)
                ->where('user_id', auth()->id())
                ->where('status', 0)
                ->where('recipe_number', $recipeNumber);
            $targetItem = (clone $recipeItems)->latest('id')->firstOrFail();

            $targetItem->update([
                'embalase' => $jasaValue,
                'final_price' => (float) $targetItem->total_price - (float) $targetItem->discount + $jasaValue,
            ]);

            // Scope completion to this one recipe in this one pending transaction.
            $recipeItems->update(['recipe_status' => 1]);

            return $targetItem->fresh();
        });

        $syncPayload = $this->buildCartSyncPayload($transactionId);

        return response()->json(array_merge([
            'success' => true,
            'message' => 'Data embalase berhasil disimpan.',
            'racikStatus' => 1,
            'item_id' => $cartItem->id,
            'finalprice' => $cartItem->final_price,
        ], $syncPayload));
    }

    public function updateCart(Request $request)
    {
        $cart = MedicineCart::where('id', $request->id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$cart) {
            return response()->json(['message' => 'Item transaksi tidak ditemukan.'], 404);
        }

        $parentTransaction = MedicineTransactions::where('id', $cart->transaction_id)
            ->where('user_id', auth()->id())
            ->where('status', 0)
            ->first();
        if (!$parentTransaction) {
            return response()->json(['message' => 'Transaksi tidak ditemukan atau sudah selesai.'], 422);
        }

        $cartType = $cart->cart_type;
        $serviceFee = $cart->service_fee;
        $embalase = $cart->embalase;
        if ($parentTransaction->transaction_type === 'KREDIT') {
            $cartType = $request->input('cart_type', $cart->cart_type);
            if (!in_array($cartType, ['UK', 'UP', 'HV'], true)) {
                return response()->json(['message' => 'Jenis item resep kredit tidak valid.'], 422);
            }
            $medicineType = [
                'UK' => 'KREDIT',
                'UP' => 'UPDS',
                'HV' => 'HV/OTC',
            ][$cartType];
            $serviceFee = $cartType === 'UK' && (int) $request->input('racikstatus', 0) !== 1
                ? (float) $request->input('service', 0)
                : 0;
            $embalase = $cartType === 'UK' ? (float) $request->input('embalase', 0) : 0;
        }

        if ($request->has('debtor_id') && $request->filled('debtor_id')) {
            MedicineTransactions::where('id', $cart->transaction_id)
                ->where('user_id', auth()->id())
                ->where('transaction_type', 'KREDIT')
                ->update([
                    'debtor_id' => $request->get('debtor_id'),
                ]);
        }

        $cart->update([
            'cart_type' => $cartType,
            'medicine_type' => $parentTransaction->transaction_type === 'KREDIT'
                ? $medicineType
                : $cart->medicine_type,
            'service_fee' => $serviceFee,
            'embalase' => $embalase,
            'quantity' => $request->quantity,
            'discount' => $request->discount,
            'package' => $request->package,
            'dosage_r' => $request->dosage_r,
            'item_price' => $request->price2,
            'raw_total' => $request->raw_total,
            'total_price' => $request->final_price,
            'final_price' => $request->total_price + $embalase,
        ]);

        $cart->load('medicine');
        $syncPayload = $this->buildCartSyncPayload((int) $cart->transaction_id);

        return response()->json(array_merge([
            'success' => true,
            'item' => $cart,
        ], $syncPayload));
    }


    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $item = Item::findOrFail($id);

        return view('umkm.sales.edit', compact('item'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'item_name' => 'required',
            'item_desc' => 'required',
            'item_price' => 'required',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:5024',
        ]);

        $item = Item::findOrFail($id);

        try {
            DB::beginTransaction();

            // Handle image upload if a new one is submitted
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $fileName = 'items-' . time() . Str::random(4) . '.' . $image->extension();

                // Delete old image if it exists
                $oldImagePath = public_path('uploads/items/' . $item->item_photo);
                if (File::exists($oldImagePath)) {
                    File::delete($oldImagePath);
                }
                // Save new image
                $image->move(public_path('uploads/items/'), $fileName);

                // Update image path
                $item->item_photo = $fileName;
            }

            // Update item fields
            $item->item_name = $request->item_name;
            $item->item_desc = $request->item_desc;
            $item->item_price = $request->item_price;
            $item->save();

            DB::commit();

            return redirect()->route('items.index')->with('success', 'Produk berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', 'Gagal memperbarui produk!');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

        $item = Item::findOrFail($id);

        try {
            DB::beginTransaction();

            if (is_file(public_path('uploads/items/' . $item->photo))) {
                unlink(public_path('uploads/items/' . $item->photo));
            }
            $item->delete();

            DB::commit();

            return redirect()->back()->with('success', 'Produk berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('failed', 'Produk Gagal dihapus!');
        }
    }

    public function openMedicineMaster(Request $request)
    {
        $perPage = 30;

        $query = Medicines::with(['composition', 'factory'])
            ->where('status', 1);

        if ($request->search) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('composition', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('factory', function ($q3) use ($search) {
                        $q3->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return response()->json(
            $query->orderBy('id', 'desc')->paginate($perPage)
        );
    }
    public function verifyPin(Request $request)
    {
        $request->validate([
            'pin' => ['required', 'digits:4'],
        ]);

        $user = User::where('secret_pin', $request->pin)->first();

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' => 'PIN salah, silahkan coba lagi'
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'PIN ditemukan',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ]
        ]);
    }
    public function openSearch(Request $request)
    {
        $perPage = 30;

        $query = Medicines::with(['composition', 'factory', 'category'])
            ->where('status', 1);

        if ($request->search) {
            $search = trim($request->search);
            $tokens = array_values(array_filter(explode(' ', $search)));

            $query->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->where(function ($tq) use ($token) {
                        $tq->where('medicines.name', 'like', "%{$token}%")
                            ->orWhere('medicines.code', 'like', "%{$token}%")
                            ->orWhere('medicines.barcode', 'like', "%{$token}%")
                            ->orWhere('medicines.dosage', 'like', "%{$token}%")
                            ->orWhere('medicines.generic', 'like', "%{$token}%")
                            ->orWhereHas('composition', fn($cq) => $cq->where('name', 'like', "%{$token}%"))
                            ->orWhereHas('factory', fn($fq) => $fq->where('name', 'like', "%{$token}%"))
                            ->orWhereHas('category', fn($catq) => $catq->where('name', 'like', "%{$token}%"));
                    });
                }
            });
        }

        return response()->json(
            $query->orderBy('name', 'asc')->paginate($perPage)
        );
    }


    // Transaction Data
    public function openTransactionData(Request $request)
    {
        $perPage = 30;

        $restrictedRole = getRestrictedOnlineRole();
        $pharmacyId     = getActivePharmacyId();

        $query = MedicineCart::query()
            ->selectRaw('
            transaction_id, 
            MAX(created_at) as created_at,
            SUM(final_price) as final_price
        ')
            ->groupBy('transaction_id')
            ->with([
                'transactions:id,transaction_code,patient_id,created_at,status,transaction_type',
                'transactions.patients:id,name'
            ]);

        if ($pharmacyId) {
            $query->whereHas('transactions', function ($t) use ($pharmacyId) {
                $t->where('pharmacy_id', $pharmacyId);
            });
        }

        if ($restrictedRole) {
            $query->where(function ($sub) use ($restrictedRole) {
                $sub->whereHas('user.roles', function ($r) use ($restrictedRole) {
                    $r->where('name', $restrictedRole);
                })->orWhereHas('transactions.user.roles', function ($r) use ($restrictedRole) {
                    $r->where('name', $restrictedRole);
                })->orWhereHas('transactions.transactions.user.roles', function ($r) use ($restrictedRole) {
                    $r->where('name', $restrictedRole);
                });
            });
        }

        if ($request->search) {
            $search = $request->search;
            $parsedDate = null;

            try {
                $parsedDate = Carbon::createFromFormat('d/m/Y', $search);
            } catch (\Exception $e) {
                $parsedDate = null;
            }
            $query->where(function ($q) use ($search, $parsedDate) {

                if ($parsedDate) {
                    $q->whereDate('created_at', $parsedDate->format('Y-m-d'));
                }

                if (preg_match('/^\d{4}$/', $search)) {
                    $q->orWhereYear('created_at', $search);
                }

                $q->orWhereHas('transactions', function ($t) use ($search) {
                    $t->where('transaction_code', 'like', "%{$search}%");
                });

                $q->orWhereHas('transactions.patients', function ($p) use ($search) {
                    $p->where('name', 'like', "%{$search}%");
                });
            });
        }

        $result = $query->orderByDesc('created_at')->paginate($perPage);
        $typeMap = [
            'RESEP TUNAI' => 'resep',
            'KREDIT' => 'kredit',
            'UPDS' => 'upds',
            'HV/OTC' => 'hv',
        ];
        $result->getCollection()->transform(function ($row) use ($typeMap) {

            $transaction = $row->transactions;
            $type = $transaction?->transaction_type;

            return [
                'transaction_id' => $row->transaction_id,
                'code' => $transaction?->transaction_code ?? '-',
                'name' => $transaction?->patients?->name ?? '-',
                'date' => Carbon::parse($row->created_at)->format('d-m-Y'),
                'time' => Carbon::parse($row->created_at)->format('H:i:s'),
                'final_price' => $row->final_price,
                'status' => $transaction?->status ?? null,
                'type' => $typeMap[$type] ?? strtolower($type ?? ''),
            ];
        });
        return response()->json($result);
    }
    public function getTransactionItems($transactionId)
    {
        $items = MedicineCart::with([
            'medicine:id,name',
        ])
            ->where('transaction_id', $transactionId)
            ->where('status', 1)
            ->get()
            ->map(function ($item) {
                return [
                    'medicine' => $item->medicine?->name ?? '-',
                    'quantity' => $item->quantity,
                    'discount' => $item->discount,
                    'total_price' => $item->total_price,
                    'total' => $item->final_price,
                ];
            });

        return response()->json([
            'data' => $items
        ]);
    }

    public function markVisitor(Request $request)
    {
        $pharmacyId = getActivePharmacyId();
        $transactionId = $request->input('transaction_id');
        $visitorName = trim((string) $request->input('visitor_name'));
        $patientId = $request->input('patient_id');

        $transaction = MedicineTransactions::where('pharmacy_id', $pharmacyId)
            ->where('id', $transactionId)
            ->where('status', 0)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak ditemukan atau sudah selesai.',
            ], 404);
        }

        // If visitor name was entered and no patient_id was bound yet:
        if (!empty($visitorName) && empty($transaction->patient_id) && empty($patientId)) {
            $patient = Patients::where('name', $visitorName)->first();
            if (!$patient) {
                $patient = Patients::create([
                    'code' => $this->generatePatientCode(),
                    'name' => $visitorName,
                    'address' => '-',
                    'city' => '-',
                    'phone' => '-',
                ]);
            }
            $transaction->patient_id = $patient->id;
        } elseif (!empty($patientId)) {
            $transaction->patient_id = $patientId;
        }

        // Update totals from cart if cart items exist
        $cartSummary = MedicineCart::where('transaction_id', $transaction->id)
            ->selectRaw('SUM(final_price) as final_price, SUM(total_price) as subtotal, SUM(discount) as discount')
            ->first();

        $transaction->subtotal = $cartSummary->subtotal ?? 0;
        $transaction->discount = $cartSummary->discount ?? 0;
        $transaction->status = 0; // Remains pending / visitor
        $transaction->save();

        MedicineCart::where('transaction_id', $transaction->id)->update(['updated_at' => now()]);

        // Determine type slug
        $typeSlug = $this->dbTypeToRouteType($transaction->transaction_type ?? 'RESEP TUNAI');

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil ditandai sebagai data pengunjung.',
            'redirect_url' => route('transaction', ['type' => $typeSlug]),
        ]);
    }
}
