<?php

namespace App\Console\Commands;

use App\Models\FinanceAccount;
use App\Models\Pharmacies;
use App\Services\Finance\KinerjaOmzetService;
use App\Services\Finance\NeracaReportService;
use App\Support\FinanceShift;
use App\Support\ReceivingInvoiceTotals;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Audit read-only untuk memeriksa komponen selisih Neraca (Laba Operasional vs Pembanding)
 * serta status Opname tiap akun Kas & Bank sebelum melakukan rekonsiliasi data historis.
 */
class AuditNeracaReconciliation extends Command
{
    protected $signature = 'finance:audit-neraca-reconciliation
        {--pharmacy_id=2 : ID cabang apotek (default: 2 Sahabat Mulawarman)}
        {--start=2026-11-01 : Tanggal awal periode audit (Y-m-d)}
        {--end=2026-11-30 : Tanggal akhir periode audit (Y-m-d)}';

    protected $description = 'Audit read-only penyebab selisih Neraca (Retur Jual, Diskon Penjualan, Pembayaran tanpa detail/batch, Faktur lintas bulan, dan Opname akun)';

    public function handle(NeracaReportService $neracaService, KinerjaOmzetService $kinerjaService): int
    {
        $pharmacyId = (int) $this->option('pharmacy_id');
        $start = Carbon::parse((string) $this->option('start'))->toDateString();
        $end = Carbon::parse((string) $this->option('end'))->toDateString();

        if ($start > $end) {
            $this->error("Tanggal --start ({$start}) tidak boleh melebihi --end ({$end}).");
            return self::FAILURE;
        }

        $pharmacy = Pharmacies::find($pharmacyId);
        if (!$pharmacy) {
            $this->error("Cabang dengan pharmacy_id={$pharmacyId} tidak ditemukan.");
            return self::FAILURE;
        }

        $this->info("=== AUDIT REKONSILIASI NERACA (READ-ONLY) ===");
        $this->line("Cabang  : {$pharmacy->name} (ID: {$pharmacyId})");
        $this->line("Periode : {$start} s/d {$end}");

        $this->printNeracaVsKinerjaSummary($neracaService, $kinerjaService, $pharmacyId, $start, $end);

        [$accounts, $opnameMap] = $this->auditAccountsAndOpnames($pharmacyId, $start, $end);
        $accountIds = $accounts->pluck('id')->map(fn ($id) => (int) $id)->all();
        $cashAccountId = (int) ($accounts->firstWhere('account_type', 'kas_apotek')?->id ?? 0);

        $this->auditMissingReturJualPayments($pharmacyId, $start, $end, $cashAccountId, $opnameMap);
        $this->auditSalesDiscountOverpayments($pharmacyId, $start, $end, $opnameMap);
        $this->auditPaymentsWithoutActiveDetails($pharmacyId, $accountIds, $start, $end);
        $this->auditPaymentsOnUnbatchedReceivings($pharmacyId, $accountIds, $start, $end);
        $this->auditCrossMonthReceivingDetails($pharmacyId, $start, $end);

        $this->newLine();
        $this->info("=== AUDIT SELESAI (TIDAK ADA DATA YANG DIUBAH) ===");

        return self::SUCCESS;
    }

