<?php

namespace App\Http\Controllers;

use App\Models\Debtors;
use App\Models\Doctors;
use App\Models\Item;
use App\Models\ItemCart;
use App\Models\MedicineCart;
use App\Models\Medicines;
use App\Models\MedicineTransactions;
use App\Models\Patients;
use App\Models\PaymentParameters;
use App\Models\Sales;
use App\Models\TicketPayment;
use App\Models\TicketTransaction;
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
    public function index($slug)
    {

        if ($slug == "upds") {
            $parameters = PaymentParameters::where('id', 1)->first();
            $parameterHV = $parameters->otc;
            $parameterUP = $parameters->pdu;
            $parameterRT = $parameters->receipt;
            $ChangeFakturParameters = $parameters->pdu;
            $ChangeFakturRounding = $parameters->rounding;
            $rounding = $parameters->rounding;
            $parameters = $parameters->pdu;
            $type = "UPDS";
            // Clear Cart If Different Payment Type
            $transaction = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)->where('status', '0')->first();
            if ($transaction) {
                if ($transaction->transaction_type != "UPDS") {
                    $transaction->update([
                        'transaction_type' => $type
                    ]);
                    $clearTransaction = MedicineCart::where('transaction_id', $transaction->id)->delete();
                }
            }
        } else if ($slug == "hv") {
            $parameters = PaymentParameters::where('id', 1)->first();
            $parameterHV = $parameters->otc;
            $parameterUP = $parameters->pdu;
            $parameterRT = $parameters->receipt;
            $ChangeFakturParameters = $parameters->pdu;
            $ChangeFakturRounding = $parameters->rounding;
            $rounding = $parameters->rounding;
            $parameters = $parameters->otc;
            $type = "HV/OTC";

            // Clear Cart If Different Payment Type
            $transaction = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)->where('status', '0')->first();
            if ($transaction) {
                if ($transaction->transaction_type != $type) {
                    $transaction->update([
                        'transaction_type' => $type
                    ]);
                    $clearTransaction = MedicineCart::where('transaction_id', $transaction->id)->delete();
                }
            }
        } else if ($slug == "resep") {
            $parameters = PaymentParameters::where('id', 1)->first();
            $parameterHV = $parameters->otc;
            $parameterUP = $parameters->pdu;
            $parameterRT = $parameters->receipt;
            $ChangeFakturParameters = $parameters->pdu;
            $ChangeFakturRounding = $parameters->rounding;
            $rounding = $parameters->rounding;
            $parameters = $parameters->receipt;
            $type = "RESEP TUNAI";


            // Clear Cart If Different Payment Type
            $transaction = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)->where('status', '0')->first();
            if ($transaction) {
                if ($transaction->transaction_type != $type) {
                    $transaction->update([
                        'transaction_type' => $type
                    ]);
                    $clearTransaction = MedicineCart::where('transaction_id', $transaction->id)->delete();
                }
            }
        } else if ($slug == "kredit") {
            $parameters = PaymentParameters::where('id', 1)->first();
            $ChangeFakturParameters = $parameters->pdu;
            $ChangeFakturRounding = $parameters->rounding;
            $parameterRT = $parameters->receipt;
            $rounding = "0";
            $parameters = "0";
            $type = "KREDIT";
            // Clear Cart If Different Payment Type
            $transaction = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)->where('status', '0')->first();
            if ($transaction) {
                if ($transaction->transaction_type != $type) {
                    $transaction->update([
                        'transaction_type' => $type
                    ]);
                    $clearTransaction = MedicineCart::where('transaction_id', $transaction->id)->delete();
                }
            }
        }


        // Checking Transaction Status
        $transaction = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)->where('status', '0')->first();
        $totaltransaction =  MedicineCart::where('user_id', Auth()->user()->id)->where('status', '0')->sum('final_price');
        $rawtotal =  MedicineCart::where('user_id', Auth()->user()->id)->where('status', '0')->sum('raw_total');

        $existingpackage = MedicineCart::where('user_id', auth()->id())
            ->where('status', '0')
            ->where('recipe_status', '0')
            ->whereNotNull('package')
            ->first();
        $discount_total =  MedicineCart::where('user_id', Auth()->user()->id)->where('status', '0')->sum('discount');
        $check_transaction = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)->where('status', '0')->count();

        // Get Item Inside Cart based on user id and cart item status
        $itemInCart = MedicineCart::with('medicine')->where('status', 0)->where('user_id', Auth()->user()->id)->get();

        return view('kasir.transaction', compact('check_transaction', 'transaction', 'existingpackage', 'rawtotal', 'parameters', 'rounding', 'itemInCart', 'totaltransaction', 'discount_total', 'ChangeFakturParameters', 'parameterRT', 'parameterHV', 'parameterUP', 'ChangeFakturRounding'));
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

        $items = Medicines::query()
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($x) use ($q) {
                    $x->where('code', 'like', '%' . $q . '%')
                        ->orWhere('name', 'like', '%' . $q . '%')
                        ->orWhere('barcode', 'like', '%' . $q . '%');
                });
            })
            ->select(['id', 'code','barcode', 'name', 'net_price', 'stock', 'unit', 'packaging', 'content', 'dosage'])
            ->orderByRaw("CASE WHEN code LIKE ? THEN 0 ELSE 1 END, code ASC", [$q . '%'])
            ->limit(10)
            ->get();

        return response()->json($items);
    }
    public function searchDebtors(Request $request)
    {
        $q = trim($request->get('q', ''));

        $items = Debtors::with('parameters')
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($x) use ($q) {
                    $x->where('code', 'like', '%' . $q . '%')
                        ->orWhere('name', 'like', '%' . $q . '%');
                });
            })
            ->select(['id', 'code', 'name', 'address', 'city', 'phone', 'contact', 'email', 'status'])
            ->orderByRaw("CASE WHEN code LIKE ? THEN 0 ELSE 1 END, code ASC", [$q . '%'])
            ->limit(10)
            ->get();

        return response()->json($items);
    }

    public function searchPatients(Request $request)
    {
        $q = trim($request->get('q', ''));

        $items = Patients::select(['id', 'code', 'name', 'address', 'city', 'phone', 'birth', 'status'])
            ->where(function ($query) use ($q) {
                $query->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
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
        $check_transaction = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)
            ->where('status', '0')
            ->count();

        if ($request->get('type') == 'resep') {
            $type = "RESEP TUNAI";
            $code = "1";
        } else if ($request->get('type') == 'kredit') {
            $type = "RESEP KREDIT";
            $code = "4";
        } else if ($request->get('type') == 'upds') {
            $type = "UPDS";
            $code = "2";
        } else if ($request->get('type') == 'hv') {
            $type = "HV/OTC";
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

        if ($check_transaction == 0) {
            try {
                DB::beginTransaction();

                $transaction = MedicineTransactions::create([
                    'pharmacy_id'       => Auth()->user()->pharmacy_id,
                    'debtor_id'         => NULL,
                    'transaction_type'  => $type,
                    'transaction_code'  => $transactionCode,
                    'subtotal'          => NULL,
                    'discount'          => NULL,
                    'status'            => 0,
                ]);

                DB::commit();
                return redirect()->back()->with('message', "Berhasil Menyimpan! ");
            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()->back()->with('message', "Gagal Menyimpan! " . $e->getMessage());
            }
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
            return redirect()->route('transaction', $url);
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
            ->sum('total_price');
        return response()->json([
            'success' => true,
            'item' => $cart,
            'total_transaction' => $total_transaction,
            'total_discount' => $total_discount,
            'totalbought' => $totalbought,
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
        // $transaction = MedicineTransactions::where('id', $request->get('transaction_id'))->first();
        // $cart = MedicineCart::with('medicine')->where('transaction_id', $request->get('transaction_id'))->get();


        DB::beginTransaction();
        try {
            $transaction = MedicineTransactions::findOrFail($request->get('transaction_id'));

            $transaction->update([
                'status' => 1,
                'paid' => $request->get('paid'),
                'subtotal' => $request->get('subtotal'),
                'changes' => $request->get('changes'),
                'patient_id' => $request->get('patient_id'),
                'doctor_id' => $request->get('doctor_id'),
                'debtor_id' => $request->get('debtor_id'),
            ]);
            MedicineCart::where('transaction_id', $request->get('transaction_id'))
                ->update(['status' => 1]);

            DB::commit();

            return redirect()->back()->with('message', 'Berhasil menyimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', 'Gagal menyimpan! ' . $e->getMessage());
        }
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
            ->sum('raw_total');

        return response()->json([
            'success' => true,
            'item' => $cart,
            'total_transaction' => $total_transaction,
            'total_discount' => $total_discount,
            'totalbought' => $totalbought,
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

            $query->where(function ($q) use ($search) {
                $q->where('final_price', 'like', "%{$search}%")
                    ->orWhereDate('created_at', $search)
                    ->orWhereHas('transactions', function ($t) use ($search) {
                        $t->where('transaction_code', 'like', "%{$search}%")
                            ->orWhere('created_at', 'like', "%{$search}%");
                    })
                    ->orWhereHas('transactions.patients', function ($p) use ($search) {
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
