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
use Illuminate\Support\Facades\DB;

class MobileSyncController extends Controller
{
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