    private function printNeracaVsKinerjaSummary(
        NeracaReportService $neracaService,
        KinerjaOmzetService $kinerjaService,
        int $pharmacyId,
        string $start,
        string $end
    ): void {
        $neraca = $neracaService->build([$pharmacyId], $start, $end, $pharmacyId);
        $labaTanpaStok = (float) ($neraca['laba_tanpa_stok']['selisih'] ?? 0);
        $nonOp = $neraca['penyesuaian_non_operasional'] ?? [];

        $this->newLine();
        $this->info("--- 0. RINGKASAN NERACA VS PEMBANDING ---");
        $this->line("Selisih Posisi Bersih (Akhir - Awal) : " . $this->fmt($neraca['laba_tanpa_stok']['raw_selisih'] ?? 0));
        $this->line("Penyesuaian Non-Op Total             : " . $this->fmt($nonOp['total'] ?? 0)
            . " (Opname=" . $this->fmt($nonOp['opname_kas_bank'] ?? 0)
            . ", OpeningHutang=" . $this->fmt($nonOp['opening_hutang'] ?? 0) . ")");
        $this->line("LABA TANPA STOK (Neraca)             : " . $this->fmt($labaTanpaStok));

        try {
            $kinerja = $kinerjaService->build([$pharmacyId], $start, $end);
            $sum = KinerjaOmzetService::summarizeDays($kinerja['days'] ?? []);

            $omzet = (float) ($sum['penjualanTotal'] ?? 0);
            $pembelian = (float) ($sum['pembelianNetto'] ?? 0);
            $biaya = (float) ($sum['biayaTotal'] ?? 0);
            $pendapatanLain = (float) ($sum['pendapatanLainTotal'] ?? 0);
            $diskonOff = (float) ($sum['diskonOffTotal'] ?? 0);
            $pembanding = round($omzet - $pembelian - $biaya + $pendapatanLain + $diskonOff, 2);

            $this->line("Pembanding (Omzet-Beli-Biaya+Pend+D) : " . $this->fmt($pembanding));
            $this->line("Selisih (Neraca - Pembanding)        : " . $this->fmt($labaTanpaStok - $pembanding));
        } catch (\Throwable $e) {
            $this->warn("Pembanding KinerjaOmzet dilewati ({$e->getMessage()}).");
        }
    }

    /**
     * @return array{0: Collection<int, object>, 1: array<int, array<int, string>>}
     */
    private function auditAccountsAndOpnames(int $pharmacyId, string $start, string $end): array
    {
        $accounts = FinanceAccount::where('pharmacy_id', $pharmacyId)
            ->where('category', 'Kas & Bank')
            ->orderBy('id')
            ->get(['id', 'name', 'account_type', 'balance', 'is_active']);

        $accountIds = $accounts->pluck('id')->map(fn ($id) => (int) $id)->all();
        $opnameMap = [];

        $this->newLine();
        $this->info("--- 1. AKUN KAS & BANK, SALDO AWAL, DAN OPNAME ({$start}..{$end}) ---");

        if ($accounts->isEmpty()) {
            $this->warn("Tidak ada akun Kas & Bank untuk cabang {$pharmacyId}.");
            return [$accounts, $opnameMap];
        }

        $openings = DB::table('finance_payments')
            ->whereIn('account_id', $accountIds)
            ->where('payment_type', 'SALDO AWAL')
            ->whereDate('payment_date', '<=', $end)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get(['id', 'account_id', 'payment_date', 'amount'])
            ->groupBy('account_id');

        $opnamePayments = DB::table('finance_payments')
            ->whereIn('account_id', $accountIds)
            ->where('payment_type', 'PENYESUAIAN SALDO')
            ->whereDate('payment_date', '>=', $start)
            ->whereDate('payment_date', '<=', $end)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get(['id', 'account_id', 'payment_date', 'amount', 'reference_number', 'notes'])
            ->groupBy('account_id');

        $adjustments = DB::table('finance_balance_adjustments')
            ->whereIn('account_id', $accountIds)
            ->whereDate('adjustment_date', '>=', $start)
            ->whereDate('adjustment_date', '<=', $end)
            ->orderBy('adjustment_date')
            ->orderBy('id')
            ->get(['id', 'account_id', 'adjustment_date', 'system_balance', 'real_balance', 'difference', 'finance_payment_id', 'notes'])
            ->groupBy('account_id');

        foreach ($accounts as $acc) {
            $accId = (int) $acc->id;
            $op = $openings->get($accId)?->first();
            $opStr = $op ? "{$op->payment_date} (" . $this->fmt($op->amount) . ")" : '-';
            $status = $acc->is_active ? 'Aktif' : 'Nonaktif';

            $this->line("[#{$accId}] {$acc->name} ({$acc->account_type}, {$status}) | Balance DB=" . $this->fmt($acc->balance) . " | Saldo Awal={$opStr}");

            foreach ($opnamePayments->get($accId, collect()) as $row) {
                $dateStr = Carbon::parse($row->payment_date)->toDateString();
                $opnameMap[$accId][] = $dateStr;
                $this->line("   - FP PENYESUAIAN #{$row->id} | {$dateStr} | {$row->reference_number} | " . $this->fmt($row->amount) . " | {$row->notes}");
            }

            foreach ($adjustments->get($accId, collect()) as $adj) {
                $dateStr = Carbon::parse($adj->adjustment_date)->toDateString();
                $opnameMap[$accId][] = $dateStr;
                $this->line("   - ADJ LOG #{$adj->id} | {$dateStr} | sys=" . $this->fmt($adj->system_balance)
                    . " -> real=" . $this->fmt($adj->real_balance)
                    . " (diff=" . $this->fmt($adj->difference) . ") fp_id=" . ($adj->finance_payment_id ?? 'null'));
            }

            if (!empty($opnameMap[$accId])) {
                $opnameMap[$accId] = array_values(array_unique($opnameMap[$accId]));
                sort($opnameMap[$accId]);
            }
        }

        return [$accounts, $opnameMap];
    }

