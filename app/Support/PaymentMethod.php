<?php

namespace App\Support;

class PaymentMethod
{
    public static function label(?string $method, ?string $bank = null): string
    {
        $method = strtoupper(trim($method ?? ''));
        $bank = strtoupper(trim($bank ?? ''));

        if (in_array($method, ['CASH', 'BELUM BAYAR'], true)) {
            return $method;
        }

        if (in_array($method, ['TRANSFER', 'DEBIT', 'QRIS'], true)) {
            // Some historical bank values already include the payment method.
            $bank = preg_replace('/^(TRANSFER|DEBIT|QRIS)\s+/', '', $bank);
            return $method.' '.($bank !== '' && !is_numeric($bank) ? $bank : 'LAINNYA');
        }

        if ($bank !== '' && !is_numeric($bank)) {
            return ($method !== '' ? $method : 'METODE TIDAK TERCATAT').' '.$bank;
        }

        return $method !== '' ? $method : 'CASH';
    }
}
