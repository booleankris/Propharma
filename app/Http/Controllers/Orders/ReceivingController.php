<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Receiving;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceivingController extends Controller
{

    public function createReceiving(Request $request)
    {

        $now = Carbon::now()->format('d/m/Y');
        $check_transaction = Receiving::where('pharmacy_id', Auth()->user()->pharmacy_id)
            ->where('status', '0')->first();

        if ($check_transaction) {
            $last = Receiving::where('pharmacy_id', Auth()->user()->pharmacy_id)
                ->where('status', '0')
                ->first();
            $receiving_id = $last->id;
            $order_code = $last->code;
            $now = $last->date;

            return view('orders.receiving', compact('order_code', 'now', 'receiving_id'));
        } else {
            // Generate Order COde
            $year   = now()->format('y');
            $month  = now()->format('m');
            $prefix = $year . $month . 'OR';
            $last = Receiving::where('pharmacy_id', Auth()->user()->pharmacy_id)
                ->where('code', 'like', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            if ($last) {
                $lastNumber = intval(substr($last->code, -4));
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 0;
            }

            $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            $transactionCode = $prefix . $serial;
            try {
                DB::beginTransaction();

                $transaction = Receiving::create([
                    'pharmacy_id'       => Auth()->user()->pharmacy_id,
                    'user_id'           => Auth()->user()->id,
                    'code'              => $transactionCode,
                    'date'              => $now,
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
    function generateReceivingCode()
    {
        $now = Carbon::now();

        $year  = $now->format('y'); // 25
        $month = $now->format('m'); // 11
        $prefix = "{$year}{$month}OI";

        $lastCode = Receiving::where('code', 'like', "{$prefix}%")
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
    public function index()
    {
        $now = Carbon::now()->format('d/m/Y');
        $receiving_code = $this->generateReceivingCode();
        return view('orders.receiving', compact('receiving_code', 'now'));
    }
}