    /**
     * @param array<int, array<int, string>> $opnameMap
     */
    private function auditMissingReturJualPayments(
        int $pharmacyId,
        string $start,
        string $end,
        int $cashAccountId,
        array $opnameMap
    ): void {
        $windowStart = FinanceShift::start($start)->toDateTimeString();
        $windowEnd = FinanceShift::endExclusive($end)->toDateTimeString();

        $returns = DB::table('medicine_transactions as mt')
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
            ->get(['mt.id', 'mt.transaction_code', 'mt.transaction_type', 'mt.subtotal', 'mt.status', 'mt.created_at']);

        $total = $returns->sum(fn ($r) => abs((float) $r->subtotal));
        $maxOpDate = '-';
        $opnameDates = $opnameMap[$cashAccountId] ?? [];

        $this->newLine();
        $this->info("--- 2. RETUR JUAL TANPA FINANCE_PAYMENTS (RETUR PENJUALAN) ---");
        $this->line("Jumlah: {$returns->count()} transaksi | Total: " . $this->fmt($total) . " | Target Akun Kasir ID: {$cashAccountId}");

        foreach ($returns as $r) {
            $opDate = FinanceShift::operationalDate(Carbon::parse($r->created_at)) ?? substr((string) $r->created_at, 0, 10);
            if ($opDate > $maxOpDate) {
                $maxOpDate = $opDate;
            }
            $hasOpnameAfter = $this->hasDateOnOrAfter($opnameDates, $opDate) ? 'YA (perlu penyeimbang Opname)' : 'TIDAK';
            $this->line("   - trx#{$r->id} | {$r->created_at} (ops={$opDate}) | {$r->transaction_type} | " . $this->fmt(abs((float) $r->subtotal)) . " | Opname>=ops: {$hasOpnameAfter}");
        }
    }

