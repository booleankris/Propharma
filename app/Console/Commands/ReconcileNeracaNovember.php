<?php

namespace App\Console\Commands;

use App\Models\FinanceAccount;
use App\Models\FinanceBalanceAdjustment;
use App\Models\FinancePayment;
use App\Models\Pharmacies;
use App\Services\ConsignmentService;
use App\Support\FinanceShift;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sinkronisasi aman data rekonsiliasi Neraca periode tertentu dengan menyeimbangkan
 * OPNAME (PENYESUAIAN SALDO) pada masing-masing akun agar saldo akhir periode & DB balance tetap 0 berubah.
 */
class ReconcileNeracaNovember extends Command
{
    protected $signature = 'finance:reconcile-neraca
        {--pharmacy_id=2 : ID cabang apotek (default: 2 Sahabat Mulawarman)}
        {--start=2026-11-01 : Tanggal awal periode (Y-m-d)}
        {--end=2026-11-30 : Tanggal akhir periode (Y-m-d)}
        {--trial-before=2026-09-01 : Batas tanggal receiving trial (Y-m-d) untuk opsi void-august-trial}
        {--sync-retur=1 : Backfill RETUR JUAL yang belum tercatat ke RETUR PENJUALAN (1/0)}
        {--sync-discount=1 : Koreksi finance_payments penjualan yang melebihi subtotal-discount (1/0)}
        {--void-august-trial=1 : Hapus pelunasan faktur trial without batch (< trial-before) (1/0)}
        {--void-deleted-receiving-payment=0 : Opsional: hapus payment pembelian tanpa receiving_details aktif (1/0)}
        {--align-cross-month-inv-date=0 : Opsional: samakan invoice_date < start ke receiving.date bila rd & rec di periode ini (1/0)}
        {--apply : Simpan perubahan ke database (tanpa flag ini berjalan sebagai simulasi --dry-run)}';

    protected $description = 'Rekonsiliasi historis Neraca secara aman (berpasangan dengan Opname agar saldo akhir & Desember tidak bergeser)';

