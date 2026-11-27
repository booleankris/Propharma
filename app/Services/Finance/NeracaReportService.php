<?php

namespace App\Services\Finance;

use App\Models\FinanceAccount;
use App\Models\FinanceOpeningInvoice;
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
        $hutangDagangRow = [
            'label' => 'HUTANG DAGANG',
            'awal' => $hutangAwal,
            'akhir' => $hutangAkhir,
            'selisih' => round($hutangAwal - $hutangAkhir, 2), // Hutang: AWAL - AKHIR
            'is_liability' => true,
        ];

        // 5. Total Aset Lancar (Poin 1 s/d 8: Kas + Bank + Piutang Dagang + Piutang Usaha)
        $totalAsetLancarAwal = array_sum(array_column($kasRows, 'awal'))
            + array_sum(array_column($bankRows, 'awal'))
            + $piutangAwal
            + $piutangUsahaRow['awal'];
        $totalAsetLancarAkhir = array_sum(array_column($kasRows, 'akhir'))
            + array_sum(array_column($bankRows, 'akhir'))
            + $piutangAkhir
            + $piutangUsahaRow['akhir'];

        // 5b. Laba Tanpa Stok = (Jumlah Poin 1 s/d 8) - Poin 9 (Hutang Dagang), Selisih = AKHIR - AWAL
        $labaTanpaStokAwal = round($totalAsetLancarAwal - $hutangAwal, 2);
        $labaTanpaStokAkhir = round($totalAsetLancarAkhir - $hutangAkhir, 2);
        $labaTanpaStok = [
            'label' => 'LABA TANPA STOK',
            'awal' => $labaTanpaStokAwal,
            'akhir' => $labaTanpaStokAkhir,
            'selisih' => round($labaTanpaStokAkhir - $labaTanpaStokAwal, 2),
        ];

        // 6. Persediaan: Nilai total stok aktif (Stok * raw_price * 1.11, kecualikan konsinyasi), Selisih = AKHIR - AWAL
        $persediaanAwal = $this->inventoryValuationAtDate($pharmacyIds, $dayBeforeStart);
        $persediaanAkhir = $this->inventoryValuationAtDate($pharmacyIds, $periodEnd);
        $persediaanSelisih = round($persediaanAkhir - $persediaanAwal, 2);
        $persediaanRow = [
            'label' => 'PERSEDIAAN',
            'awal' => $persediaanAwal,
            'akhir' => $persediaanAkhir,
            'selisih' => $persediaanSelisih,
        ];

        // 7. Laba Dengan Stok: Laba Tanpa Stok + Persediaan, Selisih = AKHIR - AWAL
        $labaDenganStokAwal = round($labaTanpaStokAwal + $persediaanAwal, 2);
        $labaDenganStokAkhir = round($labaTanpaStokAkhir + $persediaanAkhir, 2);
        $labaDenganStok = [
            'label' => 'LABA DENGAN STOK',
            'awal' => $labaDenganStokAwal,
            'akhir' => $labaDenganStokAkhir,
            'selisih' => round($labaDenganStokAkhir - $labaDenganStokAwal, 2),
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

        // 10. Saldo (Ekuitas): Harta - Kewajiban (= Laba Dengan Stok)
        $saldoAwal = $hartaAwal - $hutangAwal;
        $saldoAkhir = $hartaAkhir - $hutangAkhir;
        $saldo = [
            'label' => 'SALDO',
            'awal' => $saldoAwal,
            'akhir' => $saldoAkhir,
            'selisih' => $saldoAkhir - $saldoAwal,
        ];

        // Build items list with numbers 1..9
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

    public function receivableBalanceAtDate(array $pharmacyIds, string $asOfDate): float
    {
        // Logika identik dengan halaman Piutang & Ringkasan (PayablesReceivablesService::assemblePiutangRows):
        // per faktur kredit -> total (subtotal / fallback medicine_cart) - retur (tabel retur) - pembayaran.
        $endExclusive = FinanceShift::endExclusive($asOfDate);
        $headerQuery = MedicineTransactions::query()
            ->whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT'])
            ->whereIn('pharmacy_id', $pharmacyIds)
            ->where(function ($period) use ($endExclusive) {
                $period->where('created_at', '<', $endExclusive)
                    ->orWhere(function ($legacy) use ($endExclusive) {
                        $legacy->whereNull('created_at')->where('updated_at', '<', $endExclusive);
                    });
            });
        LiphTransactionScope::applySalesStatus($headerQuery);
        $headers = $headerQuery->select(['id', 'subtotal', 'paid'])->toBase()->get();

        $totalBalance = 0.0;
        foreach ($headers->chunk(500) as $chunk) {
            $txIds = $chunk->pluck('id')->map(fn ($v) => (int) $v)->all();

            $fallbackIds = $chunk->filter(fn ($tx) => (float) ($tx->subtotal ?? 0) <= 0)
                ->pluck('id')->map(fn ($v) => (int) $v)->all();
            $cartSubtotal = [];
            if (!empty($fallbackIds)) {
                $cartRows = DB::table('medicine_cart')
                    ->whereIn('transaction_id', $fallbackIds)
                    ->select(['transaction_id', 'quantity', 'item_price', 'total_price', 'final_price'])
                    ->get();
                foreach ($cartRows as $c) {
                    $qty = (float) ($c->quantity ?? 0);
                    $harga = (float) ($c->item_price ?? 0);
                    $final = (float) ($c->final_price ?: ($c->total_price ?: ($qty * $harga)));
                    $cartSubtotal[(int) $c->transaction_id] = ($cartSubtotal[(int) $c->transaction_id] ?? 0.0) + $final;
                }
            }

            $paidAsOf = DB::table('finance_payments')
                ->whereIn('medicine_transaction_id', $txIds)
                ->whereDate('payment_date', '<=', $asOfDate)
                ->groupBy('medicine_transaction_id')
                ->select('medicine_transaction_id', DB::raw('SUM(amount) as total_amount'))
                ->pluck('total_amount', 'medicine_transaction_id')
                ->all();
            $paidAll = DB::table('finance_payments')
                ->whereIn('medicine_transaction_id', $txIds)
                ->groupBy('medicine_transaction_id')
                ->select('medicine_transaction_id', DB::raw('SUM(amount) as total_amount'))
                ->pluck('total_amount', 'medicine_transaction_id')
                ->all();

            $returByTx = DB::table('retur')
                ->whereIn('transaction_id', $txIds)
                ->where(function ($q) use ($endExclusive) {
                    $q->whereNull('created_at')->orWhere('created_at', '<', $endExclusive);
                })
                ->groupBy('transaction_id')
                ->select('transaction_id', DB::raw('SUM(total_retur) as sum_retur'))
                ->pluck('sum_retur', 'transaction_id')
                ->all();

            foreach ($chunk as $tx) {
                $txId = (int) $tx->id;
                $rawSub = (float) ($tx->subtotal ?? 0);
                $total = round($rawSub > 0 ? $rawSub : (float) ($cartSubtotal[$txId] ?? 0.0), 2);
                $netTotal = round(max(0, $total - round((float) ($returByTx[$txId] ?? 0), 2)), 2);

                $financeAsOf = (float) ($paidAsOf[$txId] ?? 0);
                $financeAll = (float) ($paidAll[$txId] ?? 0);
                $legacyPaid = (float) ($tx->paid ?? 0);
                // Sama dengan halaman Piutang: terbayar = max(pembayaran Finance, kolom paid legacy).
                // Untuk tanggal historis, pembayaran Finance setelah $asOfDate dikeluarkan.
                $paid = ($financeAsOf + 0.005 >= $financeAll)
                    ? max($financeAll, $legacyPaid)
                    : $financeAsOf + max(0, $legacyPaid - $financeAll);
                $paid = round(min($netTotal, $paid), 2);

                $sisa = round(max(0, $netTotal - $paid), 2);
                if ($sisa >= 0.005) {
                    $totalBalance += $sisa;
                }
            }
        }

        if (Schema::hasTable('finance_opening_invoices')) {
            $openingReceivables = FinanceOpeningInvoice::query()
                ->withSum(['payments as paid_as_of' => fn ($q) => $q->whereDate('payment_date', '<=', $asOfDate)], 'amount')
                ->where('type', 'PIUTANG')
                ->whereIn('pharmacy_id', $pharmacyIds)
                ->whereDate('invoice_date', '<=', $asOfDate)
                ->get(['id', 'amount']);

            foreach ($openingReceivables as $oi) {
                $amt = (float) $oi->amount;
                if ($amt < 0) {
                    $totalBalance += $amt;
                } else {
                    $totalBalance += max(0.0, $amt - (float) ($oi->paid_as_of ?? 0));
                }
            }
        }

        return round(max(0.0, $totalBalance), 2);
    }

    public function payableBalanceAtDate(array $pharmacyIds, string $asOfDate): float
    {
        // PMI (Apotek ID 1): Pemesanan dan pembelian dari supplier dicatat di Gudang PMI (ID 9).
        if (in_array(1, $pharmacyIds, true) && !in_array(9, $pharmacyIds, true)) {
            $pharmacyIds[] = 9;
        }

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $receivingDateExpr = $isSqlite
            ? "COALESCE(CASE WHEN date LIKE '__/__/____' THEN substr(date, 7, 4) || '-' || substr(date, 4, 2) || '-' || substr(date, 1, 2) WHEN date LIKE '____-__-__' THEN substr(date, 1, 10) ELSE NULL END, DATE(created_at))"
            : "COALESCE(STR_TO_DATE(NULLIF(date, ''), '%d/%m/%Y'), STR_TO_DATE(NULLIF(date, ''), '%Y-%m-%d'), DATE(created_at))";

        $hasOrderItemsJoin = Schema::hasTable('order_items') && Schema::hasColumn('receiving_items', 'order_items_id');

        $details = ReceivingDetails::query()
            ->with([
                'receiving:id,pharmacy_id,status,date,created_at',
                'creditor:code,ppn_type',
                'receiving_items' => function ($query) use ($hasOrderItemsJoin) {
                    $query->whereNotNull('batches_id')
                        ->select([
                            'id',
                            'receiving_details_id',
                            'order_items_id',
                            'total',
                            'raw_price',
                            'qty_received',
                            'qty',
                            'discount',
                            'extra_discount',
                        ]);
                    if ($hasOrderItemsJoin) {
                        $query->with('order_items:id,price');
                    }
                },
                'payments' => fn ($query) => $query->whereDate('payment_date', '<=', $asOfDate),
            ])
            ->whereRaw('UPPER(TRIM(COALESCE(invoice_payment, ""))) = ?', ['KREDIT'])
            ->postedToStock()
            ->whereHas('receiving', function ($query) use ($pharmacyIds, $asOfDate, $receivingDateExpr) {
                $query->whereIn('pharmacy_id', $pharmacyIds)
                    ->whereRaw("{$receivingDateExpr} <= ?", [$asOfDate]);
            })
            ->get();

        // Ambil retur pembelian yang berstatus "sudah_potong" hingga tanggal cutoff (konsisten & bebas duplikat seperti PayablesReceivablesService)
        $returnsByDetailId = [];
        if (Schema::hasTable('purchase_returns') && Schema::hasTable('purchase_return_items') && $details->isNotEmpty()) {
            $detailIds = [];
            $invoiceNumberToDetailIds = [];
            $uniqueInvoices = [];

            foreach ($details as $detail) {
                $dId = (int) $detail->id;
                $detailIds[] = $dId;
                $invRaw = trim((string) ($detail->invoice_number ?? ''));
                if ($invRaw !== '') {
                    $uniqueInvoices[$invRaw] = true;
                    $invoiceNumberToDetailIds[strtoupper($invRaw)][] = $dId;
                }
            }
            $uniqueInvoiceList = array_keys($uniqueInvoices);

            $seenReturnIdsByDetail = [];
            $processReturnRows = function ($retRows) use (&$seenReturnIdsByDetail, &$returnsByDetailId, $invoiceNumberToDetailIds) {
                foreach ($retRows as $r) {
                    $matchedIds = [];
                    if (!empty($r->pr_receiving_id)) {
                        $matchedIds[] = (int) $r->pr_receiving_id;
                    }
                    $invNorm = strtoupper(trim((string) ($r->pr_invoice_number ?? '')));
                    if ($invNorm !== '' && isset($invoiceNumberToDetailIds[$invNorm])) {
                        foreach ($invoiceNumberToDetailIds[$invNorm] as $mId) {
                            $matchedIds[] = $mId;
                        }
                    }

                    $amt = (float) ($r->subtotal ?? 0);
                    foreach (array_unique($matchedIds) as $dId) {
                        if (isset($seenReturnIdsByDetail[$dId][$r->id])) {
                            continue;
                        }
                        $seenReturnIdsByDetail[$dId][$r->id] = true;
                        $returnsByDetailId[$dId] = ($returnsByDetailId[$dId] ?? 0.0) + $amt;
                    }
                }
            };

            foreach (array_chunk($detailIds, 400) as $chunkIds) {
                $retByReceiving = DB::table('purchase_return_items as pri')
                    ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                    ->whereIn('pr.receiving_id', $chunkIds)
                    ->where('pr.status', '!=', 'dibatalkan')
                    ->where('pri.status', 'sudah_potong')
                    ->whereRaw('COALESCE(pri.deduction_date, pri.status_date, DATE(pr.created_at)) <= ?', [$asOfDate])
                    ->select(['pri.id', 'pri.subtotal', 'pr.receiving_id as pr_receiving_id', 'pr.invoice_number as pr_invoice_number'])
                    ->get();
                $processReturnRows($retByReceiving);
            }

            foreach (array_chunk($uniqueInvoiceList, 400) as $chunkInvoices) {
                $retByInvoice = DB::table('purchase_return_items as pri')
                    ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                    ->whereIn('pr.invoice_number', $chunkInvoices)
                    ->where('pr.status', '!=', 'dibatalkan')
                    ->where('pri.status', 'sudah_potong')
                    ->whereRaw('COALESCE(pri.deduction_date, pri.status_date, DATE(pr.created_at)) <= ?', [$asOfDate])
                    ->select(['pri.id', 'pri.subtotal', 'pr.receiving_id as pr_receiving_id', 'pr.invoice_number as pr_invoice_number'])
                    ->get();
                $processReturnRows($retByInvoice);
            }
        }

        $balance = 0.0;
        foreach ($details as $detail) {
            $ppnType = strtoupper(trim((string) ($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA')));
            $invoiceTotal = ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType)['total'];

            $returFromPr = (float) ($returnsByDetailId[$detail->id] ?? 0.0);

            // Pembayaran kas/bank & pembayaran retur
            $paidCashBank = (float) $detail->payments->where('payment_type', '!=', 'RETUR')->sum('amount');
            $paidRetur = (float) $detail->payments->where('payment_type', 'RETUR')->sum('amount');
            $totalReturDeduction = round(max($paidRetur, $returFromPr), 2);
            $netTotal = round(max(0, $invoiceTotal - $totalReturDeduction), 2);
            $paidApplied = round(min($netTotal, $paidCashBank), 2);

            // Sisa hutang faktur = (nilai faktur - potongan retur) - pembayaran kas/bank
            $sisa = round(max(0, $netTotal - $paidApplied), 2);
            if ($sisa < 0.005) {
                $sisa = 0.0;
            }
            $balance += $sisa;
        }

        if (Schema::hasTable('finance_opening_invoices')) {
            $openingPayables = FinanceOpeningInvoice::query()
                ->withSum(['payments as paid_as_of' => fn ($q) => $q->whereDate('payment_date', '<=', $asOfDate)], 'amount')
                ->where('type', 'HUTANG')
                ->whereIn('pharmacy_id', $pharmacyIds)
                ->whereDate('invoice_date', '<=', $asOfDate)
                ->get(['id', 'amount']);

            foreach ($openingPayables as $oi) {
                $amt = (float) $oi->amount;
                if ($amt < 0) {
                    $balance += $amt;
                } else {
                    $balance += max(0.0, $amt - (float) ($oi->paid_as_of ?? 0));
                }
            }
        }

        return round(max(0.0, $balance), 2);
    }

    /**
     * Hitung nilai total persediaan (obat aktif status = 1, tidak termasuk konsinyasi) per tanggal tertentu.
     * Formula: Stok * raw_price (historis per cutoff) * 1.11, identik dengan kartu Nilai Stok pada tanggal hari ini.
     */
    public function inventoryValuationAtDate(array $pharmacyIds, string $asOfDate): float
    {
        $targetPharmacyId = (int) ($pharmacyIds[0] ?? 1);
        $warehouseId = function_exists('getWarehousePharmacyId') ? getWarehousePharmacyId() : 9;
        $canSeeWarehouse = function_exists('canAccessWarehouseStock') ? canAccessWarehouseStock($targetPharmacyId) : in_array($targetPharmacyId, [1, 9, 6], true);
        $counterPharmacyId = (function_exists('isWarehousePharmacy') && isWarehousePharmacy($targetPharmacyId)) ? 1 : $targetPharmacyId;

        // 1. Snapshot qty etalase saat ini per medicine_id (obat aktif, qty > 0, kecualikan konsinyasi)
        $counterQuery = DB::table('medicine_transfer_items as mti')
            ->join('batches as b', 'b.id', '=', 'mti.batches_id')
            ->join('medicines as m', 'm.id', '=', 'b.medicine_id')
            ->where('m.status', 1)
            ->where('b.pharmacy_id', $counterPharmacyId)
            ->where('mti.status', 1)
            ->where('mti.qty', '>', 0)
            ->where(function ($q) {
                $q->whereNull('mti.source_type')->orWhere('mti.source_type', '!=', 'retur_gudang');
            });
        ConsignmentService::excludeConsignmentFromTransferItemsQuery($counterQuery, 'mti', 'b');
        $counterQtyRows = $counterQuery
            ->groupBy('b.medicine_id')
            ->selectRaw('b.medicine_id, COALESCE(SUM(mti.qty), 0) as qty_sum')
            ->pluck('qty_sum', 'b.medicine_id')
            ->all();

        $currentQtyByMed = [];
        foreach ($counterQtyRows as $medId => $qtySum) {
            $mId = (int) $medId;
            if ($mId > 0) {
                $currentQtyByMed[$mId] = ($currentQtyByMed[$mId] ?? 0.0) + (float) $qtySum;
            }
        }

        // 2. Snapshot qty gudang saat ini per medicine_id (khusus PMI, obat aktif, stok > 0, kecualikan konsinyasi)
        if ($canSeeWarehouse) {
            $storageQuery = DB::table('batches as b')
                ->join('medicines as m', 'm.id', '=', 'b.medicine_id')
                ->where('m.status', 1)
                ->where('b.pharmacy_id', $warehouseId)
                ->whereRaw("CAST(REPLACE(COALESCE(NULLIF(b.stock, ''), '0'), ',', '.') AS DECIMAL(15, 2)) > 0");
            ConsignmentService::excludeConsignmentFromBatchesQuery($storageQuery, 'b');
            $storageQtyRows = $storageQuery
                ->groupBy('b.medicine_id')
                ->selectRaw("b.medicine_id, COALESCE(SUM(CAST(REPLACE(COALESCE(NULLIF(b.stock, ''), '0'), ',', '.') AS DECIMAL(15, 2))), 0) as qty_sum")
                ->pluck('qty_sum', 'b.medicine_id')
                ->all();

            foreach ($storageQtyRows as $medId => $qtySum) {
                $mId = (int) $medId;
                if ($mId > 0) {
                    $currentQtyByMed[$mId] = ($currentQtyByMed[$mId] ?? 0.0) + (float) $qtySum;
                }
            }
        }

        $todayOperationalDate = FinanceShift::operationalDate(now());
        if ($asOfDate >= $todayOperationalDate) {
            if (empty($currentQtyByMed)) {
                return 0.0;
            }

            $medPrices = DB::table('medicines')
                ->where('status', 1)
                ->whereIn('id', array_keys($currentQtyByMed))
                ->pluck('raw_price', 'id')
                ->all();

            $totalVal = 0.0;
            foreach ($currentQtyByMed as $mId => $qty) {
                $hna = $this->parseDecimalPrice($medPrices[$mId] ?? 0);
                if ($qty > 0 && $hna > 0) {
                    $totalVal += ($qty * $hna * 1.11);
                }
            }

            return round(max(0.0, $totalVal), 2);
        }

        // 3. Cutoff historis: tarik mundur pergerakan items_log per medicine_id (kecualikan SO-SALDO-AWAL & konsinyasi)
        $trackedPharmacies = $canSeeWarehouse ? array_unique([$counterPharmacyId, $warehouseId]) : [$counterPharmacyId];
        $cutoff = Carbon::parse($asOfDate)->endOfDay()->toDateTimeString();

        $consFilter = ConsignmentService::getConsignmentStockFilter();
        $consBatchSet = array_flip($consFilter['consignment_batch_ids']);
        $nonConsBatchSet = array_flip($consFilter['non_consignment_batch_ids']);
        $consMedSet = array_flip($consFilter['consignment_medicine_ids']);

        $deltaQuery = DB::table('items_log as il')
            ->leftJoin('batches as b', 'b.id', '=', 'il.batches_id')
            ->join('medicines as m', 'm.id', '=', 'il.medicine_id')
            ->leftJoin('users as u', 'u.id', '=', 'il.user_id')
            ->where('m.status', 1)
            ->where('il.date', '>', $cutoff)
            ->where(function ($q) {
                $q->whereNull('il.type')->orWhere('il.type', '!=', 'SO-SALDO-AWAL');
            })
            ->where(function ($q) use ($trackedPharmacies) {
                $q->whereIn('b.pharmacy_id', $trackedPharmacies)
                    ->orWhere(function ($noBatch) use ($trackedPharmacies) {
                        $noBatch->whereNull('il.batches_id')
                            ->whereIn('u.pharmacy_id', $trackedPharmacies);
                    });
            })
            ->select([
                'il.medicine_id',
                'il.batches_id',
                'il.status',
                'il.qty',
                'il.qty_before',
                'il.qty_after',
                'il.total',
            ]);

        $deltaQtyByMed = [];
        foreach ($deltaQuery->cursor() as $log) {
            $mId = (int) ($log->medicine_id ?? 0);
            if ($mId <= 0) {
                continue;
            }

            $bId = (int) ($log->batches_id ?? 0);
            if ($bId > 0 && isset($consBatchSet[$bId])) {
                continue;
            }
            if (isset($consMedSet[$mId]) && !($bId > 0 && isset($nonConsBatchSet[$bId]))) {
                continue;
            }

            $deltaQtyByMed[$mId] = ($deltaQtyByMed[$mId] ?? 0.0) + $this->computeStockLogDelta($log);
        }

        $allMedIds = array_values(array_unique(array_merge(array_keys($currentQtyByMed), array_keys($deltaQtyByMed))));
        if (empty($allMedIds)) {
            return 0.0;
        }

        $qtyAtCutoffByMed = [];
        foreach ($allMedIds as $mId) {
            $qtyAtCutoff = ($currentQtyByMed[$mId] ?? 0.0) - ($deltaQtyByMed[$mId] ?? 0.0);
            if ($qtyAtCutoff > 0) {
                $qtyAtCutoffByMed[$mId] = $qtyAtCutoff;
            }
        }

        if (empty($qtyAtCutoffByMed)) {
            return 0.0;
        }

        $cutoffMedIds = array_keys($qtyAtCutoffByMed);
        $currentHnaByMed = DB::table('medicines')
            ->where('status', 1)
            ->whereIn('id', $cutoffMedIds)
            ->pluck('raw_price', 'id')
            ->all();

        $historicalHnaByMed = [];
        if (Schema::hasTable('medicine_price_history')) {
            foreach (array_chunk($cutoffMedIds, 1000) as $medChunk) {
                $priceChanges = DB::table('medicine_price_history')
                    ->whereIn('medicine_id', $medChunk)
                    ->where('created_at', '>', $cutoff)
                    ->orderBy('created_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->get(['medicine_id', 'old_price']);

                foreach ($priceChanges as $change) {
                    $mId = (int) $change->medicine_id;
                    if (!array_key_exists($mId, $historicalHnaByMed)
                        && $change->old_price !== null
                        && trim((string) $change->old_price) !== '') {
                        $historicalHnaByMed[$mId] = $this->parseDecimalPrice($change->old_price);
                    }
                }
            }
        }

        $totalVal = 0.0;
        foreach ($qtyAtCutoffByMed as $mId => $qty) {
            $hna = array_key_exists($mId, $historicalHnaByMed)
                ? $historicalHnaByMed[$mId]
                : $this->parseDecimalPrice($currentHnaByMed[$mId] ?? 0);
            if ($hna > 0) {
                $totalVal += ($qty * $hna * 1.11);
            }
        }

        return round(max(0.0, $totalVal), 2);
    }

    private function parseDecimalPrice(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        $normalized = str_replace(',', '.', trim((string) $value));
        return is_numeric($normalized) ? max(0.0, (float) $normalized) : 0.0;
    }

    private function computeStockLogDelta(object $log): float
    {
        $status = (int) ($log->status ?? 0);
        $qty = (float) ($log->qty ?? 0);

        if ($status === 1 || $status === 4 || $status === 9) {
            return -abs($qty);
        }
        if ($status === 2 || $status === 3) {
            return abs($qty);
        }
        if ($status === 8) {
            return $qty;
        }
        if ($status === 5) {
            $rawTotal = trim((string) ($log->total ?? ''));
            if ($rawTotal !== '' && $rawTotal !== '-' && is_numeric($rawTotal)) {
                return (float) $rawTotal;
            }
        }

        return (float) ($log->qty_after ?? 0) - (float) ($log->qty_before ?? 0);
    }
}