    /**
     * @param array<int, array<int, string>> $opnameMap
     */
    private function auditSalesDiscountOverpayments(int $pharmacyId, string $start, string $end, array $opnameMap): void
    {
        $rows = DB::table('finance_payments as fp')
            ->join('medicine_transactions as mt', 'mt.id', '=', 'fp.medicine_transaction_id')
            ->leftJoin('finance_accounts as fa', 'fa.id', '=', 'fp.account_id')
            ->where('mt.pharmacy_id', $pharmacyId)
            ->where('mt.status', 1)
            ->whereIn('fp.payment_type', ['PENJUALAN TUNAI', 'PENJUALAN NON TUNAI'])
            ->whereDate('fp.payment_date', '>=', $start)
            ->whereDate('fp.payment_date', '<=', $end)
            ->whereRaw('COALESCE(mt.discount, 0) > 0')
            ->orderBy('fp.id')
            ->get([
                'fp.id as fp_id',
                'fp.account_id',
                'fa.name as account_name',
                'fp.payment_type',
                'fp.payment_date',
                'fp.amount',
                'mt.id as trx_id',
                'mt.transaction_code',
                'mt.subtotal',
                'mt.discount',
                'mt.paid',
            ]);

        $anomalies = [];
        $byAccount = [];

        foreach ($rows as $row) {
            $expectedNet = round(max(0.0, (float) $row->subtotal - (float) ($row->discount ?? 0)), 2);
            $recorded = round((float) $row->amount, 2);
            $excess = round($recorded - $expectedNet, 2);

            if (abs($excess) <= 0.01) {
                continue;
            }

            $accId = (int) $row->account_id;
            $row->expected_net = $expectedNet;
            $row->excess = $excess;
            $anomalies[] = $row;

            if (!isset($byAccount[$accId])) {
                $byAccount[$accId] = [
                    'name' => $row->account_name ?? "Akun #{$accId}",
                    'count' => 0,
                    'excess' => 0.0,
                    'max_date' => $row->payment_date,
                ];
            }
            $byAccount[$accId]['count']++;
            $byAccount[$accId]['excess'] += $excess;
            if ((string) $row->payment_date > (string) $byAccount[$accId]['max_date']) {
                $byAccount[$accId]['max_date'] = $row->payment_date;
            }
        }

        $totalExcess = array_sum(array_map(fn ($r) => $r->excess, $anomalies));

        $this->newLine();
        $this->info("--- 3. PENJUALAN DENGAN SELISIH DISKON DI FINANCE_PAYMENTS ---");
        $this->line("Jumlah: " . count($anomalies) . " transaksi | Total Selisih: " . $this->fmt($totalExcess));

        foreach ($byAccount as $accId => $info) {
            $hasOpnameAfter = $this->hasDateOnOrAfter($opnameMap[$accId] ?? [], (string) $info['max_date'])
                ? 'YA (ada Opname >= ' . $info['max_date'] . ')'
                : 'TIDAK';
            $this->line("   * Akun #{$accId} ({$info['name']}): {$info['count']} trx | Selisih=" . $this->fmt($info['excess']) . " | Opname setelahnya: {$hasOpnameAfter}");
        }

        foreach ($anomalies as $a) {
            $this->line("   - fp#{$a->fp_id} (trx#{$a->trx_id} {$a->transaction_code}) | {$a->payment_date} | akun={$a->account_name} | sub=" . $this->fmt($a->subtotal) . " disc=" . $this->fmt($a->discount) . " | tercatat=" . $this->fmt($a->amount) . " | seharusnya=" . $this->fmt($a->expected_net) . " | selisih=" . $this->fmt($a->excess));
        }
    }

