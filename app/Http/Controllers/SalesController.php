<?php

namespace App\Http\Controllers;

use App\Models\Debtors;
use App\Models\Item;
use App\Models\ItemCart;
use App\Models\MedicineCart;
use App\Models\Medicines;
use App\Models\MedicineTransactions;
use App\Models\PaymentParameters;
use App\Models\Sales;
use App\Models\TicketPayment;
use App\Models\TicketTransaction;
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
            $rounding = $parameters->rounding;
            $parameters = $parameters->pdu;
            $type = "UPDS";

            // Clear Cart If Different Payment Type
            $transaction = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)->where('status', '0')->first();
            if($transaction) {
                if ($transaction->transaction_type != "UPDS") {
                    $transaction->update([
                        'transaction_type' => $type
                    ]);
                    $clearTransaction = MedicineCart::where('transaction_id', $transaction->id)->delete();
                }
            }
        } else if ($slug == "hv") {
            $parameters = PaymentParameters::where('id', 1)->first();
            $rounding = $parameters->rounding;
            $parameters = $parameters->otc;
            $type = "HV/OTC";

            // Clear Cart If Different Payment Type
            $transaction = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)->where('status', '0')->first();
            if($transaction) {
                if ($transaction->transaction_type != $type) {
                    $transaction->update([
                        'transaction_type' => $type
                    ]);
                    $clearTransaction = MedicineCart::where('transaction_id', $transaction->id)->delete();
                }
            }
        } else if ($slug == "resep") {
            $parameters = PaymentParameters::where('id', 1)->first();
            $rounding = "0";
            $parameters = "0";
            $type = "RESEP TUNAI";

            // Clear Cart If Different Payment Type
            $transaction = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)->where('status', '0')->first();
            if($transaction){
                if ($transaction->transaction_type != $type) {
                    $transaction->update([
                        'transaction_type' => $type
                    ]);
                    $clearTransaction = MedicineCart::where('transaction_id', $transaction->id)->delete();
                }
            }
        } else if ($slug == "kredit") {
            $parameters = PaymentParameters::where('id', 1)->first();
            $rounding = "0";
            $parameters = "0";
            $type = "KREDIT";
            
            // Clear Cart If Different Payment Type
            $transaction = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)->where('status', '0')->first();
            if($transaction) {
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
        $totaltransaction =  MedicineCart::where('user_id', Auth()->user()->id)->where('status', '0')->sum('total_price');
        $check_transaction = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)->where('status', '0')->count();

        // Get Item Inside Cart based on user id and cart item status
        $itemInCart = MedicineCart::with('medicine')->where('status', 0)->where('user_id', Auth()->user()->id)->get();


        return view('kasir.transaction', compact('check_transaction', 'transaction', 'parameters', 'rounding', 'itemInCart', 'totaltransaction'));
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
                        ->orWhere('name', 'like', '%' . $q . '%');
                });
            })
            ->select(['id', 'code', 'name', 'net_price', 'stock', 'unit', 'packaging', 'content', 'dosage'])
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

    public function createTransaction(Request $request)
    {
        $check_transaction = MedicineTransactions::where('pharmacy_id', Auth()->user()->pharmacy_id)->where('status', '0')->count();
        $transactionCode = 'TRX-' . strtoupper(Str::random(8)) . '-' . time();
        if ($request->get('type') == 'resep') {
            $type = "RESEP TUNAI";
        } else if ($request->get('type') == 'kredit') {
            $type = "RESEP KREDIT";
        } else if ($request->get('type') == 'upds') {
            $type = "UPDS";
        } else if ($request->get('type') == 'hv') {
            $type = "HV/OTC";
        }
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

    // Flow 4 Add to Cart

    public function addToCart(Request $request)
    {
        $transaction = MedicineCart::create([
            'user_id'        => Auth()->user()->id,
            'medicine_id'    => $request->get('medicine_id'),
            'transaction_id' => $request->get('transaction_id'),
            'quantity'       => $request->get('quantity'),
            'discount'       => $request->get('discount'),
            'total_price'    => $request->get('total_price'),
        ]);
        return response()->json([
            'id'          => $transaction->id,
            'name'        => $transaction->medicine->name,
            'total_price' => $transaction->total_price,
            'remove_url'  => route('sales.removeItem', $transaction->id),
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
    public function checkout(Request $request)
    {
        // $transaction = MedicineTransactions::where('id', $request->get('transaction_id'))->first();
        // $cart = MedicineCart::with('medicine')->where('transaction_id', $request->get('transaction_id'))->get();
        try {
            $transaction = MedicineTransactions::findOrFail($request->get('transaction_id'));

            $transaction->update([
                'status' => 1,
                'paid' => $request->get('paid'),
                'subtotal' => $request->get('subtotal'),
                'changes' => $request->get('changes'),
            ]);
            $carts = MedicineCart::where('transaction_id', $request->get('transaction_id'))
                ->update([
                    'status' => 1
                ]);

            DB::beginTransaction();
            // $change_transaction_status =          
            return redirect()->back()->with('message', "Berhasil Menyimpan! ");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', "Gagal Menyimpan! " . $e->getMessage());
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
}
