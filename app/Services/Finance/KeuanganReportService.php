<?php

namespace App\Services\Finance;

use App\Models\FinancePayment;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class KeuanganReportService
{
    public function __construct(private CashflowReportService $cashflow)
    {
    }

    public function build(array $pharmacyIds, string $startDate, string $endDate, ?int $drawerAccountId, ?FinancePayment $opening): array
    {
        $openingDate = $opening?->payment_date?->toDateString();
        $calculationStart = $openingDate && $openingDate < $startDate ? $openingDate : $startDate;
        $days = [];
        foreach (CarbonPeriod::create($calculationStart, $endDate) as $date) {
            $key = $date->toDateString();
            $days[$key] = [
                'date' => $key, 'pagi' => 0.0, 'sore' => 0.0, 'jumlah' => 0.0,
                'piutang' => 0.0, 'pendapatan_lain' => 0.0, 'bank' => 0.0, 'cashbond_masuk' => 0.0,
                'total_pemasukan' => 0.0, 'hutang_dagang' => 0.0, 'retur_pembelian' => 0.0,
                'pembelian_cash' => 0.0, 'biaya_pajak' => 0.0, 'biaya_operasional' => 0.0,
                'total_biaya' => 0.0, 'cashbond_keluar' => 0.0, 'setoran' => [], 'total_pengeluaran' => 0.0,
                'sisa' => 0.0, 'saldo_sd' => 0.0,
            ];
        }

        foreach ($this->cashflow->build($pharmacyIds, $calculationStart, $endDate) as $saleDay) {
            $key = $saleDay['date'];
            if (!isset($days[$key])) continue;
            $days[$key]['pagi'] = (float) ($saleDay['morning']['cash'] ?? 0);
            $days[$key]['sore'] = (float) ($saleDay['evening']['cash'] ?? 0);
        }

        $payments = FinancePayment::with(['expenseAccount:id,name,category', 'transferAccount:id,name'])
            ->where(function ($q) use ($pharmacyIds) {
                $q->whereIn('pharmacy_id', $pharmacyIds)->orWhereNull('pharmacy_id');
            })
            ->whereBetween('payment_date', [$calculationStart, $endDate])
            ->where(function ($q) use ($drawerAccountId) {
                $q->whereIn('payment_type', ['SALDO AWAL', 'BIAYA'])
                    ->orWhereIn('payment_type', ['PIUTANG', 'PENDAPATAN', 'KREDIT', 'CASH', 'KONSINYASI'])
                    ->orWhere(function ($sub) use ($drawerAccountId) {
                        $sub->where('payment_type', 'SETOR')
                            ->where('account_id', $drawerAccountId ?? 0);
                    });
            })
            ->get();

        foreach ($payments as $payment) {
            $date = $payment->payment_date?->toDateString();
            if (!$date || !isset($days[$date]) || $payment->payment_type === 'SALDO AWAL') continue;
            $amount = (float) $payment->amount;
            switch ($payment->payment_type) {
                case 'PIUTANG': $days[$date]['piutang'] += $amount; break;
                case 'PENDAPATAN': $days[$date]['pendapatan_lain'] += $amount; break;
                case 'KREDIT':
                case 'KONSINYASI': $days[$date]['hutang_dagang'] += $amount; break;
                case 'CASH': $days[$date]['pembelian_cash'] += $amount; break;
                case 'BIAYA':
                    $category = mb_strtolower((string) ($payment->expenseAccount?->category ?? ''));
                    $name = mb_strtolower((string) ($payment->expenseAccount?->name ?? ''));
                    if (str_contains($category . ' ' . $name, 'pajak')) $days[$date]['biaya_pajak'] += $amount;
                    else $days[$date]['biaya_operasional'] += $amount;
                    break;
                case 'SETOR':
                    $label = (string) ($payment->transferAccount?->name ?? 'Rekening');
                    $days[$date]['setoran'][$label] = ($days[$date]['setoran'][$label] ?? 0) + $amount;
                    break;
            }
        }

        $returns = DB::table('items_log as il')
            ->join('receiving as r', 'r.code', '=', 'il.transaction_code')
            ->where('il.status', 4)->whereIn('r.pharmacy_id', $pharmacyIds)
            ->whereDate('il.date', '>=', $calculationStart)->whereDate('il.date', '<=', $endDate)
            ->selectRaw('DATE(il.date) as movement_date, SUM(CAST(COALESCE(NULLIF(il.total, ""), "0") AS DECIMAL(15,2))) as amount')
            ->groupByRaw('DATE(il.date)')->get();
        foreach ($returns as $return) {
            if (isset($days[$return->movement_date])) $days[$return->movement_date]['retur_pembelian'] = (float) $return->amount;
        }

        $balance = 0.0;
        foreach ($days as $date => &$day) {
            if ($openingDate === $date) $balance = (float) $opening->amount;
            $day['jumlah'] = $day['pagi'] + $day['sore'];
            $day['total_pemasukan'] = $day['jumlah'] + $day['piutang'] + $day['pendapatan_lain'] + $day['cashbond_masuk'];
            $day['total_biaya'] = $day['hutang_dagang'] + $day['retur_pembelian'] + $day['pembelian_cash'] + $day['biaya_pajak'] + $day['biaya_operasional'];
            $day['total_pengeluaran'] = $day['total_biaya'] + $day['cashbond_keluar'] + array_sum($day['setoran']);
            $day['sisa'] = $balance + $day['total_pemasukan'];
            $day['saldo_sd'] = $day['sisa'] - $day['total_pengeluaran'];
            $balance = $day['saldo_sd'];
        }
        unset($day);

        return array_values(array_filter($days, fn ($day) => $day['date'] >= $startDate));
    }
}
