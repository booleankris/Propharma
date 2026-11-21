<?php

namespace App\Services\Finance;

use App\Exports\Report\BankSalesExport;
use App\Exports\Report\LiphExport;
use App\Models\Debtors;
use App\Models\MedicineTransactions;
use App\Models\Receiving;
use App\Support\FinanceShift;
use App\Support\LiphTransactionScope;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class KinerjaOmzetService
{
    private function blankDay(string $date): array
    {
        return [
            'date' => $date,
            'sales' => [
                'cash' => ['morning' => 0, 'evening' => 0],
                'bank' => ['morning' => 0, 'evening' => 0],
                'credit' => ['morning' => [], 'evening' => []],
                'returns' => ['morning' => 0, 'evening' => 0],
                'cashBankTotal' => 0.0,
                'creditTotal' => 0.0,
                'total' => 0.0,
                'total_sd' => 0.0,
            ],
            'purchases' => ['pbf' => 0, 'consignment' => 0, 'return' => 0, 'cash' => 0, 'discountOff' => 0, 'discountOffSd' => 0, 'ppn' => 0, 'total' => 0, 'sd' => 0, 'exVat' => 0],
            'other_income' => 0.0,
            'other_income_sd' => 0.0,
            'expense' => 0.0,
            'saving' => 0.0,
            'expense_sd' => 0.0,
            'margin' => 0.0,
            'margin_sd' => 0.0,
            'profit_loss_sd' => 0.0,
        ];
    }

    private function number($value): float
    {
        return (float) str_replace(',', '.', (string) ($value ?? 0));
    }

    private function addPurchase(array &$day, string $kind, float $amount, string $ppnType): void
    {
        $type = mb_strtoupper(trim($ppnType));
        $sign = $amount < 0 ? -1 : 1;
        $absolute = abs($amount);
        $dpp = match ($type) {
            'EXCLUDE', 'INCLUDE' => $sign * floor($absolute / 1.11),
            default => $amount,
        };
        $ppn = in_array($type, ['EXCLUDE', 'INCLUDE'], true) ? $amount - $dpp : 0;
        // receiving_items.total is already saved with invoice tax included when applicable.
        $day['purchases'][$kind] += $amount;
        $day['purchases']['ppn'] += $ppn;
    }

    private function subtractPurchaseReturn(array &$day, string $kind, float $amount, string $ppnType): void
    {
        $type = mb_strtoupper(trim($ppnType));
        $sign = $amount < 0 ? -1 : 1;
        $absolute = abs($amount);
        if ($type === 'EXCLUDE') {
            // items_log.total stores the returned line's base value for EXCLUDE invoices.
            $ppn = $sign * floor($absolute * 0.11);
            $gross = $amount + $ppn;
        } elseif ($type === 'INCLUDE') {
            $dpp = $sign * floor($absolute / 1.11);
            $ppn = $amount - $dpp;
            $gross = $amount;
        } else {
            $ppn = 0;
            $gross = $amount;
        }
        $day['purchases']['return'] += $gross;
    }

    /**
     * Build the workbook-style daily sales and purchase figures for one month.
     */
    public function build(array $pharmacyIds, string $startDate, string $endDate): array
    {
        $days = [];
        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            $key = $date->toDateString();
            $days[$key] = $this->blankDay($key);
        }

        $debtors = Debtors::query()->orderBy('name')->get(['id', 'name']);
        $extraDebtors = [];
        $transactions = MedicineTransactions::query()
            ->with([
                'transactions' => fn($query) => $query->where('status', 1),
                'debtors',
                'service',
            ])
            ->whereIn('pharmacy_id', $pharmacyIds)
            ->whereIn('transaction_type', array_merge(array_keys(LiphExport::TYPE_MAP), ['JASA']))
            ->tap(fn($q) => LiphTransactionScope::applySalesStatus($q))
            ->tap(fn($q) => LiphTransactionScope::applyPeriod($q, Carbon::parse($startDate), Carbon::parse($endDate)))
            ->orderBy('created_at')
            ->get();

        foreach ($transactions as $transaction) {
            $effectiveAt = $transaction->created_at ? Carbon::parse($transaction->created_at) : ($transaction->updated_at ? Carbon::parse($transaction->updated_at) : null);
            $date = FinanceShift::operationalDate($effectiveAt);
            if (!$date || !isset($days[$date])) {
                continue;
            }
            $shift = FinanceShift::key($effectiveAt);
            $amount = BankSalesExport::calculateTransactionAmount($transaction);

            $typeGroup = LiphExport::TYPE_MAP[$transaction->transaction_type][0] ?? 'tunai';

            if ($typeGroup === 'kredit') {
                $debtorId = (int) ($transaction->debtor_id ?? 0);
                $name = $transaction->debtors?->name ?: 'Debitur tidak tercatat';
                if (!$transaction->debtors && $debtorId > 0) {
                    $extraDebtors[$debtorId] = $name;
                }
                $days[$date]['sales']['credit'][$shift][$debtorId] = ($days[$date]['sales']['credit'][$shift][$debtorId] ?? 0) + $amount;
                if ($amount < 0) {
                    $days[$date]['sales']['returns'][$shift] += $amount;
                }
            } else {
                $category = BankSalesExport::resolveCategory($transaction);
                $catUpper = strtoupper(trim((string) $category));

                if (in_array($catUpper, ['CASH', 'TUNAI'], true)) {
                    $days[$date]['sales']['cash'][$shift] += $amount;
                } elseif ($catUpper !== 'BELUM BAYAR' && $catUpper !== 'KREDIT') {
                    $days[$date]['sales']['bank'][$shift] += $amount;
                }
                if ($amount < 0) {
                    $days[$date]['sales']['returns'][$shift] += $amount;
                }
            }
        }

        $purchasePharmacyIds = $pharmacyIds;
        if (in_array(1, $purchasePharmacyIds, true) && !in_array(9, $purchasePharmacyIds, true)) {
            $purchasePharmacyIds[] = 9;
        }

        $dailyInvoices = \App\Exports\Orders\InvoiceSingleSheetExport::queryDailyInvoices($purchasePharmacyIds, $startDate, $endDate);
        foreach ($dailyInvoices as $date => $invSummary) {
            if (isset($days[$date])) {
                // Hanya faktur KREDIT (PBF) yang langsung dihitung dari faktur penerimaan
                $days[$date]['purchases']['pbf'] = (float) ($invSummary['pbf'] ?? 0);
                $days[$date]['purchases']['ppn'] = (float) ($invSummary['pbf_ppn'] ?? 0);
            }
        }

        // Pembelian Konsinyasi (saat dibayar di menu Konsinyasi) & Pembelian Cash (saat sudah pilih akun Kas & Bank)
        $paidCashAndConsignment = DB::table('finance_payments as fp')
            ->leftJoin('receiving_details as rd', 'rd.id', '=', 'fp.receiving_detail_id')
            ->leftJoin('receiving as r', 'r.id', '=', 'fp.receiving_id')
            ->leftJoin('creditors as c', 'c.code', '=', 'rd.creditor_code')
            ->whereIn('fp.payment_type', ['CASH', 'KONSINYASI'])
            ->whereNotNull('fp.account_id')
            ->where(function ($q) use ($purchasePharmacyIds) {
                $q->whereIn('fp.pharmacy_id', $purchasePharmacyIds)
                    ->orWhereIn('r.pharmacy_id', $purchasePharmacyIds);
                if (in_array(1, $purchasePharmacyIds, true)) {
                    $q->orWhereNull('fp.pharmacy_id');
                }
            })
            ->whereBetween('fp.payment_date', [$startDate, $endDate])
            ->selectRaw('
                DATE(fp.payment_date) as payment_dt,
                fp.payment_type,
                UPPER(TRIM(COALESCE(rd.invoice_ppn, c.ppn_type, "TANPA"))) as invoice_ppn,
                SUM(fp.amount) as amount
            ')
            ->groupByRaw('DATE(fp.payment_date), fp.payment_type, UPPER(TRIM(COALESCE(rd.invoice_ppn, c.ppn_type, "TANPA")))')
            ->get();

        foreach ($paidCashAndConsignment as $paidRow) {
            $date = (string) $paidRow->payment_dt;
            if (!isset($days[$date])) {
                continue;
            }
            $kind = $paidRow->payment_type === 'KONSINYASI' ? 'consignment' : 'cash';
            $this->addPurchase($days[$date], $kind, (float) $paidRow->amount, (string) $paidRow->invoice_ppn);
        }

        $returnInvoiceMeta = DB::table('receiving_items as ri')
            ->join('receiving_details as rd', 'rd.id', '=', 'ri.receiving_details_id')
            ->leftJoin('creditors as c', 'c.code', '=', 'rd.creditor_code')
            ->whereNotNull('ri.batches_id')
            ->where(function ($q) {
                $q->whereRaw('UPPER(TRIM(COALESCE(rd.invoice_payment, ""))) = ?', ['KREDIT'])
                    ->orWhere(function ($cashQ) {
                        $cashQ->whereIn(DB::raw('UPPER(TRIM(COALESCE(rd.invoice_payment, "")))'), ['TUNAI', 'CASH'])
                            ->whereExists(function ($fp) {
                                $fp->select(DB::raw(1))
                                    ->from('finance_payments')
                                    ->whereColumn('finance_payments.receiving_detail_id', 'rd.id')
                                    ->where('finance_payments.payment_type', 'CASH')
                                    ->whereNotNull('finance_payments.account_id');
                            });
                    });
            })
            ->select('rd.receiving_id', 'ri.batches_id')
            ->selectRaw('MAX(rd.invoice_payment) as invoice_payment, MAX(COALESCE(rd.invoice_ppn, c.ppn_type, "TANPA")) as invoice_ppn')
            ->groupBy('rd.receiving_id', 'ri.batches_id');
        $returns = DB::table('items_log as il')
            ->join('receiving as r', 'r.code', '=', 'il.transaction_code')
            ->joinSub($returnInvoiceMeta, 'rd', function ($join) {
                $join->on('rd.receiving_id', '=', 'r.id')->on('rd.batches_id', '=', 'il.batches_id');
            })
            ->where('il.status', 4)
            ->whereIn('r.pharmacy_id', $purchasePharmacyIds)
            ->whereIn('r.status', [1, 2, 3])
            ->whereRaw("COALESCE(STR_TO_DATE(NULLIF(il.date, ''), '%Y-%m-%d'), STR_TO_DATE(NULLIF(il.date, ''), '%d/%m/%Y'), DATE(il.created_at)) BETWEEN ? AND ?", [$startDate, $endDate])
            ->selectRaw('DATE(COALESCE(STR_TO_DATE(NULLIF(il.date, ""), "%Y-%m-%d"), STR_TO_DATE(NULLIF(il.date, ""), "%d/%m/%Y"), il.created_at)) as return_date, UPPER(TRIM(COALESCE(rd.invoice_payment, ""))) as invoice_payment, UPPER(TRIM(COALESCE(rd.invoice_ppn, "TANPA"))) as invoice_ppn, SUM(CAST(COALESCE(NULLIF(il.total, ""), "0") AS DECIMAL(15,2))) as amount')
            ->groupByRaw('return_date, UPPER(TRIM(COALESCE(rd.invoice_payment, ""))), UPPER(TRIM(COALESCE(rd.invoice_ppn, "TANPA")))')
            ->get();
        foreach ($returns as $return) {
            $date = (string) $return->return_date;
            if (!isset($days[$date]))
                continue;
            $kind = match ($return->invoice_payment) {
                'KREDIT' => 'pbf',
                'KONSINYASI' => 'consignment',
                'TUNAI' => 'cash',
                default => null,
            };
            if ($kind) {
                $amount = (float) $return->amount;
                $this->subtractPurchaseReturn($days[$date], $kind, $amount, $return->invoice_ppn);
            }
        }

        $discounts = DB::table('finance_payments')
            ->where('payment_type', 'DISKON')
            ->where(function ($q) use ($pharmacyIds) {
                $q->whereIn('pharmacy_id', $pharmacyIds);
                if (in_array(1, $pharmacyIds, true)) {
                    $q->orWhereNull('pharmacy_id');
                }
            })
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->selectRaw('DATE(payment_date) as payment_dt, SUM(amount) as amount')
            ->groupByRaw('DATE(payment_date)')
            ->get();
        foreach ($discounts as $disc) {
            $date = (string) $disc->payment_dt;
            if (isset($days[$date])) {
                $days[$date]['purchases']['discountOff'] += (float) $disc->amount;
            }
        }

        $otherIncomes = DB::table('finance_payments')
            ->where('payment_type', 'PENDAPATAN')
            ->where(function ($q) use ($pharmacyIds) {
                $q->whereIn('pharmacy_id', $pharmacyIds);
                if (in_array(1, $pharmacyIds, true)) {
                    $q->orWhereNull('pharmacy_id');
                }
            })
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->selectRaw('DATE(payment_date) as payment_dt, SUM(amount) as amount')
            ->groupByRaw('DATE(payment_date)')
            ->get();
        foreach ($otherIncomes as $inc) {
            $date = (string) $inc->payment_dt;
            if (isset($days[$date])) {
                $days[$date]['other_income'] += (float) $inc->amount;
            }
        }

        $expenses = DB::table('finance_payments')
            ->where('payment_type', 'BIAYA')
            ->where(function ($q) use ($pharmacyIds) {
                $q->whereIn('pharmacy_id', $pharmacyIds);
                if (in_array(1, $pharmacyIds, true)) {
                    $q->orWhereNull('pharmacy_id');
                }
            })
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->selectRaw('DATE(payment_date) as payment_dt, SUM(amount) as amount')
            ->groupByRaw('DATE(payment_date)')
            ->get();
        foreach ($expenses as $exp) {
            $date = (string) $exp->payment_dt;
            if (isset($days[$date])) {
                $days[$date]['expense'] += (float) $exp->amount;
            }
        }

        $debtorRows = $debtors->map(fn($debtor) => ['id' => (int) $debtor->id, 'name' => (string) $debtor->name])->values()->all();
        foreach ($extraDebtors as $id => $name)
            $debtorRows[] = ['id' => (int) $id, 'name' => $name];
        $omzetRunningTotal = 0.0;
        $incomeRunningTotal = 0.0;
        $expenseRunningTotal = 0.0;
        $purchaseRunningTotal = 0.0;
        $discountOffRunningTotal = 0.0;

        foreach ($days as &$day) {
            $day['sales']['cashBankTotal'] = $day['sales']['cash']['morning'] + $day['sales']['cash']['evening'] + $day['sales']['bank']['morning'] + $day['sales']['bank']['evening'];
            $debtorSales = array_sum($day['sales']['credit']['morning']) + array_sum($day['sales']['credit']['evening']);
            $day['sales']['creditTotal'] = $debtorSales;
            $day['sales']['cashBankExVat'] = $day['sales']['cashBankTotal'] / 1.11;
            $day['sales']['cashBankPpn'] = $day['sales']['cashBankTotal'] - $day['sales']['cashBankExVat'];
            $day['sales']['creditExVat'] = $day['sales']['creditTotal'] / 1.11;
            $day['sales']['creditPpn'] = $day['sales']['creditTotal'] - $day['sales']['creditExVat'];
            $day['sales']['total'] = $day['sales']['cashBankTotal'] + $day['sales']['creditTotal'];

            $omzetRunningTotal += (float) $day['sales']['total'];
            $day['sales']['total_sd'] = $omzetRunningTotal;
            $day['sales']['exVat'] = $day['sales']['cashBankExVat'] + $day['sales']['creditExVat'];
            $day['sales']['ppn'] = $day['sales']['cashBankPpn'] + $day['sales']['creditPpn'];
            // Total Pembelian Netto (identik dengan Halaman Ringkasan) = Kredit (PBF) + Tunai (CASH) + Konsinyasi - Retur
            // Diskon Off dipisahkan dan masuk komponen Pendapatan (tidak mengurangi Pembelian Netto).
            $day['purchases']['total'] = $day['purchases']['pbf'] + $day['purchases']['consignment'] + $day['purchases']['cash'] - $day['purchases']['return'];
            $purchaseRunningTotal += (float) $day['purchases']['total'];
            $day['purchases']['sd'] = $purchaseRunningTotal;
            $discountOffRunningTotal += (float) $day['purchases']['discountOff'];
            $day['purchases']['discountOffSd'] = $discountOffRunningTotal;
            $day['purchases']['exVat'] = $day['purchases']['total'] - $day['purchases']['ppn'];

            // Margin S/D = Total Omzet S/D - Total Pembelian Netto S/D
            $day['margin'] = (float) $day['sales']['total_sd'] - (float) $day['purchases']['sd'];
            $day['margin_sd'] = $day['margin'];

            $incomeRunningTotal += (float) ($day['other_income'] ?? 0);
            $day['other_income_sd'] = $incomeRunningTotal;

            $expenseRunningTotal += (float) ($day['expense'] ?? 0);
            $day['expense_sd'] = $expenseRunningTotal;

            // Laba / Rugi S/D mengikuti rumus Halaman Ringkasan:
            // Penjualan S/D - Pembelian Netto S/D - Biaya S/D + (Pendapatan Lain-lain S/D + Diskon Off S/D)
            $day['profit_loss_sd'] = $day['margin_sd'] - $day['expense_sd'] + $day['other_income_sd'] + $day['purchases']['discountOffSd'];
        }
        unset($day);

        return ['days' => array_values($days), 'debtors' => $debtorRows];
    }

    /**
     * Ringkas total Penjualan, Pembelian Netto, Biaya, Pendapatan, dan Laba dari array harian KinerjaOmzet.
     * Sumber rumus tunggal (Single Source of Truth) untuk Halaman Ringkasan, Kinerja Omzet, dan Neraca:
     * Laba = Penjualan - Pembelian Netto - Biaya + (Pendapatan Lain-lain + Diskon Off).
     */
    public static function summarizeDays(array $days): array
    {
        $penjualanTotal = 0.0;
        $penjualanTunaiBank = 0.0;
        $penjualanKredit = 0.0;

        $pembelianBruto = 0.0;
        $pembelianPbf = 0.0;
        $pembelianCash = 0.0;
        $pembelianKonsinyasi = 0.0;
        $pembelianRetur = 0.0;

        $biayaTotal = 0.0;
        $pendapatanLainTotal = 0.0;
        $diskonOffTotal = 0.0;

        foreach ($days as $day) {
            $penjualanTotal += (float) ($day['sales']['total'] ?? 0);
            $penjualanTunaiBank += (float) ($day['sales']['cashBankTotal'] ?? 0);
            $penjualanKredit += (float) ($day['sales']['creditTotal'] ?? 0);

            $pbf = (float) ($day['purchases']['pbf'] ?? 0);
            $cash = (float) ($day['purchases']['cash'] ?? 0);
            $cons = (float) ($day['purchases']['consignment'] ?? 0);
            $ret = (float) ($day['purchases']['return'] ?? 0);

            $pembelianPbf += $pbf;
            $pembelianCash += $cash;
            $pembelianKonsinyasi += $cons;
            $pembelianBruto += ($pbf + $cash + $cons);
            $pembelianRetur += $ret;

            $biayaTotal += (float) ($day['expense'] ?? 0);
            $pendapatanLainTotal += (float) ($day['other_income'] ?? 0);
            $diskonOffTotal += (float) ($day['purchases']['discountOff'] ?? 0);
        }

        $pembelianNetto = $pembelianBruto - $pembelianRetur;
        $pendapatanTotal = $pendapatanLainTotal + $diskonOffTotal;
        $labaTotal = $penjualanTotal - $pembelianNetto - $biayaTotal + $pendapatanTotal;

        return [
            'penjualan' => round($penjualanTotal, 2),
            'penjualan_tunai_bank' => round($penjualanTunaiBank, 2),
            'penjualan_kredit' => round($penjualanKredit, 2),
            'pembelian' => round($pembelianNetto, 2),
            'pembelian_bruto' => round($pembelianBruto, 2),
            'pembelian_retur' => round($pembelianRetur, 2),
            'pembelian_pbf' => round($pembelianPbf, 2),
            'pembelian_cash' => round($pembelianCash, 2),
            'pembelian_konsinyasi' => round($pembelianKonsinyasi, 2),
            'biaya' => round($biayaTotal, 2),
            'pendapatan' => round($pendapatanTotal, 2),
            'pendapatan_lain' => round($pendapatanLainTotal, 2),
            'diskon_off' => round($diskonOffTotal, 2),
            'laba' => round($labaTotal, 2),
        ];
    }

    /**
     * Hitung akumulasi (Year-To-Date) bulan 1 s/d (selectedMonth - 1) menggunakan cache bulanan yang sama
     * dengan Halaman Ringkasan Keuangan.
     */
    public static function summarizeYtdBeforeMonth(array $pharmacyIds, int $selectedYear, int $selectedMonth): array
    {
        $totals = [
            'penjualan' => 0.0,
            'pembelian' => 0.0,
            'biaya' => 0.0,
            'pendapatan' => 0.0,
            'laba' => 0.0,
        ];

        if ($selectedMonth <= 1) {
            return $totals;
        }

        for ($m = 1; $m < $selectedMonth; $m++) {
            $cacheKey = 'finance_monthly_sum_v2_' . implode('_', $pharmacyIds) . "_{$selectedYear}_{$m}";
            $pastTotals = Cache::remember($cacheKey, 86400, function () use ($pharmacyIds, $selectedYear, $m) {
                $mStart = sprintf('%04d-%02d-01', $selectedYear, $m);
                $mEnd = Carbon::createFromDate($selectedYear, $m, 1)->endOfMonth()->toDateString();
                $service = new self();
                $summary = self::summarizeDays($service->build($pharmacyIds, $mStart, $mEnd)['days'] ?? []);

                return [
                    'penjualan' => $summary['penjualan'],
                    'pembelian' => $summary['pembelian'],
                    'biaya' => $summary['biaya'],
                    'pendapatan' => $summary['pendapatan'],
                    'laba' => $summary['laba'],
                ];
            });

            $totals['penjualan'] += (float) ($pastTotals['penjualan'] ?? 0);
            $totals['pembelian'] += (float) ($pastTotals['pembelian'] ?? 0);
            $totals['biaya'] += (float) ($pastTotals['biaya'] ?? 0);
            $totals['pendapatan'] += (float) ($pastTotals['pendapatan'] ?? 0);
        }

        $totals['laba'] = round(
            $totals['penjualan'] - $totals['pembelian'] - $totals['biaya'] + $totals['pendapatan'],
            2
        );

        return [
            'penjualan' => round($totals['penjualan'], 2),
            'pembelian' => round($totals['pembelian'], 2),
            'biaya' => round($totals['biaya'], 2),
            'pendapatan' => round($totals['pendapatan'], 2),
            'laba' => $totals['laba'],
        ];
    }
}
