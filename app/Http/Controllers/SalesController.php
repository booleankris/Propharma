<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use App\Models\Debtors;
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

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $user_id     = Auth::user()->id;

        // 1. Resolve display-type → DB-type + parameter key 
        $typeMap = [
            'resep'  => ['db' => 'RESEP TUNAI', 'param_key' => 'receipt', 'code' => '1'],
            'kredit' => ['db' => 'KREDIT',      'param_key' => null,      'code' => '4'],
            'upds'   => ['db' => 'UPDS',        'param_key' => 'pdu',     'code' => '2'],
            'hv'     => ['db' => 'HV/OTC',      'param_key' => 'otc',     'code' => '3'],
        ];

        abort_if(!array_key_exists($type, $typeMap), 404, 'Invalid transaction type.');

        $meta      = $typeMap[$type];
        $dbType    = $meta['db'];
        $paramKey  = $meta['param_key'];

        // 2. Load payment parameters once
        $paymentParams        = PaymentParameters::findOrFail(1);
        $parameterHV          = $paymentParams->otc;
        $parameterUP          = $paymentParams->pdu;
        $parameterRT          = $paymentParams->receipt;
        $service              = $paymentParams->embalas;
        $ChangeFakturParameters = $paymentParams->pdu;
        $ChangeFakturRounding = $paymentParams->rounding;
        $rounding             = $dbType === 'KREDIT' ? '0' : $paymentParams->rounding;
        $parameters           = $dbType === 'KREDIT' ? '0' : $paymentParams->{$paramKey};

        // 3. Resolve which transaction to use
        if ($id !== null) {
            // Tab already has a specific transaction, load it, guard status
            $transaction = MedicineTransactions::where('pharmacy_id', $pharmacy_id)
                ->where('id', $id)
                ->where('status', 0)
                ->first();

            if (!$transaction) {
                // Transaction finished or doesn't belong to this pharmacy
                // Fall back to opening a fresh transaction of the requested type
                return redirect()->route('transaction', ['type' => $type])
                    ->with('message', 'Transaksi sudah selesai atau tidak ditemukan. Transaksi baru dibuka.');
            }

            $trx_id = $transaction->id;
        } else {
            // No ID supplied — look for ANY pending transaction for this pharmacy
            $transaction = MedicineTransactions::where('pharmacy_id', $pharmacy_id)
                ->where('status', 0)
                ->latest()
                ->first();

            if ($transaction) {
                // Resume existing pending transaction; redirect WITH id so each
                // tab gets a stable URL it can bookmark / re-open
                return redirect()->route('transaction', [
                    'type' => $this->dbTypeToRouteType($transaction->transaction_type),
                    'id'   => $transaction->id,
                ]);
            }

            // No pending transaction at all — create a new one
            try {
                DB::beginTransaction();

                $transaction = MedicineTransactions::create([
                    'pharmacy_id'      => $pharmacy_id,
                    'debtor_id'        => null,
                    'transaction_type' => $dbType,
                    'transaction_code' => $this->generateTransactionCode($meta['code']),
                    'subtotal'         => null,
                    'discount'         => null,
                    'status'           => 0,
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()->back()->with('message', 'Gagal membuat transaksi: ' . $e->getMessage());
            }

            // Redirect WITH the new id so the URL is stable for this tab
            return redirect()->route('transaction', [
                'type' => $type,
                'id'   => $transaction->id,
            ]);
        }

        // 4. Handle type switch on an existing transaction ────────────────────
        // If the user navigates to a different type on the same transaction,
        // update the type and clear the cart (prices differ per type).

        if ($transaction->transaction_type !== $dbType) {
            $new_type = $meta['code'];
            $new_code = $this->regenerateTransactionCode($new_type, $transaction->transaction_code);
            $transaction->update(['transaction_type' => $dbType, 'transaction_code' => $new_code]);
            MedicineCart::where('transaction_id', $transaction->id)->delete();
        }

        $trx_id = $transaction->id;

        // 5. Collect view data───────────────
        $totaltransaction = MedicineCart::where('user_id', $user_id)
            ->where('transaction_id', $trx_id)
            ->sum('final_price');

        $rawtotal = MedicineCart::where('user_id', $user_id)
            ->where('transaction_id', $trx_id)
            ->selectRaw('SUM(raw_total) as raw_total, SUM(embalase) as embalase')
            ->first();

        $existingpackage = MedicineCart::where('user_id', $user_id)
            ->where('transaction_id', $trx_id)
            ->where('recipe_status', '0')
            ->whereNotNull('package')
            ->first();

        $discount_total = MedicineCart::where('user_id', $user_id)
            ->where('status', '0')
            ->sum('discount');

        $check_transaction = MedicineTransactions::where('pharmacy_id', $pharmacy_id)
            ->where('status', 0)
            ->count();

        $itemInCart = MedicineCart::with('medicine')
            ->where('transaction_id', $trx_id)
            ->where('user_id', $user_id)
            ->get();

        return view('kasir.transaction', compact(
            'check_transaction',
            'type',
            'trx_id',
            'transaction',
            'existingpackage',
            'rawtotal',
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
        ));
    }

    // Helper: map DB type string back to route slug ───────────────────────────
    private function dbTypeToRouteType(string $dbType): string
    {
        return match ($dbType) {
            'RESEP TUNAI' => 'resep',
            'KREDIT'      => 'kredit',
            'UPDS'        => 'upds',
            'HV/OTC'      => 'hv',
            default       => 'resep',
        };
    }
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
        $pharmacyId = auth()->user()->pharmacy_id;

        $items = Medicines::query()
            ->select([
                'id',
                'code',
                'barcode',
                'name',
                'raw_price',
                'het_price',
                'net_price',
                'location',
                'etalase',
                'stock',
                'unit',
                'packaging',
                'content',
                'dosage',
            ])

            // Total stock in storage
            ->selectRaw('(
            SELECT COALESCE(SUM(stock), 0)
            FROM batches
            WHERE medicine_id = medicines.id
            AND pharmacy_id = ?
        ) as storage_stock', [$pharmacyId])

            // Total stock in counter (medicine_transfers)
            ->selectRaw('(
            SELECT COALESCE(SUM(mt.stock), 0)
            FROM medicine_transfers mt
            JOIN batches b ON b.id = mt.batches_id
            WHERE b.medicine_id = medicines.id
            AND mt.status = 1
            AND b.pharmacy_id = ?
        ) as counter_stock', [$pharmacyId])

            // Latest etalase_id from medicine_transfers (Option 1)
            ->selectRaw('(
            SELECT mt.etalases_id
            FROM medicine_transfers mt
            JOIN batches b ON b.id = mt.batches_id
            WHERE b.medicine_id = medicines.id
            AND mt.status = 1
            AND b.pharmacy_id = ?
            ORDER BY mt.created_at DESC
            LIMIT 1
        ) as transfer_etalase_id', [$pharmacyId])

            ->with(['etalases', 'locations'])

            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($x) use ($q) {
                    $x->where('code', 'like', $q . '%')
                        ->orWhere('barcode', 'like', $q . '%')
                        ->orWhere('name', 'like', '%' . $q . '%');
                });
            })

            ->orderByRaw(
                "CASE WHEN code LIKE ? THEN 0 ELSE 1 END, code ASC",
                [$q . '%']
            )

            ->limit(40)
            ->get();

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
    //             DB::raw('(SELECT COALESCE(SUM(mt.stock), 0) FROM medicine_transfers mt JOIN batches b ON b.id = mt.batches_id WHERE b.medicine_id = medicines.id AND mt.status = 1) as counter_stock'),
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
    public function generateTransactionCode($code)
    {
        $pharmacyId = auth()->user()->pharmacy_id;

        $year   = now()->format('y');
        $month  = now()->format('m');
        $prefix = $year . $month . strtoupper($code);

        $last = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)
            ->where('transaction_code', 'like', $prefix . '%')
            ->orderBy('transaction_code', 'desc')
            ->first();

        if ($last) {
            $lastNumber = intval(substr($last->transaction_code, -4));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 0;
        }

        $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        $transactionCode = $prefix . $serial;
        return $transactionCode;
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
            $code = "1";
        } else if ($request->get('type') == 'kredit') {
            $typenew = "RESEP KREDIT";
            $code = "4";
        } else if ($request->get('type') == 'upds') {
            $typenew = "UPDS";
            $code = "2";
        } else if ($request->get('type') == 'hv') {
            $typenew = "HV/OTC";
            $code = "3";
        }

        // Generate Transaction COdes
        $year   = now()->format('y');
        $month  = now()->format('m');
        $prefix = $year . $month . strtoupper($code);

        // find last transaction with same prefix
        $last = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)
            ->where('transaction_code', 'like', $prefix . '%')
            ->orderBy('transaction_code', 'desc')
            ->first();

        if ($last) {
            $lastNumber = intval(substr($last->transaction_code, -4));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 0;
        }

        $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        $transactionCode = $prefix . $serial;
        // =====================

        try {
            DB::beginTransaction();

            $transaction = MedicineTransactions::create([
                'pharmacy_id'       => Auth()->user()->pharmacy_id,
                'debtor_id'         => NULL,
                'transaction_type'  => $typenew,
                'transaction_code'  => $transactionCode,
                'subtotal'          => NULL,
                'discount'          => NULL,
                'status'            => 0,
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
            $transaction = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)->where('status', 0)->latest()->first();

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

        if ($request->get('racikstatus') == 1) {
            $hasRacikan = MedicineCart::where('transaction_id', $request->get('transaction_id'))
                ->where('user_id', auth()->id())
                ->whereNotNull('recipe_number')
                ->exists();

            if ($hasRacikan) {
                $latestRecord = MedicineCart::where('transaction_id', $request->get('transaction_id'))
                    ->where('user_id', auth()->id())
                    ->whereNotNull('recipe_number')
                    ->latest('id')
                    ->first();

                if ($latestRecord->recipe_status == 0) {
                    $recipeNumber = $latestRecord->recipe_number;
                    $recipeStatus = 0;
                } else {
                    $recipeNumber = $latestRecord->recipe_number + 1;
                    $recipeStatus = 0;
                }
            } else {
                $recipeNumber = 1;
                $recipeStatus = 0;
            }
        } else {
            $recipeNumber = null;
            $recipeStatus = null;
        }

        $transaction = MedicineCart::create([
            'user_id'        => Auth()->user()->id,
            'medicine_id'    => $request->get('medicine_id'),
            'transaction_id' => $request->get('transaction_id'),
            'quantity'       => $request->get('quantity'),
            'discount'       => $request->get('discount'),
            'embalase'       => $request->get('embalase'),
            'cart_type'      => $request->get('cart_type'),
            'package'      => $request->get('package'),
            'dosage_r'      => $request->get('dosage_r'),
            'item_price'      => $request->get('price2'),
            'raw_total'    => $request->get('raw_total'),
            'total_price'    => $request->get('final_price'),
            'final_price'    => $request->get('total_price'),
            'status'         => 0,
            'recipe_status'  => $recipeStatus,
            'recipe_number'  => $recipeNumber,
            'medicine_type'  => $request->get('medicine_type'),
            'service_fee'    => $request->get('service'),



        ]);
        $itemInCart = MedicineCart::with('medicine')->where('transaction_id', $transaction->transaction_id)->where('user_id', Auth()->user()->id)->first();

        return response()->json([
            'id'          => $transaction->id,
            'name'        => $transaction->medicine->name,
            'unit'        => $itemInCart->medicine->unit,
            'price'       => $itemInCart->medicine->net_price,
            'quantity'    => $transaction->quantity,
            'discount'    => $transaction->discount,
            'total_price' => $transaction->total_price,
            'embalase'    => $transaction->embalase,
            'final_price' => $transaction->final_price,
            'cart_type'   => $transaction->cart_type,
            'remove_url'  => route('sales.removeItem', $transaction->id),
        ]);
    }
    public function getCartItem($id)
    {
        $cart = MedicineCart::with('medicine')->findOrFail($id);
        return response()->json($cart);
    }
    public function deleteCartItem($id)
    {
        $cart = MedicineCart::findOrFail($id);
        $cart->delete();

        $total_transaction = MedicineCart::where('transaction_id', $cart->transaction_id)
            ->where('user_id', auth()->id())
            ->sum('final_price');
        $total_discount = MedicineCart::where('transaction_id', $cart->transaction_id)
            ->where('user_id', auth()->id())
            ->sum('discount');
        $totalbought = MedicineCart::where('transaction_id', $cart->transaction_id)
            ->where('user_id', auth()->id())
            ->selectRaw('SUM(raw_total) as raw_total, SUM(embalase) as embalase')->first();

        $total_raw = $totalbought->raw_total + $totalbought->embalase;
        return response()->json([
            'success' => true,
            'item' => $cart,
            'total_transaction' => $total_transaction,
            'total_discount' => $total_discount,
            'totalbought' => $total_raw,
        ]);
    }
    public function addPatient(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone'   => 'nullable|string|max:50',
            'city'    => 'nullable|string|max:100',
            'birth'   => 'nullable|date',
        ]);

        $patient = Patients::create([
            'name'    => $request->get('name'),
            'address' => $request->get('address'),
            'phone'   => $request->get('phone'),
            'city'    => $request->get('city'),
            'birth'   => $request->get('birth'),
        ]);

        return response()->json([
            'success' => true,
            'patient' => $patient
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
        $code = $this->generateDoctorCode();

        $doctor = Doctors::create([
            'pharmacy_id' => "1",
            'code'        => $code,
            'name'        => $request->name,
            'specialist'  => $request->specialist,
            'address'     => $request->address,
            'city'        => $request->city,
            'phone'       => $request->phone,
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
            'quantity'   => $item->quantity + 1,
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
                'quantity'   => $item->quantity - 1,
            ]);
        }


        return redirect()->route('sales.index');
    }
    public function removeItem(Request $request)
    {
        $item = MedicineCart::findOrFail($request->id);
        $item->delete();
        return response()->json([
            'id'          => $item->id,
            'name'        => $item->medicine->name,
            'total_price' => $item->total_price,
            'remove_url'  => route('sales.removeItem', $item->id),
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
            'transaction_id'   => 'required|integer|',
            'paid'             => 'required|numeric|min:0',
            'discounsubtotal'  => 'nullable|numeric|min:0',
            'totaltransaction' => 'required|numeric|min:0',
            'changes'          => 'required|numeric|min:0',
            'patient_id'       => 'nullable|integer|exists:patients,id',
            'doctor_id'        => 'nullable|integer|exists:doctors,id',
            'debtor_id'        => 'nullable|integer|exists:debtors,id',
            'paymentType'      => 'nullable|string',
            'bank_name'        => 'nullable|string',
            'user_id'          => 'nullable|integer|exists:users,id',
            'shift_logs_id'    => 'nullable|integer|exists:shift_logs,id',
        ]);

        DB::beginTransaction();
        try {
            $transaction = MedicineTransactions::findOrFail($validated['transaction_id']);

            // ── Idempotency ──────────────────────────────────────────
            if ($transaction->status === 1) {
                DB::rollBack();

                $transaction->load('transactions.medicine', 'patients', 'doctors');

                return response()->json([
                    'success'          => true,
                    'print_url'        => route('sales.print', $transaction->id),
                    'print_resep_url'  => route('salesrecipe.print', $transaction->id),
                    'commands'         => $this->buildEscPos($transaction),
                ]);
            }

            $transaction->update([
                'status'             => 1,
                'paid'               => $validated['paid'],
                'discount'           => $validated['discounsubtotal'],
                'subtotal'           => $validated['totaltransaction'],
                'changes'            => $validated['changes'],
                'patient_id'         => $validated['patient_id'],
                'doctor_id'          => $validated['doctor_id'],
                'debtor_id'          => $validated['debtor_id'],
                'payment_method'     => $validated['paymentType'],
                'transfer_bank_name' => $validated['bank_name'],
                'user_id'            => $validated['user_id'],
                'shift_logs_id'      => $validated['shift_logs_id'],
            ]);

            MedicineCart::where('transaction_id', $validated['transaction_id'])
                ->update(['status' => 1]);

            $txWithItems = MedicineTransactions::with('transactions.medicine')
                ->findOrFail($validated['transaction_id']);

            $now = Carbon::now()->format('Y-m-d');

            foreach ($txWithItems->transactions as $cart) {
                $medicine    = $cart->medicine;
                $qty_before  = $medicine->stock;
                $medicine_id = $medicine->id;
                $qty_bought  = $cart->quantity;

                \Log::info('Sale debug', [
                    'medicine_id'   => $medicine_id,
                    'medicine_name' => $medicine->name,
                    'qty_bought'    => $qty_bought,
                ]);

                while ($qty_bought > 0) {
                    $transfer = MedicineTransfers::join('batches', 'medicine_transfers.batches_id', '=', 'batches.id')
                        ->where('batches.medicine_id', $medicine_id)
                        ->where('batches.pharmacy_id', auth()->user()->pharmacy_id)
                        ->where('medicine_transfers.stock', '>', 0)
                        ->orderBy('batches.expired_date', 'asc')
                        ->lockForUpdate()
                        ->select('medicine_transfers.*')
                        ->first();

                    if (!$transfer) {
                        $transfer = MedicineTransfers::join('batches', 'medicine_transfers.batches_id', '=', 'batches.id')
                            ->where('batches.medicine_id', $medicine_id)
                            ->where('batches.pharmacy_id', auth()->user()->pharmacy_id)
                            ->orderBy('batches.expired_date', 'desc')
                            ->lockForUpdate()
                            ->select('medicine_transfers.*')
                            ->first();

                        if (!$transfer) {
                            throw new \Exception("Stok counter tidak ditemukan untuk obat: {$medicine->name}.");
                        }

                        $transfer->stock -= $qty_bought;
                        $transfer->save();
                        $qty_bought = 0;
                        break;
                    }

                    if ($transfer->stock >= $qty_bought) {
                        $transfer->stock -= $qty_bought;
                        $transfer->save();
                        $qty_bought = 0;
                    } else {
                        $qty_bought      -= $transfer->stock;
                        $transfer->stock  = 0;
                        $transfer->save();
                    }
                }

                $medicine->stock -= $cart->quantity;
                $medicine->save();

                ItemsLog::create([
                    'transaction_code' => $txWithItems->transaction_code,
                    'code'             => $this->generateItemsLogCode(),
                    'type'             => 'UM',
                    'medicine_id'      => $cart->medicine_id,
                    'qty'              => $cart->quantity,
                    'qty_before'       => $qty_before,
                    'qty_after'        => $medicine->stock,
                    'total'            => $cart->final_price,
                    'date'             => $now,
                    'status'           => 1,
                    'batches_id'       => $transfer->batches_id,
                ]);
            }

            DB::commit();

            // Load relations needed for receipt
            $txWithItems->load('patients', 'doctors');

            return response()->json([
                'success'          => true,
                'print_url'        => route('sales.print', $transaction->id),
                'print_resep_url'  => route('salesrecipe.print', $transaction->id),
                'commands'         => $this->buildEscPos($txWithItems),
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
                'error'          => $e->getMessage(),
                'trace'          => $e->getTraceAsString(),
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
        $cmds  = [];

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
        $cmds[] = "Nama   : " . ($tx->patients->name    ?? '-') . "\n";
        $cmds[] = "Alamat : " . ($tx->patients->address ?? '-') . "\n";

        $cmds[] = str_repeat('-', $width) . "\n";
        $cmds[] = "Nama Dokter : " . ($tx->doctors->name ?? '-') . "\n";
        $cmds[] = str_repeat('-', $width) . "\n";

        // ── Items ────────────────────────────────────────────────────
        foreach ($tx->transactions as $cart) {
            $cmds[] = mb_substr($cart->medicine->name, 0, $width) . "\n";

            $left  = str_pad(
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
        $totalRawTotal  = $tx->transactions->sum('raw_total');
        $totaldiscount  = $tx->discount ?? 0;
        $payment        = $totalRawTotal - $totaldiscount;

        $cmds[] = $row('Sub Total', $totalRawTotal);
        $cmds[] = $row('Discount', -$totaldiscount);

        // Bold total
        $cmds[] = "\x1B\x45\x01";
        $cmds[] = $row('Jumlah', $payment);
        $cmds[] = "\x1B\x45\x00";

        $cmds[] = $row('Bayar',     $tx->paid);
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
        $transaction = MedicineCart::with('medicine', 'transactions', 'user')->where('transaction_id', $request->get('transaction_id'))->first();
        $itemTransaction = MedicineCart::with('medicine', 'transactions', 'user')->where('transaction_id', $request->get('transaction_id'))->get();


        return response()->json([
            'success'         => true,
            'transaction'     => $transaction,
            'itemTransaction' => $itemTransaction,
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
        DB::beginTransaction();

        try {
            $transactionId = $request->get('transaction_id');
            $jasaValue = (int) $request->get('jasaValue');

            $transaction = MedicineCart::where('transaction_id', $transactionId)
                ->latest()
                ->first();

            if (!$transaction) {
                throw new \Exception('Data transaksi tidak ditemukan.');
            }

            $hasRacikan = MedicineCart::where('transaction_id', $transactionId)
                ->where('user_id', auth()->id())
                ->whereNotNull('recipe_number')
                ->latest('id')
                ->first();

            if ($hasRacikan) {
                $recipeNumber = $hasRacikan->recipe_number;

                MedicineCart::where('recipe_number', $recipeNumber)->update([
                    'recipe_status' => 1,
                ]);
            }

            $finalprice = $transaction->total_price + $jasaValue;

            $transaction->update([
                'embalase'    => $jasaValue,
                'final_price' => $finalprice,
            ]);

            DB::commit();

            $total_transaction = MedicineCart::where('transaction_id', $transactionId)
                ->where('user_id', auth()->id())
                ->sum('final_price');

            return response()->json([
                'success' => true,
                'message' => 'Data embalase berhasil disimpan.',
                'racikStatus' => 1,
                'finalprice' => $transaction->final_price,
                'message' => 'Data embalase berhasil disimpan.',
                'totaltransaction' => $total_transaction,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan! ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateCart(Request $request)
    {
        $cart = MedicineCart::where('id', $request->id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$cart) {
            return response()->json(['success' => false, 'message' => 'Cart item not found.'], 404);
        }

        $cart->update([
            'quantity'     => $request->quantity,
            'discount'     => $request->discount,
            'embalase'     => $request->embalase,
            'package'      => $request->package,
            'dosage_r'     => $request->dosage_r,
            'raw_total'    => $request->raw_total,
            'total_price'  => $request->final_price,
            'final_price'  => $request->total_price,
        ]);

        $cart->load('medicine');

        $total_transaction = MedicineCart::where('transaction_id', $cart->transaction_id)
            ->where('user_id', auth()->id())
            ->sum('final_price');

        $total_discount = MedicineCart::where('transaction_id', $cart->transaction_id)
            ->where('user_id', auth()->id())
            ->sum('discount');


        $totalbought = MedicineCart::where('transaction_id', $cart->transaction_id)
            ->where('user_id', auth()->id())
            ->selectRaw('SUM(raw_total) as raw_total, SUM(embalase) as embalase')->first();

        $total_raw = $totalbought->raw_total + $totalbought->embalase;


        return response()->json([
            'success' => true,
            'item' => $cart,
            'total_transaction' => $total_transaction,
            'total_discount' => $total_discount,
            'totalbought' => $total_raw,
        ]);
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

        $query = Medicines::with(['composition', 'factory']);

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
    public function openSearch(Request $request)
    {
        $perPage = 30;

        $query = Medicines::with(['composition', 'factory']);

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


    // Transaction Data
    public function openTransactionData(Request $request)
    {
        $perPage = 30;

        $query = MedicineCart::query()
            ->where('status', 1)
            ->selectRaw('
            transaction_id,
            MAX(created_at) as created_at,
            SUM(final_price) as final_price
        ')
            ->groupBy('transaction_id')
            ->with([
                'transactions:id,transaction_code,patient_id,created_at',
                'transactions.patients:id,name'
            ]);

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
        $result->getCollection()->transform(function ($row) {
            return [
                'transaction_id' => $row->transaction_id,
                'code'           => $row->transactions?->transaction_code ?? '-',
                'name'           => $row->transactions?->patients?->name ?? '-',
                'date'           => Carbon::parse($row->created_at)->format('d-m-Y'),
                'time'           => Carbon::parse($row->created_at)->format('H:i:s'),
                'final_price'    => $row->final_price,
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
                    'medicine'       => $item->medicine?->name ?? '-',
                    'quantity'       => $item->quantity,
                    'discount'       => $item->discount,
                    'total_price'    => $item->total_price,
                    'total'          => $item->final_price,
                ];
            });

        return response()->json([
            'data' => $items
        ]);
    }
}
