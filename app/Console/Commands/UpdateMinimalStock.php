<?php

namespace App\Console\Commands;

use App\Models\Medicines;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UpdateMinimalStock extends Command
{
    protected $signature = 'medicines:update-minimal-stock
                            {--file=storage/app/STOK MIN.xlsx : Path file Excel (relatif dari root aplikasi atau absolut)}
                            {--sheet=PMI : Nama sheet Excel yang dibaca}
                            {--rounding=round : Mode pembulatan ke integer (round, ceil, round-min1)}
                            {--dry-run : Tampilkan ringkasan perubahan tanpa menulis ke database}
                            {--force : Langsung simpan tanpa prompt konfirmasi}';

    protected $description = 'Update kolom minimal_stock pada tabel medicines dari file Excel berdasarkan KODE BARU.';

    private const CODE_HEADER_ALIASES = ['KODE BARU', 'KODE BARANG', 'CODE BARU', 'CODE'];
    private const MIN_STOCK_HEADER_ALIASES = ['STOK MIN', 'STOK MINIMAL', 'MINIMAL STOK', 'MIN STOK', 'MINIMAL STOCK'];
    private const VALID_ROUNDINGS = ['round', 'ceil', 'round-min1'];

    public function handle(): int
    {
        $file = trim((string) $this->option('file'));
        if ($file === '') {
            $this->error('Tentukan file Excel dengan --file=PATH.');
            return self::FAILURE;
        }

        $rounding = strtolower(trim((string) $this->option('rounding')));
        if (!in_array($rounding, self::VALID_ROUNDINGS, true)) {
            $this->error('Opsi --rounding tidak valid. Pilihan: ' . implode(', ', self::VALID_ROUNDINGS));
            return self::FAILURE;
        }

        $path = str_starts_with($file, DIRECTORY_SEPARATOR) ? $file : base_path($file);
        if (!is_file($path) || !is_readable($path)) {
            $this->error("File tidak ditemukan atau tidak bisa dibaca: {$path}");
            return self::FAILURE;
        }

        $sheetName = trim((string) $this->option('sheet'));

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadEmptyCells(false);
            if ($sheetName !== '' && method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly([$sheetName]);
            }

            $spreadsheet = $reader->load($path);
            $sheet = $sheetName !== ''
                ? $spreadsheet->getSheetByName($sheetName)
                : $spreadsheet->getActiveSheet();

            if (!$sheet) {
                $spreadsheet->disconnectWorksheets();
                $this->error("Sheet \"{$sheetName}\" tidak ditemukan di file {$path}.");
                return self::FAILURE;
            }

            $lastRow = (int) $sheet->getHighestRow();
            if ($lastRow < 2) {
                $spreadsheet->disconnectWorksheets();
                $this->error("Sheet \"{$sheet->getTitle()}\" tidak memiliki data mulai baris 2.");
                return self::FAILURE;
            }

            [$codeColumn, $minStockColumn] = $this->resolveColumns($sheet);
            if ($codeColumn === null || $minStockColumn === null) {
                $spreadsheet->disconnectWorksheets();
                $this->error('Header kolom KODE BARU atau STOK MIN tidak ditemukan di baris 1.');
                return self::FAILURE;
            }

            $medicines = Medicines::query()
                ->select(['id', 'code', 'name', 'minimal_stock'])
                ->get();

            $medicineByCode = [];
            foreach ($medicines as $medicine) {
                $key = $this->normalizeCode((string) $medicine->code);
                if ($key !== '') {
                    $medicineByCode[$key] = $medicine;
                }
            }

            $updates = [];
            $previewRows = [];
            $seenCodes = [];
            $seenMedicineIds = [];
            $summary = [
                'total_rows' => $lastRow - 1,
                'valid_code_rows' => 0,
                'skipped_code_na' => 0,
                'skipped_code_blank' => 0,
                'duplicate_codes' => [],
                'matched' => 0,
                'not_found' => [],
                'invalid_min_stock' => [],
                'unchanged' => 0,
                'to_update' => 0,
            ];

            for ($rowNumber = 2; $rowNumber <= $lastRow; $rowNumber++) {
                $rawCode = $this->readCachedCellValue($sheet->getCell("{$codeColumn}{$rowNumber}"));
                $codeText = trim((string) ($rawCode ?? ''));

                if ($codeText === '') {
                    $summary['skipped_code_blank']++;
                    continue;
                }

                if ($this->isExcelError($codeText)) {
                    $summary['skipped_code_na']++;
                    continue;
                }

                $summary['valid_code_rows']++;

                $codeKey = $this->normalizeCode($codeText);
                if (isset($seenCodes[$codeKey])) {
                    $summary['duplicate_codes'][] = "Baris {$rowNumber}: {$codeText}";
                    continue;
                }
                $seenCodes[$codeKey] = true;

                /** @var Medicines|null $medicine */
                $medicine = $this->findMedicineByCode($codeKey, $medicineByCode);
                if (!$medicine) {
                    $summary['not_found'][] = "Baris {$rowNumber}: {$codeText}";
                    continue;
                }
                if (isset($seenMedicineIds[$medicine->id])) {
                    $summary['duplicate_codes'][] = "Baris {$rowNumber}: {$codeText}";
                    continue;
                }
                $seenMedicineIds[$medicine->id] = true;
                $summary['matched']++;

                $rawMinStock = $this->readCachedCellValue($sheet->getCell("{$minStockColumn}{$rowNumber}"));
                $numericMinStock = $this->parseNumericValue($rawMinStock);
                if ($numericMinStock === null) {
                    $summary['invalid_min_stock'][] = "Baris {$rowNumber} ({$codeText}): " . (string) ($rawMinStock ?? 'kosong');
                    continue;
                }

                $newMinimalStock = $this->roundMinimalStock($numericMinStock, $rounding);
                $currentMinimalStock = (int) ($medicine->minimal_stock ?? 0);

                if ($currentMinimalStock === $newMinimalStock) {
                    $summary['unchanged']++;
                    continue;
                }

                $updates[$medicine->id] = $newMinimalStock;
                $summary['to_update']++;

                if (count($previewRows) < 15) {
                    $previewRows[] = [
                        $medicine->code,
                        mb_strimwidth((string) $medicine->name, 0, 42, '…'),
                        $currentMinimalStock,
                        rtrim(rtrim(number_format($numericMinStock, 4, '.', ''), '0'), '.'),
                        $newMinimalStock,
                    ];
                }
            }

            $activeSheetTitle = $sheet->getTitle();
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $this->info("Ringkasan update stok minimal (Sheet: {$activeSheetTitle}, Kolom: {$codeColumn} -> {$minStockColumn}, Pembulatan: {$rounding})");
            $this->table(['Metrik', 'Jumlah'], [
                ['Total baris data (mulai baris 2)', $summary['total_rows']],
                ['Baris dengan KODE BARU valid', $summary['valid_code_rows']],
                ['Dilewati (KODE BARU #N/A / error)', $summary['skipped_code_na']],
                ['Dilewati (KODE BARU kosong)', $summary['skipped_code_blank']],
                ['Duplikat KODE BARU (dipakai baris pertama)', count($summary['duplicate_codes'])],
                ['Cocok di master obat (medicines)', $summary['matched']],
                ['Tidak ditemukan di master obat', count($summary['not_found'])],
                ['Nilai STOK MIN kosong / error', count($summary['invalid_min_stock'])],
                ['Sudah sama (tidak berubah)', $summary['unchanged']],
                ['Akan diperbarui', $summary['to_update']],
            ]);

            if ($previewRows !== []) {
                $this->line('Contoh perubahan (maks. 15 baris pertama):');
                $this->table(['Kode Obat', 'Nama Obat', 'Stok Min Lama', 'Nilai Excel', 'Stok Min Baru'], $previewRows);
            }

            if ($summary['duplicate_codes'] !== []) {
                $this->warn('Contoh duplikat KODE BARU: ' . implode(', ', array_slice($summary['duplicate_codes'], 0, 10)));
            }

            if ($summary['not_found'] !== []) {
                $this->warn('Contoh kode tidak ditemukan (' . count($summary['not_found']) . '): ' . implode(', ', array_slice($summary['not_found'], 0, 10)));
            }

            if ($summary['invalid_min_stock'] !== []) {
                $this->warn('Contoh nilai STOK MIN tidak valid (' . count($summary['invalid_min_stock']) . '): ' . implode(', ', array_slice($summary['invalid_min_stock'], 0, 10)));
            }

            if ($this->option('dry-run')) {
                $this->comment('Mode --dry-run aktif. Tidak ada data yang ditulis ke database.');
                return self::SUCCESS;
            }

            if ($updates === []) {
                $this->info('Tidak ada perubahan stok minimal yang perlu disimpan.');
                return self::SUCCESS;
            }

            if (!$this->option('force') && !$this->confirm("Lanjutkan update minimal_stock untuk {$summary['to_update']} obat?")) {
                $this->comment('Dibatalkan. Tidak ada perubahan yang ditulis.');
                return self::SUCCESS;
            }

            DB::transaction(function () use ($updates): void {
                foreach ($updates as $medicineId => $newMinimalStock) {
                    Medicines::query()
                        ->whereKey($medicineId)
                        ->update(['minimal_stock' => $newMinimalStock]);
                }
            });

            $this->info("Selesai. {$summary['to_update']} obat berhasil diperbarui kolom minimal_stock-nya.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error('Gagal membaca atau memproses file: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveColumns(Worksheet $sheet): array
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $headerMap = [];

        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $rawHeader = $this->readCachedCellValue($sheet->getCell("{$colLetter}1"));
            $normalized = $this->normalizeHeader((string) ($rawHeader ?? ''));
            if ($normalized !== '' && !isset($headerMap[$normalized])) {
                $headerMap[$normalized] = $colLetter;
            }
        }

        $codeColumn = null;
        foreach (self::CODE_HEADER_ALIASES as $alias) {
            if (isset($headerMap[$alias])) {
                $codeColumn = $headerMap[$alias];
                break;
            }
        }

        $minStockColumn = null;
        foreach (self::MIN_STOCK_HEADER_ALIASES as $alias) {
            if (isset($headerMap[$alias])) {
                $minStockColumn = $headerMap[$alias];
                break;
            }
        }

        return [$codeColumn, $minStockColumn];
    }

    private function readCachedCellValue(Cell $cell): mixed
    {
        $dataType = $cell->getDataType();
        if ($dataType === DataType::TYPE_ERROR) {
            return (string) ($cell->getValue() ?? '#ERROR');
        }

        if ($dataType === DataType::TYPE_FORMULA) {
            return $cell->getOldCalculatedValue();
        }

        return $cell->getValue();
    }

    private function isExcelError(string $value): bool
    {
        $upper = mb_strtoupper(trim($value));
        return str_starts_with($upper, '#')
            || in_array($upper, ['N/A', 'NA', 'N.A.'], true);
    }

    private function normalizeCode(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/[\s\x{00a0}\x{200b}\x{FEFF}]+/u', ' ', trim($value)) ?? trim($value);
        return mb_strtoupper($value);
    }

    /**
     * @param array<string, Medicines> $medicineByCode
     */
    private function findMedicineByCode(string $codeKey, array $medicineByCode): ?Medicines
    {
        if (isset($medicineByCode[$codeKey])) {
            return $medicineByCode[$codeKey];
        }

        if (ctype_digit($codeKey) && strlen($codeKey) < 9) {
            $padded = str_pad($codeKey, 9, '0', STR_PAD_LEFT);
            if (isset($medicineByCode[$padded])) {
                return $medicineByCode[$padded];
            }
        }

        return null;
    }

    private function parseNumericValue(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        $text = trim((string) $value);
        if ($text === '' || $this->isExcelError($text)) {
            return null;
        }

        if (is_numeric($text)) {
            return (float) $text;
        }

        $cleaned = preg_replace('/[^0-9,.\-]/', '', $text) ?? '';
        if ($cleaned === '' || !preg_match('/\d/', $cleaned)) {
            return null;
        }

        if (str_contains($cleaned, ',') && str_contains($cleaned, '.')) {
            $comma = strrpos($cleaned, ',');
            $dot = strrpos($cleaned, '.');
            if ($comma > $dot) {
                $cleaned = str_replace('.', '', $cleaned);
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                $cleaned = str_replace(',', '', $cleaned);
            }
        } elseif (str_contains($cleaned, ',')) {
            $cleaned = str_replace(',', '.', $cleaned);
        }

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private function roundMinimalStock(float $value, string $mode): int
    {
        $nonNegative = max(0.0, $value);

        return match ($mode) {
            'ceil' => (int) ceil($nonNegative),
            'round-min1' => $nonNegative > 0.0 ? max(1, (int) round($nonNegative)) : 0,
            default => (int) round($nonNegative),
        };
    }
}
