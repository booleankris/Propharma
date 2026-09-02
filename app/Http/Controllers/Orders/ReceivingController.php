<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Batches;
use App\Models\Creditor;
use App\Models\ItemsLog;
use App\Models\MedicinePriceHistory;
use App\Models\Medicines;
use App\Models\MedicineTransferItems;
use App\Models\MedicineTransfers;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Receiving;
use App\Models\ReceivingDetails;
use App\Models\ReceivingItems;
use App\Models\Transfers;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DataTables;
use Form;

class ReceivingController extends Controller
{
    public function createReceiving(Request $request)
    {
        $now = Carbon::now()->format('d/m/Y');

        $transaction = Receiving::where('pharmacy_id', getPurchasingPharmacyId())
            ->where('status', 0)
            ->first();

        if ($transaction) {
            $receiving_id = $transaction->id;
            $receiving_code = $transaction->code;
            $order_code = $transaction->code;
            $order_id = null;
            $d_price = 0;
            $d_ppn = 0;
            $d_total = 0;
            $datenow = Carbon::now()->format('Y-m-d');
            $creditorOption = collect();
            $allFakturs = collect();

            /*
             * Check if this in-progress receiving already has items
             * linked to a purchase order, traversing:
             * receiving → receiving_details → receiving_items → order_items → orders
             */
            $order_exist = Order::whereHas('order_items.receivingItems.receiving_details.receiving', function ($q) use ($transaction) {
                $q->where('id', $transaction->id);
            })
                ->where('status', '!=', 2)  // not yet completed order
                ->first();

            return view('orders.receiving', compact('order_code', 'transaction', 'now', 'order_exist', 'receiving_id', 'receiving_code', 'order_id', 'd_price', 'd_ppn', 'd_total', 'datenow', 'creditorOption', 'allFakturs'));
        } else {
            $year = now()->format('y');
            $month = now()->format('m');
            $prefix = $year . $month . 'RE';

            $last = Receiving::where('pharmacy_id', getActivePharmacyId())
                ->where('code', 'like', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            $nextNumber = $last ? intval(substr($last->code, -4)) + 1 : 0;
            $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            try {
                DB::beginTransaction();

                $transaction = Receiving::create([
                    'pharmacy_id' => getActivePharmacyId(),
                    'code' => $prefix . $serial,
                    'date' => $now,
                    'status' => 0,
                ]);

                DB::commit();
                return redirect()->route('receiving.create');
            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()->back()->with('message', 'Gagal Menyimpan! ' . $e->getMessage());
            }
        }
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

    public function searchBPBA(Request $request)
    {
        $search = $request->search;

        $data = Order::with([
            'order_items.medicines.factory'
        ])
            ->where(function ($q) use ($search) {
                $q
                    ->where('id', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
                $q->where('status', '!=', 2);
            })
            ->paginate(10);

        $data->getCollection()->transform(function ($order) {
            return [
                'id' => $order->id,
                'code' => $order->code,
                'items' => $order->order_items,
            ];
        });

        return response()->json($data);
    }

    public function searchReceiving(Request $request)
    {
        $search = $request->search;

        $order = Order::with('receiving')->where('code', $search)->first();
        if (!$order) {
            return response()->json(null);
        }

        return response()->json($order->receiving);
    }

    public function getOrderItems(Request $request)
    {
        $ordersid = $request->order_id;
        $creditorCode = $request->creditor_code;
        $searchMedicine = $request->search_medicine ?? $request->input('search.value');

        if (!$ordersid || !$creditorCode) {
            return DataTables::of(collect())->make(true);
        }

        $orderItems = OrderItems::query()
            ->with([
                'medicines.creditors',
                'creditors',
                'receivingItems.locations',
                'receivingItems.etalases',
                'receivingItems.receiving_details'
            ])
            ->withSum('receivingItems as qty_received_total', 'qty_received')
            ->whereHas('orders', fn($q) => $q->where('id', $ordersid))
            ->where('creditor_code', $creditorCode)
            ->when($searchMedicine, function ($q) use ($searchMedicine) {
                $q->whereHas('medicines', function ($mq) use ($searchMedicine) {
                    $mq->where('name', 'like', '%' . $searchMedicine . '%')
                        ->orWhere('code', 'like', '%' . $searchMedicine . '%');
                });
            })
            ->get();

        $rows = collect();

        foreach ($orderItems as $orderItem) {
            $qtyReceived = $orderItem->qty_received_total ?? 0;
            $qtyRemaining = max(0, $orderItem->quantity - $qtyReceived);
            $creditorPpn = $orderItem->creditors?->ppn_type ?? 'TANPA';

            $medCred = $orderItem->medicines?->creditors?->firstWhere('code', $creditorCode) ?? $orderItem->medicines?->creditors?->first();
            $pbfDiscRaw = floatval($medCred?->pivot?->discount ?? 0);
            $pbfDiscStr = ($pbfDiscRaw > 0) ? (($pbfDiscRaw == (int) $pbfDiscRaw ? (int) $pbfDiscRaw : $pbfDiscRaw) . '%') : '0%';

            if ($orderItem->receivingItems->isEmpty()) {
                $ppnType = strtoupper(trim($creditorPpn));
                $rawPrice = floatval($orderItem->price ?? 0);
                $gross = floatval($orderItem->quantity ?? 0) * $rawPrice;
                $disc = floatval($orderItem->discount ?? 0);
                $extraDisc = floatval($orderItem->extra_discount ?? 0);
                $nomDisc = ($disc <= 100 && $disc > 0) ? ($gross * $disc / 100) : $disc;
                $nomExtraDisc = ($extraDisc <= 100 && $extraDisc > 0) ? ($gross * $extraDisc / 100) : $extraDisc;
                $net = max(0, $gross - $nomDisc - $nomExtraDisc);

                if ($ppnType === 'EXCLUDE') {
                    $priceStr = 'Rp ' . number_format($rawPrice, 0, ',', '.');
                    $pricePpnStr = 'Rp ' . number_format(floor($rawPrice * 1.11), 0, ',', '.');
                    $itemTotal = floor($net * 1.11);
                } elseif ($ppnType === 'INCLUDE') {
                    $priceStr = 'Rp ' . number_format(floor($rawPrice / 1.11), 0, ',', '.');
                    $pricePpnStr = 'Rp ' . number_format($rawPrice, 0, ',', '.');
                    $itemTotal = $net;
                } else {  // TANPA
                    $priceStr = 'Rp ' . number_format($rawPrice, 0, ',', '.');
                    $pricePpnStr = 'Rp ' . number_format($rawPrice, 0, ',', '.');
                    $itemTotal = $net;
                }

                $rows->push([
                    'id' => $orderItem->id,
                    'order_id' => $orderItem->order_id,
                    'medicine_id' => $orderItem->medicine_id,
                    'medicines' => $orderItem->medicines,
                    'quantity' => $orderItem->quantity,
                    'qty_received' => 0,
                    'qty_remaining' => $orderItem->quantity,
                    'raw_price' => $rawPrice,
                    'pack' => $orderItem->pack,
                    'price' => $priceStr,
                    'price_ppn' => $pricePpnStr,
                    'creditor_discount' => $pbfDiscStr,
                    'pbf_discount_raw' => $pbfDiscRaw,
                    'total' => 'Rp ' . number_format($itemTotal, 0, ',', '.'),
                    'receiving_items' => null,
                    'creditor_code' => $creditorCode,
                ]);
            } else {
                foreach ($orderItem->receivingItems as $batch) {
                    $ppnType = strtoupper(trim($batch->receiving_details?->invoice_ppn ?? $creditorPpn));
                    $activePrice = floatval($batch->raw_price ?? $orderItem->price ?? 0);
                    $qtyReceived = floatval($batch->qty_received ?? 0);
                    $gross = $qtyReceived * $activePrice;
                    $disc = floatval($batch->discount ?? 0);
                    $extraDisc = floatval($batch->extra_discount ?? 0);
                    $nomDisc = ($disc <= 100 && $disc > 0) ? ($gross * $disc / 100) : $disc;
                    $nomExtraDisc = ($extraDisc <= 100 && $extraDisc > 0) ? ($gross * $extraDisc / 100) : $extraDisc;
                    $net = max(0, $gross - $nomDisc - $nomExtraDisc);

                    if ($ppnType === 'EXCLUDE') {
                        $priceStr = 'Rp ' . number_format($activePrice, 0, ',', '.');
                        $pricePpnStr = 'Rp ' . number_format(floor($activePrice * 1.11), 0, ',', '.');
                        $itemTotal = floor($net * 1.11);
                    } elseif ($ppnType === 'INCLUDE') {
                        $priceStr = 'Rp ' . number_format(floor($activePrice / 1.11), 0, ',', '.');
                        $pricePpnStr = 'Rp ' . number_format($activePrice, 0, ',', '.');
                        $itemTotal = $net;
                    } else {  // TANPA
                        $priceStr = 'Rp ' . number_format($activePrice, 0, ',', '.');
                        $pricePpnStr = 'Rp ' . number_format($activePrice, 0, ',', '.');
                        $itemTotal = $net;
                    }

                    $rows->push([
                        'id' => $orderItem->id,
                        'order_id' => $orderItem->order_id,
                        'medicine_id' => $orderItem->medicine_id,
                        'medicines' => $orderItem->medicines,
                        'quantity' => $orderItem->quantity,
                        'qty_received' => $batch->qty_received,
                        'qty_remaining' => $qtyRemaining,
                        'raw_price' => $activePrice,
                        'pack' => $orderItem->pack,
                        'price' => $priceStr,
                        'price_ppn' => $pricePpnStr,
                        'creditor_discount' => $pbfDiscStr,
                        'pbf_discount_raw' => $pbfDiscRaw,
                        'total' => 'Rp ' . number_format($batch->total ? floatval($batch->total) : $itemTotal, 0, ',', '.'),
                        'receiving_items' => $batch,
                        'creditor_code' => $creditorCode,
                    ]);
                }
            }
        }

        return DataTables::of($rows)
            ->addIndexColumn()
            ->make(true);
    }

    public function searchReceivingDetails(Request $request)
    {
        $query = ReceivingDetails::where('id', $request->detail_id)->first();
        $creditor_code = $query->creditor_code;

        // $query = ReceivingDetails::whereHas('receiving_items.order_items', function ($query) use ($orderid) {
        //     $query->where('order_id', $orderid);
        // })->where('creditor_code', $creditor_code)->first();
        $creditor = Creditor::where('code', $creditor_code)->first();
        return response()->json([
            'query' => $query,
            'creditor' => $creditor
        ]);
    }

    public function selectCreditors(Request $request)
    {
        $orderid = $request->orderid;
        $creditor_code = $request->creditor_code;

        $query = ReceivingDetails::whereHas('receiving_items.order_items', function ($query) use ($orderid) {
            $query->where('order_id', $orderid);
        })->where('creditor_code', $creditor_code)->first();
        $creditor = Creditor::where('code', $creditor_code)->first();
        return response()->json([
            'query' => $query,
            'creditor' => $creditor
        ]);
    }

    public function history(Request $request)
    {
        return view('orders.history');
    }

    public function orderhistory(Request $request)
    {
        return view('orders.orderhistory');
    }

    public function printSPBFinal($orderId)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        // Release session lock early — prevents blocking other requests from same user
        if (session()->isStarted()) {
            session()->save();
        }
        $date = Carbon::now()->translatedFormat('d F Y');
        $order = Order::with([
            'pharmacy',
            'order_items.receivingItems',
            'order_items.receivingItems.receiving_details',
            'order_items.medicines',
            'order_items.medicines.creditors',
            'order_items.creditors',
            'order_items.medicines.factory',
            'order_items.medicines.category',
            'order_items.medicines.composition',
        ])->findOrFail($orderId);

        $activePharmacyId = getActivePharmacyId();
        $targetPharmacyId = (isWarehousePharmacy($activePharmacyId) || isWarehousePharmacy($order->pharmacy_id)) ? 1 : ($activePharmacyId ?? $order->pharmacy_id);
        $pharmacy = \App\Models\Pharmacies::find($targetPharmacyId) ?? $order->pharmacy;

        $grouped = $order->order_items->groupBy(function ($item) {
            $rawType = strtoupper(trim($item->medicines->type ?? 'REGULER'));
            if ($rawType === 'NARKOTIKA') {
                return 'NARKOTIKA_' . $item->id;
            }
            if ($rawType === 'PREKURSOR') {
                return 'PREKURSOR';
            }
            if ($rawType === 'PSIKOTROPIKA') {
                return 'PSIKOTROPIKA';
            }
            if ($rawType === 'OBAT-OBAT TERTENTU (OOT)' || $rawType === 'OBAT TERTENTU' || $rawType === 'OOT') {
                return 'OBAT-OBAT TERTENTU (OOT)';
            }
            return 'REGULER';
        })->map(function ($perCreditor) {
            return $perCreditor->groupBy('creditor_code')->sortBy(function ($items) {
                return $items->first()->order_items_code ?? '';
            });
        });

        $logoPath = $pharmacy->logo && file_exists(public_path('img/' . $pharmacy->logo))
            ? public_path('img/' . $pharmacy->logo)
            : public_path('img/logo-shb.png');
        $logoBase64 = imageToBase64($logoPath, 80);

        $sigPath = $pharmacy->signature && file_exists(public_path('img/' . $pharmacy->signature))
            ? public_path('img/' . $pharmacy->signature)
            : null;
        $signatureBase64 = $sigPath ? imageToBase64($sigPath, 70) : null;

        $pdf = Pdf::loadView('orders.printSPBFinal', compact('order', 'date', 'grouped', 'pharmacy', 'logoBase64', 'signatureBase64'))
            ->setPaper('A7', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'isFontSubsettingEnabled' => true,
                'dpi' => 96,
                'defaultFont' => 'sans-serif'
            ]);

        $pdfContent = $pdf->output();
        while (ob_get_level() > 0) { ob_end_clean(); }

        $tmpFile = tempnam(sys_get_temp_dir(), 'spbf_') . '.pdf';
        file_put_contents($tmpFile, $pdfContent);

        return response()->file($tmpFile, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"SPBFINAL-{$order->code}.pdf\"",
        ])->deleteFileAfterSend(true);
    }

