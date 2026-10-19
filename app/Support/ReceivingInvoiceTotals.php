<?php

namespace App\Support;

/**
 * Nilai receiving_items.total adalah nilai baris yang disimpan saat penerimaan,
 * termasuk PPN untuk faktur EXCLUDE. Gunakan nilai itu sebagai total otoritatif
 * agar laporan dan pembayaran tidak menambahkan pajak untuk kedua kalinya.
 */
class ReceivingInvoiceTotals
{
    /** @param iterable<object> $items */
    public static function calculate(iterable $items, ?string $ppnType = null): array
    {
        $type = strtoupper(trim((string) $ppnType));
        $dpp = 0.0;
        $total = 0.0;

        foreach ($items as $item) {
            $qty = (float) ($item->qty_received ?? 0);
            $rawPrice = (float) ($item->raw_price ?? 0);
            $gross = $rawPrice * $qty;
            $discount = self::discountAmount($gross, $item->discount ?? 0);
            $extraDiscount = self::discountAmount($gross, $item->extra_discount ?? 0);
            $lineDpp = max(0, $gross - $discount - $extraDiscount);

            $savedTotal = $item->total ?? null;
            $lineTotal = ($savedTotal !== null && (float) $savedTotal > 0)
                ? (float) $savedTotal
                : ($type === 'EXCLUDE' ? $lineDpp + round($lineDpp * 0.11) : $lineDpp);
            $total += $lineTotal;

            if ($type === 'EXCLUDE') {
                $dpp += $lineDpp;
            }
        }

        $total = round($total, 2);
        if ($type === 'EXCLUDE') {
            // Derive tax from the exact saved line totals, including their rounding.
            $ppn = round($total - $dpp, 2);
            if ($ppn < 0) {
                $dpp = $total;
                $ppn = 0.0;
            }

            return ['subtotal' => round($dpp, 2), 'ppn' => $ppn, 'total' => $total];
        }

        return ['subtotal' => $total, 'ppn' => 0.0, 'total' => $total];
    }

    private static function discountAmount(float $amount, mixed $value): float
    {
        $discount = (float) $value;
        if ($discount > 0 && $discount <= 100) {
            return (float) round($amount * $discount / 100);
        }

        return max(0, $discount);
    }
}