    public function handle(): int
    {
        $pharmacyId = (int) $this->option('pharmacy_id');
        $start = Carbon::parse((string) $this->option('start'))->toDateString();
        $end = Carbon::parse((string) $this->option('end'))->toDateString();
        $trialBefore = Carbon::parse((string) $this->option('trial-before'))->toDateString();
        $apply = (bool) $this->option('apply');

        if ($start > $end) {
            $this->error("Tanggal --start ({$start}) tidak boleh melebihi --end ({$end}).");
            return self::FAILURE;
        }

        $pharmacy = Pharmacies::find($pharmacyId);
        if (!$pharmacy) {
            $this->error("Cabang dengan pharmacy_id={$pharmacyId} tidak ditemukan.");
            return self::FAILURE;
        }

        $syncRetur = $this->toBool($this->option('sync-retur'));
        $syncDiscount = $this->toBool($this->option('sync-discount'));
        $voidTrial = $this->toBool($this->option('void-august-trial'));
        $voidDeletedDetail = $this->toBool($this->option('void-deleted-receiving-payment'));
        $alignCrossMonth = $this->toBool($this->option('align-cross-month-inv-date'));

        $this->info($apply
            ? '=== EKSEKUSI REKONSILIASI NERACA (--apply) ==='
            : '=== SIMULASI REKONSILIASI NERACA (DRY-RUN, TIDAK MENGUBAH DB) ===');
        $this->line("Cabang  : {$pharmacy->name} (ID: {$pharmacyId})");
        $this->line("Periode : {$start} s/d {$end}");
        $this->line("Opsi    : sync-retur=" . ($syncRetur ? 'YA' : 'TIDAK')
            . ' | sync-discount=' . ($syncDiscount ? 'YA' : 'TIDAK')
            . ' | void-august-trial=' . ($voidTrial ? 'YA' : 'TIDAK')
            . ' | void-deleted-receiving-payment=' . ($voidDeletedDetail ? 'YA' : 'TIDAK')
            . ' | align-cross-month-inv-date=' . ($alignCrossMonth ? 'YA' : 'TIDAK'));

        $accounts = FinanceAccount::where('pharmacy_id', $pharmacyId)
            ->where('category', 'Kas & Bank')
            ->get()
            ->keyBy('id');
        $accountIds = $accounts->keys()->map(fn ($id) => (int) $id)->all();
        $cashAccount = $accounts->firstWhere('account_type', 'kas_apotek');

        $missingReturns = $syncRetur
            ? $this->collectMissingReturns($pharmacyId, $start, $end, (int) ($cashAccount?->id ?? 0))
            : collect();
        $discountFixes = $syncDiscount
            ? $this->collectDiscountFixes($pharmacyId, $start, $end)
            : collect();
        $trialPayments = $voidTrial
            ? $this->collectTrialPayments($pharmacyId, $accountIds, $start, $end, $trialBefore)
            : collect();
        $deletedDetailPayments = $voidDeletedDetail
            ? $this->collectDeletedDetailPayments($pharmacyId, $accountIds, $start, $end)
            : collect();
        $crossMonthDetails = $alignCrossMonth
            ? $this->collectCrossMonthDetails($pharmacyId, $start, $end)
            : collect();

        $accountPlans = $this->buildAccountOpnamePlans(
            $accounts,
            $start,
            $end,
            $missingReturns,
            $discountFixes,
            $trialPayments,
            $deletedDetailPayments
        );

        $this->printPlanSummary(
            $missingReturns,
            $discountFixes,
            $trialPayments,
            $deletedDetailPayments,
            $crossMonthDetails,
            $accountPlans
        );

        foreach ($accountPlans as $accId => $plan) {
            if (!$plan['opname_fp']) {
                $this->error("ABORT: Akun #{$accId} ({$plan['account_name']}) tidak memiliki FP PENYESUAIAN SALDO pada/setelah {$plan['max_date']} s/d {$end}. Eksekusi dibatalkan agar saldo akhir & Desember tidak bergeser.");
                return self::FAILURE;
            }
        }

        if (!$apply) {
            $this->newLine();
            $this->warn('Mode DRY-RUN selesai. Tidak ada perubahan disimpan. Tambahkan --apply untuk mengeksekusi.');
            return self::SUCCESS;
        }

        DB::transaction(function () use (
            $pharmacyId,
            $missingReturns,
            $discountFixes,
            $trialPayments,
            $deletedDetailPayments,
            $crossMonthDetails,
            $accountPlans
        ) {
            foreach ($missingReturns as $ret) {
                FinancePayment::create([
                    'pharmacy_id' => $pharmacyId,
                    'medicine_transaction_id' => $ret->id,
                    'account_id' => $ret->target_account_id,
                    'payment_type' => 'RETUR PENJUALAN',
                    'payment_date' => $ret->ops_date,
                    'amount' => $ret->amount,
                    'reference_number' => $ret->transaction_code ?: ('RT-' . $ret->id),
                    'notes' => "[Rekonsiliasi Nov] Backfill retur penjualan trx#{$ret->id}",
                    'created_by' => null,
                ]);
            }

            foreach ($discountFixes as $fix) {
                $fp = FinancePayment::whereKey($fix->fp_id)->lockForUpdate()->first();
                if (!$fp) {
                    continue;
                }
                $noteSuffix = ' | [Rekonsiliasi Nov] Koreksi diskon ' . number_format($fix->recorded, 0, ',', '.') . ' -> ' . number_format($fix->expected_net, 0, ',', '.');
                $notes = (string) ($fp->notes ?? '');
                if (!str_contains($notes, '[Rekonsiliasi Nov]')) {
                    $notes = trim($notes) !== '' ? ($notes . $noteSuffix) : ltrim($noteSuffix, ' |');
                }
                $fp->update([
                    'amount' => $fix->expected_net,
                    'notes' => $notes,
                ]);
            }

            $voidIds = $trialPayments->pluck('fp_id')
                ->merge($deletedDetailPayments->pluck('fp_id'))
                ->unique()
                ->values()
                ->all();
            if (!empty($voidIds)) {
                FinancePayment::whereIn('id', $voidIds)->delete();
            }

            foreach ($accountPlans as $accId => $plan) {
                /** @var FinancePayment $opFp */
                $opFp = FinancePayment::whereKey($plan['opname_fp']->id)->lockForUpdate()->firstOrFail();
                $newSigned = $plan['new_signed_opname'];
                $newRef = $newSigned >= 0 ? 'OPNAME-IN' : 'OPNAME-OUT';
                $newAmt = round(abs($newSigned), 2);
                $noteTag = ' | [Rekonsiliasi Nov] Penyeimbang delta ' . $this->fmt($plan['net_flow_delta']);
                $notes = (string) ($opFp->notes ?? '');
                if (!str_contains($notes, '[Rekonsiliasi Nov]')) {
                    $notes = trim($notes) !== '' ? ($notes . $noteTag) : ltrim($noteTag, ' |');
                }

                $opFp->update([
                    'amount' => $newAmt,
                    'reference_number' => $newRef,
                    'notes' => $notes,
                ]);

                $adj = FinanceBalanceAdjustment::where('finance_payment_id', $opFp->id)->lockForUpdate()->first();
                if ($adj) {
                    $adjNotes = (string) ($adj->notes ?? '');
                    if (!str_contains($adjNotes, '[Rekonsiliasi Nov]')) {
                        $adjNotes = trim($adjNotes) !== '' ? ($adjNotes . $noteTag) : ltrim($noteTag, ' |');
                    }
                    $adj->update([
                        'system_balance' => round((float) $adj->system_balance + $plan['net_flow_delta'], 2),
                        'difference' => round($newSigned, 2),
                        'notes' => $adjNotes,
                    ]);
                }
            }

            foreach ($crossMonthDetails as $rd) {
                DB::table('receiving_details')
                    ->where('id', $rd->id)
                    ->update([
                        'invoice_date' => $rd->target_rec_date,
                    ]);
            }
        });

        Cache::forget('finance_stats_summary_branch_' . $pharmacyId . '_' . $pharmacyId);
        Cache::forget('finance_stats_summary');
        ConsignmentService::clearStockFilterCache();

        $this->newLine();
        $this->info('Rekonsiliasi berhasil diterapkan (--apply). Saldo akhir akun & Desember tetap terjaga.');

        return self::SUCCESS;
    }

