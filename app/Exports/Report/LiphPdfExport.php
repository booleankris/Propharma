<?php

namespace App\Exports\Report;

use App\Models\MedicineTransactions;
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
        'HV/OTC'      => ['tunai',  'Obat Bebas'],
        'RETUR JUAL'  => ['tunai',  'Retur Tunai'],
        'RESEP TUNAI' => ['tunai',  'Resep Tunai'],
        'UPDS'        => ['tunai',  'UPDS'],
    ];

    const TUNAI_ORDER = ['Obat Bebas', 'Retur Tunai', 'Resep Tunai', 'UPDS'];

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
        return $collection->sum(fn($item) => (int) ($item->{$field} ?? 0));
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
        $transactions = MedicineTransactions::with('transactions')
            ->where('pharmacy_id', $this->pharmacyId)
            ->where('status', 1)
            ->whereDate('created_at', '>=', $this->startDate->toDateString())
            ->whereDate('created_at', '<=', $this->endDate->toDateString())
            ->whereIn('transaction_type', array_keys(self::TYPE_MAP))
            ->get();

        $grouped = [];

        foreach ($transactions as $trx) {
            $map = self::TYPE_MAP[$trx->transaction_type] ?? null;
            if (!$map) continue;

            [$group, $label] = $map;

            if (!isset($grouped[$group][$label])) {
                $grouped[$group][$label] = [
                    'lembar' => 0,
                    'r' => 0,
                    'jasa' => 0,
                    'embalase' => 0,
                    'potongan' => 0,
                    'netto' => 0,
                ];
            }

            $ref = &$grouped[$group][$label];
            $ref['lembar']++;
            $ref['r']        += $trx->transactions->count();
            $ref['jasa']     += $this->safeSum($trx->transactions, 'service_fee');
            $ref['embalase'] += $this->safeSum($trx->transactions, 'embalase');
            $ref['potongan'] += (int) ($trx->discount ?? 0) + $this->safeSum($trx->transactions, 'discount');

            $netto = (int) ($trx->subtotal ?? 0);
            if ($label === 'Retur Tunai') $netto = -abs($netto);
            $ref['netto'] += $netto;
        }

        // Build kredit
        $no = 1;
        $kreditRows = [];
        $kreditSub  = ['lembar' => 0, 'r' => 0, 'jasa' => 0, 'embalase' => 0, 'potongan' => 0, 'netto' => 0];

        foreach ($grouped['kredit'] ?? [] as $label => $d) {
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
