<?php

namespace App\Exports\Stocks;

use App\Models\Medicines;
use App\Models\ItemsLog;
use App\Models\Batches;
use App\Models\MedicineTransferItems;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockDataExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $request;

    public function __construct($request = null)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $warehouseId = getWarehousePharmacyId();
        $pmiPharmacyId = 1;
        $req = $this->request;

        $medicines = Medicines::query()
            ->select([
                'medicines.id',
                'medicines.code',
                'medicines.name',
                'medicines.unit',
            ])
            ->addSelect([
                // Qty Beli dari Gudang PMI (pharmacy_id = 9)
                'qty_orders' => ItemsLog::select(DB::raw('COALESCE(SUM(CAST(items_log.qty AS UNSIGNED)), 0)'))
                    ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                    ->whereColumn('items_log.medicine_id', 'medicines.id')
                    ->where('items_log.status', 2)
                    ->where('batches.pharmacy_id', $warehouseId)
                    ->when($req && $req->filled('start_date'), fn($q) => $q->whereDate('items_log.date', '>=', $req->start_date))
                    ->when($req && $req->filled('end_date'), fn($q) => $q->whereDate('items_log.date', '<=', $req->end_date)),

                // Qty Jual dari SAHABAT PMI (pharmacy_id = 1)
                'qty_sales' => ItemsLog::select(DB::raw('COALESCE(SUM(CAST(items_log.qty AS UNSIGNED)), 0)'))
                    ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                    ->whereColumn('items_log.medicine_id', 'medicines.id')
                    ->where('items_log.status', 1)
                    ->where('batches.pharmacy_id', $pmiPharmacyId)
                    ->when($req && $req->filled('start_date'), fn($q) => $q->whereDate('items_log.date', '>=', $req->start_date))
                    ->when($req && $req->filled('end_date'), fn($q) => $q->whereDate('items_log.date', '<=', $req->end_date)),

                // Qty Awal
                'qty_start' => ItemsLog::select('qty_before')
                    ->whereColumn('medicine_id', 'medicines.id')
                    ->when($req && $req->filled('start_date'), fn($q) => $q->whereDate('date', '>=', $req->start_date))
                    ->when($req && $req->filled('end_date'), fn($q) => $q->whereDate('date', '<=', $req->end_date))
                    ->orderBy('date')
                    ->orderBy('id')
                    ->limit(1),

                // Stok Gudang (pharmacy_id = 9)
                'qty_storage' => Batches::select(DB::raw('COALESCE(SUM(stock), 0)'))
                    ->whereColumn('medicine_id', 'medicines.id')
                    ->where('pharmacy_id', $warehouseId),

                // Stok Pelayanan PMI (pharmacy_id = 1)
                'qty_counter' => MedicineTransferItems::select(DB::raw('COALESCE(SUM(medicine_transfer_items.qty), 0)'))
                    ->join('batches', 'batches.id', '=', 'medicine_transfer_items.batches_id')
                    ->whereColumn('batches.medicine_id', 'medicines.id')
                    ->where('batches.pharmacy_id', $pmiPharmacyId)
                    ->where('medicine_transfer_items.status', 1)
                    ->where(function ($q) {
                        $q->whereNull('medicine_transfer_items.source_type')
                          ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                    }),
            ])
            ->when($req && $req->filled('medicine_id'), fn($q) => $q->where('medicines.id', $req->medicine_id))
            ->get();

        return $medicines->map(function ($m, $index) {
            $qtyStorage = (int) ($m->qty_storage ?? 0);
            $qtyCounter = (int) ($m->qty_counter ?? 0);
            $totalStok = $qtyStorage + $qtyCounter;

            return [
                'No' => $index + 1,
                'Kode Obat' => $m->code,
                'Nama Obat' => $m->name,
                'Satuan' => $m->unit ?? '-',
                'QTY Awal' => (int) ($m->qty_start ?? 0),
                'QTY Beli (Gudang PMI)' => (int) ($m->qty_orders ?? 0),
                'QTY Jual (Sahabat PMI)' => (int) ($m->qty_sales ?? 0),
                'Stok Gudang' => $qtyStorage,
                'Stok Pelayanan PMI' => $qtyCounter,
                'Total Stok' => $totalStok,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Obat',
            'Nama Obat',
            'Satuan',
            'QTY Awal',
            'QTY Beli (Gudang PMI)',
            'QTY Jual (Sahabat PMI)',
            'Stok Gudang',
            'Stok Pelayanan PMI',
            'Total Stok',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getStyle('A')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('E:J')->getAlignment()->setHorizontal('right');

        return [];
    }
}
