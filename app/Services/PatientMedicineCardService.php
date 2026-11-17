<?php

namespace App\Services;

use App\Models\MedicineCart;
use App\Models\Retur;
use App\Support\LiphTransactionScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PatientMedicineCardService
{
    /**
     * Build stats, per-(patient, medicine) frequency summary, and chronological card timeline.
     */
    public function build(array $filters = []): array
    {
        $startDate = !empty($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : now()->startOfDay();
        $endDate = !empty($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : now()->endOfDay();

        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        $pharmacyId = $filters['pharmacy_id'] ?? null;
        $patientFilter = trim((string) ($filters['patient_filter'] ?? ''));
        $medicineFilter = trim((string) ($filters['medicine_filter'] ?? ''));

        $purchases = $this->queryPurchases($startDate, $endDate, $pharmacyId, $patientFilter, $medicineFilter);
        $returns = $this->queryReturns($startDate, $endDate, $pharmacyId, $patientFilter, $medicineFilter);

        $events = [];

        foreach ($purchases as $cart) {
            $trx = $cart->transactions;
            $patient = $trx?->patients;
            $medicine = $cart->medicine;
            if (!$trx || !$patient || !$medicine) {
                continue;
            }

            $timestamp = $trx->created_at ?? $cart->created_at;
            $qty = max(0.0, $this->toFloat($cart->quantity));
            $unitPrice = $this->toFloat($cart->item_price);
            $subtotal = $this->toFloat($cart->final_price ?? $cart->total_price ?? ($unitPrice * $qty));
            if ($unitPrice <= 0 && $qty > 0 && $subtotal > 0) {
                $unitPrice = $subtotal / $qty;
            }

            $events[] = [
                'sort_ts' => $timestamp ? $timestamp->timestamp : 0,
                'sort_type' => 0,
                'sort_id' => (int) $cart->id,
                'event_type' => 'BELI',
                'transaction_id' => (int) $trx->id,
                'transaction_code' => $trx->transaction_code ?: '-',
                'reference_code' => null,
                'transaction_type' => $trx->transaction_type ?: '-',
                'payment_method' => $trx->payment_method ?: '-',
                'date' => $timestamp ? $timestamp->format('d/m/Y') : '-',
                'time' => $timestamp ? $timestamp->format('H:i:s') : '-',
                'iso_datetime' => $timestamp ? $timestamp->format('Y-m-d H:i:s') : '',
                'patient_id' => (int) $patient->id,
                'patient_code' => $patient->code ?: '-',
                'patient_name' => $patient->name ?: '-',
                'patient_phone' => $patient->phone ?: '-',
                'medicine_id' => (int) $medicine->id,
                'medicine_code' => $medicine->code ?: '-',
                'medicine_name' => $medicine->name ?: '-',
                'medicine_unit' => $medicine->unit ?: '-',
                'qty' => $qty,
                'signed_qty' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'signed_subtotal' => $subtotal,
                'operator' => $cart->user?->name ?? ($trx->user?->name ?? '-'),
                'pharmacy_name' => $trx->pharmacy?->name ?? '-',
            ];
        }

        foreach ($returns as $ret) {
            $trx = $ret->transaction;
            $patient = $trx?->patients;
            $medicine = $ret->medicine;
            if (!$trx || !$patient || !$medicine) {
                continue;
            }

            $timestamp = $ret->created_at ?? $trx->created_at;
            $qty = abs($this->toFloat($ret->qty_retur));
            $subtotal = abs($this->toFloat($ret->total_retur));
            $unitPrice = $qty > 0 ? ($subtotal / $qty) : 0.0;

            $events[] = [
                'sort_ts' => $timestamp ? $timestamp->timestamp : 0,
                'sort_type' => 1,
                'sort_id' => (int) $ret->id,
                'event_type' => 'RETUR',
                'transaction_id' => (int) $trx->id,
                'retur_id' => (int) $ret->id,
                'transaction_code' => $ret->code ?: ('RT-' . $ret->id),
                'reference_code' => $trx->transaction_code ?: '-',
                'transaction_type' => 'RETUR',
                'payment_method' => $trx->payment_method ?: '-',
                'date' => $timestamp ? $timestamp->format('d/m/Y') : '-',
                'time' => $timestamp ? $timestamp->format('H:i:s') : '-',
                'iso_datetime' => $timestamp ? $timestamp->format('Y-m-d H:i:s') : '',
                'patient_id' => (int) $patient->id,
                'patient_code' => $patient->code ?: '-',
                'patient_name' => $patient->name ?: '-',
                'patient_phone' => $patient->phone ?: '-',
                'medicine_id' => (int) $medicine->id,
                'medicine_code' => $medicine->code ?: '-',
                'medicine_name' => $medicine->name ?: '-',
                'medicine_unit' => $medicine->unit ?: '-',
                'qty' => $qty,
                'signed_qty' => -$qty,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'signed_subtotal' => -$subtotal,
                'operator' => $trx->user?->name ?? '-',
                'pharmacy_name' => $trx->pharmacy?->name ?? '-',
            ];
        }

        // Sort ASC chronologically to compute frequency sequence and running patient-medicine balance.
        usort($events, static function (array $a, array $b): int {
            return [$a['sort_ts'], $a['sort_type'], $a['sort_id']]
                <=> [$b['sort_ts'], $b['sort_type'], $b['sort_id']];
        });

        $summaryMap = [];
        $runningQtyMap = [];
        $seenPurchaseTrx = [];
        $seenReturnIds = [];
        $uniquePatients = [];
        $uniqueTransactions = [];

        $totalQtyBeli = 0.0;
        $totalQtyRetur = 0.0;
        $totalNominalBeli = 0.0;
        $totalNominalRetur = 0.0;
        $totalFreqBeli = 0;

        foreach ($events as &$event) {
            $pairKey = $event['patient_id'] . ':' . $event['medicine_id'];
            $uniquePatients[$event['patient_id']] = true;

            if (!isset($summaryMap[$pairKey])) {
                $summaryMap[$pairKey] = [
                    'patient_id' => $event['patient_id'],
                    'patient_code' => $event['patient_code'],
                    'patient_name' => $event['patient_name'],
                    'patient_phone' => $event['patient_phone'],
                    'medicine_id' => $event['medicine_id'],
                    'medicine_code' => $event['medicine_code'],
                    'medicine_name' => $event['medicine_name'],
                    'medicine_unit' => $event['medicine_unit'],
                    'frekuensi_beli' => 0,
                    'frekuensi_retur' => 0,
                    'qty_beli' => 0.0,
                    'qty_retur' => 0.0,
                    'qty_net' => 0.0,
                    'nominal_beli' => 0.0,
                    'nominal_retur' => 0.0,
                    'nominal_net' => 0.0,
                    'first_buy_at' => null,
                    'last_buy_at' => null,
                    'last_buy_ts' => 0,
                    'last_retur_at' => null,
                ];
                $runningQtyMap[$pairKey] = 0.0;
            }

            if ($event['event_type'] === 'BELI') {
                $uniqueTransactions['B:' . $event['transaction_id']] = true;
                $trxKey = $pairKey . ':' . $event['transaction_id'];
                if (!isset($seenPurchaseTrx[$trxKey])) {
                    $seenPurchaseTrx[$trxKey] = true;
                    $summaryMap[$pairKey]['frekuensi_beli']++;
                    $totalFreqBeli++;
                }

                $event['frekuensi_ke'] = $summaryMap[$pairKey]['frekuensi_beli'];
                $runningQtyMap[$pairKey] += $event['qty'];
                $event['akumulasi_qty'] = round($runningQtyMap[$pairKey], 2);

                $summaryMap[$pairKey]['qty_beli'] += $event['qty'];
                $summaryMap[$pairKey]['nominal_beli'] += $event['subtotal'];

                $buyDisplay = trim($event['date'] . ' ' . substr($event['time'], 0, 5));
                if ($summaryMap[$pairKey]['first_buy_at'] === null) {
                    $summaryMap[$pairKey]['first_buy_at'] = $buyDisplay;
                }
                $summaryMap[$pairKey]['last_buy_at'] = $buyDisplay;
                $summaryMap[$pairKey]['last_buy_ts'] = $event['sort_ts'];

                $totalQtyBeli += $event['qty'];
                $totalNominalBeli += $event['subtotal'];
            } else {
                $uniqueTransactions['R:' . ($event['retur_id'] ?? $event['sort_id'])] = true;
                $retKey = $pairKey . ':' . ($event['retur_id'] ?? $event['sort_id']);
                if (!isset($seenReturnIds[$retKey])) {
                    $seenReturnIds[$retKey] = true;
                    $summaryMap[$pairKey]['frekuensi_retur']++;
                }

                $event['frekuensi_ke'] = null;
                $runningQtyMap[$pairKey] -= $event['qty'];
                $event['akumulasi_qty'] = round($runningQtyMap[$pairKey], 2);

                $summaryMap[$pairKey]['qty_retur'] += $event['qty'];
                $summaryMap[$pairKey]['nominal_retur'] += $event['subtotal'];
                $summaryMap[$pairKey]['last_retur_at'] = trim($event['date'] . ' ' . substr($event['time'], 0, 5));

                $totalQtyRetur += $event['qty'];
                $totalNominalRetur += $event['subtotal'];
            }
        }
        unset($event);

        $summary = array_values(array_map(static function (array $row): array {
            $row['qty_beli'] = round($row['qty_beli'], 2);
            $row['qty_retur'] = round($row['qty_retur'], 2);
            $row['qty_net'] = round($row['qty_beli'] - $row['qty_retur'], 2);
            $row['nominal_beli'] = round($row['nominal_beli'], 2);
            $row['nominal_retur'] = round($row['nominal_retur'], 2);
            $row['nominal_net'] = round($row['nominal_beli'] - $row['nominal_retur'], 2);
            return $row;
        }, $summaryMap));

        usort($summary, static function (array $a, array $b): int {
            return [$b['frekuensi_beli'], $b['qty_beli'], $b['last_buy_ts']]
                <=> [$a['frekuensi_beli'], $a['qty_beli'], $a['last_buy_ts']];
        });

        // Display timeline newest-first like Kartu Stock.
        $timeline = array_reverse($events);

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'stats' => [
                'total_patients' => count($uniquePatients),
                'total_transactions' => count($uniqueTransactions),
                'total_items' => count($summary),
                'total_freq' => $totalFreqBeli,
                'total_qty_beli' => round($totalQtyBeli, 2),
                'total_qty_retur' => round($totalQtyRetur, 2),
                'total_qty_net' => round($totalQtyBeli - $totalQtyRetur, 2),
                'total_nominal_beli' => round($totalNominalBeli, 2),
                'total_nominal_retur' => round($totalNominalRetur, 2),
                'total_nominal_net' => round($totalNominalBeli - $totalNominalRetur, 2),
            ],
            'summary' => $summary,
            'timeline' => $timeline,
        ];
    }

    private function queryPurchases(
        Carbon $startDate,
        Carbon $endDate,
        mixed $pharmacyId,
        string $patientFilter,
        string $medicineFilter
    ) {
        $query = MedicineCart::query()
            ->with([
                'medicine:id,code,name,unit',
                'user:id,name',
                'transactions.patients:id,code,name,phone',
                'transactions.pharmacy:id,name',
                'transactions.user:id,name',
            ])
            ->where('medicine_cart.status', 1)
            ->whereHas('transactions', function (Builder $trx) use ($startDate, $endDate, $pharmacyId, $patientFilter) {
                $trx->whereNotNull('patient_id')
                    ->whereNotIn('transaction_type', ['RETUR JUAL', 'RETUR KREDIT', 'RETUR TUNAI', 'RETUR'])
                    ->where(function (Builder $code) {
                        $code->whereNull('transaction_code')
                            ->orWhere('transaction_code', '!=', 'RT');
                    });

                LiphTransactionScope::applySalesStatus($trx);
                $this->applyTransactionFilters($trx, $pharmacyId, $patientFilter);

                $trx->whereBetween('created_at', [$startDate, $endDate]);
            });

        $this->applyMedicineFilter($query, $medicineFilter);

        return $query->get();
    }

    private function queryReturns(
        Carbon $startDate,
        Carbon $endDate,
        mixed $pharmacyId,
        string $patientFilter,
        string $medicineFilter
    ) {
        $query = Retur::query()
            ->with([
                'medicine:id,code,name,unit',
                'transaction.patients:id,code,name,phone',
                'transaction.pharmacy:id,name',
                'transaction.user:id,name',
            ])
            ->where('retur.status', 1)
            ->whereBetween('retur.created_at', [$startDate, $endDate])
            ->whereHas('transaction', function (Builder $trx) use ($pharmacyId, $patientFilter) {
                $trx->whereNotNull('patient_id');
                $this->applyTransactionFilters($trx, $pharmacyId, $patientFilter);
            });

        $this->applyMedicineFilter($query, $medicineFilter);

        return $query->get();
    }

    private function applyTransactionFilters(Builder $trx, mixed $pharmacyId, string $patientFilter): void
    {
        if ($pharmacyId !== null && $pharmacyId !== '' && $pharmacyId !== 'all') {
            $trx->where('pharmacy_id', (int) $pharmacyId);
        }

        $restrictedRole = function_exists('getRestrictedOnlineRole') ? getRestrictedOnlineRole() : null;
        if ($restrictedRole !== null) {
            LiphTransactionScope::applyOnlineScope($trx, $restrictedRole);
        }

        if ($patientFilter !== '') {
            if (is_numeric($patientFilter)) {
                $trx->where('patient_id', (int) $patientFilter);
            } else {
                $trx->whereHas('patients', function (Builder $p) use ($patientFilter) {
                    $p->where('name', 'like', "%{$patientFilter}%")
                        ->orWhere('code', 'like', "%{$patientFilter}%")
                        ->orWhere('phone', 'like', "%{$patientFilter}%");
                });
            }
        }
    }

    private function applyMedicineFilter(Builder $query, string $medicineFilter): void
    {
        if ($medicineFilter === '') {
            return;
        }

        if (is_numeric($medicineFilter)) {
            $query->where('medicine_id', (int) $medicineFilter);
            return;
        }

        $query->whereHas('medicine', function (Builder $m) use ($medicineFilter) {
            $m->where('name', 'like', "%{$medicineFilter}%")
                ->orWhere('code', 'like', "%{$medicineFilter}%")
                ->orWhere('barcode', 'like', "%{$medicineFilter}%");
        });
    }

    private function toFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $normalized = str_replace(',', '.', trim((string) $value));
        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}