    public function printSPBFinalByCreditor($orderId, $creditorCode)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        // Release session lock early — prevents blocking other requests from same user
        if (session()->isStarted()) {
            session()->save();
        }
        $date = Carbon::now()->translatedFormat('d F Y');
        $order = Order::with([
            'pharmacy',
            'order_items' => function ($q) use ($creditorCode) {
                $q->where('creditor_code', $creditorCode);
            },
            'order_items.receivingItems',
            'order_items.receivingItems.receiving_details',
            'order_items.medicines',
            'order_items.medicines.creditors',
            'order_items.creditors',
            'order_items.medicines.factory',
            'order_items.medicines.category',
            'order_items.medicines.composition',
        ])->findOrFail($orderId);

        $activePharmacyId = getActivePharmacyId();
        $targetPharmacyId = (isWarehousePharmacy($activePharmacyId) || isWarehousePharmacy($order->pharmacy_id)) ? 1 : ($activePharmacyId ?? $order->pharmacy_id);
        $pharmacy = \App\Models\Pharmacies::find($targetPharmacyId) ?? $order->pharmacy;

        $grouped = $order->order_items->groupBy(function ($item) {
            $rawType = strtoupper(trim($item->medicines->type ?? 'REGULER'));
            if ($rawType === 'NARKOTIKA') {
                return 'NARKOTIKA_' . $item->id;
            }
            if ($rawType === 'PREKURSOR') {
                return 'PREKURSOR';
            }
            if ($rawType === 'PSIKOTROPIKA') {
                return 'PSIKOTROPIKA';
            }
            if ($rawType === 'OBAT-OBAT TERTENTU (OOT)' || $rawType === 'OBAT TERTENTU' || $rawType === 'OOT') {
                return 'OBAT-OBAT TERTENTU (OOT)';
            }
            return 'REGULER';
        })->map(function ($perCreditor) {
            return $perCreditor->groupBy('creditor_code')->sortBy(function ($items) {
                return $items->first()->order_items_code ?? '';
            });
        });

