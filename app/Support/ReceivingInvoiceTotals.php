<?php

namespace App\Support;

/**
 * Nilai receiving_items.total adalah nilai baris yang disimpan saat penerimaan,
 * termasuk PPN untuk faktur EXCLUDE. Gunakan nilai itu sebagai total otoritatif
 * agar laporan dan pembayaran tidak menambahkan pajak untuk kedua kalinya.
 */
class ReceivingInvoiceTotals
{
    /** @return array{subtotal: float, ppn: float, total: float} */
    public static function lineAmounts(object $item, ?string $ppnType = null): array
    {
        $type = strtoupper(trim((string) $ppnType));
        $qty = (float) ($item->qty_received ?? $item->qty ?? 0);
        $rawPrice = (float) ($item->raw_price ?? $item->order_items->price ?? 0);
        $gross = $rawPrice * $qty;
        $discount = self::discountAmount($gross, $item->discount ?? 0);
        $extraDiscount = self::discountAmount($gross, $item->extra_discount ?? 0);
        $net = max(0, $gross - $discount - $extraDiscount);

        if ($type === 'EXCLUDE') {
            $total = $net + round($net * 0.11);
            return ['subtotal' => $net, 'ppn' => $total - $net, 'total' => $total];
        }

        if ($type === 'INCLUDE') {
            $dpp = $net / 1.11;
            return ['subtotal' => $dpp, 'ppn' => $net - $dpp, 'total' => $net];
        }

        return ['subtotal' => $net, 'ppn' => 0.0, 'total' => $net];
    }

    /** @param iterable<object> $items */
    public static function calculate(iterable $items, ?string $ppnType = null): array
    {
        $subtotal = 0.0;
        $ppn = 0.0;
        $total = 0.0;

        foreach ($items as $item) {
            $line = self::lineAmounts($item, $ppnType);
            $subtotal += $line['subtotal'];
            $ppn += $line['ppn'];
            $total += $line['total'];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'ppn' => round($ppn, 2),
            'total' => round($total, 2),
        ];
    }

    private static function discountAmount(float $amount, mixed $value): float
    {
        $discount = (float) $value;
        if ($discount > 0 && $discount <= 100) {
            return $amount * $discount / 100;
        }

        return max(0, $discount);
    }
}
