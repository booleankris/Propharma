<?php

namespace App\Services\Finance;

use App\Models\FinanceOpeningInvoice;
use App\Models\MedicineTransactions;
use App\Models\Receiving;
use App\Models\Retur;
use App\Support\LiphTransactionScope;
use App\Support\ReceivingInvoiceTotals;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayablesReceivablesService
{
    public const CHUNK_SIZE = 1000;

    /**
     * Normalize date values stored in d/m/Y, Y-m-d, or timestamp formats to Y-m-d.
     */
    public function normalizeDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $raw = trim((string) $value);
        if ($raw === '' || $raw === '-') {
            return null;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $raw, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Format numeric discount matching FinanceController display convention.
     */
    protected function formatDiscount($value): string
    {
        $numeric = (float) $value;
        return $numeric > 100
            ? 'Rp ' . number_format($numeric, 0, ',', '.')
            : rtrim(rtrim(number_format($numeric, 2, '.', ''), '0'), '.') . '%';
    }

    /**
     * Build Hutang Dagang dataset, global stats, due stats, and optional server pagination.
     */
    public function getHutangDataset(
        array $pharmacyIds,
        array $filters = [],
        bool $includeDetails = false,
        ?int $perPage = null,
        int $page = 1,
        bool $syncZeroStatus = true
    ): array {
        $pharmacyIds = array_values(array_filter(array_map('intval', $pharmacyIds)));
        if (empty($pharmacyIds)) {
            return [
                'rows' => [],
                'stats' => $this->emptyHutangStats(),
                'dueStats' => ['overdue' => 0, 'dueSoon' => 0],
                'pagination' => $this->emptyPagination($perPage ?? 10, $page),
            ];
        }

        if ($syncZeroStatus) {
            Receiving::syncPostedZeroStatus($pharmacyIds);
        }

        $headers = DB::table('receiving_details as rd')
            ->join('receiving as r', 'r.id', '=', 'rd.receiving_id')
            ->leftJoin('creditors as c', 'c.code', '=', 'rd.creditor_code')
            ->leftJoin('pharmacies as p', 'p.id', '=', 'r.pharmacy_id')
            ->whereIn('r.pharmacy_id', $pharmacyIds)
            ->where('rd.invoice_payment', 'KREDIT')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('receiving_items as ri')
                    ->whereColumn('ri.receiving_details_id', 'rd.id')
                    ->whereNotNull('ri.batches_id');
            })
            ->select([
                'rd.id',
                'rd.receiving_id',
                'rd.invoice_number',
                'rd.receiving_details_code',
                'rd.creditor_code',
                'rd.invoice_date',
                'rd.invoice_due',
                'rd.invoice_ppn',
                'rd.is_submitted',
                'rd.created_at',
                'r.code as rec_code',
                'r.date as rec_date',
                'r.status as rec_status',
                'r.updated_at as rec_updated_at',
                'c.name as creditor_name',
                'c.ppn_type as creditor_ppn_type',
                'p.name as pharmacy_name',
            ])
            ->orderByDesc('r.updated_at')
            ->orderByDesc('rd.id')
            ->get();

        $allRows = array_merge(
            $this->assembleHutangRows($headers),
            $this->assembleOpeningHutangRows($pharmacyIds)
        );

        $stats = $this->summarizeHutangStats($allRows);
        $dueStats = $this->computeDueStats($allRows);

        $filtered = $this->filterAndSortHutangRows($allRows, $filters);

        $totalFiltered = count($filtered);
        $pagination = null;
        if ($perPage !== null && $perPage > 0) {
            $totalPages = max(1, (int) ceil($totalFiltered / $perPage));
            $currentPage = max(1, min($page, $totalPages));
            $offset = ($currentPage - 1) * $perPage;
            $resultRows = array_slice($filtered, $offset, $perPage);
            $pagination = [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $totalFiltered,
                'last_page' => $totalPages,
                'from' => $totalFiltered > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $totalFiltered),
            ];
        } else {
            $resultRows = $filtered;
            $pagination = [
                'current_page' => 1,
                'per_page' => max(1, $totalFiltered),
                'total' => $totalFiltered,
                'last_page' => 1,
                'from' => $totalFiltered > 0 ? 1 : 0,
                'to' => $totalFiltered,
            ];
        }

        if ($includeDetails && !empty($resultRows)) {
            $resultRows = $this->hydrateHutangDetails($resultRows);
        } else {
            foreach ($resultRows as &$row) {
                unset($row['_ppn_type'], $row['_invoice_number_raw']);
            }
            unset($row);
        }

        return [
            'rows' => $resultRows,
            'stats' => $stats,
            'dueStats' => $dueStats,
            'pagination' => $pagination,
        ];
    }

    /**
     * Fetch a single Hutang Dagang invoice with complete items, payments, and purchase return history.
     */
    public function getHutangDetail(int|string $detailId, array $pharmacyIds): ?array
    {
        $pharmacyIds = array_values(array_filter(array_map('intval', $pharmacyIds)));
        if (empty($pharmacyIds)) {
            return null;
        }

        $openingId = FinanceOpeningInvoice::parseCompositeId($detailId);
        if ($openingId !== null) {
            $rows = $this->assembleOpeningHutangRows($pharmacyIds, $openingId);
            if (empty($rows)) {
                return null;
            }
            $hydrated = $this->hydrateHutangDetails($rows);
            return $hydrated[0] ?? null;
        }

        $detailId = (int) $detailId;

        $headers = DB::table('receiving_details as rd')
            ->join('receiving as r', 'r.id', '=', 'rd.receiving_id')
            ->leftJoin('creditors as c', 'c.code', '=', 'rd.creditor_code')
            ->leftJoin('pharmacies as p', 'p.id', '=', 'r.pharmacy_id')
            ->where('rd.id', $detailId)
            ->whereIn('r.pharmacy_id', $pharmacyIds)
            ->where('rd.invoice_payment', 'KREDIT')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('receiving_items as ri')
                    ->whereColumn('ri.receiving_details_id', 'rd.id')
                    ->whereNotNull('ri.batches_id');
            })
            ->select([
                'rd.id',
                'rd.receiving_id',
                'rd.invoice_number',
                'rd.receiving_details_code',
                'rd.creditor_code',
                'rd.invoice_date',
                'rd.invoice_due',
                'rd.invoice_ppn',
                'rd.is_submitted',
                'rd.created_at',
                'r.code as rec_code',
                'r.date as rec_date',
                'r.status as rec_status',
                'r.updated_at as rec_updated_at',
                'c.name as creditor_name',
                'c.ppn_type as creditor_ppn_type',
                'p.name as pharmacy_name',
            ])
            ->get();

        if ($headers->isEmpty()) {
            return null;
        }

        $rows = $this->assembleHutangRows($headers);
        $hydrated = $this->hydrateHutangDetails($rows);

        return $hydrated[0] ?? null;
    }

    /**
     * Build Piutang Penjualan dataset, global stats, due stats, and optional server pagination.
     */
    public function getPiutangDataset(
        array $pharmacyIds,
        array $filters = [],
        bool $includeDetails = false,
        ?int $perPage = null,
        int $page = 1
    ): array {
        $pharmacyIds = array_values(array_filter(array_map('intval', $pharmacyIds)));
        if (empty($pharmacyIds)) {
            return [
                'rows' => [],
                'stats' => $this->emptyPiutangStats(),
                'dueStats' => ['overdue' => 0, 'dueSoon' => 0],
                'pagination' => $this->emptyPagination($perPage ?? 10, $page),
            ];
        }

        $query = MedicineTransactions::query()
            ->whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT'])
            ->whereIn('pharmacy_id', $pharmacyIds);
        LiphTransactionScope::applySalesStatus($query);

        $headers = $query
            ->orderByDesc('id')
            ->select([
                'id',
                'transaction_code',
                'transaction_type',
                'debtor_id',
                'patient_id',
                'doctor_id',
                'pharmacy_id',
                'subtotal',
                'paid',
                'is_submitted',
                'created_at',
                'updated_at',
            ])
            ->toBase()
            ->get();

        $allRows = array_merge(
            $this->assemblePiutangRows($headers),
            $this->assembleOpeningPiutangRows($pharmacyIds)
        );

        $stats = $this->summarizePiutangStats($allRows);
        $dueStats = $this->computeDueStats($allRows);

        $filtered = $this->filterAndSortPiutangRows($allRows, $filters);

        $totalFiltered = count($filtered);
        if ($perPage !== null && $perPage > 0) {
            $totalPages = max(1, (int) ceil($totalFiltered / $perPage));
            $currentPage = max(1, min($page, $totalPages));
            $offset = ($currentPage - 1) * $perPage;
            $resultRows = array_slice($filtered, $offset, $perPage);
            $pagination = [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $totalFiltered,
                'last_page' => $totalPages,
                'from' => $totalFiltered > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $totalFiltered),
            ];
        } else {
            $resultRows = $filtered;
            $pagination = [
                'current_page' => 1,
                'per_page' => max(1, $totalFiltered),
                'total' => $totalFiltered,
                'last_page' => 1,
                'from' => $totalFiltered > 0 ? 1 : 0,
                'to' => $totalFiltered,
            ];
        }

        if ($includeDetails && !empty($resultRows)) {
            $resultRows = $this->hydratePiutangDetails($resultRows);
        }

        return [
            'rows' => $resultRows,
            'stats' => $stats,
            'dueStats' => $dueStats,
            'pagination' => $pagination,
        ];
    }

    /**
     * Fetch a single Piutang transaction with complete items, payments, and return history.
     */
    public function getPiutangDetail(int|string $txId, array $pharmacyIds): ?array
    {
        $pharmacyIds = array_values(array_filter(array_map('intval', $pharmacyIds)));
        if (empty($pharmacyIds)) {
            return null;
        }

        $openingId = FinanceOpeningInvoice::parseCompositeId($txId);
        if ($openingId !== null) {
            $rows = $this->assembleOpeningPiutangRows($pharmacyIds, $openingId);
            if (empty($rows)) {
                return null;
            }
            $hydrated = $this->hydratePiutangDetails($rows);
            return $hydrated[0] ?? null;
        }

        $txId = (int) $txId;

        $query = MedicineTransactions::query()
            ->where('id', $txId)
            ->whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT'])
            ->whereIn('pharmacy_id', $pharmacyIds);
        LiphTransactionScope::applySalesStatus($query);

        $headers = $query
            ->select([
                'id',
                'transaction_code',
                'transaction_type',
                'debtor_id',
                'patient_id',
                'doctor_id',
                'pharmacy_id',
                'subtotal',
                'paid',
                'is_submitted',
                'created_at',
                'updated_at',
            ])
            ->toBase()
            ->get();

        if ($headers->isEmpty()) {
            return null;
        }

        $rows = $this->assemblePiutangRows($headers);
        $hydrated = $this->hydratePiutangDetails($rows);

        return $hydrated[0] ?? null;
    }

    /**
     * Build paginated Riwayat Retur Penjualan Kredit without the legacy 300-row cap.
     */
    public function getCreditReturnsDataset(
        array $pharmacyIds,
        array $filters = [],
        ?int $perPage = 10,
        int $page = 1
    ): array {
        $pharmacyIds = array_values(array_filter(array_map('intval', $pharmacyIds)));
        if (empty($pharmacyIds)) {
            return [
                'rows' => [],
                'pagination' => $this->emptyPagination($perPage ?? 10, $page),
                'totalNominal' => 0.0,
            ];
        }

        $baseQuery = Retur::query()
            ->where('status', 1)
            ->whereHas('transaction', function ($q) use ($pharmacyIds) {
                $q->whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT'])
                    ->whereIn('pharmacy_id', $pharmacyIds);
                LiphTransactionScope::applySalesStatus($q);
            });

        $totalNominal = (float) (clone $baseQuery)->sum('total_retur');

        $search = trim((string) ($filters['retur_search'] ?? $filters['search'] ?? ''));
        $debtorId = trim((string) ($filters['retur_debtor_id'] ?? $filters['debtor_id'] ?? ''));
        $startDate = trim((string) ($filters['start_date'] ?? ''));
        $endDate = trim((string) ($filters['end_date'] ?? ''));

        if ($debtorId !== '') {
            $baseQuery->whereHas('transaction', fn($q) => $q->where('debtor_id', (int) $debtorId));
        }

        if ($startDate !== '') {
            $baseQuery->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate !== '') {
            $baseQuery->whereDate('created_at', '<=', $endDate);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $baseQuery->where(function ($q) use ($like) {
                $q->where('code', 'like', $like)
                    ->orWhereHas('medicine', function ($mq) use ($like) {
                        $mq->where('name', 'like', $like)
                            ->orWhere('code', 'like', $like);
                    })
                    ->orWhereHas('transaction', function ($tq) use ($like) {
                        $tq->where('transaction_code', 'like', $like)
                            ->orWhereHas('debtors', fn($dq) => $dq->where('name', 'like', $like))
                            ->orWhereHas('patients', fn($pq) => $pq->where('name', 'like', $like));
                    });
            });
        }

        $baseQuery->with([
            'medicine:id,code,name,unit',
            'transaction' => fn($tq) => $tq->select(['id', 'transaction_code', 'debtor_id', 'patient_id', 'doctor_id', 'pharmacy_id'])
                ->with(['debtors:id,code,name', 'patients:id,name', 'doctors:id,name', 'pharmacy:id,name']),
        ])->latest('id');

        if ($perPage !== null && $perPage > 0) {
            $paginator = $baseQuery->paginate($perPage, ['*'], 'retur_page', max(1, $page));
            $items = $paginator->items();
            $pagination = [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => max(1, $paginator->lastPage()),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ];
        } else {
            $items = $baseQuery->get()->all();
            $count = count($items);
            $pagination = [
                'current_page' => 1,
                'per_page' => max(1, $count),
                'total' => $count,
                'last_page' => 1,
                'from' => $count > 0 ? 1 : 0,
                'to' => $count,
            ];
        }

        $rows = [];
        foreach ($items as $ret) {
            $tx = $ret->transaction;
            $debtorName = $tx?->debtors?->name ?? ($tx?->patient_id ? ('Pasien: ' . ($tx->patients->name ?? 'Umum')) : 'Pelanggan Umum');
            $createdCarbon = $ret->created_at ? Carbon::parse($ret->created_at) : null;

            $rows[] = [
                'id' => $ret->id,
                'code' => $ret->code ?: ('RT-' . $ret->id),
                'transaction_id' => $ret->transaction_id,
                'transaction_code' => $tx?->transaction_code ?: ('TRX-' . $ret->transaction_id),
                'debtor' => $debtorName,
                'debtor_id' => $tx?->debtor_id,
                'debtor_code' => $tx?->debtors?->code ?? '-',
                'dokter' => $tx?->doctors?->name ?? '-',
                'pasien' => $tx?->patients?->name ?? '-',
                'apotek' => $tx?->pharmacy?->name ?? 'Apotek Utama',
                'tanggal' => $createdCarbon ? $createdCarbon->format('d/m/Y') : '-',
                'raw_created_at' => $createdCarbon ? $createdCarbon->format('Y-m-d H:i:s') : null,
                'medicine_id' => $ret->medicine_id,
                'medicine_name' => $ret->medicine?->name ?? 'Obat',
                'medicine_sku' => $ret->medicine?->code ?? ('SKU-' . $ret->medicine_id),
                'satuan' => $ret->medicine?->unit ?? 'Pcs',
                'qty_retur' => (float) ($ret->qty_retur ?? 0),
                'total_retur' => (float) ($ret->total_retur ?? 0),
                'status' => 'Selesai',
            ];
        }

        return [
            'rows' => $rows,
            'pagination' => $pagination,
            'totalNominal' => round($totalNominal, 2),
        ];
    }

    /**
     * Assemble lean Hutang Dagang rows in chunks without hydrating nested line items/history.
     */
    protected function assembleHutangRows($headers): array
    {
        if ($headers->isEmpty()) {
            return [];
        }

        $detailIds = [];
        $ppnByDetailId = [];
        $invoiceNumberToDetailIds = [];

        foreach ($headers as $h) {
            $detailId = (int) $h->id;
            $detailIds[] = $detailId;
            $ppnByDetailId[$detailId] = strtoupper(trim((string) ($h->invoice_ppn ?? $h->creditor_ppn_type ?? 'TANPA')));
            $invNorm = strtoupper(trim((string) ($h->invoice_number ?? '')));
            if ($invNorm !== '') {
                $invoiceNumberToDetailIds[$invNorm][] = $detailId;
            }
        }

        $amountsByDetailId = [];
        $paidCashByDetailId = [];
        $paidReturByDetailId = [];
        $latestPaymentDateByDetailId = [];
        $returnSummaryByDetailId = [];
        $hasPurchaseReturnsTable = Schema::hasTable('purchase_returns') && Schema::hasTable('purchase_return_items');

        $hasOrderItemsJoin = Schema::hasTable('order_items') && Schema::hasColumn('receiving_items', 'order_items_id');

        foreach (array_chunk($detailIds, self::CHUNK_SIZE) as $chunkIds) {
            $itemQuery = DB::table('receiving_items as ri')
                ->whereIn('ri.receiving_details_id', $chunkIds)
                ->whereNotNull('ri.batches_id');

            if ($hasOrderItemsJoin) {
                $itemQuery->leftJoin('order_items as oi', 'oi.id', '=', 'ri.order_items_id')
                    ->select([
                        'ri.receiving_details_id',
                        'ri.qty_received',
                        'ri.qty',
                        'ri.raw_price',
                        'ri.discount',
                        'ri.extra_discount',
                        'oi.price as order_price',
                    ]);
            } else {
                $itemQuery->select([
                    'ri.receiving_details_id',
                    'ri.qty_received',
                    'ri.qty',
                    'ri.raw_price',
                    'ri.discount',
                    'ri.extra_discount',
                    DB::raw('0 as order_price'),
                ]);
            }

            $itemRows = $itemQuery->get()->groupBy('receiving_details_id');

            foreach ($chunkIds as $id) {
                $lines = $itemRows->get($id, collect())->map(function ($line) {
                    return (object) [
                        'qty_received' => $line->qty_received,
                        'qty' => $line->qty,
                        'raw_price' => $line->raw_price ?? $line->order_price ?? 0,
                        'discount' => $line->discount ?? 0,
                        'extra_discount' => $line->extra_discount ?? 0,
                    ];
                });
                $amountsByDetailId[$id] = ReceivingInvoiceTotals::calculate($lines, $ppnByDetailId[$id] ?? 'TANPA');
            }

            $paymentRows = DB::table('finance_payments')
                ->whereIn('receiving_detail_id', $chunkIds)
                ->select([
                    'receiving_detail_id',
                    'payment_type',
                    DB::raw('SUM(amount) as total_amount'),
                    DB::raw('MAX(payment_date) as max_payment_date'),
                ])
                ->groupBy('receiving_detail_id', 'payment_type')
                ->get();

            foreach ($paymentRows as $pr) {
                $id = (int) $pr->receiving_detail_id;
                if ($pr->payment_type === 'RETUR') {
                    $paidReturByDetailId[$id] = ($paidReturByDetailId[$id] ?? 0.0) + (float) $pr->total_amount;
                } else {
                    $paidCashByDetailId[$id] = ($paidCashByDetailId[$id] ?? 0.0) + (float) $pr->total_amount;
                    if (!empty($pr->max_payment_date)) {
                        $prev = $latestPaymentDateByDetailId[$id] ?? '';
                        if ($pr->max_payment_date > $prev) {
                            $latestPaymentDateByDetailId[$id] = $pr->max_payment_date;
                        }
                    }
                }
            }
        }

        if ($hasPurchaseReturnsTable) {
            $chunkInvoicePairs = [];
            foreach ($headers as $h) {
                if (!empty($h->invoice_number)) {
                    $chunkInvoicePairs[] = trim((string) $h->invoice_number);
                }
            }
            $uniqueInvoices = array_values(array_unique(array_filter($chunkInvoicePairs)));

            $seenReturnIdsByDetail = [];
            $processReturnRows = function ($retRows) use (&$seenReturnIdsByDetail, &$returnSummaryByDetailId, $invoiceNumberToDetailIds) {
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

                    foreach (array_unique($matchedIds) as $dId) {
                        if (isset($seenReturnIdsByDetail[$dId][$r->id])) {
                            continue;
                        }
                        $seenReturnIdsByDetail[$dId][$r->id] = true;
                        $returnSummaryByDetailId[$dId]['count'] = ($returnSummaryByDetailId[$dId]['count'] ?? 0) + 1;
                        if ($r->status === 'sudah_potong') {
                            $returnSummaryByDetailId[$dId]['sudah_potong'] = ($returnSummaryByDetailId[$dId]['sudah_potong'] ?? 0.0) + (float) $r->subtotal;
                        }
                    }
                }
            };

            foreach (array_chunk($detailIds, self::CHUNK_SIZE) as $chunkIds) {
                $retByReceiving = DB::table('purchase_return_items as pri')
                    ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                    ->whereIn('pr.receiving_id', $chunkIds)
                    ->where('pr.status', '!=', 'dibatalkan')
                    ->select(['pri.id', 'pri.subtotal', 'pri.status', 'pr.receiving_id as pr_receiving_id', 'pr.invoice_number as pr_invoice_number'])
                    ->get();
                $processReturnRows($retByReceiving);
            }

            foreach (array_chunk($uniqueInvoices, self::CHUNK_SIZE) as $chunkInvoices) {
                $retByInvoice = DB::table('purchase_return_items as pri')
                    ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                    ->whereIn('pr.invoice_number', $chunkInvoices)
                    ->where('pr.status', '!=', 'dibatalkan')
                    ->select(['pri.id', 'pri.subtotal', 'pri.status', 'pr.receiving_id as pr_receiving_id', 'pr.invoice_number as pr_invoice_number'])
                    ->get();
                $processReturnRows($retByInvoice);
            }
        }

        $todayEnd = now();
        $rows = [];

        foreach ($headers as $h) {
            $detailId = (int) $h->id;
            $amounts = $amountsByDetailId[$detailId] ?? ['subtotal' => 0.0, 'ppn' => 0.0, 'total' => 0.0];
            $subtotal = (float) $amounts['subtotal'];
            $ppnNominal = (float) $amounts['ppn'];
            $total = (float) $amounts['total'];

            $paidReturFinance = (float) ($paidReturByDetailId[$detailId] ?? 0.0);
            $sudahPotongTotal = (float) ($returnSummaryByDetailId[$detailId]['sudah_potong'] ?? 0.0);
            $retursCount = (int) ($returnSummaryByDetailId[$detailId]['count'] ?? 0);
            $paidCashBank = (float) ($paidCashByDetailId[$detailId] ?? 0.0);

            $totalRetur = round(max($paidReturFinance, $sudahPotongTotal), 2);
            $netTotal = round(max(0, $total - $totalRetur), 2);
            $totalTerbayar = round(min($netTotal, $paidCashBank), 2);
            $sisa = round(max(0, $netTotal - $totalTerbayar), 2);

            $isLunas = ($sisa < 0.005 && $total > 0) || ((int) $h->rec_status === 3 && $sisa <= 0.005);
            $sisa = $isLunas ? 0 : $sisa;

            if ($isLunas) {
                if ($totalRetur >= $total && $total > 0) {
                    $statusLabel = 'Diretur Penuh';
                } elseif ($totalRetur > 0) {
                    $statusLabel = 'Lunas (Ada Retur)';
                } else {
                    $statusLabel = 'Lunas';
                }
            } else {
                if ($totalTerbayar > 0) {
                    $statusLabel = 'Dibayar Sebagian';
                } elseif ($totalRetur > 0) {
                    $statusLabel = 'Diretur Sebagian';
                } else {
                    $statusLabel = 'Belum Dibayar';
                }
            }

            $invoiceDate = $this->normalizeDate($h->invoice_date);
            $dueDate = $this->normalizeDate($h->invoice_due);
            $dueCarbon = $dueDate ? Carbon::parse($dueDate)->endOfDay() : null;
            $isOverdue = ($dueCarbon && !$isLunas) ? $dueCarbon->isPast() : false;
            $daysOverdue = ($isOverdue && $dueCarbon) ? (int) $dueCarbon->diffInDays($todayEnd) : 0;
            $daysRemaining = (!$isOverdue && $dueCarbon && !$isLunas) ? (int) $todayEnd->diffInDays($dueCarbon) : 0;

            $tanggalBayar = '';
            if ($isLunas) {
                $maxPay = $latestPaymentDateByDetailId[$detailId] ?? null;
                $tanggalBayar = $maxPay
                    ? Carbon::parse($maxPay)->format('d/m/Y')
                    : ($h->rec_updated_at ? Carbon::parse($h->rec_updated_at)->format('d/m/Y') : '');
            }

            $vendorName = $h->creditor_name ?? ($h->creditor_code ?: 'PBF / Vendor');

            $rows[] = [
                'id' => $detailId,
                'receiving_id' => (int) $h->receiving_id,
                'nomor' => $h->invoice_number ?: $h->rec_code,
                'vendor' => $vendorName,
                'vendor_code' => $h->creditor_code ?: '',
                'referensi' => $h->receiving_details_code ?: $h->rec_code,
                'tanggal' => $invoiceDate ? Carbon::parse($invoiceDate)->format('d/m/Y') : ($h->rec_date ?: '-'),
                'raw_tanggal' => $invoiceDate,
                'raw_tanggal_terima' => $this->normalizeDate($h->created_at ?: $h->rec_date),
                'jatuhTempo' => $dueCarbon ? $dueCarbon->format('d/m/Y') : '-',
                'raw_jatuh_tempo' => $dueCarbon ? $dueCarbon->format('Y-m-d') : null,
                'is_overdue' => $isOverdue,
                'days_overdue' => $daysOverdue,
                'days_remaining' => $daysRemaining,
                'tanggalBayar' => $tanggalBayar,
                'status' => $statusLabel,
                'subtotal' => $subtotal,
                'ppn' => $ppnNominal,
                'total' => $total,
                'total_retur' => $totalRetur,
                'returs_count' => $retursCount,
                'net_total' => $netTotal,
                'terbayar' => $totalTerbayar,
                'sisa' => $sisa,
                'returs' => [],
                'gudang' => $h->pharmacy_name ?? 'Gudang Utama',
                'tglKirim' => $h->rec_date ?: '-',
                'items' => [],
                'payments' => [],
                'is_submitted' => (bool) ($h->is_submitted ?? false),
                'lastModified' => $h->rec_updated_at ? Carbon::parse($h->rec_updated_at)->format('d M Y H:i') : '-',
                '_ppn_type' => $ppnByDetailId[$detailId] ?? 'TANPA',
                '_invoice_number_raw' => $h->invoice_number,
            ];
        }

        return $rows;
    }

    /**
     * Build Hutang Dagang rows from manual opening balance invoices (without stock mutation).
     */
    protected function assembleOpeningHutangRows(array $pharmacyIds, ?int $onlyOpeningId = null): array
    {
        if (!Schema::hasTable('finance_opening_invoices')) {
            return [];
        }

        $query = DB::table('finance_opening_invoices as oi')
            ->leftJoin('creditors as c', 'c.code', '=', 'oi.creditor_code')
            ->leftJoin('pharmacies as p', 'p.id', '=', 'oi.pharmacy_id')
            ->where('oi.type', 'HUTANG')
            ->whereIn('oi.pharmacy_id', $pharmacyIds);

        if ($onlyOpeningId !== null) {
            $query->where('oi.id', $onlyOpeningId);
        }

        $records = $query->select([
            'oi.id',
            'oi.pharmacy_id',
            'oi.creditor_code',
            'oi.invoice_number',
            'oi.invoice_date',
            'oi.due_date',
            'oi.amount',
            'oi.is_submitted',
            'oi.notes',
            'oi.created_at',
            'oi.updated_at',
            'c.name as creditor_name',
            'p.name as pharmacy_name',
        ])->orderByDesc('oi.invoice_date')->orderByDesc('oi.id')->get();

        if ($records->isEmpty()) {
            return [];
        }

        $openingIds = $records->pluck('id')->map(fn($v) => (int) $v)->all();
        $paidByOpening = [];
        $latestPayByOpening = [];

        if (Schema::hasColumn('finance_payments', 'opening_invoice_id')) {
            $payRows = DB::table('finance_payments')
                ->whereIn('opening_invoice_id', $openingIds)
                ->select([
                    'opening_invoice_id',
                    DB::raw('SUM(amount) as total_amount'),
                    DB::raw('MAX(payment_date) as max_payment_date'),
                ])
                ->groupBy('opening_invoice_id')
                ->get();

            foreach ($payRows as $pr) {
                $id = (int) $pr->opening_invoice_id;
                $paidByOpening[$id] = (float) $pr->total_amount;
                $latestPayByOpening[$id] = $pr->max_payment_date;
            }
        }

        $todayEnd = now()->endOfDay();
        $rows = [];
        foreach ($records as $rec) {
            $id = (int) $rec->id;
            $total = round((float) $rec->amount, 2);
            $isNegativeAdj = ($total < 0);
            $paid = $isNegativeAdj ? 0.0 : round(min($total, (float) ($paidByOpening[$id] ?? 0.0)), 2);
            $sisa = $isNegativeAdj ? $total : round(max(0, $total - $paid), 2);
            $isLunas = !$isNegativeAdj && ($sisa < 0.005);
            if ($isLunas) {
                $sisa = 0.0;
            }
            $statusLabel = $isNegativeAdj
                ? 'Koreksi Saldo Awal'
                : ($isLunas ? 'Lunas' : ($paid > 0 ? 'Dibayar Sebagian' : 'Belum Dibayar'));

            $invoiceDate = $this->normalizeDate($rec->invoice_date);
            $dueDate = $this->normalizeDate($rec->due_date);
            $dueCarbon = $dueDate ? Carbon::parse($dueDate) : null;
            $isOverdue = ($dueCarbon && !$isLunas && !$isNegativeAdj) ? $dueCarbon->endOfDay()->isPast() : false;
            $daysOverdue = ($isOverdue && $dueCarbon) ? (int) $dueCarbon->diffInDays($todayEnd) : 0;
            $daysRemaining = (!$isOverdue && $dueCarbon && !$isLunas && !$isNegativeAdj) ? (int) now()->startOfDay()->diffInDays($dueCarbon->copy()->startOfDay()) : 0;

            $tanggalBayar = '';
            if ($isLunas) {
                $maxPay = $latestPayByOpening[$id] ?? null;
                $tanggalBayar = $maxPay
                    ? Carbon::parse($maxPay)->format('d/m/Y')
                    : ($rec->updated_at ? Carbon::parse($rec->updated_at)->format('d/m/Y') : '');
            }

            $rows[] = [
                'id' => 'OB-' . $id,
                'opening_id' => $id,
                'is_opening' => true,
                'is_negative_adjustment' => $isNegativeAdj,
                'receiving_id' => null,
                'nomor' => $rec->invoice_number,
                'vendor' => $rec->creditor_name ?? ($rec->creditor_code ?: 'PBF / Vendor'),
                'vendor_code' => $rec->creditor_code ?: '',
                'referensi' => 'SALDO AWAL',
                'tanggal' => $invoiceDate ? Carbon::parse($invoiceDate)->format('d/m/Y') : '-',
                'raw_tanggal' => $invoiceDate,
                'raw_tanggal_terima' => $invoiceDate,
                'jatuhTempo' => $dueCarbon ? $dueCarbon->format('d/m/Y') : '-',
                'raw_jatuh_tempo' => $dueCarbon ? $dueCarbon->format('Y-m-d') : null,
                'is_overdue' => $isOverdue,
                'days_overdue' => $daysOverdue,
                'days_remaining' => $daysRemaining,
                'tanggalBayar' => $tanggalBayar,
                'status' => $statusLabel,
                'subtotal' => $total,
                'ppn' => 0.0,
                'total' => $total,
                'total_retur' => 0.0,
                'returs_count' => 0,
                'net_total' => $total,
                'terbayar' => $paid,
                'sisa' => $sisa,
                'returs' => [],
                'gudang' => $rec->pharmacy_name ?? 'Gudang Utama',
                'tglKirim' => $invoiceDate ? Carbon::parse($invoiceDate)->format('d/m/Y') : '-',
                'items' => [],
                'payments' => [],
                'is_submitted' => (bool) ($rec->is_submitted ?? false),
                'notes' => $rec->notes ?: '',
                'lastModified' => $rec->updated_at ? Carbon::parse($rec->updated_at)->format('d M Y H:i') : '-',
                '_ppn_type' => 'TANPA',
                '_invoice_number_raw' => $rec->invoice_number,
            ];
        }

        return $rows;
    }

    /**
     * Build Piutang rows from manual opening balance invoices (without stock/LIPH mutation).
     */
    protected function assembleOpeningPiutangRows(array $pharmacyIds, ?int $onlyOpeningId = null): array
    {
        if (!Schema::hasTable('finance_opening_invoices')) {
            return [];
        }

        $query = DB::table('finance_opening_invoices as oi')
            ->leftJoin('debtors as d', 'd.id', '=', 'oi.debtor_id')
            ->leftJoin('pharmacies as p', 'p.id', '=', 'oi.pharmacy_id')
            ->where('oi.type', 'PIUTANG')
            ->whereIn('oi.pharmacy_id', $pharmacyIds);

        if ($onlyOpeningId !== null) {
            $query->where('oi.id', $onlyOpeningId);
        }

        $records = $query->select([
            'oi.id',
            'oi.pharmacy_id',
            'oi.debtor_id',
            'oi.invoice_number',
            'oi.invoice_date',
            'oi.due_date',
            'oi.amount',
            'oi.is_submitted',
            'oi.notes',
            'oi.created_at',
            'oi.updated_at',
            'd.name as debtor_name',
            'd.code as debtor_code',
            'p.name as pharmacy_name',
        ])->orderByDesc('oi.invoice_date')->orderByDesc('oi.id')->get();

        if ($records->isEmpty()) {
            return [];
        }

        $openingIds = $records->pluck('id')->map(fn($v) => (int) $v)->all();
        $paidByOpening = [];
        $latestPayByOpening = [];

        if (Schema::hasColumn('finance_payments', 'opening_invoice_id')) {
            $payRows = DB::table('finance_payments')
                ->whereIn('opening_invoice_id', $openingIds)
                ->select([
                    'opening_invoice_id',
                    DB::raw('SUM(amount) as total_amount'),
                    DB::raw('MAX(payment_date) as max_payment_date'),
                ])
                ->groupBy('opening_invoice_id')
                ->get();

            foreach ($payRows as $pr) {
                $id = (int) $pr->opening_invoice_id;
                $paidByOpening[$id] = (float) $pr->total_amount;
                $latestPayByOpening[$id] = $pr->max_payment_date;
            }
        }

        $todayEnd = now();
        $rows = [];
        foreach ($records as $rec) {
            $id = (int) $rec->id;
            $total = round((float) $rec->amount, 2);
            $isNegativeAdj = ($total < 0);
            $paid = $isNegativeAdj ? 0.0 : round(min($total, (float) ($paidByOpening[$id] ?? 0.0)), 2);
            $sisa = $isNegativeAdj ? $total : round(max(0, $total - $paid), 2);
            $isLunas = !$isNegativeAdj && ($sisa < 0.005);
            if ($isLunas) {
                $sisa = 0.0;
            }
            $statusLabel = $isNegativeAdj
                ? 'Koreksi Saldo Awal'
                : ($isLunas ? 'Lunas' : ($paid > 0 ? 'Dibayar Sebagian' : 'Belum Bayar'));

            $invoiceDate = $this->normalizeDate($rec->invoice_date);
            $createdCarbon = $invoiceDate ? Carbon::parse($invoiceDate) : null;
            $dueDate = $this->normalizeDate($rec->due_date);
            $dueCarbon = $dueDate ? Carbon::parse($dueDate) : ($createdCarbon ? $createdCarbon->copy()->addDays(30) : null);
            $isOverdue = ($dueCarbon && !$isLunas && !$isNegativeAdj) ? $dueCarbon->isPast() : false;
            $daysOverdue = ($isOverdue && $dueCarbon) ? (int) $dueCarbon->diffInDays($todayEnd) : 0;
            $daysRemaining = (!$isOverdue && $dueCarbon && !$isLunas && !$isNegativeAdj) ? (int) $todayEnd->diffInDays($dueCarbon) : 0;

            $tanggalLunas = '';
            if ($isLunas) {
                $maxPay = $latestPayByOpening[$id] ?? null;
                $tanggalLunas = $maxPay
                    ? Carbon::parse($maxPay)->format('d/m/Y')
                    : ($rec->updated_at ? Carbon::parse($rec->updated_at)->format('d/m/Y') : '');
            }

            $rows[] = [
                'id' => 'OB-' . $id,
                'opening_id' => $id,
                'is_opening' => true,
                'is_negative_adjustment' => $isNegativeAdj,
                'nomor' => $rec->invoice_number,
                'debtor' => $rec->debtor_name ?? 'Debitur Umum',
                'debtor_id' => $rec->debtor_id ? (int) $rec->debtor_id : null,
                'debtor_code' => $rec->debtor_code ?? 'SALDO AWAL',
                'dokter' => '-',
                'pasien' => 'Tagihan Saldo Awal',
                'apotek' => $rec->pharmacy_name ?? 'Apotek Utama',
                'tanggal' => $createdCarbon ? $createdCarbon->format('d/m/Y') : '-',
                'raw_created_at' => $createdCarbon ? $createdCarbon->format('Y-m-d 00:00:00') : null,
                'jatuhTempo' => $dueCarbon ? $dueCarbon->format('d/m/Y') : '-',
                'raw_jatuh_tempo' => $dueCarbon ? $dueCarbon->format('Y-m-d') : null,
                'is_overdue' => $isOverdue,
                'days_overdue' => $daysOverdue,
                'days_remaining' => $daysRemaining,
                'status' => $statusLabel,
                'tanggalLunas' => $tanggalLunas,
                'subtotal' => $total,
                'total' => $total,
                'total_retur' => 0.0,
                'returs_count' => 0,
                'net_total' => $total,
                'terbayar' => $paid,
                'sisa' => $sisa,
                'items' => [],
                'payments' => [],
                'returs' => [],
                'is_submitted' => (bool) ($rec->is_submitted ?? false),
                'notes' => $rec->notes ?: '',
                'lastModified' => $rec->updated_at ? Carbon::parse($rec->updated_at)->format('d M Y H:i') : '-',
            ];
        }

        return $rows;
    }

    /**
     * Hydrate nested items, payments, and purchase return history only for requested Hutang rows.
     */
    protected function hydrateHutangDetails(array $rows): array
    {
        $openingIds = [];
        $detailIds = [];
        foreach ($rows as $r) {
            if (!empty($r['is_opening']) && !empty($r['opening_id'])) {
                $openingIds[] = (int) $r['opening_id'];
            } else {
                $detailIds[] = (int) $r['id'];
            }
        }
        $openingIds = array_values(array_unique($openingIds));
        $detailIds = array_values(array_unique(array_filter($detailIds)));

        $paymentsByOpening = [];
        if (!empty($openingIds) && Schema::hasColumn('finance_payments', 'opening_invoice_id')) {
            $hasAccountsTable = Schema::hasTable('finance_accounts');
            $hasUsersTable = Schema::hasTable('users');
            $payQuery = DB::table('finance_payments as fp')->whereIn('fp.opening_invoice_id', $openingIds);
            if ($hasAccountsTable) {
                $payQuery->leftJoin('finance_accounts as fa', 'fa.id', '=', 'fp.account_id');
            }
            if ($hasUsersTable) {
                $payQuery->leftJoin('users as u', 'u.id', '=', 'fp.created_by');
            }
            $payRows = $payQuery->select([
                'fp.id',
                'fp.opening_invoice_id',
                'fp.payment_date',
                'fp.amount',
                'fp.payment_type',
                'fp.reference_number',
                'fp.notes',
                'fp.account_id',
                'fp.created_at',
                $hasAccountsTable ? 'fa.name as account_name' : DB::raw('NULL as account_name'),
                $hasAccountsTable ? 'fa.code as account_code' : DB::raw("'' as account_code"),
                $hasAccountsTable ? 'fa.category as account_category' : DB::raw("'Kas & Bank' as account_category"),
                $hasUsersTable ? 'u.name as creator_name' : DB::raw('NULL as creator_name'),
                $hasUsersTable ? 'u.username as creator_username' : DB::raw('NULL as creator_username'),
            ])->orderBy('fp.id')->get();

            foreach ($payRows as $p) {
                $isCutoff = $p->payment_type === 'KREDIT CUTOFF';
                $paymentsByOpening[(int) $p->opening_invoice_id][] = [
                    'id' => $p->id,
                    'tanggal' => Carbon::parse($p->payment_date)->format('d/m/Y'),
                    'nominal' => (float) $p->amount,
                    'tipe' => $p->payment_type,
                    'noReferensi' => $p->reference_number ?: '-',
                    'catatan' => $p->notes ?: '-',
                    'akunId' => $isCutoff ? null : $p->account_id,
                    'akunNama' => $isCutoff ? 'Pelunasan Pra-Cut-off (Non-Kas)' : ($p->account_name ?? 'Kas & Bank'),
                    'akunKode' => $isCutoff ? 'CUTOFF' : ($p->account_code ?? ''),
                    'akunKategori' => $isCutoff ? 'Cut-off' : ($p->account_category ?? 'Kas & Bank'),
                    'diprosesOleh' => $p->creator_name ?? ($p->creator_username ?? 'Staf Finance'),
                    'waktuAudit' => Carbon::parse($p->created_at)->format('d M Y, H:i'),
                ];
            }
        }

        if (empty($detailIds)) {
            foreach ($rows as &$row) {
                if (!empty($row['is_opening'])) {
                    $oId = (int) $row['opening_id'];
                    $row['items'] = [[
                        'sku' => 'SALDO-AWAL',
                        'nama' => 'Tagihan Saldo Awal Hutang (Pra-Sistem / Tanpa Mutasi Stok)' . (!empty($row['notes']) ? ' — ' . $row['notes'] : ''),
                        'qty' => 1,
                        'satuan' => 'Faktur',
                        'diskon' => '0%',
                        'extraDiskon' => '0%',
                        'harga' => (float) $row['total'],
                        'pajak' => 'TANPA',
                        'jumlah' => (float) $row['total'],
                    ]];
                    $row['payments'] = $paymentsByOpening[$oId] ?? [];
                    $row['returs'] = [];
                }
                unset($row['_ppn_type'], $row['_invoice_number_raw']);
            }
            unset($row);
            return $rows;
        }

        $itemsByDetail = [];
        $paymentsByDetail = [];
        $retursByDetail = [];

        $hasOrderAndMedicineJoin = Schema::hasTable('order_items')
            && Schema::hasTable('medicines')
            && Schema::hasColumn('receiving_items', 'order_items_id');

        foreach (array_chunk($detailIds, self::CHUNK_SIZE) as $chunkIds) {
            $itemQuery = DB::table('receiving_items as ri')
                ->whereIn('ri.receiving_details_id', $chunkIds)
                ->whereNotNull('ri.batches_id');

            if ($hasOrderAndMedicineJoin) {
                $itemRows = $itemQuery
                    ->leftJoin('order_items as oi', 'oi.id', '=', 'ri.order_items_id')
                    ->leftJoin('medicines as m', 'm.id', '=', 'oi.medicine_id')
                    ->select([
                        'ri.id',
                        'ri.receiving_details_id',
                        'ri.qty_received',
                        'ri.qty',
                        'ri.raw_price',
                        'ri.discount',
                        'ri.extra_discount',
                        'oi.price as order_price',
                        'm.name as medicine_name',
                        'm.code as medicine_code',
                        'm.unit as medicine_unit',
                    ])
                    ->get();
            } else {
                $itemRows = $itemQuery
                    ->select([
                        'ri.id',
                        'ri.receiving_details_id',
                        'ri.qty_received',
                        'ri.qty',
                        'ri.raw_price',
                        'ri.discount',
                        'ri.extra_discount',
                        DB::raw('0 as order_price'),
                        DB::raw("'Obat' as medicine_name"),
                        DB::raw("NULL as medicine_code"),
                        DB::raw("'Pcs' as medicine_unit"),
                    ])
                    ->get();
            }

            foreach ($itemRows as $item) {
                $dId = (int) $item->receiving_details_id;
                $itemsByDetail[$dId][] = $item;
            }
        }

        $hasAccountsTable = Schema::hasTable('finance_accounts');
        $hasUsersTable = Schema::hasTable('users');

        foreach (array_chunk($detailIds, self::CHUNK_SIZE) as $chunkIds) {
            $payQuery = DB::table('finance_payments as fp')
                ->whereIn('fp.receiving_detail_id', $chunkIds);

            if ($hasAccountsTable) {
                $payQuery->leftJoin('finance_accounts as fa', 'fa.id', '=', 'fp.account_id');
            }
            if ($hasUsersTable) {
                $payQuery->leftJoin('users as u', 'u.id', '=', 'fp.created_by');
            }

            $payRows = $payQuery->select([
                    'fp.id',
                    'fp.receiving_detail_id',
                    'fp.payment_date',
                    'fp.amount',
                    'fp.payment_type',
                    'fp.reference_number',
                    'fp.notes',
                    'fp.account_id',
                    'fp.created_at',
                    $hasAccountsTable ? 'fa.name as account_name' : DB::raw('NULL as account_name'),
                    $hasAccountsTable ? 'fa.code as account_code' : DB::raw("'' as account_code"),
                    $hasAccountsTable ? 'fa.category as account_category' : DB::raw("'Kas & Bank' as account_category"),
                    $hasUsersTable ? 'u.name as creator_name' : DB::raw('NULL as creator_name'),
                    $hasUsersTable ? 'u.username as creator_username' : DB::raw('NULL as creator_username'),
                ])
                ->orderBy('fp.id')
                ->get();

            foreach ($payRows as $p) {
                $isCutoff = $p->payment_type === 'KREDIT CUTOFF';
                $paymentsByDetail[(int) $p->receiving_detail_id][] = [
                    'id' => $p->id,
                    'tanggal' => Carbon::parse($p->payment_date)->format('d/m/Y'),
                    'nominal' => (float) $p->amount,
                    'tipe' => $p->payment_type,
                    'noReferensi' => $p->reference_number ?: '-',
                    'catatan' => $p->notes ?: '-',
                    'akunId' => $isCutoff ? null : $p->account_id,
                    'akunNama' => $isCutoff ? 'Pelunasan Pra-Cut-off (Non-Kas)' : ($p->account_name ?? 'Kas & Bank'),
                    'akunKode' => $isCutoff ? 'CUTOFF' : ($p->account_code ?? ''),
                    'akunKategori' => $isCutoff ? 'Cut-off' : ($p->account_category ?? 'Kas & Bank'),
                    'diprosesOleh' => $p->creator_name ?? ($p->creator_username ?? 'Staf Finance'),
                    'waktuAudit' => Carbon::parse($p->created_at)->format('d M Y, H:i'),
                ];
            }
        }

        if (Schema::hasTable('purchase_returns') && Schema::hasTable('purchase_return_items')) {
            $invoiceToDetailIds = [];
            $invoices = [];
            foreach ($rows as $row) {
                $inv = trim((string) ($row['_invoice_number_raw'] ?? ''));
                if ($inv !== '') {
                    $invoices[] = $inv;
                    $invoiceToDetailIds[strtoupper($inv)][] = (int) $row['id'];
                }
            }
            $invoices = array_values(array_unique($invoices));

            $retRows = DB::table('purchase_return_items as pri')
                ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                ->where(function ($q) use ($detailIds, $invoices) {
                    $q->whereIn('pr.receiving_id', $detailIds);
                    if (!empty($invoices)) {
                        $q->orWhereIn('pr.invoice_number', $invoices);
                    }
                })
                ->where('pr.status', '!=', 'dibatalkan')
                ->select([
                    'pri.id',
                    'pri.purchase_return_id',
                    'pri.medicine_name',
                    'pri.batch_number',
                    'pri.expired_date',
                    'pri.qty',
                    'pri.unit',
                    'pri.unit_price',
                    'pri.subtotal',
                    'pri.status',
                    'pri.status_date',
                    'pri.deduction_date',
                    'pri.reason',
                    'pr.return_no',
                    'pr.receiving_id as pr_receiving_id',
                    'pr.invoice_number as pr_invoice_number',
                    'pr.created_at as pr_created_at',
                ])
                ->get();

            $seenRet = [];
            foreach ($retRows as $retItem) {
                $matched = [];
                if (!empty($retItem->pr_receiving_id) && in_array((int) $retItem->pr_receiving_id, $detailIds, true)) {
                    $matched[] = (int) $retItem->pr_receiving_id;
                }
                $invNorm = strtoupper(trim((string) ($retItem->pr_invoice_number ?? '')));
                if ($invNorm !== '' && isset($invoiceToDetailIds[$invNorm])) {
                    foreach ($invoiceToDetailIds[$invNorm] as $mId) {
                        $matched[] = $mId;
                    }
                }

                $tglRetur = $retItem->deduction_date ?: ($retItem->status_date ?: ($retItem->pr_created_at ? substr((string) $retItem->pr_created_at, 0, 10) : null));
                $formatted = [
                    'id' => $retItem->id,
                    'purchase_return_id' => $retItem->purchase_return_id,
                    'code' => $retItem->return_no ?: ('RET-' . $retItem->purchase_return_id),
                    'tanggal' => $tglRetur ? Carbon::parse($tglRetur)->format('d/m/Y') : '-',
                    'raw_date' => $tglRetur,
                    'nama' => $retItem->medicine_name ?? 'Obat',
                    'batch_number' => $retItem->batch_number ?? '-',
                    'expired_date' => $retItem->expired_date ? Carbon::parse($retItem->expired_date)->format('d/m/Y') : '-',
                    'qty' => (float) $retItem->qty,
                    'satuan' => $retItem->unit ?? 'Pcs',
                    'harga' => (float) $retItem->unit_price,
                    'subtotal' => (float) $retItem->subtotal,
                    'nominal' => (float) $retItem->subtotal,
                    'status' => $retItem->status,
                    'status_label' => match ($retItem->status) {
                        'sudah_potong' => 'Sudah Potong Tagihan',
                        'sudah_diambil' => 'Sudah Diambil PBF',
                        'dikonfirmasi' => 'Dikonfirmasi',
                        'barang_kembali_pmi' => 'Barang Kembali PMI',
                        default => 'Diajukan',
                    },
                    'reason' => $retItem->reason ?: '-',
                ];

                foreach (array_unique($matched) as $dId) {
                    if (isset($seenRet[$dId][$retItem->id])) {
                        continue;
                    }
                    $seenRet[$dId][$retItem->id] = true;
                    $retursByDetail[$dId][] = $formatted;
                }
            }
        }

        foreach ($rows as &$row) {
            if (!empty($row['is_opening'])) {
                $oId = (int) $row['opening_id'];
                $row['items'] = [[
                    'sku' => 'SALDO-AWAL',
                    'nama' => 'Tagihan Saldo Awal Hutang (Pra-Sistem / Tanpa Mutasi Stok)' . (!empty($row['notes']) ? ' — ' . $row['notes'] : ''),
                    'qty' => 1,
                    'satuan' => 'Faktur',
                    'diskon' => '0%',
                    'extraDiskon' => '0%',
                    'harga' => (float) $row['total'],
                    'pajak' => 'TANPA',
                    'jumlah' => (float) $row['total'],
                ]];
                $row['payments'] = $paymentsByOpening[$oId] ?? [];
                $row['returs'] = [];
                unset($row['_ppn_type'], $row['_invoice_number_raw']);
                continue;
            }
            $dId = (int) $row['id'];
            $ppnType = $row['_ppn_type'] ?? 'TANPA';
            $formattedItems = [];

            foreach ($itemsByDetail[$dId] ?? [] as $item) {
                $qty = (float) ($item->qty_received ?? 0);
                $harga = (float) ($item->raw_price ?? ($item->order_price ?? 0));
                $diskon = (float) ($item->discount ?? 0);
                $extraDiskon = (float) ($item->extra_discount ?? 0);
                $lineObj = (object) [
                    'qty_received' => $qty,
                    'qty' => $item->qty,
                    'raw_price' => $harga,
                    'discount' => $diskon,
                    'extra_discount' => $extraDiskon,
                ];
                $lineAmounts = ReceivingInvoiceTotals::lineAmounts($lineObj, $ppnType);

                $formattedItems[] = [
                    'sku' => $item->medicine_code ?? ('SKU-' . $item->id),
                    'nama' => $item->medicine_name ?? 'Obat',
                    'qty' => $qty,
                    'satuan' => $item->medicine_unit ?? 'Pcs',
                    'diskon' => $this->formatDiscount($diskon),
                    'extraDiskon' => $this->formatDiscount($extraDiskon),
                    'harga' => $harga,
                    'pajak' => $ppnType,
                    'jumlah' => $lineAmounts['total'],
                ];
            }

            $row['items'] = $formattedItems;
            $row['payments'] = $paymentsByDetail[$dId] ?? [];
            $row['returs'] = $retursByDetail[$dId] ?? [];
            unset($row['_ppn_type'], $row['_invoice_number_raw']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Assemble lean Piutang rows in chunks without hydrating full cart/payments/retur payloads.
     */
    protected function assemblePiutangRows($headers): array
    {
        if ($headers->isEmpty()) {
            return [];
        }

        $txIds = [];
        $fallbackSubtotalIds = [];
        $debtorIds = [];
        $patientIds = [];
        $doctorIds = [];
        $pharmacyIds = [];

        foreach ($headers as $tx) {
            $txId = (int) $tx->id;
            $txIds[] = $txId;
            if ((float) ($tx->subtotal ?? 0) <= 0) {
                $fallbackSubtotalIds[] = $txId;
            }
            if (!empty($tx->debtor_id)) {
                $debtorIds[] = (int) $tx->debtor_id;
            }
            if (!empty($tx->patient_id)) {
                $patientIds[] = (int) $tx->patient_id;
            }
            if (!empty($tx->doctor_id)) {
                $doctorIds[] = (int) $tx->doctor_id;
            }
            if (!empty($tx->pharmacy_id)) {
                $pharmacyIds[] = (int) $tx->pharmacy_id;
            }
        }

        $debtorsMap = !empty($debtorIds)
            ? DB::table('debtors')->whereIn('id', array_unique($debtorIds))->get(['id', 'code', 'name'])->keyBy('id')
            : collect();
        $patientsMap = !empty($patientIds)
            ? DB::table('patients')->whereIn('id', array_unique($patientIds))->get(['id', 'name'])->keyBy('id')
            : collect();
        $doctorsMap = !empty($doctorIds)
            ? DB::table('doctors')->whereIn('id', array_unique($doctorIds))->get(['id', 'name'])->keyBy('id')
            : collect();
        $pharmaciesMap = !empty($pharmacyIds)
            ? DB::table('pharmacies')->whereIn('id', array_unique($pharmacyIds))->get(['id', 'name'])->keyBy('id')
            : collect();

        $calcSubtotalByTx = [];
        foreach (array_chunk($fallbackSubtotalIds, self::CHUNK_SIZE) as $chunkIds) {
            $cartRows = DB::table('medicine_cart')
                ->whereIn('transaction_id', $chunkIds)
                ->select(['transaction_id', 'quantity', 'item_price', 'total_price', 'final_price'])
                ->get();

            foreach ($cartRows as $c) {
                $tId = (int) $c->transaction_id;
                $qty = (float) ($c->quantity ?? 0);
                $harga = (float) ($c->item_price ?? 0);
                $final = (float) ($c->final_price ?: ($c->total_price ?: ($qty * $harga)));
                $calcSubtotalByTx[$tId] = ($calcSubtotalByTx[$tId] ?? 0.0) + $final;
            }
        }

        $paidByTx = [];
        $latestPaymentDateByTx = [];
        $returSumByTx = [];
        $returCountByTx = [];

        foreach (array_chunk($txIds, self::CHUNK_SIZE) as $chunkIds) {
            $paymentRows = DB::table('finance_payments')
                ->whereIn('medicine_transaction_id', $chunkIds)
                ->select([
                    'medicine_transaction_id',
                    DB::raw('SUM(amount) as total_amount'),
                    DB::raw('MAX(payment_date) as max_payment_date'),
                ])
                ->groupBy('medicine_transaction_id')
                ->get();

            foreach ($paymentRows as $pr) {
                $tId = (int) $pr->medicine_transaction_id;
                $paidByTx[$tId] = (float) $pr->total_amount;
                $latestPaymentDateByTx[$tId] = $pr->max_payment_date;
            }

            $retRows = DB::table('retur')
                ->whereIn('transaction_id', $chunkIds)
                ->select([
                    'transaction_id',
                    DB::raw('SUM(total_retur) as sum_retur'),
                    DB::raw('COUNT(*) as cnt_retur'),
                ])
                ->groupBy('transaction_id')
                ->get();

            foreach ($retRows as $rr) {
                $tId = (int) $rr->transaction_id;
                $returSumByTx[$tId] = (float) $rr->sum_retur;
                $returCountByTx[$tId] = (int) $rr->cnt_retur;
            }
        }

        $todayEnd = now();
        $rows = [];

        foreach ($headers as $tx) {
            $txId = (int) $tx->id;
            $debtor = $tx->debtor_id ? $debtorsMap->get((int) $tx->debtor_id) : null;
            $patient = $tx->patient_id ? $patientsMap->get((int) $tx->patient_id) : null;
            $doctor = $tx->doctor_id ? $doctorsMap->get((int) $tx->doctor_id) : null;
            $pharmacy = $tx->pharmacy_id ? $pharmaciesMap->get((int) $tx->pharmacy_id) : null;

            $debtorName = $debtor?->name ?? ($tx->patient_id ? ('Pasien: ' . ($patient?->name ?? 'Umum')) : 'Pelanggan Umum');

            $totalRetur = round((float) ($returSumByTx[$txId] ?? 0.0), 2);
            $retursCount = (int) ($returCountByTx[$txId] ?? 0);

            $rawSub = (float) ($tx->subtotal ?? 0);
            $total = round($rawSub > 0 ? $rawSub : (float) ($calcSubtotalByTx[$txId] ?? 0.0), 2);
            $netTotal = round(max(0, $total - $totalRetur), 2);

            $totalTerbayarViaFinance = (float) ($paidByTx[$txId] ?? 0.0);
            $legacyPaid = (float) ($tx->paid ?? 0);
            $totalTerbayar = round(min($netTotal, max($totalTerbayarViaFinance, $legacyPaid)), 2);
            $sisa = round(max(0, $netTotal - $totalTerbayar), 2);

            $isLunas = ($sisa < 0.005);
            $sisa = $isLunas ? 0 : $sisa;

            if ($isLunas) {
                if ($totalRetur >= $total && $total > 0) {
                    $statusLabel = 'Diretur Penuh';
                } elseif ($totalRetur > 0) {
                    $statusLabel = 'Lunas (Ada Retur)';
                } else {
                    $statusLabel = 'Lunas';
                }
            } else {
                if ($totalTerbayar > 0) {
                    $statusLabel = 'Dibayar Sebagian';
                } elseif ($totalRetur > 0) {
                    $statusLabel = 'Diretur Sebagian';
                } else {
                    $statusLabel = 'Belum Bayar';
                }
            }

            $createdCarbon = $tx->created_at ? Carbon::parse($tx->created_at) : null;
            $dueCarbon = $createdCarbon ? $createdCarbon->copy()->addDays(30) : null;
            $isOverdue = ($dueCarbon && !$isLunas) ? $dueCarbon->isPast() : false;
            $daysOverdue = ($isOverdue && $dueCarbon) ? (int) $dueCarbon->diffInDays($todayEnd) : 0;
            $daysRemaining = (!$isOverdue && $dueCarbon && !$isLunas) ? (int) $todayEnd->diffInDays($dueCarbon) : 0;

            $tanggalLunas = '';
            if ($isLunas) {
                $maxPay = $latestPaymentDateByTx[$txId] ?? null;
                $tanggalLunas = $maxPay
                    ? Carbon::parse($maxPay)->format('d/m/Y')
                    : ($tx->updated_at ? Carbon::parse($tx->updated_at)->format('d/m/Y') : '');
            }

            $rows[] = [
                'id' => $txId,
                'nomor' => $tx->transaction_code ?: ('TRX-' . $txId),
                'debtor' => $debtorName,
                'debtor_id' => $tx->debtor_id ? (int) $tx->debtor_id : null,
                'debtor_code' => $debtor?->code ?? '-',
                'dokter' => $doctor?->name ?? '-',
                'pasien' => $patient?->name ?? '-',
                'apotek' => $pharmacy?->name ?? 'Apotek Utama',
                'tanggal' => $createdCarbon ? $createdCarbon->format('d/m/Y') : '-',
                'raw_created_at' => $createdCarbon ? $createdCarbon->format('Y-m-d H:i:s') : null,
                'jatuhTempo' => $dueCarbon ? $dueCarbon->format('d/m/Y') : '-',
                'raw_jatuh_tempo' => $dueCarbon ? $dueCarbon->format('Y-m-d') : null,
                'is_overdue' => $isOverdue,
                'days_overdue' => $daysOverdue,
                'days_remaining' => $daysRemaining,
                'status' => $statusLabel,
                'tanggalLunas' => $tanggalLunas,
                'subtotal' => $total,
                'total' => $total,
                'total_retur' => $totalRetur,
                'returs_count' => $retursCount,
                'net_total' => $netTotal,
                'terbayar' => $totalTerbayar,
                'sisa' => $sisa,
                'items' => [],
                'payments' => [],
                'returs' => [],
                'is_submitted' => (bool) ($tx->is_submitted ?? false),
                'lastModified' => $tx->updated_at ? Carbon::parse($tx->updated_at)->format('d M Y H:i') : '-',
            ];
        }

        return $rows;
    }

    /**
     * Hydrate full cart items, finance payments, and returs only for requested Piutang rows.
     */
    protected function hydratePiutangDetails(array $rows): array
    {
        $openingIds = [];
        $txIds = [];
        foreach ($rows as $r) {
            if (!empty($r['is_opening']) && !empty($r['opening_id'])) {
                $openingIds[] = (int) $r['opening_id'];
            } else {
                $txIds[] = (int) $r['id'];
            }
        }
        $openingIds = array_values(array_unique($openingIds));
        $txIds = array_values(array_unique(array_filter($txIds)));

        $hasMedicinesTable = Schema::hasTable('medicines');
        $hasAccountsTable = Schema::hasTable('finance_accounts');
        $hasUsersTable = Schema::hasTable('users');

        $paymentsByOpening = [];
        if (!empty($openingIds) && Schema::hasColumn('finance_payments', 'opening_invoice_id')) {
            $payQuery = DB::table('finance_payments as fp')->whereIn('fp.opening_invoice_id', $openingIds);
            if ($hasAccountsTable) {
                $payQuery->leftJoin('finance_accounts as fa', 'fa.id', '=', 'fp.account_id');
            }
            if ($hasUsersTable) {
                $payQuery->leftJoin('users as u', 'u.id', '=', 'fp.created_by');
            }
            $payRows = $payQuery->select([
                'fp.id',
                'fp.opening_invoice_id',
                'fp.payment_date',
                'fp.amount',
                'fp.payment_type',
                'fp.reference_number',
                'fp.notes',
                'fp.account_id',
                'fp.created_at',
                $hasAccountsTable ? 'fa.name as account_name' : DB::raw('NULL as account_name'),
                $hasAccountsTable ? 'fa.code as account_code' : DB::raw("'-' as account_code"),
                $hasAccountsTable ? 'fa.category as account_category' : DB::raw("'Kas & Bank' as account_category"),
                $hasAccountsTable ? 'fa.account_number' : DB::raw("'-' as account_number"),
                $hasUsersTable ? 'u.name as creator_name' : DB::raw('NULL as creator_name'),
            ])->orderBy('fp.id')->get();

            foreach ($payRows as $p) {
                $isCutoff = $p->payment_type === 'PIUTANG CUTOFF';
                $paymentsByOpening[(int) $p->opening_invoice_id][] = [
                    'id' => $p->id,
                    'tanggal' => Carbon::parse($p->payment_date)->format('d/m/Y'),
                    'nominal' => (float) $p->amount,
                    'tipe' => $p->payment_type,
                    'noReferensi' => $p->reference_number ?: '-',
                    'catatan' => $p->notes ?: '-',
                    'akunNama' => $isCutoff ? 'Pelunasan Pra-Cut-off (Non-Kas)' : ($p->account_name ?? 'Kas & Bank'),
                    'akunKode' => $isCutoff ? 'CUTOFF' : ($p->account_code ?? '-'),
                    'akunKategori' => $isCutoff ? 'Cut-off' : ($p->account_category ?? 'Kas & Bank'),
                    'noRekening' => $isCutoff ? '-' : ($p->account_number ?? '-'),
                    'diprosesOleh' => $p->creator_name ?? 'Staf Finance',
                    'waktuAudit' => Carbon::parse($p->created_at)->format('d M Y H:i'),
                ];
            }
        }

        if (empty($txIds)) {
            foreach ($rows as &$row) {
                if (!empty($row['is_opening'])) {
                    $oId = (int) $row['opening_id'];
                    $row['items'] = [[
                        'id' => 'opening-' . $oId,
                        'sku' => 'SALDO-AWAL',
                        'nama' => 'Tagihan Saldo Awal Piutang (Pra-Sistem / Tanpa Mutasi Stok & Omzet)' . (!empty($row['notes']) ? ' — ' . $row['notes'] : ''),
                        'qty' => 1,
                        'satuan' => 'Tagihan',
                        'diskon' => '0%',
                        'harga' => (float) $row['total'],
                        'pajak' => 'NON',
                        'jumlah' => (float) $row['total'],
                    ]];
                    $row['payments'] = $paymentsByOpening[$oId] ?? [];
                    $row['returs'] = [];
                }
            }
            unset($row);
            return $rows;
        }

        $itemsByTx = [];
        $paymentsByTx = [];
        $retursByTx = [];

        foreach (array_chunk($txIds, self::CHUNK_SIZE) as $chunkIds) {
            $cartQuery = DB::table('medicine_cart as mc')
                ->whereIn('mc.transaction_id', $chunkIds);
            if ($hasMedicinesTable) {
                $cartQuery->leftJoin('medicines as m', 'm.id', '=', 'mc.medicine_id');
            }
            $cartRows = $cartQuery->select([
                    'mc.id',
                    'mc.transaction_id',
                    'mc.medicine_id',
                    'mc.quantity',
                    'mc.item_price',
                    'mc.discount',
                    'mc.package',
                    'mc.total_price',
                    'mc.final_price',
                    $hasMedicinesTable ? 'm.name as medicine_name' : DB::raw('NULL as medicine_name'),
                    $hasMedicinesTable ? 'm.code as medicine_code' : DB::raw('NULL as medicine_code'),
                    $hasMedicinesTable ? 'm.unit as medicine_unit' : DB::raw('NULL as medicine_unit'),
                ])
                ->orderBy('mc.id')
                ->get();

            foreach ($cartRows as $cart) {
                $qty = (float) ($cart->quantity ?? 0);
                $harga = (float) ($cart->item_price ?? 0);
                $diskon = (float) ($cart->discount ?? 0);
                $finalPrice = (float) ($cart->final_price ?: ($cart->total_price ?: ($qty * $harga)));

                $itemsByTx[(int) $cart->transaction_id][] = [
                    'id' => $cart->id,
                    'sku' => $cart->medicine_code ?? ('SKU-' . $cart->medicine_id),
                    'nama' => $cart->medicine_name ?? 'Obat',
                    'qty' => $qty,
                    'satuan' => $cart->medicine_unit ?? ($cart->package ?? 'Pcs'),
                    'diskon' => $diskon . '%',
                    'harga' => $harga,
                    'pajak' => 'NON',
                    'jumlah' => $finalPrice,
                ];
            }

            $payQuery = DB::table('finance_payments as fp')
                ->whereIn('fp.medicine_transaction_id', $chunkIds);
            if ($hasAccountsTable) {
                $payQuery->leftJoin('finance_accounts as fa', 'fa.id', '=', 'fp.account_id');
            }
            if ($hasUsersTable) {
                $payQuery->leftJoin('users as u', 'u.id', '=', 'fp.created_by');
            }
            $payRows = $payQuery->select([
                    'fp.id',
                    'fp.medicine_transaction_id',
                    'fp.payment_date',
                    'fp.amount',
                    'fp.payment_type',
                    'fp.reference_number',
                    'fp.notes',
                    'fp.account_id',
                    'fp.created_at',
                    $hasAccountsTable ? 'fa.name as account_name' : DB::raw('NULL as account_name'),
                    $hasAccountsTable ? 'fa.code as account_code' : DB::raw("'-' as account_code"),
                    $hasAccountsTable ? 'fa.category as account_category' : DB::raw("'Kas & Bank' as account_category"),
                    $hasAccountsTable ? 'fa.account_number' : DB::raw("'-' as account_number"),
                    $hasUsersTable ? 'u.name as creator_name' : DB::raw('NULL as creator_name'),
                ])
                ->orderBy('fp.id')
                ->get();

            foreach ($payRows as $p) {
                $isCutoff = $p->payment_type === 'PIUTANG CUTOFF';
                $paymentsByTx[(int) $p->medicine_transaction_id][] = [
                    'id' => $p->id,
                    'tanggal' => Carbon::parse($p->payment_date)->format('d/m/Y'),
                    'nominal' => (float) $p->amount,
                    'tipe' => $p->payment_type,
                    'noReferensi' => $p->reference_number ?: '-',
                    'catatan' => $p->notes ?: '-',
                    'akunNama' => $isCutoff ? 'Pelunasan Pra-Cut-off (Non-Kas)' : ($p->account_name ?? 'Kas & Bank'),
                    'akunKode' => $isCutoff ? 'CUTOFF' : ($p->account_code ?? '-'),
                    'akunKategori' => $isCutoff ? 'Cut-off' : ($p->account_category ?? 'Kas & Bank'),
                    'noRekening' => $isCutoff ? '-' : ($p->account_number ?? '-'),
                    'diprosesOleh' => $p->creator_name ?? 'Staf Finance',
                    'waktuAudit' => Carbon::parse($p->created_at)->format('d M Y H:i'),
                ];
            }

            $retQuery = DB::table('retur as r')
                ->whereIn('r.transaction_id', $chunkIds);
            if ($hasMedicinesTable) {
                $retQuery->leftJoin('medicines as m', 'm.id', '=', 'r.medicine_id');
            }
            $retRows = $retQuery->select([
                    'r.id',
                    'r.code',
                    'r.transaction_id',
                    'r.medicine_id',
                    'r.qty_retur',
                    'r.total_retur',
                    'r.created_at',
                    $hasMedicinesTable ? 'm.name as medicine_name' : DB::raw('NULL as medicine_name'),
                    $hasMedicinesTable ? 'm.code as medicine_code' : DB::raw('NULL as medicine_code'),
                    $hasMedicinesTable ? 'm.unit as medicine_unit' : DB::raw('NULL as medicine_unit'),
                ])
                ->orderBy('r.id')
                ->get();

            foreach ($retRows as $ret) {
                $retCreated = $ret->created_at ? Carbon::parse($ret->created_at) : null;
                $retursByTx[(int) $ret->transaction_id][] = [
                    'id' => $ret->id,
                    'code' => $ret->code ?: ('RT-' . $ret->id),
                    'tanggal' => $retCreated ? $retCreated->format('d/m/Y') : '-',
                    'raw_created_at' => $retCreated ? $retCreated->format('Y-m-d H:i:s') : null,
                    'medicine_id' => $ret->medicine_id,
                    'nama' => $ret->medicine_name ?? 'Obat',
                    'sku' => $ret->medicine_code ?? ('SKU-' . $ret->medicine_id),
                    'satuan' => $ret->medicine_unit ?? 'Pcs',
                    'qty' => (float) ($ret->qty_retur ?? 0),
                    'nominal' => (float) ($ret->total_retur ?? 0),
                ];
            }
        }

        foreach ($rows as &$row) {
            if (!empty($row['is_opening'])) {
                $oId = (int) $row['opening_id'];
                $row['items'] = [[
                    'id' => 'opening-' . $oId,
                    'sku' => 'SALDO-AWAL',
                    'nama' => 'Tagihan Saldo Awal Piutang (Pra-Sistem / Tanpa Mutasi Stok & Omzet)' . (!empty($row['notes']) ? ' — ' . $row['notes'] : ''),
                    'qty' => 1,
                    'satuan' => 'Tagihan',
                    'diskon' => '0%',
                    'harga' => (float) $row['total'],
                    'pajak' => 'NON',
                    'jumlah' => (float) $row['total'],
                ]];
                $row['payments'] = $paymentsByOpening[$oId] ?? [];
                $row['returs'] = [];
                continue;
            }
            $tId = (int) $row['id'];
            $row['items'] = $itemsByTx[$tId] ?? [];
            $row['payments'] = $paymentsByTx[$tId] ?? [];
            $row['returs'] = $retursByTx[$tId] ?? [];
        }
        unset($row);

        return $rows;
    }

    protected function filterAndSortHutangRows(array $rows, array $filters): array
    {
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $pbf = trim((string) ($filters['pbf'] ?? ''));
        $status = strtoupper(trim((string) ($filters['status'] ?? 'ALL')));
        $dueMode = trim((string) ($filters['due_mode'] ?? 'all')) ?: 'all';
        $dateField = trim((string) ($filters['date_field'] ?? 'due')) ?: 'due';
        $startDate = trim((string) ($filters['start_date'] ?? ''));
        $endDate = trim((string) ($filters['end_date'] ?? ''));
        $sortOrder = strtolower(trim((string) ($filters['sort_order'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';

        $filtered = array_values(array_filter($rows, function (array $item) use ($search, $pbf, $status, $dueMode, $dateField, $startDate, $endDate) {
            if ($search !== '') {
                $haystack = strtolower(
                    ($item['nomor'] ?? '') . ' ' .
                    ($item['vendor'] ?? '') . ' ' .
                    ($item['vendor_code'] ?? '') . ' ' .
                    ($item['referensi'] ?? '') . ' ' .
                    ($item['gudang'] ?? '')
                );
                if (!str_contains($haystack, $search)) {
                    return false;
                }
            }

            if ($pbf !== '' && strcasecmp((string) ($item['vendor_code'] ?? ''), $pbf) !== 0 && !str_contains(strtolower((string) ($item['vendor'] ?? '')), strtolower($pbf))) {
                return false;
            }

            $isSettled = $this->isHutangSettled($item);
            if ($status === 'BELUM_LUNAS' && $isSettled) {
                return false;
            }
            if ($status === 'DIAJUKAN' && (!empty($item['is_submitted']) === false || $isSettled)) {
                return false;
            }
            if ($status === 'RETUR') {
                $hasRetur = ($item['total_retur'] ?? 0) > 0
                    || ($item['returs_count'] ?? 0) > 0
                    || str_contains(strtolower((string) ($item['status'] ?? '')), 'retur');
                if (!$hasRetur) {
                    return false;
                }
            }
            if ($status === 'LUNAS' && !$isSettled) {
                return false;
            }

            if ($dueMode === 'overdue' && ($isSettled || empty($item['is_overdue']))) {
                return false;
            }
            if ($dueMode === 'due_soon' && ($isSettled || !empty($item['is_overdue']) || empty($item['raw_jatuh_tempo']) || ($item['days_remaining'] ?? 999) > 7)) {
                return false;
            }
            if (($dueMode === 'due_nearest' || $dueMode === 'due_furthest') && ($isSettled || empty($item['raw_jatuh_tempo']))) {
                return false;
            }

            if ($startDate !== '' || $endDate !== '') {
                $targetDate = match ($dateField) {
                    'invoice' => $item['raw_tanggal'] ?? null,
                    'received' => $item['raw_tanggal_terima'] ?? null,
                    default => $item['raw_jatuh_tempo'] ?? null,
                };
                if (!$targetDate) {
                    return false;
                }
                if ($startDate !== '' && $targetDate < $startDate) {
                    return false;
                }
                if ($endDate !== '' && $targetDate > $endDate) {
                    return false;
                }
            }

            return true;
        }));

        usort($filtered, function (array $a, array $b) use ($dueMode, $dateField, $sortOrder) {
            if ($dueMode === 'due_nearest') {
                $da = $a['raw_jatuh_tempo'] ?: '9999-12-31';
                $db = $b['raw_jatuh_tempo'] ?: '9999-12-31';
                return $da <=> $db ?: ($b['id'] <=> $a['id']);
            }
            if ($dueMode === 'due_furthest') {
                $da = $a['raw_jatuh_tempo'] ?: '0000-01-01';
                $db = $b['raw_jatuh_tempo'] ?: '0000-01-01';
                return $db <=> $da ?: ($b['id'] <=> $a['id']);
            }

            $fieldKey = match ($dateField) {
                'invoice' => 'raw_tanggal',
                'received' => 'raw_tanggal_terima',
                default => 'raw_jatuh_tempo',
            };
            $da = $a[$fieldKey] ?? '';
            $db = $b[$fieldKey] ?? '';

            if ($da === $db) {
                return $sortOrder === 'asc' ? ($a['id'] <=> $b['id']) : ($b['id'] <=> $a['id']);
            }

            return $sortOrder === 'asc' ? strcmp($da, $db) : strcmp($db, $da);
        });

        return $filtered;
    }

    protected function filterAndSortPiutangRows(array $rows, array $filters): array
    {
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $debtorId = trim((string) ($filters['debtor_id'] ?? ''));
        $status = strtoupper(trim((string) ($filters['status'] ?? 'ALL')));
        $dueMode = trim((string) ($filters['due_mode'] ?? 'all')) ?: 'all';
        $startDate = trim((string) ($filters['start_date'] ?? ''));
        $endDate = trim((string) ($filters['end_date'] ?? ''));
        $sortOrder = strtolower(trim((string) ($filters['sort_order'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';

        $filtered = array_values(array_filter($rows, function (array $item) use ($search, $debtorId, $status, $dueMode, $startDate, $endDate) {
            if ($search !== '') {
                $haystack = strtolower(
                    ($item['nomor'] ?? '') . ' ' .
                    ($item['debtor'] ?? '') . ' ' .
                    ($item['debtor_code'] ?? '') . ' ' .
                    ($item['pasien'] ?? '') . ' ' .
                    ($item['dokter'] ?? '') . ' ' .
                    ($item['apotek'] ?? '')
                );
                if (!str_contains($haystack, $search)) {
                    return false;
                }
            }

            if ($debtorId !== '' && (string) ($item['debtor_id'] ?? '') !== $debtorId) {
                return false;
            }

            $isSettled = $this->isPiutangSettled($item);
            if ($status === 'BELUM_LUNAS' && $isSettled) {
                return false;
            }
            if ($status === 'DIAJUKAN' && (empty($item['is_submitted']) || $isSettled)) {
                return false;
            }
            if ($status === 'RETUR') {
                $hasRetur = ($item['total_retur'] ?? 0) > 0
                    || ($item['returs_count'] ?? 0) > 0
                    || str_contains(strtolower((string) ($item['status'] ?? '')), 'retur');
                if (!$hasRetur) {
                    return false;
                }
            }
            if ($status === 'LUNAS' && !$isSettled) {
                return false;
            }

            if ($dueMode === 'overdue' && ($isSettled || empty($item['is_overdue']))) {
                return false;
            }
            if ($dueMode === 'due_soon' && ($isSettled || !empty($item['is_overdue']) || empty($item['raw_jatuh_tempo']) || ($item['days_remaining'] ?? 999) > 7)) {
                return false;
            }
            if (($dueMode === 'due_nearest' || $dueMode === 'due_furthest') && ($isSettled || empty($item['raw_jatuh_tempo']))) {
                return false;
            }

            if ($startDate !== '' || $endDate !== '') {
                $txDate = !empty($item['raw_created_at']) ? substr((string) $item['raw_created_at'], 0, 10) : null;
                if (!$txDate) {
                    return false;
                }
                if ($startDate !== '' && $txDate < $startDate) {
                    return false;
                }
                if ($endDate !== '' && $txDate > $endDate) {
                    return false;
                }
            }

            return true;
        }));

        $sortBy = trim((string) ($filters['sort_by'] ?? ''));

        usort($filtered, function (array $a, array $b) use ($dueMode, $sortOrder, $sortBy) {
            if ($dueMode === 'due_nearest') {
                $da = $a['raw_jatuh_tempo'] ?: '9999-12-31';
                $db = $b['raw_jatuh_tempo'] ?: '9999-12-31';
                return $da <=> $db ?: ($b['id'] <=> $a['id']);
            }
            if ($dueMode === 'due_furthest') {
                $da = $a['raw_jatuh_tempo'] ?: '0000-01-01';
                $db = $b['raw_jatuh_tempo'] ?: '0000-01-01';
                return $db <=> $da ?: ($b['id'] <=> $a['id']);
            }

            $key = $sortBy === 'due_date' ? 'raw_jatuh_tempo' : 'raw_created_at';
            $da = $a[$key] ?? '';
            $db = $b[$key] ?? '';
            if ($da === $db) {
                return $sortOrder === 'asc' ? ($a['id'] <=> $b['id']) : ($b['id'] <=> $a['id']);
            }

            return $sortOrder === 'asc' ? strcmp($da, $db) : strcmp($db, $da);
        });

        return $filtered;
    }

    public function summarizeHutangStats(array $rows): array
    {
        $countHutangBelumLunas = 0;
        $countBelumBayar = 0;
        $countSebagian = 0;
        $countLunas = 0;
        $countDiajukan = 0;
        $totalHutangSisa = 0.0;

        foreach ($rows as $h) {
            $sisa = (float) ($h['sisa'] ?? 0);
            $status = $h['status'] ?? 'Belum Dibayar';
            $isSettled = $this->isHutangSettled($h);

            $totalHutangSisa += $sisa;
            if ($status === 'Koreksi Saldo Awal') {
                continue;
            }
            if (!empty($h['is_submitted']) && !$isSettled) {
                $countDiajukan++;
            }
            if ($isSettled) {
                $countLunas++;
            } elseif ($status === 'Dibayar Sebagian') {
                $countSebagian++;
                $countHutangBelumLunas++;
            } else {
                $countBelumBayar++;
                $countHutangBelumLunas++;
            }
        }

        $totalHutangSisa = max(0.0, $totalHutangSisa);

        return [
            'totalHutangCount' => count($rows),
            'totalHutangSisa' => round($totalHutangSisa, 2),
            'totalSisaHutang' => round($totalHutangSisa, 2),
            'countHutangBelumLunas' => $countHutangBelumLunas,
            'countBelumBayar' => $countBelumBayar,
            'countSebagian' => $countSebagian,
            'countDiajukan' => $countDiajukan,
            'countLunas' => $countLunas,
        ];
    }

    public function summarizePiutangStats(array $rows): array
    {
        $totalPiutangBruto = 0.0;
        $totalPiutangRetur = 0.0;
        $totalPiutangTerbayar = 0.0;
        $totalPiutangSisa = 0.0;
        $countPiutangBelumBayar = 0;
        $countPiutangDiajukan = 0;
        $countPiutangLunas = 0;
        $countPiutangDiretur = 0;

        foreach ($rows as $p) {
            $totalPiutangBruto += (float) ($p['total'] ?? 0);
            $totalPiutangRetur += (float) ($p['total_retur'] ?? 0);
            $totalPiutangTerbayar += (float) ($p['terbayar'] ?? 0);
            $totalPiutangSisa += (float) ($p['sisa'] ?? 0);

            $status = $p['status'] ?? 'Belum Bayar';
            $sisa = (float) ($p['sisa'] ?? 0);

            if ($status === 'Koreksi Saldo Awal') {
                continue;
            }
            if (!empty($p['is_submitted']) && !in_array($status, ['Lunas', 'Diretur Penuh'], true) && $sisa > 0.005) {
                $countPiutangDiajukan++;
            }
            if ($status === 'Diretur Penuh') {
                $countPiutangDiretur++;
            } elseif ($status === 'Lunas' || $status === 'Lunas (Ada Retur)' || $sisa <= 0.005) {
                $countPiutangLunas++;
            } else {
                $countPiutangBelumBayar++;
            }
        }

        $totalPiutangSisa = max(0.0, $totalPiutangSisa);

        return [
            'totalPiutangCount' => count($rows),
            'totalPiutangBruto' => round(max(0.0, $totalPiutangBruto), 2),
            'totalPiutangRetur' => round($totalPiutangRetur, 2),
            'totalPiutangTerbayar' => round($totalPiutangTerbayar, 2),
            'totalPiutangSisa' => round($totalPiutangSisa, 2),
            'totalSisaPiutang' => round($totalPiutangSisa, 2),
            'countPiutangBelumBayar' => $countPiutangBelumBayar,
            'countPiutangDiajukan' => $countPiutangDiajukan,
            'countPiutangLunas' => $countPiutangLunas,
            'countPiutangDiretur' => $countPiutangDiretur,
        ];
    }

    protected function computeDueStats(array $rows): array
    {
        $overdue = 0;
        $dueSoon = 0;

        foreach ($rows as $row) {
            $sisa = (float) ($row['sisa'] ?? 0);
            $status = $row['status'] ?? '';
            if (in_array($status, ['Lunas', 'Lunas (Ada Retur)', 'Diretur Penuh', 'Koreksi Saldo Awal'], true) || $sisa <= 0.005) {
                continue;
            }
            if (!empty($row['is_overdue']) || ($row['days_overdue'] ?? 0) > 0) {
                $overdue++;
            } elseif (!empty($row['raw_jatuh_tempo']) && ($row['days_remaining'] ?? 999) <= 7) {
                $dueSoon++;
            }
        }

        return [
            'overdue' => $overdue,
            'dueSoon' => $dueSoon,
        ];
    }

    protected function isHutangSettled(array $item): bool
    {
        return in_array($item['status'] ?? '', ['Lunas', 'Lunas (Ada Retur)', 'Diretur Penuh', 'Koreksi Saldo Awal'], true);
    }

    protected function isPiutangSettled(array $item): bool
    {
        return in_array($item['status'] ?? '', ['Lunas', 'Lunas (Ada Retur)', 'Diretur Penuh', 'Koreksi Saldo Awal'], true)
            || (float) ($item['sisa'] ?? 0) <= 0.005;
    }

    protected function emptyHutangStats(): array
    {
        return [
            'totalHutangCount' => 0,
            'totalHutangSisa' => 0.0,
            'totalSisaHutang' => 0.0,
            'countHutangBelumLunas' => 0,
            'countBelumBayar' => 0,
            'countSebagian' => 0,
            'countDiajukan' => 0,
            'countLunas' => 0,
        ];
    }

    protected function emptyPiutangStats(): array
    {
        return [
            'totalPiutangCount' => 0,
            'totalPiutangBruto' => 0.0,
            'totalPiutangRetur' => 0.0,
            'totalPiutangTerbayar' => 0.0,
            'totalPiutangSisa' => 0.0,
            'totalSisaPiutang' => 0.0,
            'countPiutangBelumBayar' => 0,
            'countPiutangDiajukan' => 0,
            'countPiutangLunas' => 0,
            'countPiutangDiretur' => 0,
        ];
    }

    protected function emptyPagination(int $perPage, int $page): array
    {
        return [
            'current_page' => max(1, $page),
            'per_page' => max(1, $perPage),
            'total' => 0,
            'last_page' => 1,
            'from' => 0,
            'to' => 0,
        ];
    }
}
