<?php

namespace App\Support;

/**
 * Perhitungan otoritatif faktur penerimaan (ReceivingDetails).
 *
 * Aturan baku: per item hanya menghitung `QTY * HNA - DISC`,
 * sedangkan PPN (11%) dihitung satu kali pada total akhir faktur (DPP).
 */
class ReceivingInvoiceTotals
{
    public const PPN_RATE = 0.11;

    /**
     * Hitung nilai baris per item: murni `QTY * HNA - DISC` tanpa PPN per item.
     *
     * @return array{gross: float, discount_nominal: float, net: float, subtotal: float, ppn: float, total: float}
     */
    public static function lineAmounts(object $item, ?string $ppnType = null): array
    {
        $qty = (float) ($item->qty_received ?? $item->qty ?? 0);
        $rawPrice = (float) (
            $item->raw_price
            ?? $item->receiving_raw_price
            ?? ($item->order_items?->price ?? null)
            ?? $item->order_items_price
            ?? $item->order_price
            ?? 0
        );
        $gross = $rawPrice * $qty;
        $discount = self::discountAmount($gross, $item->discount ?? 0);
        $extraDiscount = self::discountAmount($gross, $item->extra_discount ?? 0);
        $discountNominal = $discount + $extraDiscount;
        $net = max(0.0, $gross - $discountNominal);
        $roundedNet = round($net, 2);

        return [
            'gross' => $gross,
            'discount_nominal' => $discountNominal,
            'net' => $net,
            'subtotal' => $roundedNet,
            'ppn' => 0.0,
            'total' => $roundedNet,
        ];
    }

    /**
     * Nilai satuan baris inklusif PPN untuk referensi potongan tagihan saat Retur Pembelian.
     */
    public static function linePayableDeductionTotal(object $item, ?string $ppnType = null): float
    {
        $type = strtoupper(trim((string) $ppnType));
        $net = self::lineAmounts($item, $type)['net'];

        if ($type === 'EXCLUDE') {
            return round($net + round($net * self::PPN_RATE), 2);
        }

        return round($net, 2);
    }

    /**
     * Hitung subtotal (DPP), PPN, dan total tagihan pada level total faktur.
     *
     * @return array{subtotal: float, ppn: float, total: float}
     */
    public static function calculateFromSubtotalDiscount(float $subtotal, float $discount, ?string $ppnType = null): array
    {
        $type = strtoupper(trim((string) ($ppnType ?: 'TANPA')));
        $dpp = max(0.0, $subtotal - $discount);

        if ($type === 'EXCLUDE') {
            $ppn = (float) floor($dpp * self::PPN_RATE);
            $total = $dpp + $ppn;
            $outSubtotal = $dpp;
        } elseif ($type === 'INCLUDE') {
            $total = $dpp;
            $outSubtotal = (float) floor($dpp / (1 + self::PPN_RATE));
            $ppn = $total - $outSubtotal;
        } else {
            $ppn = 0.0;
            $outSubtotal = $dpp;
            $total = $dpp;
        }

        return [
            'subtotal' => round($outSubtotal, 2),
            'ppn' => round($ppn, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Hitung ringkasan DPP, PPN, dan Total Tagihan dari kumpulan item dalam satu faktur.
     *
     * @param iterable<object> $items
     * @return array{subtotal: float, ppn: float, total: float}
     */
    public static function calculate(iterable $items, ?string $ppnType = null): array
    {
        $subtotal = 0.0;
        $discount = 0.0;

        foreach ($items as $item) {
            $line = self::lineAmounts($item, $ppnType);
            $subtotal += $line['gross'];
            $discount += $line['discount_nominal'];
        }

        return self::calculateFromSubtotalDiscount($subtotal, $discount, $ppnType);
    }

    /**
     * Total faktur menurut rumus lama (pembulatan PPN per item) khusus untuk mendeteksi
     * faktur historis yang sudah dilunasi penuh sebelum penyeragaman PPN di akhir faktur.
     *
     * @param iterable<object> $items
     */
    public static function legacyPerItemTotal(iterable $items, ?string $ppnType = null): float
    {
        $type = strtoupper(trim((string) $ppnType));
        $total = 0.0;

        foreach ($items as $item) {
            $net = self::lineAmounts($item, $type)['net'];
            $total += ($type === 'EXCLUDE') ? ($net + round($net * self::PPN_RATE)) : $net;
        }

        return round($total, 2);
    }

    /**
     * Hitung tagihan bersih, terbayar efektif, dan sisa hutang dengan toleransi pelunasan lama
     * agar faktur yang sudah dibayar lunas dengan rumus per-item lama tidak menimbulkan sisa receh.
     *
     * @param iterable<object> $items
     * @param array{subtotal: float, ppn: float, total: float}|null $precomputedInvoiceAmounts
     * @return array{subtotal: float, ppn: float, total: float, net_total: float, paid_applied: float, sisa: float, settled_by_legacy_rounding: bool}
     */
    public static function resolveSettlementAmounts(
        iterable $items,
        ?string $ppnType,
        float $paidCashBank,
        float $totalRetur,
        ?array $precomputedInvoiceAmounts = null
    ): array {
        $amounts = $precomputedInvoiceAmounts ?? self::calculate($items, $ppnType);
        $total = (float) $amounts['total'];
        $netTotal = round(max(0.0, $total - $totalRetur), 2);
        $paidApplied = round(min($netTotal, $paidCashBank), 2);
        $sisa = round(max(0.0, $netTotal - $paidApplied), 2);
        $settledByLegacyRounding = false;

        if ($sisa >= 0.005 && ($paidCashBank + $totalRetur) > 0) {
            $itemCount = is_countable($items) ? count($items) : iterator_count((function () use ($items) {
                yield from $items;
            })());
            $legacyTotal = self::legacyPerItemTotal($items, $ppnType);
            $legacyNetTotal = round(max(0.0, $legacyTotal - $totalRetur), 2);
            $tolerance = max(100.0, $itemCount * 1.0);

            if ($paidCashBank >= ($legacyNetTotal - 0.005)
                && ($total - $legacyTotal) > 0
                && ($total - $legacyTotal) <= $tolerance) {
                $paidApplied = $netTotal;
                $sisa = 0.0;
                $settledByLegacyRounding = true;
            }
        } elseif ($sisa < 0.005) {
            $sisa = 0.0;
        }

        return [
            'subtotal' => (float) $amounts['subtotal'],
            'ppn' => (float) $amounts['ppn'],
            'total' => $total,
            'net_total' => $netTotal,
            'paid_applied' => $paidApplied,
            'sisa' => $sisa,
            'settled_by_legacy_rounding' => $settledByLegacyRounding,
        ];
    }

    private static function discountAmount(float $amount, mixed $value): float
    {
        $discount = (float) $value;
        if ($discount > 0 && $discount <= 100) {
            return $amount * $discount / 100;
        }

        return max(0.0, $discount);
    }
}