        $receivingDetail = \App\Models\ReceivingDetails::where('sp_code', $order->order_items->first()->order_items_code)
            ->first();

        $logoPath = $pharmacy->logo && file_exists(public_path('img/' . $pharmacy->logo))
            ? public_path('img/' . $pharmacy->logo)
            : public_path('img/logo-shb.png');
        $logoBase64 = imageToBase64($logoPath, 80);

        $sigPath = $pharmacy->signature && file_exists(public_path('img/' . $pharmacy->signature))
            ? public_path('img/' . $pharmacy->signature)
            : null;
        $signatureBase64 = $sigPath ? imageToBase64($sigPath, 70) : null;

        $pdf = Pdf::loadView('orders.printSPBFinal', compact('order', 'date', 'grouped', 'pharmacy', 'receivingDetail', 'logoBase64', 'signatureBase64'))
            ->setPaper('A7', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'isFontSubsettingEnabled' => true,
                'dpi' => 96,
                'defaultFont' => 'sans-serif'
            ]);

        $pdfContent = $pdf->output();
        while (ob_get_level() > 0) { ob_end_clean(); }

        $tmpFile = tempnam(sys_get_temp_dir(), 'spbfc_') . '.pdf';
        file_put_contents($tmpFile, $pdfContent);

        return response()->file($tmpFile, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"SPBFINAL-{$order->code}-{$creditorCode}.pdf\"",
        ])->deleteFileAfterSend(true);
    }

    public function printSPBFinalByFaktur($orderId, $receivingDetailsId)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        // Release session lock early — prevents blocking other requests from same user
        if (session()->isStarted()) {
            session()->save();
        }
        $date = Carbon::now()->translatedFormat('d F Y');

        $receivingDetail = \App\Models\ReceivingDetails::findOrFail($receivingDetailsId);
        $creditorCode = $receivingDetail->creditor_code;

        $order = Order::with([
            'pharmacy',
            'order_items' => function ($q) use ($creditorCode, $receivingDetailsId) {
                $q
                    ->where('creditor_code', $creditorCode)
                    ->whereHas('receivingItems', function ($q2) use ($receivingDetailsId) {
                        $q2->where('receiving_details_id', $receivingDetailsId);
                    });
            },
            'order_items.receivingItems' => function ($q) use ($receivingDetailsId) {
                $q->where('receiving_details_id', $receivingDetailsId);
            },
            'order_items.receivingItems.receiving_details',
            'order_items.medicines',
            'order_items.medicines.creditors',
            'order_items.creditors',
            'order_items.medicines.factory',
            'order_items.medicines.category',
            'order_items.medicines.composition',
        ])->findOrFail($orderId);

        $activePharmacyId = getActivePharmacyId();
        $targetPharmacyId = (isWarehousePharmacy($activePharmacyId) || isWarehousePharmacy($order->pharmacy_id)) ? 1 : ($activePharmacyId ?? $order->pharmacy_id);
        $pharmacy = \App\Models\Pharmacies::find($targetPharmacyId) ?? $order->pharmacy;

        $grouped = $order->order_items->groupBy(function ($item) {
            $rawType = strtoupper(trim($item->medicines->type ?? 'REGULER'));
            if ($rawType === 'NARKOTIKA') {
                return 'NARKOTIKA_' . $item->id;
            }
            if ($rawType === 'PREKURSOR') {
                return 'PREKURSOR';
            }
            if ($rawType === 'PSIKOTROPIKA') {
                return 'PSIKOTROPIKA';
            }
            if ($rawType === 'OBAT-OBAT TERTENTU (OOT)' || $rawType === 'OBAT TERTENTU' || $rawType === 'OOT') {
                return 'OBAT-OBAT TERTENTU (OOT)';
            }
            return 'REGULER';
        })->map(function ($perCreditor) {
            return $perCreditor->groupBy('creditor_code')->sortBy(function ($items) {
                return $items->first()->order_items_code ?? '';
            });
        });

        $logoPath = $pharmacy->logo && file_exists(public_path('img/' . $pharmacy->logo))
            ? public_path('img/' . $pharmacy->logo)
            : public_path('img/logo-shb.png');
        $logoBase64 = imageToBase64($logoPath, 80);

        $sigPath = $pharmacy->signature && file_exists(public_path('img/' . $pharmacy->signature))
            ? public_path('img/' . $pharmacy->signature)
            : null;
        $signatureBase64 = $sigPath ? imageToBase64($sigPath, 70) : null;

        $pdf = Pdf::loadView('orders.printSPBFinal', compact('order', 'date', 'grouped', 'pharmacy', 'receivingDetail', 'logoBase64', 'signatureBase64'))
            ->setPaper('A7', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'isFontSubsettingEnabled' => true,
                'dpi' => 96,
                'defaultFont' => 'sans-serif'
            ]);

        $pdfContent = $pdf->output();
        while (ob_get_level() > 0) { ob_end_clean(); }

        $tmpFile = tempnam(sys_get_temp_dir(), 'spbff_') . '.pdf';
        file_put_contents($tmpFile, $pdfContent);

        return response()->file($tmpFile, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"SPBFINAL-{$order->code}-{$creditorCode}-FAKTUR.pdf\"",
        ])->deleteFileAfterSend(true);
    }

    public function printSPBFinalByItem($orderId, $orderItemId)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        // Release session lock early — prevents blocking other requests from same user
        if (session()->isStarted()) {
            session()->save();
        }
        $date = Carbon::now()->translatedFormat('d F Y');
        $order = Order::with([
            'pharmacy',
            'order_items' => function ($q) use ($orderItemId) {
                $q->where('id', $orderItemId);
            },
            'order_items.receivingItems',
            'order_items.receivingItems.receiving_details',
            'order_items.medicines',
            'order_items.medicines.creditors',
            'order_items.creditors',
            'order_items.medicines.factory',
            'order_items.medicines.category',
            'order_items.medicines.composition',
        ])->findOrFail($orderId);

        $activePharmacyId = getActivePharmacyId();
        $targetPharmacyId = (isWarehousePharmacy($activePharmacyId) || isWarehousePharmacy($order->pharmacy_id)) ? 1 : ($activePharmacyId ?? $order->pharmacy_id);
        $pharmacy = \App\Models\Pharmacies::find($targetPharmacyId) ?? $order->pharmacy;

        $grouped = $order->order_items->groupBy(function ($item) {
            $rawType = strtoupper(trim($item->medicines->type ?? 'REGULER'));
            if ($rawType === 'NARKOTIKA') {
                return 'NARKOTIKA_' . $item->id;
            }
            if ($rawType === 'PREKURSOR') {
                return 'PREKURSOR';
            }
            if ($rawType === 'PSIKOTROPIKA') {
                return 'PSIKOTROPIKA';
            }
            if ($rawType === 'OBAT-OBAT TERTENTU (OOT)' || $rawType === 'OBAT TERTENTU' || $rawType === 'OOT') {
                return 'OBAT-OBAT TERTENTU (OOT)';
            }
            return 'REGULER';
        })->map(function ($perCreditor) {
            return $perCreditor->groupBy('creditor_code')->sortBy(function ($items) {
                return $items->first()->order_items_code ?? '';
            });
        });

        $logoPath = $pharmacy->logo && file_exists(public_path('img/' . $pharmacy->logo))
            ? public_path('img/' . $pharmacy->logo)
            : public_path('img/logo-shb.png');
        $logoBase64 = imageToBase64($logoPath, 80);

        $sigPath = $pharmacy->signature && file_exists(public_path('img/' . $pharmacy->signature))
            ? public_path('img/' . $pharmacy->signature)
            : null;
        $signatureBase64 = $sigPath ? imageToBase64($sigPath, 70) : null;

        $pdf = Pdf::loadView('orders.printSPBFinal', compact('order', 'date', 'grouped', 'pharmacy', 'logoBase64', 'signatureBase64'))
            ->setPaper('A7', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'isFontSubsettingEnabled' => true,
                'dpi' => 96,
                'defaultFont' => 'sans-serif'
            ]);

        $pdfContent = $pdf->output();
        while (ob_get_level() > 0) { ob_end_clean(); }

        $tmpFile = tempnam(sys_get_temp_dir(), 'spbi_') . '.pdf';
        file_put_contents($tmpFile, $pdfContent);

        return response()->file($tmpFile, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"SPBFINAL-{$order->code}-item-{$orderItemId}.pdf\"",
        ])->deleteFileAfterSend(true);
    }

    public function printOrders($orderId)
    {
        $order = Order::findOrFail($orderId);

        $receivingItem = ReceivingItems::whereHas('order_items', function ($q) use ($orderId) {
            $q->where('order_id', $orderId);
        })
            ->with('receiving_details.receiving')
            ->first();

        abort_if(!$receivingItem, 404, 'Data receiving tidak ditemukan untuk order ini.');

        $receivingId = $receivingItem->receiving_details->receiving_id;

        $receiving = Receiving::with([
            'pharmacy',
            'receiving_details' => function ($q) use ($orderId) {
                $q->whereHas('receiving_items.order_items', function ($sub) use ($orderId) {
                    $sub->where('order_id', $orderId);
                });
            },
            'receiving_details.creditor',
            'receiving_details.receiving_items' => function ($q) use ($orderId) {
                $q->whereHas('order_items', function ($sub) use ($orderId) {
                    $sub->where('order_id', $orderId);
                });
            },
            'receiving_details.receiving_items.order_items.medicines',
        ])
            ->findOrFail($receivingId);

        $groupedByPBF = $receiving->receiving_details->groupBy(function ($detail) {
            return $detail->creditor ? $detail->creditor->name : 'Unknown PBF';
        });

        return \PDF::loadView('orders.printOrders', compact('receiving', 'order', 'groupedByPBF'))
            ->setPaper('a4', 'landscape')
            ->stream('tanda-penerimaan-barang-' . $receiving->code . '.pdf');
    }

    public function gethistory(Request $request)
    {
        $query = MedicinePriceHistory::with(['medicines', 'user'])
            ->select('medicine_price_history.*');

        if ($request->filled('search_medicine')) {
            $kw = $request->search_medicine;
            $query->whereHas('medicines', function ($q) use ($kw) {
                $q
                    ->where('name', 'like', "%{$kw}%")
                    ->orWhere('code', 'like', "%{$kw}%");
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $query->orderByDesc('created_at');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('medicine_code', fn($row) => $row->medicines?->code ?? '-')
            ->addColumn('medicine_name', fn($row) => $row->medicines?->name ?? '-')
            ->addColumn('medicine_unit', fn($row) => $row->medicines?->unit ?? '-')
            ->addColumn('new_price_fmt', function ($row) {
                return 'Rp ' . number_format($row->new_price, 0, ',', '.');
            })
            ->addColumn('changed_by', fn($row) => $row->user?->name ?? '-')
            ->addColumn('changed_at', fn($row) => $row->created_at?->format('d/m/Y H:i') ?? '-')
            ->addColumn('direction', function ($row) {
                $current = $row->medicines?->net_price ?? 0;
                $new = $row->new_price;

                if ($new > $current) {
                    return '<span class="badge-up">▲ Naik</span>';
                } elseif ($new < $current) {
                    return '<span class="badge-down">▼ Turun</span>';
                }
                return '<span class="badge-same">— Sama</span>';
            })
            ->rawColumns(['direction'])
            ->make(true);
    }

    public function getorderhistory(Request $request)
    {
        $items = ReceivingDetails::with([
            'receiving',
            'creditor',
            'receiving_items.order_items',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $items->where(function ($query) use ($search) {
                $query
                    ->where('invoice_date', $search)
                    ->orWhere('invoice_number', $search)
                    ->orWhereHas('creditor', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%");
                    });
            });
        }

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('date', function ($row) {
                return Carbon::parse($row->created_at)->format('d/m/Y');
            })
            ->addColumn('invoice_payment', function ($row) {
                return $row->invoice_payment;
            })
            ->addColumn('invoice_date', function ($row) {
                return Carbon::parse($row->invoice_date)->format('d/m/Y');
            })
            ->addColumn('invoice_number', function ($row) {
                return $row->invoice_number ?? '-';
            })
            ->addColumn('creditor', function ($row) {
                return $row->creditor->name;
            })
            ->addColumn('action', function ($row) {
                return ' <a target="_blank" href="../invoice/print/' . $row->id . '">
                            <div class="flex gap-1">
                                <div class="w-full">
                                    <button style="background-color:#eab308;color:white;" class="rounded-full px-2 py-2 font-semibold">
                                        <div class="flex gap-2 justify-center items-center">
                                            <span>
                                            <svg 
                                            xmlns="http://www.w3.org/2000/svg" 
                                            viewBox="0 0 24 24" 
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            class="w-6 h-6 text-[#fff] hover:text-blue-600 transition cursor-pointer"
                                        >
                                            <path d="M6 9V3H18V9" />
                                            <rect x="6" y="14" width="12" height="7" rx="1" />
                                            <path d="M6 18H5A2 2 0 0 1 3 16V11A2 2 0 0 1 5 9H19A2 2 0 0 1 21 11V16A2 2 0 0 1 19 18H18" />
                                        </svg>
                                            </span>
                                            <span class="text-xs pr-2">Cetak</span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </a>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function orderList(Request $request)
    {
        $items = Order::query()
            ->where('pharmacy_id', getPurchasingPharmacyId())
            ->with(['order_items.receivingItems.receiving_details'])
            ->withSum('order_items', 'total')
            ->orderByDesc('id');

        if ($request->filled('order_code')) {
            $searchTerm = $request->order_code;
            $items->where(function ($q) use ($searchTerm) {
                $q
                    ->where('code', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('order_items.receivingItems.receiving_details', function ($q2) use ($searchTerm) {
                        $q2->where('receiving_details_code', 'like', '%' . $searchTerm . '%');
                    });
            });
        }
        if ($request->filled('start_date')) {
            $items->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $items->whereDate('created_at', '<=', $request->end_date);
        }

        // Helper tombol: satu template, warna & label beda-beda lewat parameter.
        // stroke-width 1.75 + w-3.5 h-3.5 dipakai di SEMUA tombol (termasuk Lanjutkan/Terima)
        // supaya ukuran icon konsisten di seluruh kolom aksi.
        $actionBtn = function (string $href, string $label, string $iconPath, string $colorClasses, bool $blank = false) {
            $target = $blank ? ' target="_blank"' : '';
            return '<a href="' . $href . '"' . $target . ' class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border rounded-lg transition-colors ' . $colorClasses . '">'
                . '<svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">' . $iconPath . '</svg>'
                . '<span>' . $label . '</span>'
                . '</a>';
        };

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('date', fn($row) => $row->date ? date('d M Y', strtotime($row->updated_at)) : '-')
            // SPB code + badge NT, dibatasi 3 + "+N lainnya" (bagian yang kamu suka, tetap dipertahankan)
            ->addColumn('code', function ($row) {
                $code = e($row->code ?? '0');
                $html = '<span style="font-size:10px" class="inline-flex items-center px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 font-nunito-bold tracking-wide border border-slate-200">' . $code . '</span>';

                $codes = collect();
                if ($row->relationLoaded('order_items')) {
                    $codes = $row->order_items->flatMap(function ($item) {
                        return $item->receivingItems->map(function ($ri) {
                            return $ri->receiving_details->receiving_details_code ?? null;
                        });
                    })->filter()->unique()->values();
                }

                if ($codes->isNotEmpty()) {
                    $visibleLimit = 3;
                    $visible = $codes->take($visibleLimit);
                    $hidden = $codes->slice($visibleLimit);

                    // font-size disamakan: 11px, level "sekunder" yang sama buat badge NT & "+N lainnya"
                    $badge = fn($c) => '<span style="font-size:10px" class="inline-flex items-center px-2 py-0.5 rounded font-nunito font-medium bg-blue-50 text-blue-700 border border-blue-200">' . e($c) . '</span>';

                    $html .= '<div class="mt-1 flex flex-wrap items-center gap-1">';
                    foreach ($visible as $c) {
                        $html .= $badge($c);
                    }

                    if ($hidden->isNotEmpty()) {
                        $html .= '<details class="inline-block align-middle">';
                        $html .= '<summary class="list-none [&::-webkit-details-marker]:hidden inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-500 border border-slate-200 cursor-pointer hover:bg-slate-200">+' . $hidden->count() . ' lainnya</summary>';
                        $html .= '<div class="mt-1 flex flex-wrap gap-1">';
                        foreach ($hidden as $c) {
                            $html .= $badge($c);
                        }
                        $html .= '</div></details>';
                    }
                    $html .= '</div>';
                }

                return $html;
            })
            // Status badge — pakai raw inline style, bukan class Tailwind, supaya warnanya
            // pasti render tanpa tergantung content-scanning/build Tailwind (ini yang bikin
            // titik statusnya sempat tidak muncul sebelumnya)
            ->addColumn('status_order', function ($row) {
                $variants = [
                    'diterima' => ['label' => 'DITERIMA', 'text' => '#047857', 'bg' => '#ecfdf5', 'border' => '#a7f3d0', 'dot' => '#10b981'],
                    'dipesan' => ['label' => 'DIPESAN', 'text' => '#b45309', 'bg' => '#fffbeb', 'border' => '#fde68a', 'dot' => '#f59e0b'],
                    'pending' => ['label' => 'PENDING', 'text' => '#be123c', 'bg' => '#fff1f2', 'border' => '#fecdd3', 'dot' => '#f43f5e'],
                ];

                $key = $row->status == 3 ? 'diterima' : (in_array($row->status, [1, 2]) ? 'dipesan' : 'pending');
                $v = $variants[$key];

                return '<span style="display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:9999px; font-size:12px; font-weight:600; color:' . $v['text'] . '; background-color:' . $v['bg'] . '; border:1px solid ' . $v['border'] . ';">'
                    . '<span style="display:inline-block; width:6px; height:6px; border-radius:9999px; background-color:' . $v['dot'] . ';"></span>'
                    . $v['label']
                    . '</span>';
            })
            // Action buttons — warna balik lagi, icon lebih kecil & konsisten, tanpa glow
            ->addColumn('action', function ($row) use ($actionBtn) {
                if ($row->status == 0) {
                    return $actionBtn(
                        route('orders.create', ['order_id' => $row->id]),
                        'Lanjutkan',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>',
                        'text-white bg-blue-600 hover:bg-blue-700 border-blue-600'
                    );
                }

                if ($row->status == 1 || $row->status == 2) {
                    return $actionBtn(
                        '/receive/' . $row->id,
                        'Terima',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                        'text-white bg-emerald-600 hover:bg-emerald-700 border-emerald-600'
                    );
                }

                // status == 3 (DITERIMA): tiga aksi, masing-masing warna beda biar cepat dibedain
                return '<div class="flex items-center gap-2">'
                    . $actionBtn(
                        '/receiving/' . $row->id . '/printspbfinal',
                        'Cetak SPB',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656"/>',
                        'text-amber-700 bg-amber-50 hover:bg-amber-100 border-amber-200',
                        true
                    )
                    . $actionBtn(
                        '/receiving/' . $row->id . '/printorders',
                        'Invoice',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.25-2.142V8.25"/>',
                        'text-pink-700 bg-pink-50 hover:bg-pink-100 border-pink-200',
                        true
                    )
                    . $actionBtn(
                        '/orders/' . $row->id . '/revision',
                        'Revisi Faktur',
                        '<path d="M7 15h-3a1 1 0 0 1 -1 -1v-8a1 1 0 0 1 1 -1h12a1 1 0 0 1 1 1v3" /><path d="M11 19h-3a1 1 0 0 1 -1 -1v-8a1 1 0 0 1 1 -1h12a1 1 0 0 1 1 1v1.25" /><path d="M18.42 15.61a2.1 2.1 0 1 1 2.97 2.97l-3.39 3.42h-3v-3l3.42 -3.39" />',
                        'text-sky-700 bg-sky-50 hover:bg-sky-100 border-sky-200'
                    )
                    . '</div>';
            })
            // Total & Total PPN — font-size 13px (sengaja sedikit lebih besar dari 12px karena ini
            // yang paling penting dilihat kasir), no-wrap biar angka gak patah baris
            ->addColumn('total', fn($row) => '<span class="text-[13px] font-semibold text-slate-700 whitespace-nowrap tabular-nums">Rp ' . number_format($row->order_items_sum_total ?? 0, 0, ',', '.') . '</span>')
            ->addColumn('total_ppn', fn($row) => '<span class="text-[13px] font-bold text-slate-900 whitespace-nowrap tabular-nums">Rp ' . number_format(floor(($row->order_items_sum_total ?? 0) * 1.11), 0, ',', '.') . '</span>')
            ->rawColumns(['code', 'status_order', 'action', 'total', 'total_ppn'])
            ->make(true);
    }

    function generateReceivingCode()
    {
        $now = Carbon::now();

        $year = $now->format('y');
        $month = $now->format('m');
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

    function generateReceivingDetailsCode($pharmacy_id)
    {
        $now = Carbon::now();
        $year = $now->format('y');
        $month = $now->format('m');
        $prefix = "NT-{$year}-{$month}/";

        $lastCode = ReceivingDetails::whereHas('receiving', function ($q) use ($pharmacy_id) {
            $q->where('pharmacy_id', $pharmacy_id);
        })
            ->where('receiving_details_code', 'like', "{$prefix}%")
            ->orderBy('receiving_details_code', 'desc')
            ->value('receiving_details_code');

        if ($lastCode) {
            $lastNumber = (int) substr($lastCode, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function updateReceivingItem(Request $request, $id)
    {
        $request->validate([
            'qty_received' => 'required|numeric|min:0',
            'raw_price' => 'required|numeric|min:0',
            'batch' => 'required',
            'expired_date' => 'required',
            'discount' => 'required',
            'extra_discount' => 'required',
            'status' => 'required',
            'total' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $item = ReceivingItems::with('order_items')->findOrFail($id);
            $medicineId = $item->order_items->medicine_id;
            $pharmacyId = getActivePharmacyId();

            $oldQty = $item->qty_received;
            $newQty = $request->qty_received;
            $oldBatchKey = "{$medicineId}|{$item->batch}|{$item->expired_date}";
            $newBatchKey = "{$medicineId}|{$request->batch}|{$request->expired_date}";
            $medicine = Medicines::findOrFail($medicineId);

            $isPack = ($item->order_items->pack == 1);
            $content = $isPack ? (int) ($medicine->content ?? 1) : 1;
            $oldActualQty = $oldQty * $content;
            $newActualQty = $newQty * $content;
            $deltaActual = $newActualQty - $oldActualQty;

            // Proteksi stok minus jika barang sudah terjual di kasir
            if ($deltaActual < 0 && ($medicine->stock + $deltaActual < 0)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Gagal mengurangi kuantiti: Stok saat ini tersisa {$medicine->stock}, tidak mencukupi untuk dikurangi sebesar " . abs($deltaActual) . ". Sebagian barang kemungkinan telah terjual di kasir.",
                ], 422);
            }

            if ($oldBatchKey === $newBatchKey) {
                if ($deltaActual != 0) {
                    $qtyBefore = $medicine->stock;
                    $medicine->increment('stock', $deltaActual);

                    if (!isWarehousePharmacy($pharmacyId)) {
                        $transferItem = MedicineTransferItems::where('receiving_items_id', $item->id)->first();
                        if ($transferItem) {
                            $transferItem->update(['qty' => $newActualQty]);
                        }
                    } else {
                        Batches::where('id', $item->batches_id)->increment('stock', $deltaActual);
                    }

                    ItemsLog::create([
                        'transaction_code' => 'REV-' . $item->id,
                        'code' => $this->generateItemsLogCode(),
                        'type' => 'RV',
                        'medicine_id' => $medicineId,
                        'qty' => $deltaActual,
                        'qty_before' => $qtyBefore,
                        'qty_after' => $medicine->stock,
                        'total' => $request->total,
                        'date' => Carbon::now()->format('Y-m-d H:i:s'),
                        'status' => 8,
                        'batches_id' => $item->batches_id,
                        'user_id' => auth()->user()->id,
                    ]);
                }
            } else {
                $qtyBeforeReverse = $medicine->stock;
                $medicine->decrement('stock', $oldActualQty);

                $transferHeaderId = null;
                if (!isWarehousePharmacy($pharmacyId)) {
                    $transferItem = MedicineTransferItems::where('receiving_items_id', $item->id)->first();
                    if ($transferItem) {
                        $transferHeaderId = $transferItem->medicine_transfer_id;
                        $transferItem->delete();
                    }
                } else {
                    Batches::where('id', $item->batches_id)->decrement('stock', $oldActualQty);
                }

                $newBatch = Batches::firstOrCreate(
                    [
                        'medicine_id' => $medicineId,
                        'name' => $request->batch,
                        'expired_date' => $request->expired_date,
                        'pharmacy_id' => $pharmacyId,
                    ],
                    ['status' => 0, 'stock' => 0]
                );

                $medicine->increment('stock', $newActualQty);

                if (!isWarehousePharmacy($pharmacyId)) {
                    if (!$transferHeaderId) {
                        $transferHeader = MedicineTransfers::create([
                            'code' => $this->generateTransfersCode(),
                            'status' => 1,
                        ]);
                        $transferHeaderId = $transferHeader->id;
                    }

                    MedicineTransferItems::create([
                        'medicine_transfer_id' => $transferHeaderId,
                        'batches_id' => $newBatch->id,
                        'receiving_items_id' => $item->id,
                        'etalases_id' => 99,
                        'qty' => $newActualQty,
                        'status' => 1,
                    ]);
                } else {
                    $newBatch->increment('stock', $newActualQty);
                }

                ItemsLog::create([
                    'transaction_code' => 'REV-' . $item->id,
                    'code' => $this->generateItemsLogCode(),
                    'type' => 'RV',
                    'medicine_id' => $medicineId,
                    'qty' => $deltaActual,
                    'qty_before' => $qtyBeforeReverse,
                    'qty_after' => $medicine->stock,
                    'total' => $request->total,
                    'date' => Carbon::now()->format('Y-m-d H:i:s'),
                    'status' => 8,
                    'batches_id' => $newBatch->id,
                    'user_id' => auth()->user()->id,
                ]);

                $item->batches_id = $newBatch->id;
            }

            $item->update([
                'qty_received' => $newQty,
                'qty' => $newQty,
                'raw_price' => $request->raw_price,
                'discount' => $request->discount,
                'subtotal' => $request->total,
                'batch' => $request->batch,
                'expired_date' => $request->expired_date,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data penerimaan berhasil diubah.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteReceivingItem($id)
    {
        try {
            DB::beginTransaction();

            $item = ReceivingItems::with('order_items')->findOrFail($id);
            $medicineId = $item->order_items->medicine_id;
            $pharmacyId = getActivePharmacyId();
            $medicine = Medicines::findOrFail($medicineId);

            $isPack = ($item->order_items->pack == 1);
            $content = $isPack ? (int) ($medicine->content ?? 1) : 1;
            $actualQty = $item->qty_received * $content;

            if ($medicine->stock < $actualQty) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Gagal menghapus item: Stok saat ini tersisa {$medicine->stock}, kurang dari kuantiti yang akan ditarik ({$actualQty}). Sebagian barang kemungkinan telah terjual di kasir.",
                ], 422);
            }

            $qtyBefore = $medicine->stock;
            $medicine->decrement('stock', $actualQty);

            if (!isWarehousePharmacy($pharmacyId)) {
                $transferItem = MedicineTransferItems::where('receiving_items_id', $item->id)->first();
                if ($transferItem) {
                    $transferHeaderId = $transferItem->medicine_transfer_id;
                    $transferItem->delete();

                    if (MedicineTransferItems::where('medicine_transfer_id', $transferHeaderId)->count() === 0) {
                        MedicineTransfers::where('id', $transferHeaderId)->delete();
                    }
                }
            } else {
                Batches::where('id', $item->batches_id)->decrement('stock', $actualQty);
            }

            ItemsLog::create([
                'transaction_code' => 'REV-DEL-' . $item->id,
                'code' => $this->generateItemsLogCode(),
                'type' => 'RV',
                'medicine_id' => $medicineId,
                'qty' => -$actualQty,
                'qty_before' => $qtyBefore,
                'qty_after' => $medicine->stock,
                'total' => 0,
                'date' => Carbon::now()->format('Y-m-d H:i:s'),
                'status' => 9,
                'batches_id' => $item->batches_id,
                'user_id' => auth()->user()->id,
            ]);

            $item->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item berhasil dihapus',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Revision delete error', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus item',
            ], 500);
        }
    }

    public function deleteReceivingDraftItem($id)
    {
        try {
            DB::beginTransaction();

            $item = ReceivingItems::findOrFail($id);

            if (!is_null($item->batches_id)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Barang sudah diterima dan disimpan, tidak dapat dihapus',
                ], 422);
            }

            $item->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item berhasil dihapus',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Delete draft item error', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus item',
            ], 500);
        }
    }

    public function invoiceRevision($orderId)
    {
        $order = Order::with([
            'order_items.medicines',
            'order_items.receivingItems.locations',
            'order_items.receivingItems.etalases',
        ])->findOrFail($orderId);

        return view('orders.revision', compact('order'));
    }

    public function index()
    {
        $now = Carbon::now()->format('d/m/Y');
        $receiving_code = $this->generateReceivingCode();
        return view('orders.index', compact('receiving_code', 'now'));
    }

    public function receive($id)
    {
        $now = Carbon::now()->format('d/m/Y');
        $datenow = Carbon::now()->format('Y-m-d');

        $getOrder = Order::findOrFail($id);
        $orderPharmacyId = $getOrder->pharmacy_id;

        $transaction = Receiving::with('receiving_details')->where('status', 0)->where(
            'pharmacy_id',
            $orderPharmacyId
        )->first();

        if ($getOrder->status == 3) {
            return redirect()->route('receiving.index')->with('success', 'Pesanan ini sudah selesai diterima.');
        }

        $orderItemCount = OrderItems::where('order_id', $id)->count();
        if ($orderItemCount === 0) {
            return redirect()->route('orders.create', ['order_id' => $getOrder->id])
                ->with('warning', 'Pesanan ini belum memiliki item obat. Silakan isi item obat terlebih dahulu.');
        }

        $creditorOption = OrderItems::where('order_id', $id)
            ->select('creditor_code')
            ->distinct()
            ->with('creditors:id,code,name')
            ->get()
            ->pluck('creditors')
            ->filter()
            ->unique('code')
            ->values();

        $allFakturs = collect();
        if ($transaction && $transaction->receiving_details) {
            $allFakturs = $allFakturs->merge($transaction->receiving_details);
        }
        $historicalFakturs = \App\Models\ReceivingDetails::whereHas('receiving_items.order_items', function ($q) use ($id) {
            $q->where('order_id', $id);
        })->get();
        $allFakturs = $allFakturs->merge($historicalFakturs)->unique('id')->values();

        if (!$transaction) {
            $year = now()->format('y');
            $month = now()->format('m');
            $prefix = $year . $month . 'RE';
            $last = Receiving::where('pharmacy_id', $orderPharmacyId)
                ->where('code', 'like', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            $nextNumber = $last ? (intval(substr($last->code, -4)) + 1) : 1;
            $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $receiving_code = $prefix . $serial;

            $transaction = Receiving::create([
                'order_id' => $id,
                'creditors_id' => NULL,
                'pharmacy_id' => $orderPharmacyId,
                'code' => $receiving_code,
                'date' => $now,
                'status' => 0,
            ]);
        }

        $receiving_id = $transaction->id;
        if (!$getOrder->receiving_id || $getOrder->receiving_id != $transaction->id) {
            $getOrder->update(['receiving_id' => $transaction->id]);
        }
        $receiving_code = $transaction->code;
        $order_id = $getOrder->id;
        $order_code = $getOrder->code;

        $receivingDetails = ReceivingDetails::with(['receiving_items.order_items.medicines', 'creditor'])
            ->where('receiving_id', $transaction->id)
            ->get();

        $d_price = 0;
        $d_ppn = 0;
        $d_total = 0;

        foreach ($receivingDetails as $detail) {
            $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
            $detailSubtotal = 0;
            $detailDiscount = 0;

            foreach ($detail->receiving_items as $rItem) {
                $qty = floatval($rItem->qty_received ?? $rItem->qty ?? 0);
                $price = floatval($rItem->raw_price ?? $rItem->order_items->price ?? 0);
                $gross = $qty * $price;
                $disc = floatval($rItem->discount ?? 0);
                $extraDisc = floatval($rItem->extra_discount ?? 0);
                $nomDisc = ($disc <= 100 && $disc > 0) ? ($gross * $disc / 100) : $disc;
                $nomExtraDisc = ($extraDisc <= 100 && $extraDisc > 0) ? ($gross * $extraDisc / 100) : $extraDisc;

                $detailSubtotal += $gross;
                $detailDiscount += ($nomDisc + $nomExtraDisc);
            }

            $detailDpp = max(0, $detailSubtotal - $detailDiscount);

            if ($ppnType === 'EXCLUDE') {
                $detailPpn = floor($detailDpp * 0.11);
                $detailGrandTotal = $detailDpp + $detailPpn;
                $detailHna = $detailDpp;
            } elseif ($ppnType === 'INCLUDE') {
                $detailGrandTotal = $detailDpp;
                $detailHna = floor($detailDpp / 1.11);
                $detailPpn = $detailGrandTotal - $detailHna;
            } else {  // TANPA
                $detailPpn = 0;
                $detailGrandTotal = $detailDpp;
                $detailHna = $detailDpp;
            }

            $d_price += $detailHna;
            $d_ppn += $detailPpn;
            $d_total += $detailGrandTotal;
        }

        return view('orders.receiving', compact('order_id', 'd_price', 'd_ppn', 'd_total', 'order_code', 'creditorOption', 'receiving_code', 'transaction', 'now', 'datenow', 'receiving_id', 'allFakturs'));
    }

    public function addReceivingItem(Request $request)
    {
        $request->validate([
            'receiving_id' => 'required',
            'order_items_id' => 'required',
            'qty_received' => 'required|numeric|min:1',
            'raw_price' => 'required|numeric|min:0',
            'discount' => 'required',
            'extra_discount' => 'required',
            'expired_date' => 'required',
            'batch' => 'required',
            'status' => 'required',
            'invoice_date' => 'required',
            'invoice_due' => 'required',
            'invoice_number' => 'required',
            'invoice_payment' => 'required',
            'invoice_ppn' => 'required',
            'invoice_times' => 'required',
        ]);

        $receiving = Receiving::findOrFail($request->receiving_id);

        if ($receiving->status == 3) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan ini sudah diselesaikan dan tidak bisa diubah.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $details = ReceivingDetails::updateOrCreate(
                [
                    'receiving_id' => $request->receiving_id,
                    'invoice_number' => $request->invoice_number,
                    'creditor_code' => $request->creditor_code,
                ],
                [
                    'invoice_date' => $request->invoice_date,
                    'invoice_times' => $request->invoice_times,
                    'invoice_due' => $request->invoice_due,
                    'invoice_payment' => $request->invoice_payment,
                    'invoice_ppn' => $request->invoice_ppn,
                ]
            );

            $itemData = [
                'receiving_details_id' => $details->id,
                'order_items_id' => $request->order_items_id,
                'qty_received' => $request->qty_received,
                'qty' => $request->qty_received,
                'raw_price' => $request->raw_price,
                'discount' => $request->discount,
                'extra_discount' => $request->extra_discount,
                'expired_date' => $request->expired_date,
                'batch' => $request->batch,
                'location' => null,
                'etalase' => null,
                'total' => $request->total,
                'status' => $request->status,
            ];

            if ($request->has('pack')) {
                OrderItems::where('id', $request->order_items_id)->update([
                    'pack' => $request->pack ? 1 : 0,
                ]);
            }

            if ($request->filled('receiving_items_id')) {
                // Editing an existing batch row for this order item
                $item = ReceivingItems::findOrFail($request->receiving_items_id);
                $item->update($itemData);
            } else {
                // New batch entry — same medicine can appear multiple times
                // under one order item with different expiry/qty/price
                $item = ReceivingItems::create($itemData);
            }

            DB::commit();

            $receivingDetails = ReceivingDetails::with(['receiving_items.order_items.medicines', 'creditor'])
                ->where('receiving_id', $receiving->id)
                ->get();

            $d_price = 0;
            $d_price = 0;
            $d_ppn = 0;
            $d_total = 0;

            foreach ($receivingDetails as $detail) {
                $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
                $detailSubtotal = 0;
                $detailDiscount = 0;

                foreach ($detail->receiving_items as $rItem) {
                    $qty = floatval($rItem->qty_received ?? $rItem->qty ?? 0);
                    $price = floatval($rItem->raw_price ?? $rItem->order_items->price ?? 0);
                    $gross = $qty * $price;
                    $disc = floatval($rItem->discount ?? 0);
                    $extraDisc = floatval($rItem->extra_discount ?? 0);
                    $nomDisc = ($disc <= 100 && $disc > 0) ? ($gross * $disc / 100) : $disc;
                    $nomExtraDisc = ($extraDisc <= 100 && $extraDisc > 0) ? ($gross * $extraDisc / 100) : $extraDisc;

                    $detailSubtotal += $gross;
                    $detailDiscount += ($nomDisc + $nomExtraDisc);
                }

                $detailDpp = max(0, $detailSubtotal - $detailDiscount);

                if ($ppnType === 'EXCLUDE') {
                    $detailPpn = floor($detailDpp * 0.11);
                    $detailGrandTotal = $detailDpp + $detailPpn;
                    $detailHna = $detailDpp;
                } elseif ($ppnType === 'INCLUDE') {
                    $detailGrandTotal = $detailDpp;
                    $detailHna = floor($detailDpp / 1.11);
                    $detailPpn = $detailGrandTotal - $detailHna;
                } else {  // TANPA
                    $detailPpn = 0;
                    $detailGrandTotal = $detailDpp;
                    $detailHna = $detailDpp;
                }

                $d_price += $detailHna;
                $d_ppn += $detailPpn;
                $d_total += $detailGrandTotal;
            }

            return response()->json([
                'success' => true,
                'receiving' => $receiving,
                'item' => $item,
                'summary' => [
                    'price_item' => $d_price,
                    'price_ppn' => $d_ppn,
                    'price_total' => $d_total,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function printReceiving($id)
    {
        $receiving = Receiving::with([
            'receiving_details.receiving_items.order_items.medicines',
            'receiving_details.creditor'
        ])->findOrFail($id);

        $totalDiscount = 0;
        $extraDiscount = 0;
        $subtotal = 0;

        foreach ($receiving->receiving_details as $detail) {
            $totalDiscount += $detail->receiving_items->sum('discount');
            $extraDiscount += $detail->receiving_items->sum('extra_discount');
            $subtotal += $detail->receiving_items->sum('total');
        }

        $totaldiscount = $totalDiscount + $extraDiscount;
        $totalwithdiscount = $subtotal;
        $total_receiving = $subtotal - $totaldiscount;

        return view('orders.printReceiving', compact(
            'totaldiscount',
            'totalwithdiscount',
            'total_receiving',
            'receiving'
        ));
    }

    public function printInvoice($id)
    {
        $invoice = ReceivingDetails::with([
            'receiving',
            'receiving_items.order_items.medicines',
            'creditor'
        ])->findOrFail($id);

        $totalDiscount = $invoice->receiving_items->sum('discount');
        $extraDiscount = $invoice->receiving_items->sum('extra_discount');
        $subtotal = $invoice->receiving_items->sum('total');

        $totaldiscount = $totalDiscount + $extraDiscount;
        $totalwithdiscount = $subtotal;
        $total_receiving = $subtotal - $totaldiscount;

        return view('orders.printInvoice', compact(
            'totaldiscount',
            'totalwithdiscount',
            'total_receiving',
            'invoice'
        ));
    }

    function generateTransfersCode()
    {
        $now = Carbon::now();

        $year = $now->format('y');
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

    public function saveOrder(Request $request)
    {
        $request->validate([
            'receivingid' => 'required',
            'orderid' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $receiving = Receiving::with(['receiving_details.receiving_items.order_items'])
                ->findOrFail($request->receivingid);

            if ($receiving->status == 3) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Pesanan ini sudah diselesaikan dan tidak bisa diubah.',
                ], 422);
            }

            $order = Order::findOrFail($request->orderid);

            // Generate Nomor Terima (NT) and SP Code for any ReceivingDetails that doesn't have one yet
            foreach ($receiving->receiving_details as $details) {
                $needsSave = false;
                if (empty($details->receiving_details_code)) {
                    $details->receiving_details_code = $this->generateReceivingDetailsCode($receiving->pharmacy_id);
                    $needsSave = true;
                }
                if (empty($details->sp_code)) {
                    $firstItem = $details->receiving_items->first();
                    $details->sp_code = $firstItem && $firstItem->order_items ? $firstItem->order_items->order_items_code : $this->generateSPCode($receiving->pharmacy_id);
                    $needsSave = true;
                }
                if ($needsSave) {
                    $details->save();
                }
            }

            $receivingItems = $receiving
                ->receiving_details
                ->pluck('receiving_items')
                ->flatten()
                ->whereNull('batches_id')
                ->values();

            if ($receivingItems->isEmpty()) {
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Tidak ada item baru untuk disimpan',
                ]);
            }

            $now = Carbon::now()->format('Y-m-d');
            $order = Order::find($request->orderid);
            $pharmacyId = $order ? $order->pharmacy_id : getPurchasingPharmacyId();

            $medicineIds = $receivingItems->pluck('order_items.medicine_id')->unique()->values();
            $medicines = Medicines::whereIn('id', $medicineIds)->get()->keyBy('id');

            $existingBatches = Batches::where('pharmacy_id', $pharmacyId)
                ->where(function ($q) use ($receivingItems) {
                    foreach ($receivingItems as $item) {
                        $q->orWhere(
                            fn($q2) => $q2
                                ->where('medicine_id', $item->order_items->medicine_id)
                                ->where('name', $item->batch)
                                ->where('expired_date', $item->expired_date)
                        );
                    }
                })
                ->get()
                ->keyBy(fn($b) => "{$b->medicine_id}|{$b->name}|{$b->expired_date}");

            $itemsLogInserts = [];
            $medicineIncrements = [];
            $receivingItemUpdates = [];

            $transferHeader = null;
            if (!isWarehousePharmacy($pharmacyId)) {
                $transferHeader = MedicineTransfers::create([
                    'code' => $this->generateTransfersCode(),
                    'status' => 1,
                ]);
            }

            foreach ($receivingItems as $item) {
                $medicineId = $item->order_items->medicine_id;
                $medicine = $medicines->get($medicineId);

                if (!$medicine) {
                    throw new \Exception("Medicine ID {$medicineId} not found.");
                }

                $batchKey = "{$medicineId}|{$item->batch}|{$item->expired_date}";

                if (!isset($existingBatches[$batchKey])) {
                    $batch = Batches::create([
                        'medicine_id' => $medicineId,
                        'name' => $item->batch,
                        'expired_date' => $item->expired_date,
                        'status' => 0,
                        'pharmacy_id' => $pharmacyId,
                        'stock' => 0,
                    ]);
                    $existingBatches[$batchKey] = $batch;
                }

                $batch = $existingBatches[$batchKey];
                $qtyBefore = $medicine->stock;

                $isPack = ($item->order_items->pack == 1);
                $content = $isPack ? (int) ($medicine->content ?? 1) : 1;
                $actualStockQty = $item->qty_received * $content;

                $medicine->stock += $actualStockQty;

                $medicineIncrements[$medicineId] = ($medicineIncrements[$medicineId] ?? 0) + $actualStockQty;
                $receivingItemUpdates[$item->id] = $batch->id;

                if (!isWarehousePharmacy($pharmacyId)) {
                    MedicineTransferItems::create([
                        'medicine_transfer_id' => $transferHeader->id,
                        'batches_id' => $batch->id,
                        'receiving_items_id' => $item->id,
                        'etalases_id' => 99,
                        'qty' => $actualStockQty,
                        'status' => 1,
                    ]);
                } else {
                    Batches::where('id', $batch->id)->increment('stock', $actualStockQty);
                }

                $itemsLogInserts[] = [
                    'transaction_code' => $receiving->code,
                    'code' => $this->generateItemsLogCode(),
                    'type' => 'OR',
                    'medicine_id' => $medicineId,
                    'qty' => $actualStockQty,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $medicine->stock,
                    'total' => $item->order_items->total ?? 0,
                    'date' => $now,
                    'status' => 2,
                    'batches_id' => $batch->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach ($medicineIncrements as $medicineId => $qty) {
                Medicines::where('id', $medicineId)->increment('stock', $qty);
            }

            collect($receivingItemUpdates)
                ->chunk(500)
                ->each(function ($chunk) {
                    foreach ($chunk as $itemId => $batchId) {
                        ReceivingItems::where('id', $itemId)->update(['batches_id' => $batchId]);
                    }
                });

            collect($itemsLogInserts)
                ->chunk(500)
                ->each(fn($chunk) => ItemsLog::insert($chunk->values()->all()));

            // Update status only for the order items that are actually received
            $receivedOrderItemIds = $receivingItems->pluck('order_items.id')->unique()->filter()->values()->all();
            if (!empty($receivedOrderItemIds)) {
                OrderItems::whereIn('id', $receivedOrderItemIds)->update(['status' => 2]);
            }

            // Check if all order items for this order have been received (status 2)
            $totalOrderItems = OrderItems::where('order_id', $order->id)->count();
            $receivedOrderItems = OrderItems::where('order_id', $order->id)->where('status', 2)->count();

            if ($totalOrderItems > 0 && $totalOrderItems === $receivedOrderItems) {
                $order->update(['status' => 2]);
            }

            $receiving->update(['status' => 2]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item Tersimpan',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Error saving receiving', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function completeOrder(Request $request)
    {
        $request->validate([
            'receivingid' => 'required',
            'orderid' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $receiving = Receiving::with(['receiving_details.receiving_items'])
                ->findOrFail($request->receivingid);

            if ($receiving->status == 3) {
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Pesanan sudah diselesaikan sebelumnya',
                ]);
            }

            $order = Order::findOrFail($request->orderid);

            $receivingItems = $receiving
                ->receiving_details
                ->pluck('receiving_items')
                ->flatten();

            if ($receivingItems->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada item untuk diselesaikan',
                ], 422);
            }

            if ($receivingItems->whereNull('batches_id')->isNotEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Masih ada item yang belum disimpan. Klik "Simpan" terlebih dahulu.',
                ], 422);
            }

            $order->update(['status' => 3]);
            $receiving->update(['status' => 3]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pesanan Berhasil Diselesaikan',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Error completing receiving', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyelesaikan receiving',
            ], 500);
        }
    }

    private function generateSPCode($pharmacyId)
    {
        $code = 'R';
        $year = now()->format('y');
        $month = now()->format('m');
        $prefix = "SP-O-{$year}{$month}/";

        $lastItem = \App\Models\ReceivingDetails::where('sp_code', 'like', $prefix . '%')
            ->whereHas('receiving', function ($query) use ($pharmacyId) {
                $query->where('pharmacy_id', $pharmacyId);
            })
            ->orderBy('sp_code', 'desc')
            ->first();

        if ($lastItem && $lastItem->sp_code) {
            $parts = explode('/', $lastItem->sp_code);
            $lastPart = end($parts);
            $serialPart = explode('-', $lastPart)[0];
            $lastSerial = intval($serialPart);
            $nextSerial = $lastSerial + 1;
        } else {
            $nextSerial = 1;
        }

        return $prefix . str_pad($nextSerial, 6, '0', STR_PAD_LEFT) . '-' . $pharmacyId;
    }
}
