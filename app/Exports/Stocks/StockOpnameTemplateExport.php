<?php

namespace App\Exports\Stocks;

use App\Models\Medicines;
use App\Models\MedicineTransferItems;
use App\Models\Batches;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class StockOpnameTemplateExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected int $pharmacyId;
    protected string $mode;
    protected bool $includeSampleData;

    public function __construct(int $pharmacyId, string $mode = 'pelayanan', bool $includeSampleData = true)
    {
        $this->pharmacyId = $pharmacyId;
        $this->mode = $mode;
        $this->includeSampleData = $includeSampleData;
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Barang',
            'Stok Fisik',
            'Expired Date',
            'Etalase',
            'Nama Obat (Referensi)',
            'Satuan',
        ];
    }

    public function collection(): Collection
    {
        if (!$this->includeSampleData) {
            return collect([
                [
                    'No'           => 1,
                    'Kode Barang'  => 'OBT001',
                    'Stok Fisik'   => 10,
                    'Expired Date' => '2027-12-31',
                    'Etalase'      => 'Rak 1',
                    'Nama Obat'    => 'Paracetamol 500mg (Contoh)',
                    'Satuan'       => 'TABLET',
                ]
            ]);
        }

        $medicines = Medicines::query()
            ->select('id', 'code', 'name', 'unit')
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('name', 'asc')
            ->get();

        $rows = [];
        $no = 1;

        if ($this->mode === 'gudang') {
            // Bulk prefetch for gudang (pharmacy 9)
            $gudangBatchMap = DB::table('batches')
                ->select('medicine_id', DB::raw('MIN(expired_date) as latest_ed'))
                ->where('pharmacy_id', 9)
                ->whereNotNull('expired_date')
                ->where('expired_date', '!=', '')
                ->groupBy('medicine_id')
                ->pluck('latest_ed', 'medicine_id');

            foreach ($medicines as $med) {
                $ed = $gudangBatchMap[$med->id] ?? '';
                $rows[] = [
                    'No'           => $no++,
                    'Kode Barang'  => $med->code,
                    'Stok Fisik'   => '', // Left empty for user to fill
                    'Expired Date' => $ed ? substr($ed, 0, 10) : '',
                    'Etalase'      => '',
                    'Nama Obat'    => $med->name,
                    'Satuan'       => $med->unit,
                ];
            }
        } else {
            // Bulk prefetch for pelayanan (active branch pharmacy)
            $targetPharmacyId = $this->pharmacyId ?: getActivePharmacyId();

            $pelayananMap = DB::table('medicine_transfer_items as mti')
                ->join('batches as b', 'b.id', '=', 'mti.batches_id')
                ->leftJoin('etalases as e', 'e.id', '=', 'mti.etalases_id')
                ->where('b.pharmacy_id', $targetPharmacyId)
                ->whereNotNull('mti.etalases_id')
                ->select(
                    'b.medicine_id',
                    'e.name as etalase_name',
                    'b.expired_date',
                    'mti.id as mti_id'
                )
                ->orderBy('mti.id', 'desc')
                ->get()
                ->unique('medicine_id')
                ->keyBy('medicine_id');

            $batchFallback = DB::table('batches')
                ->select('medicine_id', DB::raw('MIN(expired_date) as latest_ed'))
                ->where('pharmacy_id', $targetPharmacyId)
                ->whereNotNull('expired_date')
                ->where('expired_date', '!=', '')
                ->groupBy('medicine_id')
                ->pluck('latest_ed', 'medicine_id');

            foreach ($medicines as $med) {
                $info = $pelayananMap->get($med->id);
                $etalaseName = $info->etalase_name ?? '';
                $latestEd = $info->expired_date ?? ($batchFallback[$med->id] ?? '');

                $rows[] = [
                    'No'           => $no++,
                    'Kode Barang'  => $med->code,
                    'Stok Fisik'   => '', // Left empty for user to fill
                    'Expired Date' => $latestEd ? substr($latestEd, 0, 10) : '',
                    'Etalase'      => $etalaseName,
                    'Nama Obat'    => $med->name,
                    'Satuan'       => $med->unit,
                ];
            }
        }

        return collect($rows);
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        // Style header row (Row 1)
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'], // Indigo-600
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(28);

        // Center align columns A, B, C, D
        $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B2:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("C2:C{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("D2:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Borders
        $sheet->getStyle("A1:G{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return [];
    }
}
