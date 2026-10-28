<?php

namespace App\Services\Finance;

use App\Models\MedicineTransactions;
use App\Support\FinanceShift;
use App\Support\LiphTransactionScope;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MonitoringPenjualanService
{
    private function emptyShift(): array
    {
        return [
            'sales' => [
                'cashPrescription' => 0, 'upds' => 0, 'hv' => 0,
                'creditPrescription' => 0, 'creditReturns' => 0, 'returns' => 0,
                'onlineWhatsappPrescription' => 0, 'onlineWhatsappNonPrescription' => 0,
                'onlineShopee' => 0, 'onlineTiktok' => 0, 'onlineGrabmart' => 0,
                'onlineDigital' => 0, 'onlineOther' => 0,
                'offlinePrescription' => 0, 'offlineNonPrescription' => 0,
                'onlineWhatsappTotal' => 0, 'onlineTotal' => 0,
                'offlineTotal' => 0, 'offlineReturns' => 0,
            ],
            'receipts' => [
                'cashPrescription' => 0, 'upds' => 0, 'hv' => 0,
                'creditPrescription' => 0, 'creditReturns' => 0, 'returns' => 0,
                'onlineWhatsappPrescription' => 0, 'onlineWhatsappNonPrescription' => 0,
                'onlineShopee' => 0, 'onlineTiktok' => 0, 'onlineGrabmart' => 0,
                'onlineDigital' => 0, 'onlineOther' => 0,
                'offlinePrescription' => 0, 'offlineNonPrescription' => 0,
                'cashTotal' => 0, 'creditTotal' => 0, 'total' => 0, 'onlineWhatsappTotal' => 0,
                'onlineTotal' => 0, 'offlineTotal' => 0,
            ],
            'transactions' => [
                'all' => 0, 'online' => 0, 'offline' => 0,
                'onlineWhatsapp' => 0, 'onlineShopee' => 0, 'onlineTiktok' => 0,
                'onlineGrabmart' => 0, 'onlineDigital' => 0, 'onlineOther' => 0,
            ],
            'items' => [
                'cashPrescription' => 0, 'upds' => 0, 'hv' => 0,
                'creditPrescription' => 0, 'creditReturns' => 0, 'returns' => 0,
                'onlineWhatsappPrescription' => 0, 'onlineWhatsappNonPrescription' => 0,
                'onlineShopee' => 0, 'onlineTiktok' => 0, 'onlineGrabmart' => 0,
                'onlineDigital' => 0, 'onlineOther' => 0,
                'offlinePrescription' => 0, 'offlineNonPrescription' => 0,
                'cashTotal' => 0, 'creditTotal' => 0, 'total' => 0, 'onlineWhatsappTotal' => 0,
                'onlineTotal' => 0, 'offlineTotal' => 0,
            ],
        ];
    }

    private function emptyDay(string $date): array
    {
        return ['date' => $date, 'morning' => $this->emptyShift(), 'evening' => $this->emptyShift(), 'otherShift' => $this->emptyShift()];
    }

    private function roleNames($user): array
    {
        if (!$user) return [];
        return $user->roles->pluck('name')->map(fn ($name) => mb_strtolower(trim((string) $name)))->all();
    }

    private function onlineChannel(Collection $users, string $transactionType): ?string
    {
        $roles = $users->flatMap(fn ($user) => $this->roleNames($user))->unique()->values();
        foreach ([
            'shopee' => 'onlineShopee',
            'tiktok' => 'onlineTiktok',
            'grabmart' => 'onlineGrabmart',
            'grab' => 'onlineGrabmart',
            'digital' => 'onlineDigital',
            'whatsapp' => 'onlineWhatsapp',
            'online' => 'onlineWhatsapp',
        ] as $needle => $channel) {
            if ($roles->contains(fn ($role) => str_contains($role, $needle))) return $channel;
        }
        return $transactionType === 'ONLINE' ? 'onlineDigital' : null;
    }

    private function effectiveItemType($item, string $transactionType): string
    {
        $type = mb_strtoupper(trim((string) ($item->cart_type ?: $transactionType)));
        return match ($type) {
            'UK', 'KREDIT', 'RESEP KREDIT' => 'creditPrescription',
            'UM', 'RESEP TUNAI' => 'cashPrescription',
            'UP', 'UPDS' => 'upds',
            'HV', 'HV/OTC', 'OTC' => 'hv',
            'ONLINE' => 'nonPrescription',
            'RETUR KREDIT' => 'creditReturns',
            'RETUR JUAL', 'RETUR TUNAI' => 'returns',
            default => match (mb_strtoupper(trim($transactionType))) {
                'KREDIT' => 'creditPrescription',
                'RESEP TUNAI' => 'cashPrescription',
                'UPDS' => 'upds',
                'HV', 'HV/OTC', 'OTC' => 'hv',
                'ONLINE' => 'nonPrescription',
                'RETUR KREDIT' => 'creditReturns',
                'RETUR JUAL', 'RETUR TUNAI' => 'returns',
                default => null,
            },
        };
    }

    private function add(array &$target, string $key, float $value): void
    {
        if (array_key_exists($key, $target)) $target[$key] += $value;
    }

    /** Build daily figures from completed transaction rows and their completed cart items. */
    public function build(array $pharmacyIds, string $startDate, string $endDate): array
    {
        $days = [];
        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            $key = $date->toDateString();
            $days[$key] = $this->emptyDay($key);
        }

        $transactions = MedicineTransactions::query()
            ->with([
                'transactions' => fn ($query) => $query->where('status', 1),
                'transactions.user.roles',
                'user.roles',
            ])
            ->whereIn('pharmacy_id', $pharmacyIds)
            ->tap(fn ($q) => LiphTransactionScope::applySalesStatus($q))
            ->tap(fn ($q) => LiphTransactionScope::applyPeriod($q, Carbon::parse($startDate), Carbon::parse($endDate)))
            ->orderBy('created_at')
            ->get();

        foreach ($transactions as $transaction) {
            $effectiveAt = $transaction->created_at ?? $transaction->updated_at;
            $date = FinanceShift::operationalDate($effectiveAt);
            if (!$date || !isset($days[$date])) continue;

            $shiftKey = FinanceShift::key($effectiveAt);
            $shift = &$days[$date][$shiftKey];
            $items = $transaction->transactions;
            $users = collect([$transaction->user])->concat($items->pluck('user'))->filter()->unique('id');
            $transactionType = mb_strtoupper(trim((string) $transaction->transaction_type));
            $channel = $this->onlineChannel($users, $transactionType);
            $isOnline = $channel !== null;
            $channelPrefix = $isOnline ? 'online' : 'offline';

            $shift['transactions']['all']++;
            $shift['transactions'][$channelPrefix]++;
            if ($isOnline) $this->add($shift['transactions'], $channel, 1);

            $classified = [];
            foreach ($items as $item) {
                $type = $this->effectiveItemType($item, $transactionType);
                if (!$type) continue;
                $classified[] = ['item' => $item, 'type' => $type];
                // Sum final_price preserving decimal precision to match LIPH and other reports.
                $amount = (float) ($item->final_price ?? 0);
                if (in_array($type, ['returns', 'creditReturns'], true)) $amount = -abs($amount);

                if ($type === 'cashPrescription') $this->add($shift['sales'], 'cashPrescription', $amount);
                elseif ($type === 'upds') $this->add($shift['sales'], 'upds', $amount);
                elseif ($type === 'hv' || $type === 'nonPrescription') $this->add($shift['sales'], 'hv', $amount);
                elseif ($type === 'creditPrescription') $this->add($shift['sales'], 'creditPrescription', $amount);
                elseif ($type === 'creditReturns') $this->add($shift['sales'], 'creditReturns', $amount);
                elseif ($type === 'returns') $this->add($shift['sales'], 'returns', $amount);

                $isPrescription = in_array($type, ['cashPrescription', 'creditPrescription', 'creditReturns'], true);
                if ($isOnline) {
                    if ($channel === 'onlineWhatsapp') {
                        $channelTypeKey = 'onlineWhatsapp' . ($isPrescription ? 'Prescription' : 'NonPrescription');
                        $this->add($shift['sales'], $channelTypeKey, $amount);
                        $this->add($shift['items'], $channelTypeKey, 1);
                    } else {
                        $this->add($shift['sales'], $channel, $amount);
                        $this->add($shift['items'], $channel, 1);
                    }
                    $this->add($shift['sales'], 'onlineTotal', $amount);
                } else {
                    $channelTypeKey = 'offline' . ($isPrescription ? 'Prescription' : 'NonPrescription');
                    $this->add($shift['sales'], $channelTypeKey, $amount);
                    $this->add($shift['items'], $channelTypeKey, 1);
                    $this->add($shift['sales'], 'offlineTotal', $amount);
                    if ($type === 'returns') $this->add($shift['sales'], 'offlineReturns', $amount);
                }
                if ($type === 'returns') $this->add($shift['items'], 'returns', 1);
                elseif ($type === 'creditReturns') $this->add($shift['items'], 'creditReturns', 1);
                elseif ($type === 'cashPrescription') $this->add($shift['items'], 'cashPrescription', 1);
                elseif ($type === 'upds') $this->add($shift['items'], 'upds', 1);
                elseif ($type === 'hv' || $type === 'nonPrescription') $this->add($shift['items'], 'hv', 1);
                elseif ($type === 'creditPrescription') $this->add($shift['items'], 'creditPrescription', 1);
            }

            // LIPH uses subtotal for a finalized transaction with no cart rows.
            if (!$classified) {
                $fallbackType = $this->effectiveItemType((object) ['cart_type' => null], $transactionType);
                $fallbackAmount = (float) ($transaction->subtotal ?? 0);
                if (in_array($fallbackType, ['returns', 'creditReturns'], true)) $fallbackAmount = -abs($fallbackAmount);

                $fallbackKey = match ($fallbackType) {
                    'cashPrescription' => 'cashPrescription',
                    'upds' => 'upds',
                    'hv', 'nonPrescription' => 'hv',
                    'creditPrescription' => 'creditPrescription',
                    'creditReturns' => 'creditReturns',
                    'returns' => 'returns',
                    default => null,
                };
                if ($fallbackKey) $this->add($shift['sales'], $fallbackKey, $fallbackAmount);

                $fallbackPrescription = in_array($fallbackType, ['cashPrescription', 'creditPrescription', 'creditReturns'], true);
                $fallbackChannelKey = $isOnline
                    ? ($channel === 'onlineWhatsapp'
                        ? 'onlineWhatsapp' . ($fallbackPrescription ? 'Prescription' : 'NonPrescription')
                        : $channel)
                    : 'offline' . ($fallbackPrescription ? 'Prescription' : 'NonPrescription');
                $this->add($shift['sales'], $fallbackChannelKey, $fallbackAmount);
                $this->add($shift['sales'], $isOnline ? 'onlineTotal' : 'offlineTotal', $fallbackAmount);
                if (!$isOnline && $fallbackType === 'returns') $this->add($shift['sales'], 'offlineReturns', $fallbackAmount);
            }

            // LIPH subtracts the transaction-level discount once from the
            // receipt's effective class. Keep Monitoring Penjualan on the same
            // basis so its sales totals reconcile with LIPH's Netto Akhir.
            if ($classified) {
                $originalType = $this->effectiveItemType((object) ['cart_type' => null], $transactionType);
                $typeKeys = array_column($classified, 'type');
                $discountType = in_array($originalType, $typeKeys, true)
                    ? $originalType
                    : $typeKeys[0];
            } else {
                $discountType = $this->effectiveItemType((object) ['cart_type' => null], $transactionType);
            }

            $transactionDiscount = (float) ($transaction->discount ?? 0);
            if ($transactionDiscount !== 0.0 && $discountType) {
                $salesKey = match ($discountType) {
                    'cashPrescription' => 'cashPrescription',
                    'upds' => 'upds',
                    'hv', 'nonPrescription' => 'hv',
                    'creditPrescription' => 'creditPrescription',
                    'creditReturns' => 'creditReturns',
                    'returns' => 'returns',
                    default => null,
                };
                if ($salesKey) $this->add($shift['sales'], $salesKey, -$transactionDiscount);

                $isPrescription = in_array($discountType, ['cashPrescription', 'creditPrescription', 'creditReturns'], true);
                if ($isOnline) {
                    if ($channel === 'onlineWhatsapp') {
                        $channelTypeKey = 'onlineWhatsapp' . ($isPrescription ? 'Prescription' : 'NonPrescription');
                        $this->add($shift['sales'], $channelTypeKey, -$transactionDiscount);
                    } else {
                        $this->add($shift['sales'], $channel, -$transactionDiscount);
                    }
                    $this->add($shift['sales'], 'onlineTotal', -$transactionDiscount);
                } else {
                    $channelTypeKey = 'offline' . ($isPrescription ? 'Prescription' : 'NonPrescription');
                    $this->add($shift['sales'], $channelTypeKey, -$transactionDiscount);
                    $this->add($shift['sales'], 'offlineTotal', -$transactionDiscount);
                    if ($discountType === 'returns') $this->add($shift['sales'], 'offlineReturns', -$transactionDiscount);
                }
            }

            // Count one receipt once, following LIPH's rule: keep its original class if present;
            // otherwise assign the receipt to its sole/first effective item class.
            if ($classified) {
                $originalType = $this->effectiveItemType((object) ['cart_type' => null], $transactionType);
                $typeKeys = array_column($classified, 'type');
                if (in_array($originalType, $typeKeys, true)) $receiptType = $originalType;
                else $receiptType = $typeKeys[0];
                $isPrescription = in_array($receiptType, ['cashPrescription', 'creditPrescription', 'creditReturns'], true);
                if ($receiptType === 'cashPrescription') $this->add($shift['receipts'], 'cashPrescription', 1);
                elseif ($receiptType === 'upds') $this->add($shift['receipts'], 'upds', 1);
                elseif ($receiptType === 'hv' || $receiptType === 'nonPrescription') $this->add($shift['receipts'], 'hv', 1);
                elseif ($receiptType === 'creditPrescription') $this->add($shift['receipts'], 'creditPrescription', 1);
                elseif ($receiptType === 'creditReturns') $this->add($shift['receipts'], 'creditReturns', 1);
                elseif ($receiptType === 'returns') $this->add($shift['receipts'], 'returns', 1);
                if ($isOnline) {
                    if ($channel === 'onlineWhatsapp') {
                        $this->add($shift['receipts'], 'onlineWhatsapp' . ($isPrescription ? 'Prescription' : 'NonPrescription'), 1);
                    } else {
                        $this->add($shift['receipts'], $channel, 1);
                    }
                } else {
                    $this->add($shift['receipts'], 'offline' . ($isPrescription ? 'Prescription' : 'NonPrescription'), 1);
                }
                $this->add($shift['receipts'], $isPrescription ? 'creditTotal' : 'cashTotal', 1);
                $this->add($shift['receipts'], 'total', 1);
                $this->add($shift['receipts'], $isOnline ? 'onlineTotal' : 'offlineTotal', 1);
            } else {
                $originalType = $this->effectiveItemType((object) ['cart_type' => null], $transactionType);
                if ($originalType === 'cashPrescription') $this->add($shift['receipts'], 'cashPrescription', 1);
                elseif ($originalType === 'upds') $this->add($shift['receipts'], 'upds', 1);
                elseif ($originalType === 'hv' || $originalType === 'nonPrescription') $this->add($shift['receipts'], 'hv', 1);
                elseif ($originalType === 'creditPrescription') $this->add($shift['receipts'], 'creditPrescription', 1);
                elseif ($originalType === 'returns') $this->add($shift['receipts'], 'returns', 1);
                if ($originalType === 'creditReturns') $this->add($shift['receipts'], 'creditReturns', 1);
                $isPrescription = in_array($originalType, ['cashPrescription', 'creditPrescription', 'creditReturns'], true);
                if ($isOnline && $channel === 'onlineWhatsapp') {
                    $this->add($shift['receipts'], 'onlineWhatsapp' . ($isPrescription ? 'Prescription' : 'NonPrescription'), 1);
                } else {
                    $this->add($shift['receipts'], $isOnline ? $channel : 'offline' . ($isPrescription ? 'Prescription' : 'NonPrescription'), 1);
                }
                $this->add($shift['receipts'], $isPrescription ? 'creditTotal' : 'cashTotal', 1);
                $this->add($shift['receipts'], 'total', 1);
                $this->add($shift['receipts'], $isOnline ? 'onlineTotal' : 'offlineTotal', 1);
            }

            unset($shift);
        }

        foreach ($days as &$day) {
            foreach (['morning', 'evening', 'otherShift'] as $shiftKey) {
                $shift = &$day[$shiftKey];
                $shift['sales']['cashTotal'] = $shift['sales']['cashPrescription'] + $shift['sales']['upds'] + $shift['sales']['hv'] + $shift['sales']['returns'];
                $shift['sales']['creditTotal'] = $shift['sales']['creditPrescription'] + $shift['sales']['creditReturns'];
                $shift['sales']['total'] = $shift['sales']['cashTotal'] + $shift['sales']['creditTotal'];
                $shift['sales']['onlineWhatsappTotal'] = $shift['sales']['onlineWhatsappPrescription'] + $shift['sales']['onlineWhatsappNonPrescription'];
                $shift['receipts']['cashTotal'] = $shift['receipts']['cashPrescription'] + $shift['receipts']['upds'] + $shift['receipts']['hv'] + $shift['receipts']['returns'];
                $shift['receipts']['creditTotal'] = $shift['receipts']['creditPrescription'] + $shift['receipts']['creditReturns'];
                $shift['receipts']['total'] = $shift['receipts']['cashTotal'] + $shift['receipts']['creditTotal'];
                $shift['receipts']['onlineWhatsappTotal'] = $shift['receipts']['onlineWhatsappPrescription'] + $shift['receipts']['onlineWhatsappNonPrescription'];
                $shift['receipts']['onlineTotal'] = $shift['receipts']['onlineWhatsappTotal'] + $shift['receipts']['onlineShopee'] + $shift['receipts']['onlineTiktok'] + $shift['receipts']['onlineGrabmart'] + $shift['receipts']['onlineDigital'] + $shift['receipts']['onlineOther'];
                $shift['receipts']['offlineTotal'] = $shift['receipts']['offlinePrescription'] + $shift['receipts']['offlineNonPrescription'];
                $shift['items']['cashTotal'] = $shift['items']['cashPrescription'] + $shift['items']['upds'] + $shift['items']['hv'] + $shift['items']['returns'];
                $shift['items']['creditTotal'] = $shift['items']['creditPrescription'] + $shift['items']['creditReturns'];
                $shift['items']['total'] = $shift['items']['cashTotal'] + $shift['items']['creditTotal'];
                $shift['items']['onlineWhatsappTotal'] = $shift['items']['onlineWhatsappPrescription'] + $shift['items']['onlineWhatsappNonPrescription'];
                $shift['items']['onlineTotal'] = $shift['items']['onlineWhatsappTotal'] + $shift['items']['onlineShopee'] + $shift['items']['onlineTiktok'] + $shift['items']['onlineGrabmart'] + $shift['items']['onlineDigital'] + $shift['items']['onlineOther'];
                $shift['items']['offlineTotal'] = $shift['items']['offlinePrescription'] + $shift['items']['offlineNonPrescription'];
                unset($shift);
            }
        }
        unset($day);

        return array_values($days);
    }
}