    private function collectMissingReturns(int $pharmacyId, string $start, string $end, int $cashAccountId)
    {
        if ($cashAccountId <= 0) {
            return collect();
        }

        $windowStart = FinanceShift::start($start)->toDateTimeString();
        $windowEnd = FinanceShift::endExclusive($end)->toDateTimeString();

        return DB::table('medicine_transactions as mt')
            ->where('mt.pharmacy_id', $pharmacyId)
            ->whereIn('mt.transaction_type', ['RETUR JUAL', 'RETUR TUNAI'])
            ->where('mt.created_at', '>=', $windowStart)
            ->where('mt.created_at', '<', $windowEnd)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('finance_payments as fp')
                    ->whereColumn('fp.medicine_transaction_id', 'mt.id')
                    ->where('fp.payment_type', 'RETUR PENJUALAN');
            })
            ->orderBy('mt.id')
            ->get(['mt.id', 'mt.transaction_code', 'mt.subtotal', 'mt.created_at'])
            ->map(function ($row) use ($cashAccountId) {
                $row->target_account_id = $cashAccountId;
                $row->ops_date = FinanceShift::operationalDate(Carbon::parse($row->created_at))
                    ?? substr((string) $row->created_at, 0, 10);
                $row->amount = round(abs((float) $row->subtotal), 2);
                return $row;
            })
            ->filter(fn ($row) => $row->amount > 0)
            ->values();
    }

    private function collectDiscountFixes(int $pharmacyId, string $start, string $end)
    {
        return DB::table('finance_payments as fp')
            ->join('medicine_transactions as mt', 'mt.id', '=', 'fp.medicine_transaction_id')
            ->where('mt.pharmacy_id', $pharmacyId)
            ->where('mt.status', 1)
            ->whereIn('fp.payment_type', ['PENJUALAN TUNAI', 'PENJUALAN NON TUNAI'])
            ->whereDate('fp.payment_date', '>=', $start)
            ->whereDate('fp.payment_date', '<=', $end)
            ->whereRaw('COALESCE(mt.discount, 0) > 0')
            ->orderBy('fp.id')
            ->select([
                'fp.id as fp_id',
                'fp.account_id',
                'fp.payment_date',
                'fp.amount',
                'mt.id as trx_id',
                'mt.transaction_code',
                'mt.subtotal',
                'mt.discount',
            ])
            ->selectRaw("(SELECT COUNT(*) FROM finance_payments fp2 WHERE fp2.medicine_transaction_id = mt.id AND fp2.payment_type IN ('PENJUALAN TUNAI', 'PENJUALAN NON TUNAI')) as sales_fp_count")
            ->get()
            ->map(function ($row) {
                $expectedNet = round(max(0.0, (float) $row->subtotal - (float) ($row->discount ?? 0)), 2);
                $recorded = round((float) $row->amount, 2);
                $row->recorded = $recorded;
                $row->expected_net = $expectedNet;
                $row->excess = round($recorded - $expectedNet, 2);
                return $row;
            })
            // Hanya pola bug lama yang persis: 1 mutasi penjualan per transaksi, tercatat = subtotal kotor,
            // dan lebih besar dari subtotal - diskon. Transaksi dengan talangan ongkir / split payment
            // (lebih dari 1 mutasi PENJUALAN) tidak pernah disentuh.
            ->filter(fn ($row) => (int) $row->sales_fp_count === 1
                && abs($row->recorded - round((float) $row->subtotal, 2)) <= 0.01
                && $row->excess > 0.01)
            ->values();
    }

    /**
     * @param array<int, int> $accountIds
     */
    private function collectTrialPayments(int $pharmacyId, array $accountIds, string $start, string $end, string $trialBefore)
    {
        return DB::table('finance_payments as fp')
            ->join('receiving_details as rd', 'rd.id', '=', 'fp.receiving_detail_id')
            ->join('receiving as r', 'r.id', '=', 'rd.receiving_id')
            ->where(function ($q) use ($pharmacyId, $accountIds) {
                $q->where('fp.pharmacy_id', $pharmacyId)
                    ->orWhere('r.pharmacy_id', $pharmacyId);
                if (!empty($accountIds)) {
                    $q->orWhereIn('fp.account_id', $accountIds);
                }
            })
            ->whereIn('fp.payment_type', ['KREDIT', 'CASH', 'KONSINYASI'])
            ->whereDate('fp.payment_date', '>=', $start)
            ->whereDate('fp.payment_date', '<=', $end)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('receiving_items as ri')
                    ->whereColumn('ri.receiving_details_id', 'rd.id')
                    ->whereNotNull('ri.batches_id');
            })
            ->orderBy('fp.id')
            ->get([
                'fp.id as fp_id',
                'fp.account_id',
                'fp.payment_date',
                'fp.amount',
                'r.id as rec_id',
                'r.code as rec_code',
                'r.date as rec_date',
                'r.created_at as rec_created_at',
                'rd.invoice_number',
            ])
            ->filter(function ($row) use ($trialBefore) {
                $recDate = $this->normalizeDate($row->rec_date, $row->rec_created_at);
                $row->normalized_rec_date = $recDate;
                return $recDate < $trialBefore;
            })
            ->values();
    }

    /**
     * @param array<int, int> $accountIds
     */
    private function collectDeletedDetailPayments(int $pharmacyId, array $accountIds, string $start, string $end)
    {
        return DB::table('finance_payments as fp')
            ->leftJoin('receiving_details as rd', 'rd.id', '=', 'fp.receiving_detail_id')
            ->leftJoin('receiving as r', 'r.id', '=', 'fp.receiving_id')
            ->where(function ($q) use ($pharmacyId, $accountIds) {
                $q->where('fp.pharmacy_id', $pharmacyId);
                if (!empty($accountIds)) {
                    $q->orWhereIn('fp.account_id', $accountIds);
                }
            })
            ->whereIn('fp.payment_type', ['KREDIT', 'CASH', 'KONSINYASI'])
            ->whereDate('fp.payment_date', '>=', $start)
            ->whereDate('fp.payment_date', '<=', $end)
            ->where(function ($q) {
                $q->whereNull('fp.receiving_detail_id')
                    ->orWhereNull('rd.id');
            })
            ->orderBy('fp.id')
            ->get([
                'fp.id as fp_id',
                'fp.account_id',
                'fp.payment_date',
                'fp.amount',
                'fp.receiving_id',
                'r.code as rec_code',
            ]);
    }

    private function collectCrossMonthDetails(int $pharmacyId, string $start, string $end)
    {
        return DB::table('receiving_details as rd')
            ->join('receiving as r', 'r.id', '=', 'rd.receiving_id')
            ->where('r.pharmacy_id', $pharmacyId)
            ->whereIn('r.status', [1, 2, 3])
            ->whereDate('rd.created_at', '>=', $start)
            ->whereDate('rd.created_at', '<=', $end)
            ->whereNotNull('rd.invoice_date')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('receiving_items as ri')
                    ->whereColumn('ri.receiving_details_id', 'rd.id')
                    ->whereNotNull('ri.batches_id');
            })
            ->orderBy('rd.id')
            ->get([
                'rd.id',
                'rd.invoice_number',
                'rd.invoice_date',
                'rd.created_at as rd_created_at',
                'r.date as rec_date',
                'r.created_at as rec_created_at',
            ])
            ->filter(function ($row) use ($start, $end) {
                $recDate = $this->normalizeDate($row->rec_date, $row->rec_created_at);
                $invDate = $this->normalizeDate($row->invoice_date, $recDate);
                $row->target_rec_date = $recDate;
                $row->normalized_inv_date = $invDate;
                return $invDate < $start && $recDate >= $start && $recDate <= $end;
            })
            ->values();
    }

    private function buildAccountOpnamePlans(
        $accounts,
        string $start,
        string $end,
        $missingReturns,
        $discountFixes,
        $trialPayments,
        $deletedDetailPayments
    ): array {
        $deltas = [];

        $addDelta = function (int $accountId, string $date, float $delta) use (&$deltas, $accounts) {
            if (!isset($deltas[$accountId])) {
                $acc = $accounts->get($accountId);
                $deltas[$accountId] = [
                    'account_name' => $acc?->name ?? "Akun #{$accountId}",
                    'net_flow_delta' => 0.0,
                    'max_date' => $date,
                    'opname_fp' => null,
                    'old_signed_opname' => 0.0,
                    'new_signed_opname' => 0.0,
                ];
            }
            $deltas[$accountId]['net_flow_delta'] = round($deltas[$accountId]['net_flow_delta'] + $delta, 2);
            if ($date > $deltas[$accountId]['max_date']) {
                $deltas[$accountId]['max_date'] = $date;
            }
        };

        foreach ($missingReturns as $ret) {
            $addDelta((int) $ret->target_account_id, (string) $ret->ops_date, -$ret->amount);
        }
        foreach ($discountFixes as $fix) {
            $addDelta((int) $fix->account_id, (string) $fix->payment_date, -$fix->excess);
        }
        foreach ($trialPayments as $tp) {
            $addDelta((int) $tp->account_id, (string) $tp->payment_date, (float) $tp->amount);
        }
        foreach ($deletedDetailPayments as $dp) {
            $addDelta((int) $dp->account_id, (string) $dp->payment_date, (float) $dp->amount);
        }

        foreach ($deltas as $accId => &$plan) {
            if (abs($plan['net_flow_delta']) < 0.01) {
                unset($deltas[$accId]);
                continue;
            }

            $opnameFp = FinancePayment::where('account_id', $accId)
                ->where('payment_type', 'PENYESUAIAN SALDO')
                ->whereDate('payment_date', '>=', $plan['max_date'])
                ->whereDate('payment_date', '<=', $end)
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->first();

            if ($opnameFp) {
                $oldSigned = $opnameFp->reference_number === 'OPNAME-OUT'
                    ? -(float) $opnameFp->amount
                    : (float) $opnameFp->amount;
                $plan['opname_fp'] = $opnameFp;
                $plan['old_signed_opname'] = round($oldSigned, 2);
                $plan['new_signed_opname'] = round($oldSigned - $plan['net_flow_delta'], 2);
            }
        }
        unset($plan);

        ksort($deltas);
        return $deltas;
    }

    private function printPlanSummary(
        $missingReturns,
        $discountFixes,
        $trialPayments,
        $deletedDetailPayments,
        $crossMonthDetails,
        array $accountPlans
    ): void {
        $this->newLine();
        $this->info('--- RENCANA PERUBAHAN PER KOMPONEN ---');
        $this->line('1. Backfill RETUR PENJUALAN       : ' . $missingReturns->count() . ' trx | Total=' . $this->fmt($missingReturns->sum('amount')));
        $this->line('2. Koreksi Diskon Penjualan       : ' . $discountFixes->count() . ' fp  | Total=' . $this->fmt($discountFixes->sum('excess')));
        $this->line('3. Hapus Pelunasan Trial Agustus  : ' . $trialPayments->count() . ' fp  | Total=' . $this->fmt($trialPayments->sum(fn ($r) => (float) $r->amount)));
        $this->line('4. Hapus Payment Tanpa Detail     : ' . $deletedDetailPayments->count() . ' fp  | Total=' . $this->fmt($deletedDetailPayments->sum(fn ($r) => (float) $r->amount)));
        $this->line('5. Selaraskan Invoice Lintas Bln  : ' . $crossMonthDetails->count() . ' rd');

        $this->newLine();
        $this->info('--- PENYEIMBANG OPNAME PER AKUN (MENJAGA SALDO AKHIR = 0 BERGESER) ---');
        if (empty($accountPlans)) {
            $this->line('Tidak ada mutasi akun yang perlu disinkronkan.');
            return;
        }

        foreach ($accountPlans as $accId => $plan) {
            $opFp = $plan['opname_fp'];
            if (!$opFp) {
                $this->error("   [#{$accId}] {$plan['account_name']} | delta=" . $this->fmt($plan['net_flow_delta']) . " | OPNAME TIDAK DITEMUKAN (>= {$plan['max_date']})");
                continue;
            }
            $opDate = $opFp->payment_date?->format('Y-m-d') ?? '-';
            $this->line("   [#{$accId}] {$plan['account_name']} | delta_operasional=" . $this->fmt($plan['net_flow_delta'])
                . " | Opname FP #{$opFp->id} ({$opDate}): "
                . $this->fmt($plan['old_signed_opname']) . ' -> ' . $this->fmt($plan['new_signed_opname'])
                . ' | Efek ke saldo akhir periode: 0,00');
        }
    }

    private function normalizeDate(?string $raw, mixed $fallback = null): string
    {
        $val = trim((string) ($raw ?? ''));
        if ($val !== '') {
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $val, $m)) {
                return "{$m[3]}-{$m[2]}-{$m[1]}";
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $val)) {
                return substr($val, 0, 10);
            }
        }

        if ($fallback instanceof Carbon) {
            return $fallback->toDateString();
        }
        return $fallback ? Carbon::parse((string) $fallback)->toDateString() : '1970-01-01';
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return !in_array(strtolower(trim((string) $value)), ['0', 'false', 'no', 'off', ''], true);
    }

    private function fmt(mixed $amount): string
    {
        return number_format((float) $amount, 2, ',', '.');
    }
}
