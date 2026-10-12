<?php

namespace App\Exports\HO;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithEvents, WithCustomStartCell, WithCustomValueBinder
{
    private $dataCount = 0;
    private $periodLabel;

    public function __construct($periodLabel)
    {
        $this->periodLabel = $periodLabel;
        $this->dataCount = DB::table('medicines')->count();
    }

    public function query()
    {
        return DB::table('medicines as m')
            ->where('m.status', 1)
            ->leftJoin(DB::raw("(SELECT medicine_id, SUM(stock) as gudang_qty FROM batches WHERE pharmacy_id = 9 GROUP BY medicine_id) as g"), 'g.medicine_id', '=', 'm.id')
            ->leftJoin(DB::raw("(
                SELECT b.medicine_id, SUM(mt.qty) as pmi_qty
                FROM medicine_transfer_items mt
                JOIN batches b ON b.id = mt.batches_id
                WHERE mt.status = 1 AND b.pharmacy_id = 1 
                AND (mt.source_type IS NULL OR mt.source_type != 'retur_gudang')
                GROUP BY b.medicine_id
            ) as pmi"), 'pmi.medicine_id', '=', 'm.id')
            ->leftJoin(DB::raw("(
                SELECT b.medicine_id, SUM(mt.qty) as asm_qty
                FROM medicine_transfer_items mt
                JOIN batches b ON b.id = mt.batches_id
                WHERE mt.status = 1 AND b.pharmacy_id = 2 
                AND (mt.source_type IS NULL OR mt.source_type != 'retur_gudang')
                GROUP BY b.medicine_id
            ) as asm"), 'asm.medicine_id', '=', 'm.id')
            ->leftJoin(DB::raw("(
                SELECT b.medicine_id, SUM(mt.qty) as mim_qty
                FROM medicine_transfer_items mt
                JOIN batches b ON b.id = mt.batches_id
                WHERE mt.status = 1 AND b.pharmacy_id = 3 
                AND (mt.source_type IS NULL OR mt.source_type != 'retur_gudang')
                GROUP BY b.medicine_id
            ) as mim"), 'mim.medicine_id', '=', 'm.id')
            ->leftJoin(DB::raw("(
                SELECT b.medicine_id, SUM(mt.qty) as asa_qty
                FROM medicine_transfer_items mt
                JOIN batches b ON b.id = mt.batches_id
                WHERE mt.status = 1 AND b.pharmacy_id = 5 
                AND (mt.source_type IS NULL OR mt.source_type != 'retur_gudang')
                GROUP BY b.medicine_id
            ) as asa"), 'asa.medicine_id', '=', 'm.id')
            ->select('m.code', 'm.name', 'g.gudang_qty', 'pmi.pmi_qty', 'asm.asm_qty', 'mim.mim_qty', 'asa.asa_qty')
            ->orderBy('m.name', 'asc');
    }

    public function startCell(): string
    {
        return 'A4';
    }

    public function headings(): array
    {
        return [
            'KODE OBAT',
            'NAMA OBAT',
            'STOK GUDANG',
            'STOK PMI',
            'STOK ASM',
            'STOK MIM',
            'STOK ASA'
        ];
    }

    public function map($row): array
    {
        return [
            (string) $row->code,
            $row->name,
            (int) $row->gudang_qty,
            (int) $row->pmi_qty,
            (int) $row->asm_qty,
            (int) $row->mim_qty,
            (int) $row->asa_qty,
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // Keep medicine codes as literal text so Excel preserves leading zeroes.
        if ($cell->getColumn() === 'A' && $cell->getRow() >= 5) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $this->dataCount + 4; // Start cell is A4

                // ==============================
                // 1. SET JUDUL (TITLE)
                // ==============================
                $sheet->mergeCells('A1:G1');
                $sheet->setCellValue('A1', 'STOK OPNAME APOTEK SAHABAT');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['rgb' => '1E293B'] // Dark slate color
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ]
                ]);

                // ==============================
                // 2. SET SUBJUDUL (TANGGAL)
                // ==============================
                $sheet->mergeCells('A2:G2');
                $sheet->setCellValue('A2', $this->periodLabel);
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'size' => 11,
                        'color' => ['rgb' => '64748B'] // Slate 500
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ]
                ]);

                // ==============================
                // 3. STYLING HEADER TABEL (BARIS 4)
                // ==============================
                $headerStyle = [
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0F172A'] // Sangat gelap agar header terlihat premium
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '475569']
                        ]
                    ]
                ];
                $sheet->getStyle('A4:G4')->applyFromArray($headerStyle);
                $sheet->getRowDimension(4)->setRowHeight(25); // Tinggi baris header

                // ==============================
                // 4. STYLING KONTEN TABEL
                // ==============================
                if ($this->dataCount > 0) {
                    $contentStyle = [
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'E2E8F0'] // Garis abu-abu tipis
                            ]
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ]
                    ];
                    
                    // Tengahkan angka stok (kolom C s.d G)
                    $sheet->getStyle('C5:G' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    
                    // Aplikasikan border untuk seluruh tabel
                    $sheet->getStyle('A5:G' . $highestRow)->applyFromArray($contentStyle);
                    
                    // Efek Zebra Cross (belang-belang) untuk mempermudah mata membaca
                    for ($row = 5; $row <= $highestRow; $row++) {
                        if ($row % 2 == 0) {
                            $sheet->getStyle('A'.$row.':G'.$row)
                                ->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()
                                ->setRGB('F8FAFC'); // Abu-abu sangat tipis
                        }
                    }

                    // Tambahkan filter otomatis di baris header (A4 s.d G)
                    $sheet->setAutoFilter('A4:G' . $highestRow);
                }
            }
        ];
    }
}
