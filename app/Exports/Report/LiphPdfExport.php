<?php

namespace App\Exports\Report;

use App\Models\MedicineTransactions;
use App\Support\LiphTransactionScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

class LiphPdfExport
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $pharmacyName;
    protected $pharmacyAddress;

    const TYPE_MAP = [
        'KREDIT'      => ['kredit', 'Resep Kredit'],
        'RESEP KREDIT' => ['kredit', 'Resep Kredit'],
        'RETUR KREDIT' => ['kredit', 'Retur Kredit'],
        'HV/OTC'      => ['tunai',  'Obat Bebas'],
        'RETUR JUAL'  => ['tunai',  'Retur Tunai'],
        'RESEP TUNAI' => ['tunai',  'Resep Tunai'],
        'UPDS'        => ['tunai',  'UPDS'],
        'ONLINE'      => ['tunai',  'Obat Bebas'],
    ];

    const KREDIT_ORDER = ['Resep Kredit', 'Retur Kredit'];
    const TUNAI_ORDER = ['Obat Bebas', 'Retur Tunai', 'Resep Tunai', 'UPDS'];

    const CART_TYPE_ABBR = [
        'UK' => 'KREDIT',
        'UM' => 'RESEP TUNAI',
        'UP' => 'UPDS',
        'HV' => 'HV/OTC',
    ];

    public function __construct($pharmacyId, $startDate, $endDate, $pharmacyName = '', $pharmacyAddress = '')
    {
        $this->pharmacyId      = $pharmacyId;
        $this->startDate       = Carbon::parse($startDate)->startOfDay();
        $this->endDate         = Carbon::parse($endDate)->endOfDay();
        $this->pharmacyName    = $pharmacyName;
        $this->pharmacyAddress = $pharmacyAddress;
    }

    private function safeSum($collection, $field)
    {
        return (int) $collection->sum(fn($item) => (int) round((float) ($item->{$field} ?? 0)));
    }

    private function safeItemNettoSum($collection): int
    {
        return (int) $collection->sum(function ($item) {
            $qty = (float) ($item->quantity ?? 0);
            $price = (int) round((float) ($item->item_price ?? 0));
            $disc = (int) round((float) ($item->discount ?? 0));
            return (int) round((float) ($item->final_price ?? $item->total_price ?? ($price * $qty - $disc)));
        });
    }

    public function download()
    {
        $data = $this->buildData();
        $pdf  = Pdf::loadView('report.liph', $data)->setPaper('a4', 'landscape');
        $filename = 'LIPH_' . $this->startDate->format('d-m-Y') . '_sd_' . $this->endDate->format('d-m-Y') . '.pdf';
        return $pdf->download($filename);
    }

    private function buildData(): array
    {
        $query = MedicineTransactions::with(['transactions' => fn($q) => $q->where('status', 1)])
            ->where('pharmacy_id', $this->pharmacyId)
            ->whereIn('transaction_type', array_keys(self::TYPE_MAP));

        LiphTransactionScope::applySalesStatus($query);
        LiphTransactionScope::applyPeriod($query, $this->startDate, $this->endDate);

        $transactions = $query->get();

        $grouped = [];

        foreach ($transactions as $trx) {
            $originalMap = self::TYPE_MAP[$trx->transaction_type] ?? null;
            if (!$originalMap) continue;

            [, $originalLabel] = $originalMap;
            $items = $trx->transactions->where('status', 1);
            $isReturnTrx = in_array($originalLabel, ['Retur Tunai', 'Retur Kredit'], true);

            if ($items->isEmpty() || $isReturnTrx) {
                [$group, $label] = $originalMap;
                if (!isset($grouped[$group][$label])) {
                    $grouped[$group][$label] = ['lembar' => 0, 'r' => 0, 'jasa' => 0, 'embalase' => 0, 'potongan' => 0, 'netto' => 0];
                }
                $ref = &$grouped[$group][$label];
                if ($isReturnTrx) {
                    $ref['r'] -= max(1, $items->count());
                    $trxSubtotal = is_numeric($trx->subtotal) ? (int) round((float) $trx->subtotal) : 0;
                    $returnBase = $trxSubtotal !== 0 ? $trxSubtotal : $this->safeItemNettoSum($items);
                    $ref['netto'] += -abs($returnBase);
                } else {
                    $trxDiscount = is_numeric($trx->discount) ? (int) round((float) $trx->discount) : 0;
                    $ref['lembar']++;
                    $ref['potongan'] += $trxDiscount;
                    $ref['netto'] += (int) round((float) ($trx->subtotal ?? 0)) - $trxDiscount;
                }
                unset($ref);
                continue;
            }

            $byLabel = [];
            foreach ($items as $item) {
                if ($trx->transaction_type === 'KREDIT') {
                    $effectiveType = 'KREDIT';
                } elseif ($item->cart_type) {
                    $effectiveType = self::CART_TYPE_ABBR[$item->cart_type] ?? $item->cart_type;
                } else {
                    $effectiveType = $trx->transaction_type;
                }
                $map = self::TYPE_MAP[$effectiveType] ?? $originalMap;
                [$grp, $lbl] = $map;
                $byLabel[$lbl]['group'] = $grp;
                $byLabel[$lbl]['items'][] = $item;
            }

            $potonganTransaksiTarget = isset($byLabel[$originalLabel]) ? $originalLabel : array_key_first($byLabel);

            foreach ($byLabel as $label => $bucket) {
                $group = $bucket['group'];
                $groupItems = collect($bucket['items']);

                if (!isset($grouped[$group][$label])) {
                    $grouped[$group][$label] = ['lembar' => 0, 'r' => 0, 'jasa' => 0, 'embalase' => 0, 'potongan' => 0, 'netto' => 0];
                }

                $ref = &$grouped[$group][$label];
                $ref['r']        += $groupItems->count();
                $ref['jasa']     += $this->safeSum($groupItems, 'embalase');
                $ref['embalase'] += $this->safeSum($groupItems, 'service_fee');
                $ref['potongan'] += $this->safeSum($groupItems, 'discount');

                $trxDiscount = ($label === $potonganTransaksiTarget && is_numeric($trx->discount))
                    ? (int) round((float) $trx->discount)
                    : 0;
                if ($label === $potonganTransaksiTarget) {
                    $ref['lembar']++;
                    $ref['potongan'] += $trxDiscount;
                }

                $itemNetto = $this->safeItemNettoSum($groupItems) - $trxDiscount;
                $ref['netto'] += $itemNetto;
                unset($ref);
            }
        }

        // Build kredit
        $no = 1;
        $kreditRows = [];
        $kreditSub  = ['lembar' => 0, 'r' => 0, 'jasa' => 0, 'embalase' => 0, 'potongan' => 0, 'netto' => 0];

        foreach (self::KREDIT_ORDER as $label) {
            if (!isset($grouped['kredit'][$label])) continue;
            $d = $grouped['kredit'][$label] ?? ['lembar' => 0, 'r' => 0, 'jasa' => 0, 'embalase' => 0, 'potongan' => 0, 'netto' => 0];
            $kreditRows[] = array_merge(['no' => $no++, 'label' => $label], $d);
            foreach ($kreditSub as $k => $_) $kreditSub[$k] += $d[$k];
        }

        // Build tunai
        $tunaiRows = [];
        $tunaiSub  = ['lembar' => 0, 'r' => 0, 'jasa' => 0, 'embalase' => 0, 'potongan' => 0, 'netto' => 0];

        foreach (self::TUNAI_ORDER as $label) {
            $d = $grouped['tunai'][$label] ?? ['lembar' => 0, 'r' => 0, 'jasa' => 0, 'embalase' => 0, 'potongan' => 0, 'netto' => 0];
            $tunaiRows[] = array_merge(['no' => $no++, 'label' => $label], $d);
            foreach ($tunaiSub as $k => $_) $tunaiSub[$k] += $d[$k];
        }

        $grand = [
            'lembar'   => $kreditSub['lembar'] + $tunaiSub['lembar'],
            'r'        => $kreditSub['r'] + $tunaiSub['r'],
            'jasa'     => $kreditSub['jasa'] + $tunaiSub['jasa'],
            'embalase' => $kreditSub['embalase'] + $tunaiSub['embalase'],
            'potongan' => $kreditSub['potongan'] + $tunaiSub['potongan'],
            'netto'    => $kreditSub['netto'] + $tunaiSub['netto'],
        ];

        return [
            'pharmacyName'    => $this->pharmacyName,
            'pharmacyAddress' => $this->pharmacyAddress,
            'startDate'       => $this->startDate->format('d/m/Y'),
            'endDate'         => $this->endDate->format('d/m/Y'),
            'kredit'          => ['rows' => $kreditRows, 'sub' => $kreditSub],
            'tunai'           => ['rows' => $tunaiRows, 'sub' => $tunaiSub],
            'grand'           => $grand,
        ];
    }
}
