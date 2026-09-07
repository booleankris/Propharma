<?php

namespace App\Exports\Report;

use App\Models\MedicineCart;
use App\Models\Medicines;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportedMedicineSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $medicine;
    protected $startDate;
    protected $endDate;
    protected $pharmacyId;
    protected $pharmacyName;
    protected $sheetTitle;
    protected $dataRowCount = 0;
    protected $totalRows = 0;

    public function __construct($medicine, $startDate, $endDate, $pharmacyId, $pharmacyName, $sheetTitle)
    {
        $this->medicine     = $medicine;
        $this->startDate    = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
        $this->endDate      = $endDate ? Carbon::parse($endDate)->endOfDay() : null;
        $this->pharmacyId   = $pharmacyId;
        $this->pharmacyName = strtoupper($pharmacyName);
        $this->sheetTitle   = $sheetTitle;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 26, // NAMA PASIEN
            'B' => 40, // ALAMAT PASIEN
            'C' => 20, // TANGGAL DIBERIKAN
            'D' => 12, // JUMLAH
            'E' => 30, // NAMA DOKTER
            'F' => 45, // ALAMAT DOKTER
        ];
    }

    public function array(): array
    {
        $rows = [];

        // 1. Tentukan teks periode
        if ($this->startDate && $this->endDate) {
            if ($this->startDate->format('Y-m') === $this->endDate->format('Y-m')) {
                $monthNames = [
                    1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
                    5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
                    9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER'
                ];
                $periodText = 'PERIODE ' . ($monthNames[(int)$this->startDate->format('n')] ?? $this->startDate->format('F')) . ' ' . $this->startDate->format('Y');
            } else {
                $periodText = 'PERIODE ' . $this->startDate->format('d/m/Y') . ' - ' . $this->endDate->format('d/m/Y');
            }
        } else {
            $periodText = 'SEMUA PERIODE';
        }

        $medName = $this->medicine ? strtoupper($this->medicine->name) : 'TIDAK ADA OBAT';
        $medCode = $this->medicine ? ($this->medicine->code ?: '-') : '-';

        // 2. Ambil data transaksi selesai (medicine_cart status = 1 dan transaksi status = 1)
        $carts = collect();
        if ($this->medicine) {
            $query = MedicineCart::where('medicine_id', $this->medicine->id)
                ->where('status', 1)
                ->whereHas('transactions', function ($q) {
                    $q->where('status', 1);
                    if ($this->pharmacyId) {
                        $q->where('pharmacy_id', $this->pharmacyId);
                    }
                    if ($this->startDate && $this->endDate) {
                        $q->whereBetween('created_at', [$this->startDate, $this->endDate]);
                    }
                })
                ->with(['transactions.patients', 'transactions.doctors', 'transactions'])
                ->orderBy('created_at', 'asc');

            $carts = $query->get();
        }

        $totalPengeluaran = $carts->sum(function ($c) {
            return (float)$c->quantity;
        });

        // Sisa stok saat ini (master stock)
        $sisaStok = $this->medicine ? (int)$this->medicine->stock : 0;

        // Baris 1: Judul Utama
        $rows[] = ["PELAPORAN {$medName} {$periodText} - {$this->pharmacyName}", '', '', '', '', ''];
        // Baris 2: Spasi kosong
        $rows[] = ['', '', '', '', '', ''];
        // Baris 3-6: Rincian Header Obat
        $rows[] = ['', 'Nama Obat :', $medName, '', '', ''];
        $rows[] = ['', 'Kode Obat :', $medCode, '', '', ''];
        $rows[] = ['', 'Total Pengeluaran:', $totalPengeluaran, '', '', ''];
        $rows[] = ['', 'Sisa :', $sisaStok, '', '', ''];
        // Baris 7: Spasi kosong
        $rows[] = ['', '', '', '', '', ''];
        // Baris 8: Header Tabel
        $rows[] = [
            'NAMA PASIEN',
            'ALAMAT PASIEN',
            'TANGGAL DIBERIKAN',
            'JUMLAH',
            'NAMA DOKTER',
            'ALAMAT DOKTER'
        ];

        $this->dataRowCount = $carts->count();

        // Baris 9+: Isi Transaksi
        foreach ($carts as $cart) {
            $tx = $cart->transactions;
            $patient = $tx?->patients;
            $doctor  = $tx?->doctors;

            $patientName    = $patient?->name ?: ($tx?->transaction_type === 'HV' ? 'UMUM (NON RESEP)' : '-');
            $patientAddress = $patient?->address ?: ($patient?->city ?: '-');
            
            $givenDate = $tx?->created_at 
                ? Carbon::parse($tx->created_at)->format('d/m/Y')
                : ($cart->created_at ? Carbon::parse($cart->created_at)->format('d/m/Y') : '-');

            $qty = (float)$cart->quantity;

            $doctorName    = $doctor?->name ?: '-';
            $doctorAddress = $doctor?->address ?: ($doctor?->practice ?: ($doctor?->city ?: '-'));

            $rows[] = [
                $patientName,
                $patientAddress,
                $givenDate,
                $qty,
                $doctorName,
                $doctorAddress
            ];
        }

        // Tambahkan baris kosong bergaris jika data kurang dari 18 baris, agar menyerupai template kertas resmi
        $minimumDisplayRows = 18;
        $blankRowsNeeded = max(0, $minimumDisplayRows - $this->dataRowCount);
        for ($i = 0; $i < $blankRowsNeeded; $i++) {
            $rows[] = ['', '', '', '', '', ''];
        }

        $this->totalRows = count($rows);

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // 1. Judul Utama (Baris 1)
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'name' => 'Calibri',
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(25);

        // 2. Subheader Obat (Baris 3 s/d 6)
        $sheet->getStyle('B3:B6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $sheet->getStyle('C3:C6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // 3. Header Tabel (Baris 8)
        $headerRange = 'A8:F8';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold'  => true,
                'size'  => 10,
                'color' => ['rgb' => '000000'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FF4500'], // Bright Orange Red
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
        ]);
        $sheet->getRowDimension(8)->setRowHeight(24);

        // 4. Border dan format tabel dari baris 8 sampai baris terakhir
        $tableRange = "A8:F{$this->totalRows}";
        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Alignment kolom data
        if ($this->totalRows >= 9) {
            $dataRange = "A9:F{$this->totalRows}";
            // Kolom Tanggal (C) dan Jumlah (D) rata tengah
            $sheet->getStyle("C9:D{$this->totalRows}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            // Kolom Nama & Alamat rata kiri dengan sedikit indent
            $sheet->getStyle("A9:B{$this->totalRows}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("E9:F{$this->totalRows}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            // Tinggi baris data
            for ($r = 9; $r <= $this->totalRows; $r++) {
                $sheet->getRowDimension($r)->setRowHeight(20);
            }
        }

        // Tampilkan grid lines
        $sheet->setShowGridlines(true);

        return [];
    }
}
