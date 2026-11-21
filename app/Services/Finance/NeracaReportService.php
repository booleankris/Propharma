<?php

namespace App\Services\Finance;

use App\Models\FinanceAccount;
use App\Models\FinancePayment;
use App\Models\MedicineTransactions;
use App\Models\ReceivingDetails;
use App\Services\ConsignmentService;
use App\Support\FinanceAccountBalanceResolver;
use App\Support\FinanceShift;
use App\Support\LiphTransactionScope;
use App\Support\ReceivingInvoiceTotals;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NeracaReportService
{
    /**
     * Build Neraca (Balance Sheet) data comparing beginning and ending of period.
     */
    public function build(array $pharmacyIds, string $startDate, string $endDate, ?int $accountPharmacyId = null, ?array $kinerjaDays = null): array
    {
        $pharmacyId = $accountPharmacyId && $accountPharmacyId > 0
            ? $accountPharmacyId
            : ((function_exists('getActivePharmacyId') && (int) getActivePharmacyId() > 0)
                ? (int) getActivePharmacyId()
                : (int) ($pharmacyIds[0] ?? 1));
        $dayBeforeStart = Carbon::parse($startDate)->subDay()->toDateString();
        $todayDate = now()->toDateString();
        $todayOperationalDate = FinanceShift::operationalDate(now());
        $periodEnd = max($startDate, min($endDate, $todayOperationalDate));

        if ($pharmacyId > 0) {
            FinanceAccount::ensureBranchDefaultAccounts($pharmacyId);
        }

        // 1. Kas & Bank accounts of the active pharmacy
        $accounts = FinanceAccount::where('category', 'Kas & Bank')
            ->where('pharmacy_id', $pharmacyId)
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('balance', '!=', 0))
            ->orderByRaw("CASE WHEN account_type = 'kas_apotek' OR code = '1-10001' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->get(['id', 'name', 'code', 'account_type', 'balance']);

        $preferredBankOrder = ['BANK BNI', 'BANK MANDIRI', 'BANK BRI', 'BANK BCA', 'BANK BTN', 'BANK BPD'];
        $accountIds = $accounts->pluck('id')->all();
        $accountsWithOpening = !empty($accountIds)
            ? FinancePayment::where('payment_type', 'SALDO AWAL')
                ->whereIn('account_id', $accountIds)
                ->pluck('account_id')
                ->all()
            : [];

        $kasRows = [];
        $bankRows = [];
        $hasEstimatedOpening = false;

        foreach ($accounts as $account) {
            $openingBalance = FinanceAccountBalanceResolver::resolveOpeningBalanceAtStartDate($account, $startDate, $todayDate);
            $closingBalance = FinanceAccountBalanceResolver::resolveClosingBalanceAsOf($account, $periodEnd, $todayDate);

            if (
                !in_array($account->id, $accountsWithOpening, true)
                && (abs($openingBalance) >= 0.01 || abs($closingBalance) >= 0.01)
            ) {
                $hasEstimatedOpening = true;
            }

            $name = strtoupper(trim((string) $account->name));
            $isKas = $account->account_type === 'kas_apotek' || $account->code === '1-10001';
            if ($isKas) {
                $kasRows[] = [
                    'label' => 'KAS',
                    'awal' => $openingBalance,
                    'akhir' => $closingBalance,
                    'selisih' => round($closingBalance - $openingBalance, 2),
                ];
            } else {
                // Normalize label (e.g. Rekening BCA -> BANK BCA)
                $label = str_starts_with($name, 'REKENING ')
                    ? 'BANK ' . substr($name, 9)
                    : (str_starts_with($name, 'BANK ') ? $name : 'BANK ' . $name);
                $bankRows[] = [
                    'label' => $label,
                    'code' => (string) ($account->code ?? ''),
                    'awal' => $openingBalance,
                    'akhir' => $closingBalance,
                    'selisih' => round($closingBalance - $openingBalance, 2),
                ];
            }
        }

        // If KAS was not in accounts, ensure a KAS row exists
        if (empty($kasRows)) {
            $kasRows[] = ['label' => 'KAS', 'awal' => 0.0, 'akhir' => 0.0, 'selisih' => 0.0];
        }

        // Sort actual branch banks in consistent template order without injecting nonexistent ghost banks
        usort($bankRows, function ($a, $b) use ($preferredBankOrder) {
            $posA = array_search($a['label'], $preferredBankOrder, true);
            $posB = array_search($b['label'], $preferredBankOrder, true);
            $posA = $posA === false ? 999 : $posA;
            $posB = $posB === false ? 999 : $posB;
            if ($posA !== $posB) {
                return $posA <=> $posB;
            }
            return strcmp((string) ($a['code'] ?? ''), (string) ($b['code'] ?? ''));
        });
        $bankRows = array_map(function ($row) {
            unset($row['code']);
            return $row;
        }, $bankRows);

        // 2. Piutang Dagang (Penjualan Kredit yang belum lunas)
        $piutangAwal = $this->receivableBalanceAtDate($pharmacyIds, $dayBeforeStart);
        $piutangAkhir = $this->receivableBalanceAtDate($pharmacyIds, $periodEnd);
        $piutangDagangRow = [
            'label' => 'PIUTANG DAGANG',
            'awal' => $piutangAwal,
            'akhir' => $piutangAkhir,
            'selisih' => $piutangAkhir - $piutangAwal,
        ];

        // 3. Piutang Usaha (0 sesuai konfirmasi)
        $piutangUsahaRow = [
            'label' => 'PIUTANG USAHA',
            'awal' => 0.0,
            'akhir' => 0.0,
            'selisih' => 0.0,
        ];

        // 4. Hutang Dagang (Pembelian Kredit yang belum lunas)
        $hutangAwal = $this->payableBalanceAtDate($pharmacyIds, $dayBeforeStart);
        $hutangAkhir = $this->payableBalanceAtDate($pharmacyIds, $periodEnd);
        // Note: For liability, SELISIH in template is AWAL - AKHIR (or -(AKHIR - AWAL))
        // because increasing liability decreases net worth/profit.
        $hutangDagangRow = [
            'label' => 'HUTANG DAGANG',
            'awal' => $hutangAwal,
            'akhir' => $hutangAkhir,
            'selisih' => $hutangAwal - $hutangAkhir,
            'is_liability' => true,
        ];

        // 5. Total Aset Lancar (Kas + Bank + Piutang) untuk perhitungan Harta & Saldo Ekuitas
        $totalAsetLancarAwal = array_sum(array_column($kasRows, 'awal'))
            + array_sum(array_column($bankRows, 'awal'))
            + $piutangAwal;
        $totalAsetLancarAkhir = array_sum(array_column($kasRows, 'akhir'))
            + array_sum(array_column($bankRows, 'akhir'))
            + $piutangAkhir;

        // 5b. Laba Tanpa Stok mengikuti rumus Halaman Ringkasan Keuangan (bulan berjalan):
        // Laba = Penjualan - Pembelian Netto - Biaya + (Pendapatan Lain-lain + Diskon Off)
        // AWAL = Akumulasi Laba YTD s/d bulan sebelumnya; AKHIR = Akumulasi Laba YTD s/d bulan berjalan;
        // SELISIH = Laba Bulan Berjalan (100% identik dengan kartu Laba di Halaman Ringkasan).
        $periodCarbon = Carbon::parse($startDate);
        $daysForProfit = $kinerjaDays !== null
            ? $kinerjaDays
            : ((new KinerjaOmzetService())->build($pharmacyIds, $startDate, $endDate)['days'] ?? []);
        $periodProfitSummary = KinerjaOmzetService::summarizeDays($daysForProfit);
        $ytdBeforeSummary = KinerjaOmzetService::summarizeYtdBeforeMonth(
            $pharmacyIds,
            (int) $periodCarbon->year,
            (int) $periodCarbon->month
        );

        $labaBulanBerjalan = (float) $periodProfitSummary['laba'];
        $labaTanpaStokAwal = (float) $ytdBeforeSummary['laba'];
        $labaTanpaStokAkhir = round($labaTanpaStokAwal + $labaBulanBerjalan, 2);
        $labaTanpaStok = [
            'label' => 'LABA TANPA STOK',
            'awal' => $labaTanpaStokAwal,
            'akhir' => $labaTanpaStokAkhir,
            'selisih' => round($labaBulanBerjalan, 2),
        ];

        // 6. Persediaan: Nilai total stok (Stok * raw_price)
        $persediaanAwal = $this->inventoryValuationAtDate($pharmacyIds, $dayBeforeStart);
        $persediaanAkhir = $this->inventoryValuationAtDate($pharmacyIds, $periodEnd);
        $persediaanSelisih = round($persediaanAkhir - $persediaanAwal, 2);
        $persediaanRow = [
            'label' => 'PERSEDIAAN',
            'awal' => $persediaanAwal,
            'akhir' => $persediaanAkhir,
            'selisih' => $persediaanSelisih,
        ];

        // 7. Laba Dengan Stok: Laba Tanpa Stok + Persediaan
        // SELISIH = Laba Bulan Berjalan (Ringkasan) + Perubahan Persediaan Bulan Berjalan
        $labaDenganStokAwal = round($labaTanpaStokAwal + $persediaanAwal, 2);
        $labaDenganStokAkhir = round($labaTanpaStokAkhir + $persediaanAkhir, 2);
        $labaDenganStok = [
            'label' => 'LABA DENGAN STOK',
            'awal' => $labaDenganStokAwal,
            'akhir' => $labaDenganStokAkhir,
            'selisih' => round($labaBulanBerjalan + $persediaanSelisih, 2),
        ];

        // 8. Harta (Wealth / Total Aktiva): Kas + Bank + Piutang + Persediaan
        $hartaAwal = $totalAsetLancarAwal + $persediaanAwal;
        $hartaAkhir = $totalAsetLancarAkhir + $persediaanAkhir;
        $harta = [
            'label' => 'HARTA',
            'awal' => $hartaAwal,
            'akhir' => $hartaAkhir,
            'selisih' => $hartaAkhir - $hartaAwal,
        ];

        // 9. Kewajiban (Total Pasiva / Liabilitas): Hutang Dagang
        $kewajiban = [
            'label' => 'KEWAJIBAN',
            'awal' => $hutangAwal,
            'akhir' => $hutangAkhir,
            'selisih' => $hutangAkhir - $hutangAwal,
        ];

        // 10. Saldo (Ekuitas): Harta - Kewajiban
        $saldoAwal = $hartaAwal - $hutangAwal;
        $saldoAkhir = $hartaAkhir - $hutangAkhir;
        $saldo = [
            'label' => 'SALDO',
            'awal' => $saldoAwal,
            'akhir' => $saldoAkhir,
            'selisih' => $saldoAkhir - $saldoAwal,
        ];

        // Build items list with numbers 1..10 exactly as in screenshot
        $numberedItems = [];
        $no = 1;

        foreach ($kasRows as $row) {
            $numberedItems[] = array_merge(['no' => $no++], $row);
        }
        foreach ($bankRows as $row) {
            $numberedItems[] = array_merge(['no' => $no++], $row);
        }
        $numberedItems[] = array_merge(['no' => $no++], $piutangDagangRow);
        $numberedItems[] = array_merge(['no' => $no++], $piutangUsahaRow);
        $numberedItems[] = array_merge(['no' => $no++], $hutangDagangRow);

        $persediaanItem = array_merge(['no' => $no++], $persediaanRow);

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dayBeforeStart' => $dayBeforeStart,
            'periodEnd' => $periodEnd,
            'hasEstimatedOpening' => $hasEstimatedOpening,
            'laba_ringkasan' => $periodProfitSummary,
            'items' => $numberedItems,
            'laba_tanpa_stok' => $labaTanpaStok,
            'persediaan' => $persediaanItem,
            'laba_dengan_stok' => $labaDenganStok,
            'harta' => $harta,
            'kewajiban' => $kewajiban,
            'saldo' => $saldo,
        ];
    }

    private function calculateAccountBalance(int $accountId, FinancePayment $opening, string $asOfDate): float
    {
        return FinanceAccountBalanceResolver::calculateFromOpening($accountId, $opening, $asOfDate);
    }

    private function receivableBalanceAtDate(array $pharmacyIds, string $asOfDate): float
    {
        $transactions = MedicineTransactions::query()
            ->with(['transactions' => fn ($query) => $query->where('status', 1)])
            ->withSum(['payments as finance_paid' => fn ($query) => $query
                ->where('payment_type', 'PIUTANG')
                ->whereDate('payment_date', '<=', $asOfDate)], 'amount')
            ->withSum(['payments as finance_paid_total' => fn ($query) => $query
                ->where('payment_type', 'PIUTANG')], 'amount')
            ->whereIn('pharmacy_id', $pharmacyIds)
            ->whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT', 'RETUR KREDIT'])
            ->where(function ($period) use ($asOfDate) {
                $period->where('created_at', '<', FinanceShift::endExclusive($asOfDate))
                    ->orWhere(function ($legacy) use ($asOfDate) {
                        $legacy->whereNull('created_at')->where('updated_at', '<', FinanceShift::endExclusive($asOfDate));
                    });
            })
            ->tap(fn ($query) => LiphTransactionScope::applySalesStatus($query))
            ->get(['id', 'debtor_id', 'transaction_type', 'subtotal', 'discount', 'paid']);

        $balancesByDebtor = [];
        $returnsByDebtor = [];

        foreach ($transactions as $transaction) {
            $debtorKey = (string) ((int) ($transaction->debtor_id ?? 0));

            if (strtoupper(trim((string) $transaction->transaction_type)) === 'RETUR KREDIT') {
                $returnsByDebtor[$debtorKey] = ($returnsByDebtor[$debtorKey] ?? 0)
                    + abs((float) ($transaction->subtotal ?? 0));
                continue;
            }

            $total = (float) ($transaction->subtotal ?? 0);
            if ($total <= 0) {
                $total = (float) $transaction->transactions->sum(
                    fn ($item) => (float) ($item->final_price ?: $item->total_price)
                );
                $total = max(0, $total - (float) ($transaction->discount ?? 0));
            }

            $total = max(0, $total);
            // Pisahkan pembayaran historis (legacy paid sebelum tabel finance_payments) agar pelunasan
            // yang baru terjadi setelah $asOfDate tidak ikut melunasi saldo piutang per tanggal $asOfDate.
            $legacyPaid = max(0, (float) ($transaction->paid ?? 0) - (float) ($transaction->finance_paid_total ?? 0));
            $paid = min($total, max(0, (float) ($transaction->finance_paid ?? 0) + $legacyPaid));
            $balancesByDebtor[$debtorKey] = ($balancesByDebtor[$debtorKey] ?? 0) + max(0, $total - $paid);
        }

        foreach ($returnsByDebtor as $debtorKey => $returnAmount) {
            if (isset($balancesByDebtor[$debtorKey])) {
                $balancesByDebtor[$debtorKey] = max(0, $balancesByDebtor[$debtorKey] - $returnAmount);
            }
        }

        return round((float) array_sum($balancesByDebtor), 2);
    }

    private function payableBalanceAtDate(array $pharmacyIds, string $asOfDate): float
    {
        // PMI (Apotek ID 1): Pemesanan dan pembelian dari supplier dicatat di Gudang PMI (ID 9).
        if (in_array(1, $pharmacyIds, true) && !in_array(9, $pharmacyIds, true)) {
            $pharmacyIds[] = 9;
        }

        // Ambil retur pembelian yang sudah berstatus "sudah_potong" hingga tanggal cutoff (konsisten dengan PayablesReceivablesService)
        $returnsByDetailId = [];
        $returnsByInvoiceNo = [];

        if (Schema::hasTable('purchase_returns') && Schema::hasTable('purchase_return_items')) {
            $returnItems = DB::table('purchase_return_items as pri')
                ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                ->where('pr.status', '!=', 'dibatalkan')
                ->where('pri.status', 'sudah_potong')
                ->whereIn('pr.pharmacy_id', $pharmacyIds)
                ->whereRaw('COALESCE(pri.deduction_date, pri.status_date, DATE(pr.created_at)) <= ?', [$asOfDate])
                ->select('pr.receiving_id', 'pr.invoice_number', 'pri.subtotal')
                ->get();

            foreach ($returnItems as $item) {
                $amt = (float) ($item->subtotal ?? 0);
                if ($item->receiving_id) {
                    $returnsByDetailId[$item->receiving_id] = ($returnsByDetailId[$item->receiving_id] ?? 0) + $amt;
                }
                if ($item->invoice_number) {
                    $invKey = strtoupper(trim((string) $item->invoice_number));
                    $returnsByInvoiceNo[$invKey] = ($returnsByInvoiceNo[$invKey] ?? 0) + $amt;
                }
            }
        }

        $details = ReceivingDetails::query()
            ->with([
                'receiving:id,pharmacy_id,status,date,created_at',
                'creditor:code,ppn_type',
                'receiving_items:id,receiving_details_id,total,raw_price,qty_received',
                'payments' => fn ($query) => $query->whereDate('payment_date', '<=', $asOfDate),
            ])
            ->whereRaw('UPPER(TRIM(COALESCE(invoice_payment, ""))) = ?', ['KREDIT'])
            ->postedToStock()
            ->whereHas('receiving', function ($query) use ($pharmacyIds, $asOfDate) {
                $query->whereIn('pharmacy_id', $pharmacyIds)
                    ->where(function ($date) use ($asOfDate) {
                        $date->whereRaw("COALESCE(STR_TO_DATE(NULLIF(date, ''), '%d/%m/%Y'), STR_TO_DATE(NULLIF(date, ''), '%Y-%m-%d'), DATE(created_at)) <= ?", [$asOfDate]);
                    });
            })
            ->get();

        $balance = 0.0;
        foreach ($details as $detail) {
            $ppnType = strtoupper(trim((string) ($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA')));
            $invoiceTotal = ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType)['total'];

            $returFromPr = 0.0;
            if (isset($returnsByDetailId[$detail->id])) {
                $returFromPr = (float) $returnsByDetailId[$detail->id];
            } elseif ($detail->invoice_number && isset($returnsByInvoiceNo[strtoupper(trim($detail->invoice_number))])) {
                $returFromPr = (float) $returnsByInvoiceNo[strtoupper(trim($detail->invoice_number))];
            }

            // Pembayaran kas/bank & pembayaran retur
            $paidCashBank = (float) $detail->payments->where('payment_type', '!=', 'RETUR')->sum('amount');
            $paidRetur = (float) $detail->payments->where('payment_type', 'RETUR')->sum('amount');
            $totalReturDeduction = round(max($paidRetur, $returFromPr), 2);
            $netTotal = round(max(0, $invoiceTotal - $totalReturDeduction), 2);
            $paidApplied = round(min($netTotal, $paidCashBank), 2);

            // Sisa hutang faktur = (nilai faktur - potongan retur) - pembayaran kas/bank
            $balance += max(0, $netTotal - $paidApplied);
        }

        return round($balance, 2);
    }

    /**
     * Calculate automatic inventory valuation for the target pharmacy as of a date.
     */
    private function inventoryValuationAtDate(array $pharmacyIds, string $asOfDate): float
    {
        $targetPharmacyId = (int) ($pharmacyIds[0] ?? 1);
        $warehouseId = function_exists('getWarehousePharmacyId') ? getWarehousePharmacyId() : 9;
        $canSeeWarehouse = function_exists('canAccessWarehouseStock') ? canAccessWarehouseStock($targetPharmacyId) : in_array($targetPharmacyId, [1, 9, 6], true);
        $counterPharmacyId = (function_exists('isWarehousePharmacy') && isWarehousePharmacy($targetPharmacyId)) ? 1 : $targetPharmacyId;

        // Current counter stock valuation (exclude consignment)
        $counterQuery = DB::table('medicine_transfer_items as mti')
            ->join('batches as b', 'b.id', '=', 'mti.batches_id')
            ->join('medicines as m', 'm.id', '=', 'b.medicine_id')
            ->where('b.pharmacy_id', $counterPharmacyId)
            ->where('mti.status', 1)
            ->where(function ($q) {
                $q->whereNull('mti.source_type')->orWhere('mti.source_type', '!=', 'retur_gudang');
            });
        ConsignmentService::excludeConsignmentFromTransferItemsQuery($counterQuery, 'mti', 'b');
        $counterValuation = (float) $counterQuery
            ->selectRaw('SUM(mti.qty * COALESCE(NULLIF(m.raw_price, 0), 0)) as total_val')
            ->value('total_val');

        // Storage / warehouse valuation if applicable (exclude consignment)
        $storageValuation = 0.0;
        if ($canSeeWarehouse) {
            $storageQuery = DB::table('batches as b')
                ->join('medicines as m', 'm.id', '=', 'b.medicine_id')
                ->where('b.pharmacy_id', $warehouseId)
                ->where('b.stock', '>', 0);
            ConsignmentService::excludeConsignmentFromBatchesQuery($storageQuery, 'b');
            $storageValuation = (float) $storageQuery
                ->selectRaw('SUM(b.stock * COALESCE(NULLIF(m.raw_price, 0), 0)) as total_val')
                ->value('total_val');
        }

        $currentTotalValuation = round($counterValuation + $storageValuation, 2);

        // Jika tanggal cutoff adalah hari ini (atau di masa depan), gunakan langsung snapshot stok aktual
        $todayOperationalDate = FinanceShift::operationalDate(now());
        if ($asOfDate >= $todayOperationalDate) {
            return max(0.0, $currentTotalValuation);
        }

        // Reconcile backwards from today to $asOfDate using items_log movements (exclude consignment)
        $trackedPharmacies = $canSeeWarehouse ? array_unique([$counterPharmacyId, $warehouseId]) : [$counterPharmacyId];
        $cutoff = Carbon::parse($asOfDate)->endOfDay()->toDateTimeString();

        $deltaQuery = DB::table('items_log as il')
            ->join('batches as b', 'b.id', '=', 'il.batches_id')
            ->join('medicines as m', 'm.id', '=', 'il.medicine_id')
            ->whereIn('b.pharmacy_id', $trackedPharmacies)
            ->where('il.date', '>', $cutoff);
        ConsignmentService::excludeConsignmentFromBatchesQuery($deltaQuery, 'b');

        $deltaSinceCutoff = (float) $deltaQuery
            ->selectRaw('SUM(
                CASE 
                    WHEN il.status IN (1, 4, 9) THEN -ABS(il.qty) * COALESCE(NULLIF(m.raw_price, 0), 0)
                    WHEN il.status IN (2, 3) THEN ABS(il.qty) * COALESCE(NULLIF(m.raw_price, 0), 0)
                    WHEN il.status = 5 THEN 
                        CASE 
                            WHEN il.total IS NOT NULL AND TRIM(il.total) REGEXP "^[+-]?[0-9]+([.][0-9]+)?$" 
                            THEN CAST(il.total AS DECIMAL(15,2)) * COALESCE(NULLIF(m.raw_price, 0), 0)
                            ELSE (CAST(il.qty_after AS DECIMAL(15,2)) - CAST(il.qty_before AS DECIMAL(15,2))) * COALESCE(NULLIF(m.raw_price, 0), 0)
                        END
                    WHEN il.status IN (6, 7) THEN 
                        (CAST(il.qty_after AS DECIMAL(15,2)) - CAST(il.qty_before AS DECIMAL(15,2))) * COALESCE(NULLIF(m.raw_price, 0), 0)
                    WHEN il.status = 8 THEN il.qty * COALESCE(NULLIF(m.raw_price, 0), 0)
                    ELSE 0
                END
            ) as delta_val')
            ->value('delta_val');

        return round(max(0.0, $currentTotalValuation - $deltaSinceCutoff), 2);
    }
}

