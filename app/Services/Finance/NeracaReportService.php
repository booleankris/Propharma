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

        // 5b. Laba Tanpa Stok = (Jumlah Poin 1 s/d 8) - Poin 9 (Hutang Dagang)
        // Penyesuaian non-operasional (Opname KasBank, Tagihan Saldo Awal dalam periode, Pelunasan Cutoff non-kas)
        // dikeluarkan dari Selisih Laba agar Laba mencerminkan kinerja operasional murni, sementara AWAL & AKHIR tetap riil.
        $nonOperational = $this->computeNonOperationalAdjustments(
            $pharmacyIds,
            $accountIds,
            $startDate,
            $periodEnd,
            $dayBeforeStart
        );
        $nonOpTotal = (float) ($nonOperational['total'] ?? 0.0);

        $labaTanpaStokAwal = round($totalAsetLancarAwal - $hutangAwal, 2);
        $labaTanpaStokAkhir = round($totalAsetLancarAkhir - $hutangAkhir, 2);
        $labaTanpaStokRawSelisih = round($labaTanpaStokAkhir - $labaTanpaStokAwal, 2);
        $labaTanpaStokSelisih = round($labaTanpaStokRawSelisih - $nonOpTotal, 2);
        $labaTanpaStok = [
            'label' => 'LABA TANPA STOK',
            'awal' => $labaTanpaStokAwal,
            'akhir' => $labaTanpaStokAkhir,
            'selisih' => $labaTanpaStokSelisih,
            'raw_selisih' => $labaTanpaStokRawSelisih,
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

        // 7. Laba Dengan Stok: Laba Tanpa Stok + Persediaan (Operasional)
        $labaDenganStokAwal = round($labaTanpaStokAwal + $persediaanAwal, 2);
        $labaDenganStokAkhir = round($labaTanpaStokAkhir + $persediaanAkhir, 2);
        $labaDenganStokRawSelisih = round($labaDenganStokAkhir - $labaDenganStokAwal, 2);
        $labaDenganStokSelisih = round($labaTanpaStokSelisih + $persediaanSelisih, 2);
        $labaDenganStok = [
            'label' => 'LABA DENGAN STOK',
            'awal' => $labaDenganStokAwal,
            'akhir' => $labaDenganStokAkhir,
            'selisih' => $labaDenganStokSelisih,
            'raw_selisih' => $labaDenganStokRawSelisih,
        ];

        // 8. Harta (Wealth / Total Aktiva): Kas + Bank + Piutang + Persediaan
        $hartaAwal = round($totalAsetLancarAwal + $persediaanAwal, 2);
        $hartaAkhir = round($totalAsetLancarAkhir + $persediaanAkhir, 2);
        $harta = [
            'label' => 'HARTA',
            'awal' => $hartaAwal,
            'akhir' => $hartaAkhir,
            'selisih' => round($hartaAkhir - $hartaAwal, 2),
        ];

        // 9. Kewajiban (Total Pasiva / Liabilitas): Hutang Dagang
        $kewajiban = [
            'label' => 'KEWAJIBAN',
            'awal' => $hutangAwal,
            'akhir' => $hutangAkhir,
            'selisih' => round($hutangAkhir - $hutangAwal, 2),
        ];

        // 10. Saldo (Ekuitas): Harta - Kewajiban (tetap posisi riil)
        $saldoAwal = round($hartaAwal - $hutangAwal, 2);
        $saldoAkhir = round($hartaAkhir - $hutangAkhir, 2);
        $saldo = [
            'label' => 'SALDO',
            'awal' => $saldoAwal,
            'akhir' => $saldoAkhir,
            'selisih' => round($saldoAkhir - $saldoAwal, 2),
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
            'penyesuaian_non_operasional' => $nonOperational,
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
        $returnsByDetailId = $this->collectPurchaseReturnTotals($details, $asOfDate);

        $balance = 0.0;
        foreach ($details as $detail) {
            $balance += $this->computePayableDetailRemaining(
                $detail,
                (float) ($returnsByDetailId[$detail->id] ?? 0.0),
                $detail->payments
            );
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

    /**
     * Hitung seluruh dampak ekuitas non-operasional di dalam [startDate, periodEnd]
     * agar Selisih Laba di Neraca tidak terdistorsi oleh Opname KasBank, Tagihan Saldo Awal,
     * maupun Pelunasan Pra-Cut-off non-kas yang dicatat pada periode berjalan.
     */
    private function computeNonOperationalAdjustments(
        array $pharmacyIds,
        array $accountIds,
        string $startDate,
        string $periodEnd,
        string $dayBeforeStart
    ): array {
        $payablePharmacyIds = $pharmacyIds;
        if (in_array(1, $payablePharmacyIds, true) && !in_array(9, $payablePharmacyIds, true)) {
            $payablePharmacyIds[] = 9;
        }

        // 1. Opname Kas & Bank (PENYESUAIAN SALDO: OPNAME-IN - OPNAME-OUT)
        $opnameKasBank = FinanceAccountBalanceResolver::netOpnameAdjustmentBetween($accountIds, $startDate, $periodEnd);

        // 2. Tagihan Saldo Awal (finance_opening_invoices) HUTANG & PIUTANG
        $openingHutangEffect = 0.0;
        $openingPiutangEffect = 0.0;
        $hasOpeningInvoiceCol = Schema::hasTable('finance_payments') && Schema::hasColumn('finance_payments', 'opening_invoice_id');

        if (Schema::hasTable('finance_opening_invoices') && $startDate <= $periodEnd) {
            $query = FinanceOpeningInvoice::query()
                ->whereDate('invoice_date', '<=', $periodEnd)
                ->where(function ($q) use ($payablePharmacyIds, $pharmacyIds) {
                    $q->where(function ($hq) use ($payablePharmacyIds) {
                        $hq->where('type', 'HUTANG')->whereIn('pharmacy_id', $payablePharmacyIds);
                    })->orWhere(function ($pq) use ($pharmacyIds) {
                        $pq->where('type', 'PIUTANG')->whereIn('pharmacy_id', $pharmacyIds);
                    });
                });

            if ($hasOpeningInvoiceCol) {
                $query->with(['payments' => fn ($q) => $q
                    ->whereDate('payment_date', '<=', $periodEnd)
                    ->select(['id', 'opening_invoice_id', 'account_id', 'payment_type', 'payment_date', 'amount'])]);
            }

            foreach ($query->get(['id', 'type', 'pharmacy_id', 'invoice_date', 'amount']) as $oi) {
                $invoiceDate = $oi->invoice_date?->format('Y-m-d');
                $amt = (float) $oi->amount;
                $paidBeforeStart = 0.0;
                $paidAsOfEnd = 0.0;
                $cashOutflowInPeriod = 0.0;
                $cashInflowInPeriod = 0.0;

                if ($hasOpeningInvoiceCol) {
                    foreach ($oi->payments as $p) {
                        $pDate = $p->payment_date?->format('Y-m-d');
                        if (!$pDate || $pDate > $periodEnd) {
                            continue;
                        }
                        $pAmt = (float) $p->amount;
                        if ($pDate <= $dayBeforeStart) {
                            $paidBeforeStart += $pAmt;
                        }
                        $paidAsOfEnd += $pAmt;

                        if ($pDate >= $startDate && $pDate <= $periodEnd && in_array((int) ($p->account_id ?? 0), $accountIds, true)) {
                            if (in_array($p->payment_type, FinanceAccountBalanceResolver::OUTFLOW_TYPES, true)) {
                                $cashOutflowInPeriod += $pAmt;
                            } elseif (in_array($p->payment_type, FinanceAccountBalanceResolver::INFLOW_TYPES, true)) {
                                $cashInflowInPeriod += $pAmt;
                            }
                        }
                    }
                }

                $beforeBal = ($invoiceDate && $invoiceDate <= $dayBeforeStart)
                    ? ($amt < 0 ? $amt : max(0.0, $amt - $paidBeforeStart))
                    : 0.0;
                $afterBal = $amt < 0 ? $amt : max(0.0, $amt - $paidAsOfEnd);

                if (strtoupper((string) $oi->type) === 'HUTANG') {
                    // Kenaikan hutang non-operasional mengurangi Posisi Bersih; pembayaran kas regulernya diperiode saling menghapus
                    $openingHutangEffect += (-($afterBal - $beforeBal) - $cashOutflowInPeriod);
                } elseif (strtoupper((string) $oi->type) === 'PIUTANG') {
                    $openingPiutangEffect += (($afterBal - $beforeBal) + $cashInflowInPeriod);
                }
            }
        }

        // 3. Pelunasan Pra-Cut-off non-kas (KREDIT CUTOFF / PIUTANG CUTOFF) pada faktur reguler dalam periode
        $cutoffSettlementEffect = $this->computeRegularCutoffSettlementEffect(
            $pharmacyIds,
            $payablePharmacyIds,
            $startDate,
            $periodEnd
        );

        $opnameKasBank = round($opnameKasBank, 2);
        $openingHutangEffect = round($openingHutangEffect, 2);
        $openingPiutangEffect = round($openingPiutangEffect, 2);
        $cutoffSettlementEffect = round($cutoffSettlementEffect, 2);
        $total = round($opnameKasBank + $openingHutangEffect + $openingPiutangEffect + $cutoffSettlementEffect, 2);

        $hasAdjustments = abs($total) >= 0.005
            || abs($opnameKasBank) >= 0.005
            || abs($openingHutangEffect) >= 0.005
            || abs($openingPiutangEffect) >= 0.005
            || abs($cutoffSettlementEffect) >= 0.005;

        return [
            'opname_kas_bank' => $opnameKasBank,
            'opening_hutang' => $openingHutangEffect,
            'opening_piutang' => $openingPiutangEffect,
            'cutoff_settlement' => $cutoffSettlementEffect,
            'total' => $total,
            'has_adjustments' => $hasAdjustments,
        ];
    }

    private function computeRegularCutoffSettlementEffect(
        array $pharmacyIds,
        array $payablePharmacyIds,
        string $startDate,
        string $periodEnd
    ): float {
        if ($startDate > $periodEnd || !Schema::hasTable('finance_payments')) {
            return 0.0;
        }

        $hasOpeningCol = Schema::hasColumn('finance_payments', 'opening_invoice_id');
        $cutoffQuery = FinancePayment::query()
            ->whereIn('payment_type', ['KREDIT CUTOFF', 'PIUTANG CUTOFF'])
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $periodEnd);
        if ($hasOpeningCol) {
            $cutoffQuery->whereNull('opening_invoice_id');
        }
        $cutoffRows = $cutoffQuery->get(['payment_type', 'receiving_detail_id', 'medicine_transaction_id']);
        if ($cutoffRows->isEmpty()) {
            return 0.0;
        }

        $effect = 0.0;

        // 3a. KREDIT CUTOFF pada receiving_details
        $detailIds = $cutoffRows
            ->where('payment_type', 'KREDIT CUTOFF')
            ->pluck('receiving_detail_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        if (!empty($detailIds) && Schema::hasTable('receiving_details')) {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $receivingDateExpr = $isSqlite
                ? "COALESCE(CASE WHEN date LIKE '__/__/____' THEN substr(date, 7, 4) || '-' || substr(date, 4, 2) || '-' || substr(date, 1, 2) WHEN date LIKE '____-__-__' THEN substr(date, 1, 10) ELSE NULL END, DATE(created_at))"
                : "COALESCE(STR_TO_DATE(NULLIF(date, ''), '%d/%m/%Y'), STR_TO_DATE(NULLIF(date, ''), '%Y-%m-%d'), DATE(created_at))";
            $hasOrderItemsJoin = Schema::hasTable('order_items') && Schema::hasColumn('receiving_items', 'order_items_id');

            $details = ReceivingDetails::query()
                ->whereIn('id', $detailIds)
                ->with([
                    'receiving:id,pharmacy_id,status,date,created_at',
                    'creditor:code,ppn_type',
                    'receiving_items' => function ($query) use ($hasOrderItemsJoin) {
                        $query->whereNotNull('batches_id')
                            ->select(['id', 'receiving_details_id', 'order_items_id', 'total', 'raw_price', 'qty_received', 'qty', 'discount', 'extra_discount']);
                        if ($hasOrderItemsJoin) {
                            $query->with('order_items:id,price');
                        }
                    },
                    'payments' => fn ($query) => $query->whereDate('payment_date', '<=', $periodEnd),
                ])
                ->whereRaw('UPPER(TRIM(COALESCE(invoice_payment, ""))) = ?', ['KREDIT'])
                ->postedToStock()
                ->whereHas('receiving', function ($query) use ($payablePharmacyIds, $periodEnd, $receivingDateExpr) {
                    $query->whereIn('pharmacy_id', $payablePharmacyIds)
                        ->whereRaw("{$receivingDateExpr} <= ?", [$periodEnd]);
                })
                ->get();

            if ($details->isNotEmpty()) {
                $returnsByDetailId = $this->collectPurchaseReturnTotals($details, $periodEnd);
                foreach ($details as $detail) {
                    $retAmt = (float) ($returnsByDetailId[$detail->id] ?? 0.0);
                    $withCutoff = $this->computePayableDetailRemaining($detail, $retAmt, $detail->payments);
                    $paymentsWithoutPeriodCutoff = $detail->payments->reject(function ($p) use ($startDate, $periodEnd) {
                        $pDate = $p->payment_date?->format('Y-m-d');
                        return $p->payment_type === 'KREDIT CUTOFF'
                            && $pDate
                            && $pDate >= $startDate
                            && $pDate <= $periodEnd;
                    });
                    $withoutCutoff = $this->computePayableDetailRemaining($detail, $retAmt, $paymentsWithoutPeriodCutoff);
                    $effect += ($withoutCutoff - $withCutoff);
                }
            }
        }

        // 3b. PIUTANG CUTOFF pada medicine_transactions
        $txIds = $cutoffRows
            ->where('payment_type', 'PIUTANG CUTOFF')
            ->pluck('medicine_transaction_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        if (!empty($txIds) && Schema::hasTable('medicine_transactions')) {
            $endExclusive = FinanceShift::endExclusive($periodEnd);
            $txQuery = MedicineTransactions::query()
                ->whereIn('id', $txIds)
                ->whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT'])
                ->whereIn('pharmacy_id', $pharmacyIds)
                ->where(function ($period) use ($endExclusive) {
                    $period->where('created_at', '<', $endExclusive)
                        ->orWhere(function ($legacy) use ($endExclusive) {
                            $legacy->whereNull('created_at')->where('updated_at', '<', $endExclusive);
                        });
                });
            LiphTransactionScope::applySalesStatus($txQuery);
            $txHeaders = $txQuery->select(['id', 'subtotal', 'paid'])->toBase()->get();

            if ($txHeaders->isNotEmpty()) {
                $validTxIds = $txHeaders->pluck('id')->map(fn ($v) => (int) $v)->all();
                $cartRows = DB::table('medicine_cart')
                    ->whereIn('transaction_id', $validTxIds)
                    ->select(['transaction_id', 'quantity', 'item_price', 'total_price', 'final_price'])
                    ->get();
                $cartSubtotal = [];
                foreach ($cartRows as $c) {
                    $final = (float) ($c->final_price ?: ($c->total_price ?: ((float) ($c->quantity ?? 0) * (float) ($c->item_price ?? 0))));
                    $cartSubtotal[(int) $c->transaction_id] = ($cartSubtotal[(int) $c->transaction_id] ?? 0.0) + $final;
                }
                $returByTx = DB::table('retur')
                    ->whereIn('transaction_id', $validTxIds)
                    ->where(fn ($q) => $q->whereNull('created_at')->orWhere('created_at', '<', $endExclusive))
                    ->groupBy('transaction_id')
                    ->select('transaction_id', DB::raw('SUM(total_retur) as sum_retur'))
                    ->pluck('sum_retur', 'transaction_id')
                    ->all();
                $allPaymentsByTx = DB::table('finance_payments')
                    ->whereIn('medicine_transaction_id', $validTxIds)
                    ->get(['medicine_transaction_id', 'payment_type', 'payment_date', 'amount'])
                    ->groupBy('medicine_transaction_id');

                foreach ($txHeaders as $tx) {
                    $txId = (int) $tx->id;
                    $rawSub = (float) ($tx->subtotal ?? 0);
                    $total = round($rawSub > 0 ? $rawSub : (float) ($cartSubtotal[$txId] ?? 0.0), 2);
                    $netTotal = round(max(0, $total - round((float) ($returByTx[$txId] ?? 0), 2)), 2);

                    $txPays = $allPaymentsByTx->get($txId, collect());
                    $financeAll = (float) $txPays->sum('amount');
                    $financeAsOf = (float) $txPays->filter(fn ($p) => $p->payment_date && substr((string) $p->payment_date, 0, 10) <= $periodEnd)->sum('amount');
                    $periodCutoffAmt = (float) $txPays->filter(function ($p) use ($startDate, $periodEnd) {
                        $d = substr((string) ($p->payment_date ?? ''), 0, 10);
                        return $p->payment_type === 'PIUTANG CUTOFF' && $d !== '' && $d >= $startDate && $d <= $periodEnd;
                    })->sum('amount');

                    $legacyPaid = (float) ($tx->paid ?? 0);
                    $paidWith = ($financeAsOf + 0.005 >= $financeAll)
                        ? max($financeAll, $legacyPaid)
                        : $financeAsOf + max(0, $legacyPaid - $financeAll);
                    $paidWith = round(min($netTotal, $paidWith), 2);
                    $sisaWith = round(max(0, $netTotal - $paidWith), 2);

                    $financeWithout = max(0.0, $financeAsOf - $periodCutoffAmt);
                    $financeAllWithout = max(0.0, $financeAll - $periodCutoffAmt);
                    $paidWithout = ($financeWithout + 0.005 >= $financeAllWithout)
                        ? max($financeAllWithout, $legacyPaid)
                        : $financeWithout + max(0, $legacyPaid - $financeAllWithout);
                    $paidWithout = round(min($netTotal, $paidWithout), 2);
                    $sisaWithout = round(max(0, $netTotal - $paidWithout), 2);

                    // PIUTANG CUTOFF menurunkan piutang akhir tanpa kas masuk (efek ekuitas negatif)
                    $effect -= ($sisaWithout - $sisaWith);
                }
            }
        }

        return round($effect, 2);
    }

    private function collectPurchaseReturnTotals(iterable $details, string $asOfDate): array
    {
        $returnsByDetailId = [];
        if (!Schema::hasTable('purchase_returns') || !Schema::hasTable('purchase_return_items')) {
            return $returnsByDetailId;
        }

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
        if (empty($detailIds)) {
            return $returnsByDetailId;
        }

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

        return $returnsByDetailId;
    }

    private function computePayableDetailRemaining(ReceivingDetails $detail, float $returFromPr, $payments): float
    {
        $ppnType = strtoupper(trim((string) ($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA')));
        $invoiceTotal = ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType)['total'];

        $paidCashBank = (float) $payments->where('payment_type', '!=', 'RETUR')->sum('amount');
        $paidRetur = (float) $payments->where('payment_type', 'RETUR')->sum('amount');
        $totalReturDeduction = round(max($paidRetur, $returFromPr), 2);
        $netTotal = round(max(0, $invoiceTotal - $totalReturDeduction), 2);
        $paidApplied = round(min($netTotal, $paidCashBank), 2);

        $sisa = round(max(0, $netTotal - $paidApplied), 2);
        return $sisa < 0.005 ? 0.0 : $sisa;
    }
}

