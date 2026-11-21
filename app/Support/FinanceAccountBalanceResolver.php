<?php

namespace App\Support;

use App\Models\FinanceAccount;
use App\Models\FinanceBalanceAdjustment;
use App\Models\FinancePayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class FinanceAccountBalanceResolver
{
    public const INFLOW_TYPES = [
        'PIUTANG',
        'PENDAPATAN',
        'PENJUALAN TUNAI',
        'PENJUALAN NON TUNAI',
    ];

    public const OUTFLOW_TYPES = [
        'KREDIT',
        'CASH',
        'KONSINYASI',
        'BIAYA',
        'SETOR',
        'TARIK TUNAI',
        'RETUR PENJUALAN',
    ];

    /**
     * Hitung saldo akun per tanggal tertentu mulai dari record SALDO AWAL (inklusif).
     * Memperbaiki bug seleksi reference_number untuk OPNAME-OUT dan menyertakan RETUR PENJUALAN.
     */
    public static function calculateFromOpening(int $accountId, FinancePayment $opening, string $asOfDate): float
    {
        $openingDate = $opening->payment_date?->format('Y-m-d');
        if (!$openingDate || $openingDate > $asOfDate) {
            return 0.0;
        }

        $payments = FinancePayment::where('account_id', $accountId)
            ->where('id', '!=', $opening->id)
            ->whereDate('payment_date', '>=', $openingDate)
            ->whereDate('payment_date', '<=', $asOfDate)
            ->get(['payment_type', 'amount', 'transfer_account_id', 'reference_number']);

        $balance = (float) $opening->amount;
        foreach ($payments as $payment) {
            if (in_array($payment->payment_type, self::INFLOW_TYPES, true)) {
                $balance += (float) $payment->amount;
            } elseif (in_array($payment->payment_type, self::OUTFLOW_TYPES, true)) {
                $balance -= (float) $payment->amount;
            } elseif ($payment->payment_type === 'PENYESUAIAN SALDO') {
                if ($payment->reference_number === 'OPNAME-OUT') {
                    $balance -= (float) $payment->amount;
                } else {
                    $balance += (float) $payment->amount;
                }
            }
        }

        $balance += (float) FinancePayment::whereIn('payment_type', ['SETOR', 'TARIK TUNAI'])
            ->where('transfer_account_id', $accountId)
            ->whereDate('payment_date', '>=', $openingDate)
            ->whereDate('payment_date', '<=', $asOfDate)
            ->sum('amount');

        return round($balance, 2);
    }

    /**
     * Hitung saldo akun per tanggal tertentu mulai dari hasil Opname (real_balance)
     * yang dianggap sebagai saldo penutup (akhir hari) pada adjustment_date.
     */
    public static function calculateFromOpnameAnchor(int $accountId, FinanceBalanceAdjustment $opname, string $asOfDate): float
    {
        $anchorDate = $opname->adjustment_date?->format('Y-m-d');
        if (!$anchorDate || $anchorDate > $asOfDate) {
            return 0.0;
        }

        $balance = (float) $opname->real_balance;
        if ($asOfDate === $anchorDate) {
            return round($balance, 2);
        }

        $payments = FinancePayment::where('account_id', $accountId)
            ->whereDate('payment_date', '>', $anchorDate)
            ->whereDate('payment_date', '<=', $asOfDate)
            ->get(['payment_type', 'amount', 'transfer_account_id', 'reference_number']);

        foreach ($payments as $payment) {
            if (in_array($payment->payment_type, self::INFLOW_TYPES, true)) {
                $balance += (float) $payment->amount;
            } elseif (in_array($payment->payment_type, self::OUTFLOW_TYPES, true)) {
                $balance -= (float) $payment->amount;
            } elseif ($payment->payment_type === 'PENYESUAIAN SALDO') {
                if ($payment->reference_number === 'OPNAME-OUT') {
                    $balance -= (float) $payment->amount;
                } else {
                    $balance += (float) $payment->amount;
                }
            }
        }

        $balance += (float) FinancePayment::whereIn('payment_type', ['SETOR', 'TARIK TUNAI'])
            ->where('transfer_account_id', $accountId)
            ->whereDate('payment_date', '>', $anchorDate)
            ->whereDate('payment_date', '<=', $asOfDate)
            ->sum('amount');

        return round($balance, 2);
    }

    /**
     * Ambil record opname terakhir pada atau sebelum tanggal tertentu (jika tabel tersedia).
     */
    public static function latestOpnameOnOrBefore(int $accountId, string $asOfDate): ?FinanceBalanceAdjustment
    {
        if (!Schema::hasTable('finance_balance_adjustments')) {
            return null;
        }

        return FinanceBalanceAdjustment::where('account_id', $accountId)
            ->whereDate('adjustment_date', '<=', $asOfDate)
            ->orderByDesc('adjustment_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Tentukan Saldo Awal sebuah akun untuk periode [periodStart, periodEnd].
     * Prioritas:
     * 1. Record SALDO AWAL (jika ada):
     *    - Jika tanggal SALDO AWAL berada di dalam periode -> pakai nominal SALDO AWAL langsung.
     *    - Jika tanggal SALDO AWAL sebelum periode -> hitung roll-forward s/d sehari sebelum periodStart.
     * 2. Fallback otomatis ke Opname terakhir sebelum periodStart (roll-forward dari real_balance opname).
     */
    public static function resolveOpeningBalanceForPeriod(int $accountId, string $periodStart, string $periodEnd): float
    {
        $dayBeforeStart = Carbon::parse($periodStart)->subDay()->toDateString();
        $opening = FinancePayment::where('payment_type', 'SALDO AWAL')
            ->where('account_id', $accountId)
            ->first();

        if ($opening) {
            $openingDate = $opening->payment_date?->format('Y-m-d');
            if (!$openingDate || $openingDate > $periodEnd) {
                return 0.0;
            }
            if ($openingDate >= $periodStart && $openingDate <= $periodEnd) {
                return round((float) $opening->amount, 2);
            }

            return self::calculateFromOpening($accountId, $opening, $dayBeforeStart);
        }

        $opname = self::latestOpnameOnOrBefore($accountId, $dayBeforeStart);
        if ($opname) {
            return self::calculateFromOpnameAnchor($accountId, $opname, $dayBeforeStart);
        }

        return 0.0;
    }

    /**
     * Hitung perubahan bersih buku besar (termasuk mutasi operasional, transfer masuk, dan PENYESUAIAN SALDO)
     * untuk rentang tanggal ($afterDateExclusive, $toDateInclusive].
     */
    public static function netLedgerChangeBetween(int $accountId, string $afterDateExclusive, string $toDateInclusive): float
    {
        if ($afterDateExclusive >= $toDateInclusive) {
            return 0.0;
        }

        $payments = FinancePayment::where('account_id', $accountId)
            ->where('payment_type', '!=', 'SALDO AWAL')
            ->whereDate('payment_date', '>', $afterDateExclusive)
            ->whereDate('payment_date', '<=', $toDateInclusive)
            ->get(['payment_type', 'amount', 'reference_number']);

        $delta = 0.0;
        foreach ($payments as $payment) {
            if (in_array($payment->payment_type, self::INFLOW_TYPES, true)) {
                $delta += (float) $payment->amount;
            } elseif (in_array($payment->payment_type, self::OUTFLOW_TYPES, true)) {
                $delta -= (float) $payment->amount;
            } elseif ($payment->payment_type === 'PENYESUAIAN SALDO') {
                if ($payment->reference_number === 'OPNAME-OUT') {
                    $delta -= (float) $payment->amount;
                } else {
                    $delta += (float) $payment->amount;
                }
            }
        }

        $delta += (float) FinancePayment::whereIn('payment_type', ['SETOR', 'TARIK TUNAI'])
            ->where('transfer_account_id', $accountId)
            ->whereDate('payment_date', '>', $afterDateExclusive)
            ->whereDate('payment_date', '<=', $toDateInclusive)
            ->sum('amount');

        return round($delta, 2);
    }

    /**
     * Tentukan Saldo Awal tepat pada tanggal periodStart (untuk Neraca / Rekap Cashflow).
     * Prioritas:
     * 1. SALDO AWAL (maju jika sebelum periodStart, langsung jika sama dengan periodStart,
     *    atau hitung mundur jika dicatat setelah periodStart).
     * 2. Opname terakhir pada/sebelum sehari sebelum periodStart.
     * 3. Hitung mundur akurat dari saldo buku hari ini (balance - net mutasi setelah dayBeforeStart s/d hari ini)
     *    sehingga Neraca AWAL tidak pernah jatuh ke Rp 0 ketika akun sudah punya saldo bawaan.
     */
    public static function resolveOpeningBalanceAtStartDate(int|FinanceAccount $accountOrId, string $startDate, ?string $todayDate = null): float
    {
        $account = $accountOrId instanceof FinanceAccount ? $accountOrId : null;
        $accountId = $account ? (int) $account->id : (int) $accountOrId;
        $dayBeforeStart = Carbon::parse($startDate)->subDay()->toDateString();

        $opening = FinancePayment::where('payment_type', 'SALDO AWAL')
            ->where('account_id', $accountId)
            ->first();

        if ($opening) {
            $openingDate = $opening->payment_date?->format('Y-m-d');
            if ($openingDate) {
                if ($openingDate === $startDate) {
                    return round((float) $opening->amount, 2);
                }
                if ($openingDate < $startDate) {
                    return self::calculateFromOpening($accountId, $opening, $dayBeforeStart);
                }

                // SALDO AWAL dicatat di tengah/setelah periode: hitung mundur ke akhir hari sebelum startDate
                $dayBeforeOpening = Carbon::parse($openingDate)->subDay()->toDateString();
                $netBeforeOpening = self::netLedgerChangeBetween($accountId, $dayBeforeStart, $dayBeforeOpening);
                return round((float) $opening->amount - $netBeforeOpening, 2);
            }
        }

        $opname = self::latestOpnameOnOrBefore($accountId, $dayBeforeStart);
        if ($opname) {
            return self::calculateFromOpnameAnchor($accountId, $opname, $dayBeforeStart);
        }

        $account = $account ?: FinanceAccount::find($accountId);
        if (!$account) {
            return 0.0;
        }

        $today = $todayDate ?: now()->toDateString();
        if ($dayBeforeStart >= $today) {
            return round((float) $account->balance, 2);
        }

        $netAfterCutoff = self::netLedgerChangeBetween($accountId, $dayBeforeStart, $today);
        return round((float) $account->balance - $netAfterCutoff, 2);
    }

    /**
     * Tentukan Saldo Akhir sebuah akun per tanggal $asOfDate.
     * Konsisten dengan resolveOpeningBalanceAtStartDate sehingga AWAL bulan N+1 === AKHIR bulan N.
     */
    public static function resolveClosingBalanceAsOf(FinanceAccount $account, string $asOfDate, ?string $todayDate = null): float
    {
        $opening = FinancePayment::where('payment_type', 'SALDO AWAL')
            ->where('account_id', $account->id)
            ->first();
        $openingDate = $opening?->payment_date?->format('Y-m-d');

        if ($opening && $openingDate) {
            if ($openingDate <= $asOfDate) {
                return self::calculateFromOpening($account->id, $opening, $asOfDate);
            }

            $dayBeforeOpening = Carbon::parse($openingDate)->subDay()->toDateString();
            $netBeforeOpening = self::netLedgerChangeBetween($account->id, $asOfDate, $dayBeforeOpening);
            return round((float) $opening->amount - $netBeforeOpening, 2);
        }

        $opname = self::latestOpnameOnOrBefore($account->id, $asOfDate);
        if ($opname) {
            return self::calculateFromOpnameAnchor($account->id, $opname, $asOfDate);
        }

        $today = $todayDate ?: now()->toDateString();
        if ($asOfDate >= $today) {
            return round((float) $account->balance, 2);
        }

        $netAfterCutoff = self::netLedgerChangeBetween($account->id, $asOfDate, $today);
        return round((float) $account->balance - $netAfterCutoff, 2);
    }

    /**
     * Hitung total mutasi operasional (Masuk & Keluar) pada rentang [fromDate, toDate],
     * mengecualikan SALDO AWAL dan PENYESUAIAN SALDO.
     *
     * @return array{inflow: float, outflow: float, net: float, opname_payment_count: int}
     */
    public static function sumOperationalFlows(int $accountId, string $fromDate, string $toDate): array
    {
        if ($fromDate > $toDate) {
            return [
                'inflow' => 0.0,
                'outflow' => 0.0,
                'net' => 0.0,
                'opname_payment_count' => 0,
            ];
        }

        $payments = FinancePayment::where('account_id', $accountId)
            ->whereDate('payment_date', '>=', $fromDate)
            ->whereDate('payment_date', '<=', $toDate)
            ->get(['payment_type', 'amount']);

        $inflow = 0.0;
        $outflow = 0.0;
        $opnamePaymentCount = 0;

        foreach ($payments as $payment) {
            if (in_array($payment->payment_type, self::INFLOW_TYPES, true)) {
                $inflow += (float) $payment->amount;
            } elseif (in_array($payment->payment_type, self::OUTFLOW_TYPES, true)) {
                $outflow += (float) $payment->amount;
            } elseif ($payment->payment_type === 'PENYESUAIAN SALDO') {
                $opnamePaymentCount++;
            }
        }

        $inflow += (float) FinancePayment::whereIn('payment_type', ['SETOR', 'TARIK TUNAI'])
            ->where('transfer_account_id', $accountId)
            ->whereDate('payment_date', '>=', $fromDate)
            ->whereDate('payment_date', '<=', $toDate)
            ->sum('amount');

        $inflow = round($inflow, 2);
        $outflow = round($outflow, 2);

        return [
            'inflow' => $inflow,
            'outflow' => $outflow,
            'net' => round($inflow - $outflow, 2),
            'opname_payment_count' => $opnamePaymentCount,
        ];
    }

    /**
     * Estimasi mundur Saldo Awal per $targetDate dari hasil Opname terakhir (atau saldo buku hari ini).
     * Rumus: Saldo Awal = Saldo Akhir Acuan - Total Masuk + Total Keluar.
     */
    public static function estimateOpeningForAccount(FinanceAccount $account, string $targetDate, string $anchorMode = 'auto'): array
    {
        $today = now()->toDateString();
        $latestOpname = null;

        if ($anchorMode !== 'book' && Schema::hasTable('finance_balance_adjustments')) {
            $latestOpname = FinanceBalanceAdjustment::where('account_id', $account->id)
                ->whereDate('adjustment_date', '>=', $targetDate)
                ->orderByDesc('adjustment_date')
                ->orderByDesc('id')
                ->first();
        }

        if ($latestOpname) {
            $anchorDate = $latestOpname->adjustment_date->format('Y-m-d');
            $anchorBalance = round((float) $latestOpname->real_balance, 2);
            $anchorSource = 'opname';
            $opnameId = $latestOpname->id;
        } else {
            $anchorDate = $today;
            $anchorBalance = round((float) $account->balance, 2);
            $anchorSource = 'book';
            $opnameId = null;
        }

        $flows = self::sumOperationalFlows($account->id, $targetDate, $anchorDate);
        $rawEstimated = round($anchorBalance - $flows['inflow'] + $flows['outflow'], 2);

        return [
            'account_id' => $account->id,
            'account_code' => $account->code,
            'account_name' => $account->name,
            'account_type' => $account->account_type,
            'target_date' => $targetDate,
            'anchor_source' => $anchorSource,
            'anchor_date' => $anchorDate,
            'anchor_balance' => $anchorBalance,
            'opname_id' => $opnameId,
            'inflow' => $flows['inflow'],
            'outflow' => $flows['outflow'],
            'net_flow' => $flows['net'],
            'opname_payment_count' => $flows['opname_payment_count'],
            'raw_estimated_opening' => $rawEstimated,
            'suggested_opening' => max(0.0, $rawEstimated),
            'is_negative' => $rawEstimated < -0.005,
        ];
    }
}
