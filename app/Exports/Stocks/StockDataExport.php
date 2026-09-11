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
    protected $pharmacyId;
    protected $canSeeWarehouse;

    public function __construct($request = null, $pharmacyId = null)
    {
        $this->request = $request;
        $this->pharmacyId = $pharmacyId ?? getActivePharmacyId();
        $this->canSeeWarehouse = canAccessWarehouseStock($this->pharmacyId);
    }

    public function collection()
    {
        $warehouseId = getWarehousePharmacyId();
        $pmiPharmacyId = 1;
        $req = $this->request;
        $pharmacyId = $this->pharmacyId;
        $canSeeWarehouse = $this->canSeeWarehouse;

        $startDate = ($req && $req->filled('start_date')) ? \Carbon\Carbon::parse($req->start_date)->toDateString() : null;
        $endDate = ($req && $req->filled('end_date')) ? \Carbon\Carbon::parse($req->end_date)->toDateString() : null;

        $ordersPharmacyId = $canSeeWarehouse ? $warehouseId : $pharmacyId;
        $salesPharmacyId = $canSeeWarehouse ? $pmiPharmacyId : $pharmacyId;
        $startPharmacyIds = $canSeeWarehouse ? [$warehouseId, $pmiPharmacyId] : [$pharmacyId];
        $counterPharmacyId = $canSeeWarehouse ? $pmiPharmacyId : $pharmacyId;

        $medicines = Medicines::query()
            ->select([
                'medicines.id',
                'medicines.code',
                'medicines.name',
                'medicines.unit',
            ])
            ->addSelect([
                // Qty Beli: Gudang PMI jika ada akses gudang, atau Pembelian cabang
                'qty_orders' => ItemsLog::select(DB::raw('COALESCE(SUM(CAST(items_log.qty AS UNSIGNED)), 0)'))
                    ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                    ->whereColumn('items_log.medicine_id', 'medicines.id')
                    ->where('items_log.status', 2)
                    ->where('batches.pharmacy_id', $ordersPharmacyId)
                    ->when($req && $req->filled('start_date'), fn($q) => $q->whereDate('items_log.date', '>=', $req->start_date))
                    ->when($req && $req->filled('end_date'), fn($q) => $q->whereDate('items_log.date', '<=', $req->end_date)),

                // Qty Jual: Sahabat PMI jika ada akses gudang, atau Penjualan cabang
                'qty_sales' => ItemsLog::select(DB::raw('COALESCE(SUM(CAST(items_log.qty AS UNSIGNED)), 0)'))
                    ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                    ->whereColumn('items_log.medicine_id', 'medicines.id')
                    ->where('items_log.status', 1)
                    ->where('batches.pharmacy_id', $salesPharmacyId)
                    ->when($req && $req->filled('start_date'), fn($q) => $q->whereDate('items_log.date', '>=', $req->start_date))
                    ->when($req && $req->filled('end_date'), fn($q) => $q->whereDate('items_log.date', '<=', $req->end_date)),

                // Qty Retur Beli cabang
                'qty_orders_rt' => ItemsLog::select(DB::raw('COALESCE(SUM(CAST(items_log.qty AS UNSIGNED)), 0)'))
                    ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                    ->whereColumn('items_log.medicine_id', 'medicines.id')
                    ->where('items_log.status', 4)
                    ->where('batches.pharmacy_id', $ordersPharmacyId)
                    ->when($req && $req->filled('start_date'), fn($q) => $q->whereDate('items_log.date', '>=', $req->start_date))
                    ->when($req && $req->filled('end_date'), fn($q) => $q->whereDate('items_log.date', '<=', $req->end_date)),

                // Qty Retur Jual cabang
                'qty_sales_rt' => ItemsLog::select(DB::raw('COALESCE(SUM(CAST(items_log.qty AS UNSIGNED)), 0)'))
                    ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                    ->whereColumn('items_log.medicine_id', 'medicines.id')
                    ->where('items_log.status', 3)
                    ->where('batches.pharmacy_id', $salesPharmacyId)
                    ->when($req && $req->filled('start_date'), fn($q) => $q->whereDate('items_log.date', '>=', $req->start_date))
                    ->when($req && $req->filled('end_date'), fn($q) => $q->whereDate('items_log.date', '<=', $req->end_date)),

                // Qty Awal: Opname/Log awal sesuai unit untuk Gudang PMI
                'qty_start' => $canSeeWarehouse
                    ? ItemsLog::select(DB::raw("CASE WHEN items_log.type = 'SO' THEN items_log.qty_after ELSE items_log.qty_before END"))
                        ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                        ->whereColumn('items_log.medicine_id', 'medicines.id')
                        ->whereIn('batches.pharmacy_id', $startPharmacyIds)
                        ->when($req && $req->filled('start_date'), fn($q) => $q->whereDate('items_log.date', '>=', $req->start_date))
                        ->when($req && $req->filled('end_date'), fn($q) => $q->whereDate('items_log.date', '<=', $req->end_date))
                        ->orderBy('items_log.date', 'asc')
                        ->orderBy('items_log.id', 'asc')
                        ->limit(1)
                    : DB::raw('0'),

                // Stok Gudang (hanya jika ada akses gudang)
                'qty_storage' => $canSeeWarehouse
                    ? Batches::select(DB::raw('COALESCE(SUM(stock), 0)'))
                        ->whereColumn('medicine_id', 'medicines.id')
                        ->where('pharmacy_id', $warehouseId)
                    : DB::raw('0'),

                // Stok Pelayanan / Etalase (Cabang atau Sahabat PMI)
                'qty_counter' => MedicineTransferItems::select(DB::raw('COALESCE(SUM(medicine_transfer_items.qty), 0)'))
                    ->join('batches', 'batches.id', '=', 'medicine_transfer_items.batches_id')
                    ->whereColumn('batches.medicine_id', 'medicines.id')
                    ->where('batches.pharmacy_id', $counterPharmacyId)
                    ->where('medicine_transfer_items.status', 1)
                    ->where(function ($q) {
                        $q->whereNull('medicine_transfer_items.source_type')
                          ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                    }),
            ])
            ->when($req && $req->filled('searchMedicine'), function ($q) use ($req) {
                $q->where(function ($sub) use ($req) {
                    $sub->where('medicines.name', 'like', "%{$req->searchMedicine}%")
                        ->orWhere('medicines.code', 'like', "%{$req->searchMedicine}%");
                });
            })
            ->when($req && $req->filled('medicine_id'), fn($q) => $q->where('medicines.id', $req->medicine_id))
            ->orderBy('medicines.code')
            ->orderBy('medicines.id')
            ->get();

        return $medicines->map(function ($m, $index) use ($canSeeWarehouse) {
            $qtyStorage = $canSeeWarehouse ? (int) ($m->qty_storage ?? 0) : 0;
            $qtyCounter = (int) ($m->qty_counter ?? 0);
            $totalStok = $qtyStorage + $qtyCounter;

            if ($canSeeWarehouse) {
                $qtyStart = (int) ($m->qty_start ?? 0);
            } else {
                $netIn = (int) ($m->qty_orders ?? 0) - (int) ($m->qty_orders_rt ?? 0);
                $netOut = (int) ($m->qty_sales ?? 0) - (int) ($m->qty_sales_rt ?? 0);
                $qtyStart = $qtyCounter - $netIn + $netOut;
            }

            $row = [
                'No' => $index + 1,
                'Kode Obat' => $m->code,
                'Nama Obat' => $m->name,
                'Satuan' => $m->unit ?? '-',
                'QTY Awal' => $qtyStart,
                'QTY Beli' => (int) ($m->qty_orders ?? 0),
                'QTY Jual' => (int) ($m->qty_sales ?? 0),
            ];

            if ($canSeeWarehouse) {
                $row['Stok Gudang'] = $qtyStorage;
                $row['Stok Pelayanan'] = $qtyCounter;
            } else {
                $row['Stok Etalase'] = $qtyCounter;
            }

            $row['Total Stok'] = $totalStok;

            return $row;
        });
    }

    public function headings(): array
    {
        if ($this->canSeeWarehouse) {
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

        return [
            'No',
            'Kode Obat',
            'Nama Obat',
            'Satuan',
            'QTY Awal',
            'QTY Beli',
            'QTY Jual',
            'Stok Etalase',
            'Total Stok',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = $this->canSeeWarehouse ? 'J' : 'I';
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle('A')->getAlignment()->setHorizontal('center');
        $sheet->getStyle("E:{$lastCol}")->getAlignment()->setHorizontal('right');

        return [];
    }
}
