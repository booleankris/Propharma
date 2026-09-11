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

            $hasSavedBatches = \App\Models\ReceivingItems::whereHas('receiving_details', fn($q) => $q->where('receiving_id', $transaction->id))
                ->whereNotNull('batches_id')
                ->exists();

            return view('orders.receiving', compact('order_code', 'transaction', 'now', 'order_exist', 'receiving_id', 'receiving_code', 'order_id', 'd_price', 'd_ppn', 'd_total', 'datenow', 'creditorOption', 'allFakturs', 'hasSavedBatches'));
        } else {
            $receiving_code = $this->generateReceivingCode();

            try {
                DB::beginTransaction();

                $transaction = Receiving::create([
                    'pharmacy_id' => getActivePharmacyId(),
                    'code' => $receiving_code,
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

    public function generateItemsLogCode()
    {
        $now = Carbon::now();
        $year = $now->format('y');
        $month = $now->format('m');
        $prefix = "{$year}{$month}LOG-";

        $lastCode = ItemsLog::where('code', 'like', "{$prefix}%")
            ->orderBy('code', 'desc')
            ->value('code');

        $nextNumber = $lastCode ? ((int) substr($lastCode, -4) + 1) : 1;
        $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        $code = $prefix . $serial;

        while (ItemsLog::where('code', $code)->exists()) {
            $nextNumber++;
            $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $code = $prefix . $serial;
        }

        return $code;
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
                    $mq
                        ->where('name', 'like', '%' . $searchMedicine . '%')
                        ->orWhere('code', 'like', '%' . $searchMedicine . '%');
                });
            })
            ->get();

        $rows = collect();

        foreach ($orderItems as $orderItem) {
            $qtyReceived = $orderItem->qty_received_total ?? 0;
            $qtyRemaining = max(0, $orderItem->quantity - $qtyReceived);
            if ((float) $orderItem->quantity <= 0 && $orderItem->original_quantity !== null && $orderItem->receivingItems->isEmpty()) {
                continue;
            }
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
            ->setPaper([0, 0, 396, 612])
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'isFontSubsettingEnabled' => true,
                'dpi' => 96,
                'defaultFont' => 'sans-serif'
            ]);

        $pdfContent = $pdf->output();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

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
            ->setPaper([0, 0, 396, 612])
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'isFontSubsettingEnabled' => true,
                'dpi' => 96,
                'defaultFont' => 'sans-serif'
            ]);

        $pdfContent = $pdf->output();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

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
            ->setPaper([0, 0, 396, 612])
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'isFontSubsettingEnabled' => true,
                'dpi' => 96,
                'defaultFont' => 'sans-serif'
            ]);

        $pdfContent = $pdf->output();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

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
            ->setPaper([0, 0, 396, 612])
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'isFontSubsettingEnabled' => true,
                'dpi' => 96,
                'defaultFont' => 'sans-serif'
            ]);

        $pdfContent = $pdf->output();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

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
            ->withCount('order_items')
            ->withCount(['order_items as active_items_count' => function ($q) {
                $q->where('quantity', '>', 0);
            }])
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

                $isEmpty = (($row->order_items_count ?? 0) == 0) || (($row->active_items_count ?? 0) == 0);
                if ($isEmpty) {
                    $html .= ' <span style="font-size:10px" class="inline-flex items-center px-2 py-0.5 rounded font-nunito font-semibold bg-rose-50 text-rose-600 border border-rose-200">Kosong</span>';
                }

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
            // Action buttons — Split Button Group (Primary + ••• Dropdown)
            ->addColumn('action', function ($row) {
                $isEmpty = (($row->order_items_count ?? 0) == 0) || (($row->active_items_count ?? 0) == 0);

                // Helper untuk action item di dalam dropdown menu
                $menuItem = function (string $href, string $label, string $iconSvg, string $colorClass = 'text-slate-700 hover:bg-slate-50', bool $blank = false) {
                    $target = $blank ? ' target="_blank"' : '';
                    return '<a href="' . $href . '"' . $target . ' class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-semibold ' . $colorClass . ' transition-colors">'
                        . '<svg class="w-4 h-4 shrink-0 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">' . $iconSvg . '</svg>'
                        . '<span>' . $label . '</span>'
                        . '</a>';
                };

                $deleteMenuItem = '<button type="button" onclick="deleteEmptyOrder(' . $row->id . ", '" . e($row->code) . '\')" class="w-full flex items-center gap-2.5 px-3.5 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition-colors text-left">'
                    . '<svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>'
                    . '<span>Hapus BPBA Kosong</span>'
                    . '</button>';

                $compareItem = $menuItem(
                    route('orders.comparison', $row->id),
                    'Bandingkan',
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l4-4m-4 4l-4-4"/>'
                );

                // STATUS 0: PENDING (Blue Solid Split Button)
                if ($row->status == 0) {
                    $dropdownItems = ($isEmpty ? $deleteMenuItem : $menuItem(route('orders.create', ['order_id' => $row->id]), 'Buka Pesanan', '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>'));

                    return '<div class="btn-split-group btn-split-blue inline-flex items-stretch rounded-lg shadow-xs overflow-hidden">'
                        . '<a href="' . route('orders.create', ['order_id' => $row->id]) . '" class="btn-split-main inline-flex items-center gap-2 px-3.5 py-1.5 text-xs font-bold text-white transition-colors">'
                        . '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>'
                        . '<span>Lanjutkan</span>'
                        . '</a>'
                        . '<div class="relative action-dropdown-container inline-flex">'
                        . '<button type="button" class="btn-split-toggle btn-action-dropdown inline-flex items-center justify-center px-2.5 transition-colors" title="Aksi Lainnya">'
                        . '<svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>'
                        . '</button>'
                        . '<div class="action-dropdown-menu hidden fixed bg-white rounded-xl shadow-xl border border-slate-200/90 py-1.5 min-w-[175px] z-[99999]">'
                        . $dropdownItems
                        . '</div>'
                        . '</div>'
                        . '</div>';
                }

                // STATUS 1 & 2: DIPESAN (Emerald Solid Split Button - Sesuai Mockup Pengguna)
                if ($row->status == 1 || $row->status == 2) {
                    $dropdownItems = $compareItem . ($isEmpty ? '<div class="my-1 border-t border-slate-100"></div>' . $deleteMenuItem : '');

                    return '<div class="btn-split-group btn-split-emerald inline-flex items-stretch rounded-lg shadow-xs overflow-hidden">'
                        . '<a href="/receive/' . $row->id . '" class="btn-split-main inline-flex items-center gap-2 px-3.5 py-1.5 text-xs font-bold text-white transition-colors">'
                        . '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/></svg>'
                        . '<span>Terima</span>'
                        . '</a>'
                        . '<div class="relative action-dropdown-container inline-flex">'
                        . '<button type="button" class="btn-split-toggle btn-action-dropdown inline-flex items-center justify-center px-2.5 transition-colors" title="Aksi Lainnya">'
                        . '<svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>'
                        . '</button>'
                        . '<div class="action-dropdown-menu hidden fixed bg-white rounded-xl shadow-xl border border-slate-200/90 py-1.5 min-w-[175px] z-[99999]">'
                        . $dropdownItems
                        . '</div>'
                        . '</div>'
                        . '</div>';
                }

                // STATUS 3: DITERIMA (Crisp White/Slate Split Button - Sesuai Mockup Pengguna)
                $invoiceItem = $menuItem(
                    '/receiving/' . $row->id . '/printorders',
                    'Cetak Invoice',
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.25-2.142V8.25"/>',
                    'text-slate-700 hover:bg-slate-50',
                    true
                );

                $revisionItem = $menuItem(
                    '/orders/' . $row->id . '/revision',
                    'Revisi Faktur',
                    '<path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>'
                );

                $dropdownItems = $invoiceItem . $revisionItem . $compareItem . ($isEmpty ? '<div class="my-1 border-t border-slate-100"></div>' . $deleteMenuItem : '');

                return '<div class="btn-split-group btn-split-white inline-flex items-stretch rounded-lg shadow-xs overflow-hidden">'
                    . '<a href="/receiving/' . $row->id . '/printspbfinal" target="_blank" class="btn-split-main inline-flex items-center gap-2 px-3.5 py-1.5 text-xs font-bold text-slate-800 transition-colors">'
                    . '<svg class="w-4 h-4 shrink-0 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2"/><path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4"/><path d="M7 13m0 2a2 2 0 1 1 2 -2h6a2 2 0 1 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2z"/></svg>'
                    . '<span>Cetak SPB</span>'
                    . '</a>'
                    . '<div class="relative action-dropdown-container inline-flex">'
                    . '<button type="button" class="btn-split-toggle btn-action-dropdown inline-flex items-center justify-center px-2.5 transition-colors" title="Menu Aksi Lainnya">'
                    . '<svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>'
                    . '</button>'
                    . '<div class="action-dropdown-menu hidden fixed bg-white rounded-xl shadow-xl border border-slate-200/90 py-1.5 min-w-[175px] z-[99999]">'
                    . $dropdownItems
                    . '</div>'
                    . '</div>';
            })
            // Total & Total PPN — font-size 13px (sengaja sedikit lebih besar dari 12px karena ini
            // yang paling penting dilihat kasir), no-wrap biar angka gak patah baris
            ->addColumn('total', fn($row) => '<span class="text-[13px] font-semibold text-slate-700 whitespace-nowrap tabular-nums">Rp ' . number_format($row->order_items_sum_total ?? 0, 0, ',', '.') . '</span>')
            ->addColumn('total_ppn', fn($row) => '<span class="text-[13px] font-bold text-slate-900 whitespace-nowrap tabular-nums">Rp ' . number_format(floor(($row->order_items_sum_total ?? 0) * 1.11), 0, ',', '.') . '</span>')
            ->rawColumns(['code', 'status_order', 'action', 'total', 'total_ppn'])
            ->make(true);
    }

    public function generateReceivingCode()
    {
        $year = now()->format('y');
        $month = now()->format('m');
        $prefix = $year . $month . 'RE';

        $lastCode = Receiving::where('code', 'like', "{$prefix}%")
            ->orderBy('code', 'desc')
            ->value('code');

        $nextNumber = $lastCode ? ((int) substr($lastCode, -4) + 1) : 1;
        $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        $code = $prefix . $serial;

        while (Receiving::where('code', $code)->exists()) {
            $nextNumber++;
            $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $code = $prefix . $serial;
        }

        return $code;
    }

    public function generateReceivingDetailsCode($pharmacy_id)
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

        $nextNumber = $lastCode ? ((int) substr($lastCode, -4) + 1) : 1;
        $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        $code = $prefix . $serial;

        $existsInPharmacy = fn($c) => ReceivingDetails::whereHas('receiving', function ($q) use ($pharmacy_id) {
            $q->where('pharmacy_id', $pharmacy_id);
        })->where('receiving_details_code', $c)->exists();

        while ($existsInPharmacy($code)) {
            $nextNumber++;
            $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $code = $prefix . $serial;
        }

        return $code;
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
            $lockedOrderItem = OrderItems::whereKey($item->order_items_id)->lockForUpdate()->firstOrFail();
            if (($lockedOrderItem->original_quantity !== null || $lockedOrderItem->incomingMovement()->exists()) &&
                    (float) $request->qty_received + (float) $lockedOrderItem->receivingItems()->where('id', '!=', $id)->sum('qty_received') > (float) $lockedOrderItem->quantity) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Jumlah penerimaan melampaui kuantitas aktif setelah konsolidasi.'], 422);
            }
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
                    'message' => "Gagal mengurangi kuantiti: Stok saat ini tersisa {$medicine->stock}, tidak mencukupi untuk dikurangi sebesar " . abs($deltaActual) . '. Sebagian barang kemungkinan telah terjual di kasir.',
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

            $itemData = [
                'qty_received' => $newQty,
                'qty' => $newQty,
                'raw_price' => $request->raw_price,
                'discount' => $request->discount,
                'extra_discount' => $request->extra_discount ?? 0,
                'subtotal' => $request->total,
                'total' => $request->total,
                'batch' => $request->batch,
                'expired_date' => $request->expired_date,
            ];
            if ($request->filled('receiving_details_id')) {
                $oldDetailId = $item->receiving_details_id;
                $itemData['receiving_details_id'] = $request->receiving_details_id;
            }
            $item->update($itemData);

            if (!empty($oldDetailId) && $oldDetailId != $request->receiving_details_id) {
                if (ReceivingItems::where('receiving_details_id', $oldDetailId)->count() === 0) {
                    ReceivingDetails::where('id', $oldDetailId)->delete();
                }
            }

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

            $orderItem = $item->order_items;

            $item->delete();

            // Clean up the order item if it was created as a revision "susulan" item
            // (added from master) and no longer has any receiving items.
            if ($orderItem &&
                    ($orderItem->status == 1 || $orderItem->note === 'Item susulan/pengganti saat revisi faktur') &&
                    ReceivingItems::where('order_items_id', $orderItem->id)->count() === 0) {
                $orderItem->delete();
            }

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
            'order_items.receivingItems.batches',
            'order_items.receivingItems.locations',
            'order_items.receivingItems.etalases',
        ])->findOrFail($orderId);

        $rdIdsFromItems = ReceivingItems::whereIn('order_items_id', $order->order_items->pluck('id'))
            ->pluck('receiving_details_id')
            ->unique()
            ->filter();

        $orderSpCodes = $order->order_items->pluck('order_items_code')->filter();

        $allReceivingDetails = ReceivingDetails::where(function ($q) use ($rdIdsFromItems, $order, $orderSpCodes) {
            $q->whereIn('id', $rdIdsFromItems);
            if ($order->receiving_id) {
                $q->orWhere('receiving_id', $order->receiving_id);
            }
            if ($orderSpCodes->isNotEmpty()) {
                $q->orWhereIn('sp_code', $orderSpCodes);
            }
        })
            ->with([
                'receiving_items' => function ($q) use ($order) {
                    $q
                        ->whereIn('order_items_id', $order->order_items->pluck('id'))
                        ->with(['order_items.medicines', 'batches', 'locations', 'etalases']);
                },
                'creditor'
            ])
            ->orderBy('id', 'asc')
            ->get();

        $knownItemIds = $allReceivingDetails->flatMap->receiving_items->pluck('id');
        $orphanedItems = ReceivingItems::whereIn('order_items_id', $order->order_items->pluck('id'))
            ->whereNotIn('id', $knownItemIds)
            ->with(['order_items.medicines', 'batches', 'locations', 'etalases'])
            ->get();

        $orderItemsData = $order->order_items->map(function ($oi) {
            $receivedQty = $oi->receivingItems->whereNotNull('batches_id')->sum('qty_received');
            $remainingQty = max(0, (float) $oi->quantity - (float) $receivedQty);
            return [
                'id' => $oi->id,
                'medicine_id' => $oi->medicine_id,
                'medicine_name' => $oi->medicines->name ?? '-',
                'ordered_qty' => (float) $oi->quantity,
                'received_qty' => (float) $receivedQty,
                'remaining_qty' => (float) $remainingQty,
                'price' => (float) ($oi->price ?? 0),
                'raw_price' => (float) ($oi->medicines->raw_price ?? 0),
                'content' => (int) ($oi->medicines->content ?? 1),
                'discount' => (float) ($oi->discount ?? 0),
                'pack' => (bool) $oi->pack,
            ];
        });

        return view('orders.revision', compact('order', 'allReceivingDetails', 'orphanedItems', 'orderItemsData'));
    }

    public function searchMasterMedicine(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $search = preg_replace('/\s+/', ' ', $search);

        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $items = Medicines::query()
            ->select([
                'medicines.id',
                'medicines.code',
                'medicines.name',
                'medicines.raw_price',
                'medicines.content',
                'medicines.unit',
                'factories.name as factory_name',
            ])
            ->leftJoin('factories', 'factories.id', '=', 'medicines.factory_id')
            ->where(function ($q) use ($search) {
                $q
                    ->where('medicines.name', 'like', $search . '%')
                    ->orWhere('medicines.code', 'like', $search . '%');
            })
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $items->map(fn($m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'raw_price' => (float) $m->raw_price,
                'content' => (int) ($m->content ?? 1),
                'unit' => $m->unit,
                'factory_name' => $m->factory_name,
            ]),
        ]);
    }

    public function addRevisionItem(Request $request, $orderId)
    {
        $request->validate([
            'receiving_details_id' => 'required|exists:receiving_details,id',
            'batch' => 'required|string',
            'expired_date' => 'required|date',
            'qty_received' => 'required|numeric|min:0.01',
            'raw_price' => 'required',
            'pack' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $order = Order::with('order_items')->findOrFail($orderId);
            $detail = ReceivingDetails::findOrFail($request->receiving_details_id);

            if ($request->filled('order_items_id')) {
                $orderItem = OrderItems::with('medicines')->where('order_id', $orderId)->findOrFail($request->order_items_id);

                if ($request->has('pack')) {
                    $orderItem->update(['pack' => $request->boolean('pack') ? 1 : 0]);
                }
            } elseif ($request->filled('medicine_id')) {
                $medicine = Medicines::findOrFail($request->medicine_id);
                $firstItem = $order->order_items->first();
                $orderItem = OrderItems::create([
                    'order_items_code' => $firstItem->order_items_code ?? ('SP-' . $order->id),
                    'order_id' => $order->id,
                    'medicine_id' => $medicine->id,
                    'creditor_code' => $detail->creditor_code ?? $firstItem?->creditor_code,
                    'pack' => $request->boolean('pack') ? 1 : 0,
                    'price' => (float) preg_replace('/[^\d.]/', '', (string) $request->raw_price),
                    'quantity' => (float) $request->qty_received,
                    'total' => (float) preg_replace('/[^\d.]/', '', (string) ($request->total ?? 0)),
                    'note' => 'Item susulan/pengganti saat revisi faktur',
                    'status' => 1,
                ]);
            } else {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Pilih obat terlebih dahulu.'], 422);
            }

            $medicineId = $orderItem->medicine_id;
            $medicine = Medicines::findOrFail($medicineId);
            $pharmacyId = getActivePharmacyId();

            $isPack = ($orderItem->pack == 1);
            $content = $isPack ? (int) ($medicine->content ?? 1) : 1;
            $qtyReceived = (float) $request->qty_received;
            $actualQty = $qtyReceived * $content;

            $rawPrice = (float) preg_replace('/[^\d.]/', '', (string) $request->raw_price);
            $discount = (float) preg_replace('/[^\d.]/', '', (string) ($request->discount ?? 0));
            $extraDiscount = (float) preg_replace('/[^\d.]/', '', (string) ($request->extra_discount ?? 0));

            $gross = $qtyReceived * $rawPrice;
            $nomDiscount = ($discount <= 100 && $discount > 0) ? ($gross * $discount / 100) : $discount;
            $nomExtraDiscount = ($extraDiscount <= 100 && $extraDiscount > 0) ? ($gross * $extraDiscount / 100) : $extraDiscount;
            $total = max(0, $gross - $nomDiscount - $nomExtraDiscount);

            $batch = Batches::firstOrCreate(
                [
                    'medicine_id' => $medicineId,
                    'name' => $request->batch,
                    'expired_date' => $request->expired_date,
                    'pharmacy_id' => $pharmacyId,
                ],
                [
                    'status' => 0,
                    'stock' => 0,
                ]
            );

            $qtyBefore = $medicine->stock;
            $medicine->increment('stock', $actualQty);

            if (!isWarehousePharmacy($pharmacyId)) {
                $transferHeader = MedicineTransfers::create([
                    'code' => $this->generateTransfersCode(),
                    'status' => 1,
                    'user_id' => auth()->user()->id ?? 1,
                ]);
            } else {
                Batches::where('id', $batch->id)->increment('stock', $actualQty);
            }

            $receivingItem = ReceivingItems::create([
                'receiving_details_id' => $detail->id,
                'order_items_id' => $orderItem->id,
                'qty_received' => $qtyReceived,
                'qty' => $qtyReceived,
                'raw_price' => $rawPrice,
                'discount' => $discount,
                'extra_discount' => $extraDiscount,
                'expired_date' => $request->expired_date,
                'batch' => $request->batch,
                'total' => $total,
                'status' => $request->status ?? 1,
                'batches_id' => $batch->id,
            ]);

            if (!isWarehousePharmacy($pharmacyId) && isset($transferHeader)) {
                MedicineTransferItems::create([
                    'medicine_transfer_id' => $transferHeader->id,
                    'batches_id' => $batch->id,
                    'receiving_items_id' => $receivingItem->id,
                    'etalases_id' => 99,
                    'qty' => $actualQty,
                    'status' => 1,
                ]);
            }

            ItemsLog::create([
                'transaction_code' => 'REV-ADD-' . $receivingItem->id,
                'code' => $this->generateItemsLogCode(),
                'type' => 'RV',
                'medicine_id' => $medicineId,
                'qty' => $actualQty,
                'qty_before' => $qtyBefore,
                'qty_after' => $medicine->stock,
                'total' => $total,
                'date' => Carbon::now()->format('Y-m-d H:i:s'),
                'status' => 8,
                'batches_id' => $batch->id,
                'user_id' => auth()->user()->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Obat {$medicine->name} berhasil ditambahkan ke Nomor Terima {$detail->receiving_details_code}.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Revision add item error', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan item: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function mergeRevisionDetails(Request $request, $orderId)
    {
        $request->validate([
            'source_details_id' => 'required|exists:receiving_details,id',
            'target_details_id' => 'required|exists:receiving_details,id|different:source_details_id',
        ]);

        try {
            DB::beginTransaction();

            $source = ReceivingDetails::findOrFail($request->source_details_id);
            $target = ReceivingDetails::findOrFail($request->target_details_id);

            $sourceCode = $source->receiving_details_code;
            $targetCode = $target->receiving_details_code;

            $count = ReceivingItems::where('receiving_details_id', $source->id)->count();

            ReceivingItems::where('receiving_details_id', $source->id)
                ->update(['receiving_details_id' => $target->id]);

            $source->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil memindahkan {$count} item dari {$sourceCode} ke {$targetCode}.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Merge revision details error', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menggabungkan nomor terima: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function moveRevisionItem(Request $request, $orderId)
    {
        $request->validate([
            'receiving_item_id' => 'nullable|exists:receiving_items,id',
            'receiving_item_ids' => 'nullable|array',
            'receiving_item_ids.*' => 'exists:receiving_items,id',
            'target_details_id' => 'required|exists:receiving_details,id',
        ]);

        $itemIds = $request->input('receiving_item_ids', []);
        if (empty($itemIds) && $request->filled('receiving_item_id')) {
            $itemIds = [$request->input('receiving_item_id')];
        }

        if (empty($itemIds)) {
            return response()->json(['success' => false, 'message' => 'Pilih minimal satu item obat untuk dipindahkan.'], 422);
        }

        try {
            DB::beginTransaction();

            $targetDetail = ReceivingDetails::findOrFail($request->target_details_id);
            $items = ReceivingItems::with('order_items.medicines', 'receiving_details')
                ->whereIn('id', $itemIds)
                ->get();

            $movedCount = 0;
            $oldDetailIds = [];

            foreach ($items as $item) {
                if ($item->receiving_details_id == $targetDetail->id) {
                    continue;
                }

                if ($item->receiving_details_id) {
                    $oldDetailIds[] = $item->receiving_details_id;
                }

                $item->receiving_details_id = $targetDetail->id;
                $item->save();
                $movedCount++;
            }

            // Cleanup any source receiving_details that have 0 items left
            $oldDetailIds = array_unique($oldDetailIds);
            foreach ($oldDetailIds as $oldId) {
                if ($oldId != $targetDetail->id && ReceivingItems::where('receiving_details_id', $oldId)->count() === 0) {
                    ReceivingDetails::where('id', $oldId)->delete();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$movedCount} item berhasil dipindahkan / digabung ke Nomor Terima {$targetDetail->receiving_details_code}.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Move revision item error', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memindahkan item: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteEmptyRevisionDetails($orderId, $detailsId)
    {
        try {
            DB::beginTransaction();

            $detail = ReceivingDetails::findOrFail($detailsId);
            $itemCount = ReceivingItems::where('receiving_details_id', $detail->id)->count();

            if ($itemCount > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor terima ini masih memiliki item obat. Hapus atau pindahkan obatnya terlebih dahulu.',
                ], 422);
            }

            $code = $detail->receiving_details_code;
            $detail->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Nomor terima {$code} berhasil dihapus.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus nomor terima: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function orderComparison($orderId)
    {
        $order = Order::with([
            'pharmacy',
            'user',
            'order_items' => function ($q) {
                $q->orderBy('creditor_code')->orderBy('id');
            },
            'order_items.medicines',
            'order_items.creditors',
            'order_items.receivingItems.receiving_details',
            'order_items.receivingItems.batches',
        ])->findOrFail($orderId);

        $orderItems = $order->order_items;
        $totalItems = $orderItems->count();

        $fullCount = 0;
        $partialCount = 0;
        $zeroCount = 0;

        $totalOrderedQty = 0;
        $totalReceivedQty = 0;
        $totalOrderedValue = 0;
        $totalReceivedValue = 0;

        $processedItems = $orderItems->map(function ($item) use (
            &$fullCount,
            &$partialCount,
            &$zeroCount,
            &$totalOrderedQty,
            &$totalReceivedQty,
            &$totalOrderedValue,
            &$totalReceivedValue
        ) {
            $qtyOrdered = floatval($item->quantity ?? 0);
            // Hanya hitung item yang sudah fix masuk/disimpan ke stok (batches_id tidak null)
            $savedReceivingItems = $item->receivingItems->whereNotNull('batches_id')->values();

            $qtyReceived = floatval($savedReceivingItems->sum('qty_received') ?? 0);
            $rawPrice = floatval($item->price ?? 0);

            $orderedSubtotal = $qtyOrdered * $rawPrice;
            $receivedSubtotal = $savedReceivingItems->sum(function ($ri) use ($rawPrice) {
                $p = floatval($ri->raw_price ?? $rawPrice);
                $q = floatval($ri->qty_received ?? 0);
                return $p * $q;
            });

            $qtyDiff = $qtyOrdered - $qtyReceived;
            $valueDiff = $orderedSubtotal - $receivedSubtotal;

            if ($qtyReceived >= $qtyOrdered && $qtyOrdered > 0) {
                $statusKey = 'full';
                $statusLabel = 'Lengkap';
                $fullCount++;
            } elseif ($qtyReceived > 0 && $qtyReceived < $qtyOrdered) {
                $statusKey = 'partial';
                $statusLabel = 'Sebagian';
                $partialCount++;
            } else {
                $statusKey = 'zero';
                $statusLabel = 'Tidak Datang';
                $zeroCount++;
            }

            $totalOrderedQty += $qtyOrdered;
            $totalReceivedQty += $qtyReceived;
            $totalOrderedValue += $orderedSubtotal;
            $totalReceivedValue += $receivedSubtotal;

            $fakturList = $savedReceivingItems->map(function ($ri) {
                return [
                    'invoice_number' => $ri->receiving_details->invoice_number ?? '-',
                    'batch' => $ri->batch ?? '-',
                    'expired_date' => $ri->expired_date ? date('d/m/Y', strtotime($ri->expired_date)) : '-',
                    'qty' => floatval($ri->qty_received ?? 0),
                    'price' => floatval($ri->raw_price ?? 0),
                    'is_saved' => true,
                ];
            })->values();

            return (object) [
                'id' => $item->id,
                'sp_code' => $item->order_items_code ?? '-',
                'creditor_code' => $item->creditor_code ?? '-',
                'creditor_name' => $item->creditors->name ?? ($item->creditor_code ?? '-'),
                'medicine_code' => $item->medicines->code ?? '-',
                'medicine_name' => $item->medicines->name ?? '-',
                'unit' => $item->medicines->unit ?? 'Satuan',
                'pack' => (bool) $item->pack,
                'content' => $item->medicines->content ?? 1,
                'qty_ordered' => $qtyOrdered,
                'qty_received' => $qtyReceived,
                'qty_diff' => $qtyDiff,
                'raw_price' => $rawPrice,
                'ordered_subtotal' => $orderedSubtotal,
                'received_subtotal' => $receivedSubtotal,
                'value_diff' => $valueDiff,
                'status_key' => $statusKey,
                'status_label' => $statusLabel,
                'fakturs' => $fakturList,
            ];
        });

        $creditors = $processedItems->pluck('creditor_name', 'creditor_code')->unique();

        return view('orders.comparison', compact(
            'order',
            'processedItems',
            'totalItems',
            'fullCount',
            'partialCount',
            'zeroCount',
            'totalOrderedQty',
            'totalReceivedQty',
            'totalOrderedValue',
            'totalReceivedValue',
            'creditors'
        ));
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

        $transaction = null;
        if ($getOrder->receiving_id) {
            $transaction = Receiving::with('receiving_details')
                ->where('status', 0)
                ->where('pharmacy_id', $orderPharmacyId)
                ->find($getOrder->receiving_id);
        }

        if (!$transaction) {
            $transaction = Receiving::with('receiving_details')
                ->where('status', 0)
                ->where('pharmacy_id', $orderPharmacyId)
                ->whereHas('receiving_details.receiving_items.order_items', function ($q) use ($id) {
                    $q->where('order_id', $id);
                })
                ->first();
        }

        if ($getOrder->status == 3) {
            return redirect()->route('receiving.index')->with('success', 'Pesanan ini sudah selesai diterima.');
        }

        $orderItemCount = OrderItems::where('order_id', $id)->count();
        if ($orderItemCount === 0) {
            return redirect()
                ->route('orders.create', ['order_id' => $getOrder->id])
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

        $allFakturs = \App\Models\ReceivingDetails::whereHas('receiving_items.order_items', function ($q) use ($id) {
            $q->where('order_id', $id);
        })
            ->when($transaction, function ($q) use ($transaction) {
                $q->orWhere('receiving_id', $transaction->id);
            })
            ->get()
            ->unique('id')
            ->values();

        $pId = $orderPharmacyId ?? getPurchasingPharmacyId();
        foreach ($allFakturs as $faktur) {
            if (empty($faktur->receiving_details_code)) {
                $faktur->receiving_details_code = $this->generateReceivingDetailsCode($pId);
                $faktur->save();
            }
        }

        if (!$transaction) {
            $receiving_code = $this->generateReceivingCode();

            $transaction = Receiving::create([
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

        $hasSavedBatches = \App\Models\ReceivingItems::whereHas('order_items', fn($q) => $q->where('order_id', $id))
            ->whereNotNull('batches_id')
            ->exists();

        return view('orders.receiving', compact('order_id', 'd_price', 'd_ppn', 'd_total', 'order_code', 'creditorOption', 'receiving_code', 'transaction', 'now', 'datenow', 'receiving_id', 'allFakturs', 'hasSavedBatches'));
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
            $orderItem = OrderItems::whereKey($request->order_items_id)->lockForUpdate()->firstOrFail();
            $sourceOrder = Order::findOrFail($orderItem->order_id);
            $otherQty = $orderItem
                ->receivingItems()
                ->when($request->filled('receiving_items_id'), fn($q) => $q->where('id', '!=', $request->receiving_items_id))
                ->sum('qty_received');
            $protectedItem = $orderItem->original_quantity !== null || $orderItem->incomingMovement()->exists();
            $wrongEdit = $request->filled('receiving_items_id') && !$orderItem
                ->receivingItems()
                ->whereKey($request->receiving_items_id)
                ->whereNull('batches_id')
                ->whereHas('receiving_details', fn($q) => $q->where('receiving_id', $receiving->id))
                ->exists();
            if ((int) $sourceOrder->pharmacy_id !== getPurchasingPharmacyId() ||
                (int) $sourceOrder->receiving_id !== (int) $receiving->id ||
                (int) $sourceOrder->status === 3 ||
                (string) $orderItem->creditor_code !== (string) $request->creditor_code ||
                $wrongEdit ||
                ($protectedItem && ((float) $request->qty_received + (float) $otherQty > (float) $orderItem->quantity ||
                    ($request->has('pack') && (bool) $request->pack !== (bool) $orderItem->pack)))) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'BPBA/PBF tidak sesuai atau jumlah/satuan melampaui pesanan konsolidasi. Muat ulang penerimaan.'], 422);
            }
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

            if (empty($details->receiving_details_code)) {
                $pId = $receiving->pharmacy_id ?? getPurchasingPharmacyId();
                $details->receiving_details_code = $this->generateReceivingDetailsCode($pId);
                $details->save();
            }

            return response()->json([
                'success' => true,
                'receiving' => $receiving,
                'receiving_details_code' => $details->receiving_details_code,
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
        $order = Order::with('pharmacy')->find($id);
        $receiving = null;

        if ($order) {
            $orderId = $order->id;
            if ($order->receiving_id) {
                $receiving = Receiving::with('pharmacy')->find($order->receiving_id);
            }
        } else {
            $receiving = Receiving::with('pharmacy')->find($id);
            if ($receiving) {
                $order = Order::where('receiving_id', $receiving->id)->first();
                if (!$order) {
                    $order = Order::whereHas('order_items.receivingItems.receiving_details', function ($q) use ($receiving) {
                        $q->where('receiving_id', $receiving->id);
                    })->first();
                }
            }
            $orderId = $order ? $order->id : null;
        }

        if (!$receiving && !$order) {
            abort(404, 'Data penerimaan tidak ditemukan.');
        }

        if (!$receiving && $order) {
            $receiving = new Receiving([
                'pharmacy_id' => $order->pharmacy_id,
                'code' => $order->code,
            ]);
            $receiving->setRelation('pharmacy', $order->pharmacy);
        }

        if ($orderId) {
            // Strictly get receiving details and items that belong to THIS ORDER only
            $allDetails = ReceivingDetails::whereHas('receiving_items.order_items', function ($sub) use ($orderId) {
                $sub->where('order_id', $orderId);
            })
                ->with([
                    'receiving_items' => function ($q) use ($orderId) {
                        $q->whereHas('order_items', function ($sub) use ($orderId) {
                            $sub->where('order_id', $orderId);
                        })->whereNotNull('batches_id');
                    },
                    'receiving_items.order_items.medicines',
                    'creditor'
                ])
                ->get()
                ->filter(fn($d) => $d->receiving_items->isNotEmpty())
                ->unique('id')
                ->values();

            $receiving->setRelation('receiving_details', $allDetails);
        } else {
            $allDetails = $receiving
                ->receiving_details()
                ->with([
                    'receiving_items.order_items.medicines',
                    'creditor'
                ])
                ->get();
            $receiving->setRelation('receiving_details', $allDetails);
        }

        if ($receiving->receiving_details->isEmpty() || $receiving->receiving_details->flatMap->receiving_items->isEmpty()) {
            return redirect()->back()->with('warning', 'Belum ada faktur atau barang yang tersimpan dalam draft penerimaan ini.');
        }

        $pId = $receiving->pharmacy_id ?? getPurchasingPharmacyId();
        foreach ($receiving->receiving_details as $detail) {
            if (empty($detail->receiving_details_code)) {
                $detail->receiving_details_code = $this->generateReceivingDetailsCode($pId);
                $detail->save();
            }
        }

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

    public function generateTransfersCode()
    {
        $now = Carbon::now();
        $year = $now->format('y');
        $month = $now->format('m');
        $prefix = "{$year}{$month}MUT";

        $lastCode = MedicineTransfers::where('code', 'like', "{$prefix}%")
            ->orderBy('code', 'desc')
            ->value('code');

        $nextNumber = $lastCode ? ((int) substr($lastCode, -4) + 1) : 1;
        $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        $code = $prefix . $serial;

        while (MedicineTransfers::where('code', $code)->exists()) {
            $nextNumber++;
            $serial = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $code = $prefix . $serial;
        }

        return $code;
    }

    public function saveOrder(Request $request)
    {
        $request->validate([
            'receivingid' => 'required',
            'orderid' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $order = Order::findOrFail($request->orderid);

            if ($order->status == 3) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Pesanan ini sudah diselesaikan dan tidak bisa diubah.',
                ], 422);
            }

            $receiving = Receiving::with(['receiving_details.receiving_items.order_items'])
                ->find($request->receivingid);

            // Generate Nomor Terima (NT) and SP Code for any ReceivingDetails on this order that doesn't have one yet
            $allDetails = ReceivingDetails::whereHas('receiving_items.order_items', fn($q) => $q->where('order_id', $order->id))
                ->get();

            foreach ($allDetails as $details) {
                $needsSave = false;
                $pId = $order->pharmacy_id ?? getPurchasingPharmacyId();
                if (empty($details->receiving_details_code)) {
                    $details->receiving_details_code = $this->generateReceivingDetailsCode($pId);
                    $needsSave = true;
                }
                if (empty($details->sp_code)) {
                    $firstItem = $details->receiving_items->first();
                    $details->sp_code = $firstItem && $firstItem->order_items ? $firstItem->order_items->order_items_code : $this->generateSPCode($pId);
                    $needsSave = true;
                }
                if ($needsSave) {
                    $details->save();
                }
            }

            // Find all uncommitted receiving items for this order
            $receivingItems = ReceivingItems::whereNull('batches_id')
                ->whereHas('order_items', fn($q) => $q->where('order_id', $order->id))
                ->with(['order_items.medicines', 'receiving_details'])
                ->get();

            if ($receivingItems->isEmpty()) {
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Seluruh item faktur telah tersimpan ke stok.',
                ]);
            }

            $now = Carbon::now()->format('Y-m-d');
            $pharmacyId = $order->pharmacy_id ?? getPurchasingPharmacyId();

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

            $baseLogCode = $this->generateItemsLogCode();
            $logPrefix = substr($baseLogCode, 0, -4);
            $currentLogNum = (int) substr($baseLogCode, -4);

            $recCode = $receiving ? $receiving->code : ($order->code ?? 'REC');

            foreach ($receivingItems as $index => $item) {
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
                    'transaction_code' => $recCode,
                    'code' => $logPrefix . str_pad($currentLogNum + $index, 4, '0', STR_PAD_LEFT),
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
                ->each(function ($chunk) {
                    ItemsLog::insert($chunk->toArray());
                });

            // Update order items status to received (2) for all items in this save
            $savedOrderItemIds = $receivingItems->pluck('order_items_id')->filter()->unique();
            if ($savedOrderItemIds->isNotEmpty()) {
                OrderItems::whereIn('id', $savedOrderItemIds)->update(['status' => 2]);
            }

            // Update receiving status to partially received (2) if still 0
            if ($receiving && $receiving->status == 0) {
                $receiving->update(['status' => 2]);
            }
            if ($order && $order->status == 1) {
                $order->update(['status' => 2]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Faktur berhasil disimpan ke stok!',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Error saving receiving order', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan faktur: ' . $e->getMessage(),
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

            $order = Order::with(['order_items.receivingItems'])->findOrFail($request->orderid);

            if ($order->status == 3) {
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Pesanan sudah diselesaikan sebelumnya',
                ]);
            }

            $allOrderReceivingItems = $order->order_items->flatMap->receivingItems;

            if ($allOrderReceivingItems->whereNull('batches_id')->isNotEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Masih ada item faktur yang belum disimpan ke stok. Silakan klik tombol "Simpan Faktur" terlebih dahulu.',
                ], 422);
            }

            // Lock and complete the Order
            $order->update(['status' => 3]);
            $receivedItemIds = $order->order_items->filter(fn($oi) => $oi->receivingItems->whereNotNull('batches_id')->isNotEmpty())->pluck('id');
            if ($receivedItemIds->isNotEmpty()) {
                OrderItems::whereIn('id', $receivedItemIds)->update(['status' => 2]);
            }

            // Lock and complete all associated Receiving headers for this order
            if ($order->receiving_id) {
                Receiving::where('id', $order->receiving_id)->update(['status' => 3]);
            }
            if ($request->receivingid) {
                Receiving::where('id', $request->receivingid)->update(['status' => 3]);
            }
            Receiving::whereHas('receiving_details.receiving_items.order_items', function ($q) use ($order) {
                $q->where('order_id', $order->id);
            })->update(['status' => 3]);

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
                'message' => 'Gagal menyelesaikan pesanan: ' . $e->getMessage(),
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