    /**
     * @param array<int, int> $accountIds
     */
    private function auditPaymentsWithoutActiveDetails(int $pharmacyId, array $accountIds, string $start, string $end): void
    {
        $payments = DB::table('finance_payments as fp')
            ->leftJoin('receiving_details as rd', 'rd.id', '=', 'fp.receiving_detail_id')
            ->leftJoin('receiving as r', 'r.id', '=', 'fp.receiving_id')
            ->leftJoin('finance_accounts as fa', 'fa.id', '=', 'fp.account_id')
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
                'fp.id',
                'fp.account_id',
                'fa.name as account_name',
                'fp.payment_type',
                'fp.payment_date',
                'fp.amount',
                'fp.receiving_id',
                'fp.receiving_detail_id',
                'fp.notes',
                'r.code as rec_code',
                'r.date as rec_date',
                'r.status as rec_status',
            ]);

        $total = $payments->sum(fn ($p) => (float) $p->amount);

        $this->newLine();
        $this->info("--- 4. PEMBAYARAN PEMBELIAN TANPA RECEIVING_DETAILS AKTIF ---");
        $this->line("Jumlah: {$payments->count()} payment | Total: " . $this->fmt($total));

        foreach ($payments as $p) {
            $activeItems = $p->receiving_id
                ? DB::table('receiving_items as ri')
                    ->join('receiving_details as rd2', 'rd2.id', '=', 'ri.receiving_details_id')
                    ->where('rd2.receiving_id', $p->receiving_id)
                    ->count()
                : 0;
            $this->line("   - fp#{$p->id} | {$p->payment_date} | {$p->payment_type} | akun={$p->account_name} (#{$p->account_id}) | amt=" . $this->fmt($p->amount) . " | rec#{$p->receiving_id} ({$p->rec_code}, date={$p->rec_date}, status={$p->rec_status}, active_items={$activeItems}) | {$p->notes}");
        }
    }

    /**
     * @param array<int, int> $accountIds
     */
    private function auditPaymentsOnUnbatchedReceivings(int $pharmacyId, array $accountIds, string $start, string $end): void
    {
        $payments = DB::table('finance_payments as fp')
            ->join('receiving_details as rd', 'rd.id', '=', 'fp.receiving_detail_id')
            ->join('receiving as r', 'r.id', '=', 'rd.receiving_id')
            ->leftJoin('finance_accounts as fa', 'fa.id', '=', 'fp.account_id')
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
            ->orderBy('r.id')
            ->orderBy('fp.id')
            ->get([
                'fp.id as fp_id',
                'fp.account_id',
                'fa.name as account_name',
                'fp.payment_type',
                'fp.payment_date',
                'fp.amount',
                'r.id as rec_id',
                'r.code as rec_code',
                'r.date as rec_date',
                'r.status as rec_status',
                'rd.id as rd_id',
                'rd.invoice_number',
                'rd.invoice_payment',
            ]);

        $grouped = $payments->groupBy('rec_id');
        $total = $payments->sum(fn ($p) => (float) $p->amount);

        $this->newLine();
        $this->info("--- 5. PEMBAYARAN ATAS FAKTUR TANPA BATCH (with_batch = 0) ---");
        $this->line("Jumlah: {$payments->count()} payment pada {$grouped->count()} receiving | Total: " . $this->fmt($total));

        foreach ($grouped as $recId => $rows) {
            $first = $rows->first();
            $sumAmt = $rows->sum(fn ($r) => (float) $r->amount);
            $invoices = $rows->pluck('invoice_number')->filter()->unique()->implode(', ');
            $payDates = $rows->pluck('payment_date')->unique()->implode(', ');
            $accounts = $rows->map(fn ($r) => ($r->account_name ?? ('#' . $r->account_id)))->unique()->implode(', ');
            $itemsCount = DB::table('receiving_items as ri')
                ->whereIn('ri.receiving_details_id', $rows->pluck('rd_id')->unique()->all())
                ->count();
            $this->line("   - rec#{$recId} ({$first->rec_code}) | rec_date={$first->rec_date} | status={$first->rec_status} | items_cnt={$itemsCount} | pay_date=[{$payDates}] | akun=[{$accounts}] | cnt={$rows->count()} | total=" . $this->fmt($sumAmt) . " | faktur=[{$invoices}]");
        }
    }

    private function auditCrossMonthReceivingDetails(int $pharmacyId, string $start, string $end): void
    {
        $lookbackStart = Carbon::parse($start)->subMonths(2)->toDateString();

        $details = DB::table('receiving_details as rd')
            ->join('receiving as r', 'r.id', '=', 'rd.receiving_id')
            ->leftJoin('creditors as c', 'c.code', '=', 'rd.creditor_code')
            ->where('r.pharmacy_id', $pharmacyId)
            ->whereIn('r.status', [1, 2, 3])
            ->where(function ($q) use ($lookbackStart, $end) {
                $q->whereDate('rd.created_at', '>=', $lookbackStart)
                    ->whereDate('rd.created_at', '<=', Carbon::parse($end)->addMonths(2)->toDateString());
            })
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
                'rd.invoice_payment',
                'rd.invoice_ppn',
                'rd.created_at as rd_created_at',
                'r.id as rec_id',
                'r.code as rec_code',
                'r.date as rec_date',
                'r.created_at as rec_created_at',
                'c.ppn_type as cred_ppn',
            ]);

        $cross = [];
        foreach ($details as $d) {
            $recDate = $this->normalizeDate($d->rec_date, $d->rec_created_at);
            $effDate = $this->normalizeDate($d->invoice_date, $recDate);
            $rdDate = Carbon::parse($d->rd_created_at)->toDateString();

            $rdInPeriod = ($rdDate >= $start && $rdDate <= $end);
            $effInPeriod = ($effDate >= $start && $effDate <= $end);

            if ($rdInPeriod === $effInPeriod) {
                continue;
            }

            $items = DB::table('receiving_items as ri')
                ->leftJoin('order_items as oi', 'oi.id', '=', 'ri.order_items_id')
                ->where('ri.receiving_details_id', $d->id)
                ->select('ri.qty_received', 'ri.qty', 'ri.raw_price', 'ri.discount', 'ri.extra_discount', 'ri.total', 'oi.price as oi_price')
                ->get();

            $ppnType = strtoupper(trim((string) ($d->invoice_ppn ?? $d->cred_ppn ?? 'TANPA')));
            $calc = ReceivingInvoiceTotals::calculate($items, $ppnType);

            $d->normalized_eff_date = $effDate;
            $d->normalized_rec_date = $recDate;
            $d->normalized_rd_date = $rdDate;
            $d->direction = $rdInPeriod ? 'MASUK_BELI_PERIODE_TP_TGL_FAKTUR_DILUAR' : 'TGL_FAKTUR_PERIODE_TP_RD_DILUAR';
            $d->invoice_total = $calc['total'];
            $cross[] = $d;
        }

        $totalIn = array_sum(array_map(
            fn ($x) => $x->direction === 'MASUK_BELI_PERIODE_TP_TGL_FAKTUR_DILUAR' ? $x->invoice_total : 0.0,
            $cross
        ));
        $totalOut = array_sum(array_map(
            fn ($x) => $x->direction === 'TGL_FAKTUR_PERIODE_TP_RD_DILUAR' ? $x->invoice_total : 0.0,
            $cross
        ));

        $this->newLine();
        $this->info("--- 6. FAKTUR LINTAS PERIODE (receiving/invoice_date VS receiving_details.created_at) ---");
        $this->line("Jumlah: " . count($cross) . " faktur | rd di periode & tgl faktur di luar=" . $this->fmt($totalIn) . " | tgl faktur di periode & rd di luar=" . $this->fmt($totalOut));

        foreach ($cross as $c) {
            $this->line("   - rd#{$c->id} ({$c->invoice_number}) | {$c->invoice_payment} | {$c->direction} | rec_date={$c->normalized_rec_date} | inv_date={$c->normalized_eff_date} | rd_created={$c->normalized_rd_date} | total=" . $this->fmt($c->invoice_total));
        }
    }

    /**
     * @param array<int, string> $dates
     */
    private function hasDateOnOrAfter(array $dates, string $thresholdDate): bool
    {
        foreach ($dates as $d) {
            if ($d >= $thresholdDate) {
                return true;
            }
        }
        return false;
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

    private function fmt(mixed $amount): string
    {
        return number_format((float) $amount, 2, ',', '.');
    }
}
