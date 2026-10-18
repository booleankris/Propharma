<?php

namespace App\Services;

use App\Models\Composition;
use App\Models\Creditor;
use App\Models\Factory;
use App\Models\MedicineCategory;
use App\Models\MedicineCreditor;
use App\Models\Medicines;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MedicineImportService
{
    /**
     * Analyze the file without writing to the database, for the import confirmation step.
     */
    public function preview(string $filePath): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = (int) $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        if (Coordinate::columnIndexFromString($highestCol) < 40) {
            $highestCol = 'AN';
        }

        $summary = [
            'total_rows' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'skipped_count' => 0,
            'skipped_samples' => [],
        ];

        $mapping = $this->resolveColumnMapping($sheet, $highestRow, $highestCol);
        $startRow = $mapping['start_row'];
        $cols = $mapping['columns'];

        if ($highestRow < $startRow) {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            return $summary;
        }

        $existingCodes = Medicines::query()->pluck('code')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->flip();
        $categoryMap = $this->buildCategoryMap();
        $seenCodes = [];
        $rows = $sheet->rangeToArray("A{$startRow}:{$highestCol}{$highestRow}", null, false, false, true);

        $nameCol = $cols['name'] ?? 'C';
        $codeCol = $cols['code'] ?? 'B';
        $categoryCol = $cols['category'] ?? 'AL';

        foreach ($rows as $rowIndex => $row) {
            $name = trim((string) ($row[$nameCol] ?? ''));
            if ($name === '') {
                continue;
            }

            $summary['total_rows']++;
            $code = trim((string) ($row[$codeCol] ?? ''));
            $categoryName = trim((string) ($row[$categoryCol] ?? ''));
            $categoryId = $this->resolveCategoryId($categoryName, $categoryMap);
            $codeKey = strtoupper($code);
            $exists = $code !== '' && ($existingCodes->has($codeKey) || isset($seenCodes[$codeKey]));
            if ($code !== '') {
                $seenCodes[$codeKey] = true;
            }

            if ($categoryName !== '' && $categoryId === null) {
                $summary['skipped_count']++;
                if (count($summary['skipped_samples']) < 10) {
                    $summary['skipped_samples'][] = [
                        'row' => $rowIndex,
                        'reason' => "Golongan '{$categoryName}' tidak ditemukan di medicine_categories.",
                    ];
                }
                continue;
            }

            if ($categoryId === null && !$exists && empty($categoryMap)) {
                $summary['skipped_count']++;
                if (count($summary['skipped_samples']) < 10) {
                    $summary['skipped_samples'][] = [
                        'row' => $rowIndex,
                        'reason' => 'Tidak ada golongan obat di medicine_categories untuk digunakan.',
                    ];
                }
                continue;
            }

            $summary[$exists ? 'updated_count' : 'created_count']++;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $summary;
    }

    /**
     * Import medicines master from Excel file.
     *
     * Expected columns (Row 2 onwards):
     * B: Code barang
     * C: Name (Nama obat)
     * O: HNA (raw_price)
     * P: HET (het_price)
     * Q: Barcode (read as formatted text to preserve leading zeroes)
     * R: PBF (Distributor/Creditor -> medicine_creditors)
     * AC: Surat Pesanan (type)
     * AE: Isi (content)
     * AF: Strip
     * AG: Dosis (dosage)
     * AH: Pabrik (factory)
     * AI: Kemasan (packaging)
     * AJ: Satuan (unit)
     * AK: Sediaan (preparations)
     * AL: Golongan (medicine_category)
     * AN: Komposisi (composition)
     *
     * @param string $filePath
     * @return array
     */
    public function import(string $filePath): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = (int) $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $highestColIdx = Coordinate::columnIndexFromString($highestCol);

        // Ensure we read at least up to column AN (column 40)
        if ($highestColIdx < 40) {
            $highestCol = 'AN';
        }

        $mapping = $this->resolveColumnMapping($sheet, $highestRow, $highestCol);
        $startRow = $mapping['start_row'];
        $cols = $mapping['columns'];

        if ($highestRow < $startRow) {
            return [
                'success' => false,
                'message' => 'File Excel kosong atau tidak memiliki data pada baris ' . $startRow . '.',
                'total_rows' => 0,
                'created_count' => 0,
                'updated_count' => 0,
                'skipped_count' => 0,
                'skipped_rows' => [],
            ];
        }

        // 1. Preload master tables into in-memory maps for 0 N+1 overhead
        $factoryMap = $this->buildFactoryMap();
        $categoryMap = $this->buildCategoryMap();
        $compositionMap = $this->buildCompositionMap();
        $creditorMap = $this->buildCreditorMap();

        // Preload existing medicines by uppercase trimmed code
        $existingMedicines = Medicines::all()->keyBy(function ($item) {
            return strtoupper(trim((string) $item->code));
        });

        // 2. Read rows with column letter keys
        $rows = $sheet->rangeToArray("A{$startRow}:{$highestCol}{$highestRow}", null, false, false, true);

        $totalRows = 0;
        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $skippedRows = [];

        $nameCol = $cols['name'] ?? 'C';
        $codeCol = $cols['code'] ?? 'B';
        $barcodeCol = $cols['barcode'] ?? 'Q';
        $genericCol = $cols['generic'] ?? null;
        $rawPriceCol = $cols['raw_price'] ?? 'O';
        $hetCol = $cols['het_price'] ?? 'P';
        $netPriceCol = $cols['net_price'] ?? null;
        $pbfCol = $cols['creditor'] ?? 'R';
        $typeCol = $cols['type'] ?? 'AC';
        $contentCol = $cols['content'] ?? 'AE';
        $stripCol = $cols['strip'] ?? 'AF';
        $dosageCol = $cols['dosage'] ?? 'AG';
        $factoryCol = $cols['factory'] ?? 'AH';
        $packagingCol = $cols['packaging'] ?? 'AI';
        $unitCol = $cols['unit'] ?? 'AJ';
        $prepCol = $cols['preparations'] ?? 'AK';
        $categoryCol = $cols['category'] ?? 'AL';
        $compCol = $cols['composition'] ?? 'AN';
        $componentCol = $cols['component'] ?? null;
        $minStockCol = $cols['minimal_stock'] ?? null;

        $targetPharmacyId = getActivePharmacyId() ?: 1;

        DB::beginTransaction();
        try {
            foreach ($rows as $rowIndex => $row) {
                $name = trim((string) ($row[$nameCol] ?? ''));

                // If name is empty, skip row
                if ($name === '') {
                    continue;
                }

                $totalRows++;

                $rawCode = trim((string) ($row[$codeCol] ?? ''));
                $barcode = '';
                if ($barcodeCol) {
                    $barcode = trim((string) $sheet->getCell("{$barcodeCol}{$rowIndex}")->getFormattedValue());
                    if ($barcode === '' && isset($row[$barcodeCol])) {
                        $barcode = trim((string) $row[$barcodeCol]);
                    }
                }
                $hna = $this->parsePrice($row[$rawPriceCol] ?? null);
                $het = $this->parsePrice($row[$hetCol] ?? null);
                $providedNetPrice = $netPriceCol ? $this->parsePrice($row[$netPriceCol] ?? null) : 0.0;
                $netPrice = $providedNetPrice > 0 ? (string) $providedNetPrice : (string) floor($hna * 1.11);

                $pbfName = $pbfCol ? trim((string) ($row[$pbfCol] ?? '')) : '';
                $generic = $genericCol ? trim((string) ($row[$genericCol] ?? '')) : '';
                $spType = $typeCol ? trim((string) ($row[$typeCol] ?? '')) : '';
                $content = $contentCol ? trim((string) ($row[$contentCol] ?? '')) : '';
                $strip = $stripCol ? trim((string) ($row[$stripCol] ?? '')) : '';
                $dosage = $dosageCol ? trim((string) ($row[$dosageCol] ?? '')) : '';
                $factoryName = $factoryCol ? trim((string) ($row[$factoryCol] ?? '')) : '';
                $packaging = $packagingCol ? trim((string) ($row[$packagingCol] ?? '')) : '';
                $unit = $unitCol ? trim((string) ($row[$unitCol] ?? '')) : '';
                $preparations = $prepCol ? trim((string) ($row[$prepCol] ?? '')) : '';
                $categoryName = $categoryCol ? trim((string) ($row[$categoryCol] ?? '')) : '';
                $compositionName = $compCol ? trim((string) ($row[$compCol] ?? '')) : '';
                $component = $componentCol ? trim((string) ($row[$componentCol] ?? '')) : null;
                $minimalStock = $minStockCol ? (float) $this->parsePrice($row[$minStockCol] ?? null) : 0;

                // Resolve Foreign Keys
                $factoryId = $this->resolveFactoryId($factoryName, $factoryMap);
                $categoryId = $this->resolveCategoryId($categoryName, $categoryMap);
                $compositionId = $this->resolveCompositionId($compositionName, $compositionMap);
                $creditor = $this->resolveCreditor($pbfName, $creditorMap);

                // Code generation if blank
                if ($rawCode === '') {
                    $code = Medicines::generateCode();
                } else {
                    $code = $rawCode;
                }

                $codeKey = strtoupper($code);
                $isExisting = $existingMedicines->has($codeKey);

                // Never silently assign a medicine to an unrelated/default category.
                if ($categoryName !== '' && $categoryId === null) {
                    $skippedCount++;
                    $skippedRows[] = [
                        'row' => $rowIndex,
                        'reason' => "Golongan '{$categoryName}' tidak ditemukan di medicine_categories.",
                    ];
                    continue;
                }

                if ($categoryId === null && $isExisting) {
                    $categoryId = $existingMedicines->get($codeKey)->medicine_category_id;
                }

                if ($categoryId === null) {
                    $firstCategory = reset($categoryMap);
                    $categoryId = $firstCategory ? $firstCategory->id : null;
                }

                if ($categoryId === null) {
                    $skippedCount++;
                    $skippedRows[] = [
                        'row' => $rowIndex,
                        'reason' => 'Tidak ada golongan obat di medicine_categories untuk digunakan.',
                    ];
                    continue;
                }

                $payload = [
                    'code'                 => $code,
                    'name'                 => $name,
                    'barcode'              => $barcode !== '' ? $barcode : null,
                    'pharmacy_id'          => $targetPharmacyId,
                    'medicine_category_id' => $categoryId,
                    'composition_id'       => $compositionId,
                    'factory_id'           => $factoryId,
                    'creditors_id'         => $creditor ? $creditor->id : null,
                    'packaging'            => $packaging !== '' ? $packaging : null,
                    'unit'                 => $unit !== '' ? $unit : null,
                    'content'              => $content !== '' ? $content : '1',
                    'dosage'               => $dosage !== '' ? $dosage : null,
                    'strip'                => $strip !== '' ? $strip : null,
                    'preparations'         => $preparations !== '' ? $preparations : null,
                    'type'                 => $spType !== '' ? $spType : '0',
                    'raw_price'            => (string) $hna,
                    'pharmacy_net_price'   => (string) $hna,
                    'net_price'            => $netPrice,
                    'het_price'            => (string) $het,
                    'minimal_stock'        => $minimalStock,
                    'status'               => 1,
                ];

                if ($generic !== '') {
                    $payload['generic'] = $generic;
                }

                if ($component !== null && $component !== '') {
                    $payload['component'] = $component;
                }

                if ($isExisting) {
                    $medicine = $existingMedicines->get($codeKey);
                    $medicine->update($payload);
                    $updatedCount++;
                } else {
                    $payload['stock'] = 0;
                    $payload['psychotropic'] = 0;
                    $payload['whole'] = 0;
                    $payload['precursor'] = 0;
                    $payload['receipt'] = 0;
                    $payload['etalase'] = 0;
                    $payload['location'] = 0;
                    $medicine = Medicines::create($payload);
                    $existingMedicines->put($codeKey, $medicine);
                    $createdCount++;
                }

                // Synchronize PBF / Creditor in medicine_creditors
                if ($creditor) {
                    MedicineCreditor::firstOrCreate(
                        [
                            'medicine_id'   => $medicine->id,
                            'creditor_code' => $creditor->code,
                        ],
                        [
                            'discount' => 0,
                            'status'   => 1,
                        ]
                    );
                }
            }

            DB::commit();

            return [
                'success'       => true,
                'message'       => "Import selesai: {$createdCount} obat baru ditambahkan, {$updatedCount} obat diperbarui.",
                'total_rows'    => $totalRows,
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
                'skipped_rows'  => $skippedRows,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("MedicineImportService failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success'       => false,
                'message'       => 'Terjadi kesalahan saat memproses import obat: ' . $e->getMessage(),
                'total_rows'    => $totalRows,
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
                'skipped_rows'  => $skippedRows,
            ];
        }
    }

    /**
     * Parse raw price strings safely to float.
     */
    private function parsePrice($val): float
    {
        if ($val === null || $val === '') {
            return 0.0;
        }

        if (is_numeric($val)) {
            return (float) $val;
        }

        $str = (string) $val;
        $cleaned = preg_replace('/[^\d.]/', '', str_replace(',', '.', $str));

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }

    /**
     * Preload factories into uppercase map.
     */
    private function buildFactoryMap(): array
    {
        $map = [];
        foreach (Factory::all() as $f) {
            $key = mb_strtoupper(trim((string) $f->name));
            if ($key !== '') {
                $map[$key] = $f;
            }
        }
        return $map;
    }

    /**
     * Resolve factory ID, auto-creating if not found.
     */
    private function resolveFactoryId(string $name, array &$map): int
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            $first = reset($map);
            return $first ? $first->id : 1;
        }

        $key = mb_strtoupper($trimmed);
        if (isset($map[$key])) {
            return $map[$key]->id;
        }

        // Auto-create factory
        $maxId = Factory::max('id') ?? 0;
        $newCode = str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);
        $newFactory = Factory::create([
            'code'   => $newCode,
            'name'   => $trimmed,
            'status' => 1,
        ]);
        $map[$key] = $newFactory;

        return $newFactory->id;
    }

    /**
     * Preload medicine categories.
     */
    private function buildCategoryMap(): array
    {
        $map = [];
        foreach (MedicineCategory::all() as $c) {
            foreach ([$c->name, $c->code] as $label) {
                $key = $this->normalizeLookupKey((string) $label);
                if ($key !== '') {
                    $map[$key] = $c;
                }
            }
        }
        return $map;
    }

    /**
     * Resolve category ID only from the existing medicine_categories table.
     */
    private function resolveCategoryId(string $name, array &$map): ?int
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }

        $category = $map[$this->normalizeLookupKey($trimmed)] ?? null;
        if ($category) {
            return $category->id;
        }

        // Try fuzzy or prefix match (e.g. "OBAT BEBAS/OTC (B)" vs "OBAT BEBAS")
        $cleanNorm = $this->normalizeLookupKey($trimmed);
        foreach ($map as $key => $cat) {
            if ($key !== '' && (str_contains($cleanNorm, $key) || str_contains($key, $cleanNorm))) {
                return $cat->id;
            }
        }

        return null;
    }

    /**
     * Detect header row and map column letters dynamically.
     * Supports both modern exported header names (Row 1 or Row 5, etc.) and legacy fixed columns.
     */
    public function resolveColumnMapping($sheet, int $highestRow, string $highestCol): array
    {
        $maxScanRows = min($highestRow, 10);
        $highestColIdx = Coordinate::columnIndexFromString($highestCol);

        $fieldPatterns = [
            'name'          => ['nama obat', 'nama barang', 'nama', 'name', 'item name'],
            'code'          => ['kode obat', 'kode barang', 'kode', 'code', 'item code'],
            'barcode'       => ['barcode', 'kode barcode', 'upc', 'ean'],
            'generic'       => ['generik', 'nama generik', 'generic'],
            'category'      => ['kategori', 'golongan', 'golongan obat', 'kategori obat', 'category'],
            'raw_price'     => ['hna', 'harga dasar (beli)', 'harga dasar', 'harga beli', 'hna (netto apotek)', 'beli', 'buy price'],
            'het_price'     => ['het', 'harga het', 'h.e.t'],
            'net_price'     => ['harga jual netto', 'harga netto', 'harga jual', 'net price'],
            'creditor'      => ['supplier utama', 'supplier', 'kreditur', 'distributor', 'pbf', 'vendor'],
            'composition'   => ['komposisi', 'zat aktif'],
            'component'     => ['komponen sp', 'komponen'],
            'factory'       => ['pabrik (produsen)', 'pabrik', 'produsen', 'factory', 'manufacturer'],
            'packaging'     => ['kemasan', 'packaging', 'package'],
            'unit'          => ['satuan', 'unit'],
            'content'       => ['isi', 'content'],
            'strip'         => ['strip'],
            'dosage'        => ['dosis', 'dosage'],
            'preparations'  => ['sediaan', 'preparations'],
            'type'          => ['surat pesanan', 'tipe', 'sp type'],
            'minimal_stock' => ['stok minimal', 'minimal stok', 'min stok', 'minimum stock'],
        ];

        for ($row = 1; $row <= $maxScanRows; $row++) {
            $mapped = [];

            // Pass 1: Exact normalized matching
            for ($colIdx = 1; $colIdx <= $highestColIdx; $colIdx++) {
                $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                $cellVal = trim((string) $sheet->getCell("{$colLetter}{$row}")->getValue());
                if ($cellVal === '') continue;

                $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $cellVal));

                foreach ($fieldPatterns as $field => $patterns) {
                    if (isset($mapped[$field])) continue;

                    foreach ($patterns as $pattern) {
                        $normPattern = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $pattern));
                        if ($normalized === $normPattern) {
                            $mapped[$field] = $colLetter;
                            break;
                        }
                    }
                }
            }

            // Pass 2: Partial contains for unmapped columns (avoid short substrings like 'isi' inside 'komposisi')
            for ($colIdx = 1; $colIdx <= $highestColIdx; $colIdx++) {
                $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                if (in_array($colLetter, $mapped, true)) continue;

                $cellVal = trim((string) $sheet->getCell("{$colLetter}{$row}")->getValue());
                if ($cellVal === '') continue;

                $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $cellVal));

                foreach ($fieldPatterns as $field => $patterns) {
                    if (isset($mapped[$field])) continue;

                    foreach ($patterns as $pattern) {
                        $normPattern = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $pattern));
                        if (strlen($normPattern) >= 4 && str_contains($normalized, $normPattern)) {
                            $mapped[$field] = $colLetter;
                            break;
                        }
                    }
                }
            }

            // If this row has both 'name' and at least one of ('code', 'barcode', 'category', 'raw_price')
            if (isset($mapped['name']) && (isset($mapped['code']) || isset($mapped['category']) || isset($mapped['barcode']) || isset($mapped['raw_price']))) {
                return [
                    'header_row' => $row,
                    'start_row'  => $row + 1,
                    'columns'    => $mapped,
                    'is_header_detected' => true,
                ];
            }
        }

        // Fallback to legacy fixed column layout (Row 2 onwards)
        return [
            'header_row' => 1,
            'start_row'  => 2,
            'columns'    => [
                'code'         => 'B',
                'name'         => 'C',
                'raw_price'    => 'O',
                'het_price'    => 'P',
                'barcode'      => 'Q',
                'creditor'     => 'R',
                'type'         => 'AC',
                'content'      => 'AE',
                'strip'        => 'AF',
                'dosage'       => 'AG',
                'factory'      => 'AH',
                'packaging'    => 'AI',
                'unit'         => 'AJ',
                'preparations' => 'AK',
                'category'     => 'AL',
                'composition'  => 'AN',
            ],
            'is_header_detected' => false,
        ];
    }

    private function normalizeLookupKey(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        return mb_strtoupper($value);
    }

    /**
     * Preload compositions.
     */
    private function buildCompositionMap(): array
    {
        $map = [];
        foreach (Composition::all() as $cp) {
            $key = mb_strtoupper(trim((string) $cp->name));
            if ($key !== '') {
                $map[$key] = $cp;
            }
        }
        return $map;
    }

    /**
     * Resolve composition ID.
     */
    private function resolveCompositionId(string $name, array &$map): int
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            // Find default "BELUM DI TENTUKAN" or first
            if (isset($map['BELUM DI TENTUKAN'])) {
                return $map['BELUM DI TENTUKAN']->id;
            }
            $first = reset($map);
            return $first ? $first->id : 1;
        }

        $key = mb_strtoupper($trimmed);
        if (isset($map[$key])) {
            return $map[$key]->id;
        }

        // Auto-create composition
        $maxId = Composition::max('id') ?? 0;
        $newCode = str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);
        $newComp = Composition::create([
            'code'   => $newCode,
            'name'   => $trimmed,
            'status' => 1,
        ]);
        $map[$key] = $newComp;

        return $newComp->id;
    }

    /**
     * Preload creditors.
     */
    private function buildCreditorMap(): array
    {
        $map = [
            'exact' => [],
            'clean' => [],
        ];

        foreach (Creditor::all() as $cr) {
            $exactKey = mb_strtoupper(trim((string) $cr->name));
            if ($exactKey !== '') {
                $map['exact'][$exactKey] = $cr;
                $clean = preg_replace('/[^A-Z0-9]/', '', $exactKey);
                $clean = str_replace(['PT', 'CV', 'TBK'], '', $clean);
                if ($clean !== '') {
                    $map['clean'][$clean] = $cr;
                }
            }
        }

        return $map;
    }

    /**
     * Resolve Creditor (PBF), auto-creating if not found.
     */
    private function resolveCreditor(string $name, array &$map): ?Creditor
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }

        $exactKey = mb_strtoupper($trimmed);
        if (isset($map['exact'][$exactKey])) {
            return $map['exact'][$exactKey];
        }

        $clean = preg_replace('/[^A-Z0-9]/', '', $exactKey);
        $clean = str_replace(['PT', 'CV', 'TBK'], '', $clean);
        if (isset($map['clean'][$clean])) {
            return $map['clean'][$clean];
        }

        // Auto-create Creditor
        $count = Creditor::count();
        $code = 'KR' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        while (Creditor::where('code', $code)->exists()) {
            $code = 'KR' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        }

        $newCreditor = Creditor::create([
            'code'        => $code,
            'name'        => $trimmed,
            'address'     => '-',
            'numbers'     => '-',
            'bank_type'   => '-',
            'bank_number' => '-',
            'bank_name'   => '-',
            'npwp'        => '-',
            'credit_time' => '30',
            'status'      => 1,
        ]);

        $map['exact'][$exactKey] = $newCreditor;
        if ($clean !== '') {
            $map['clean'][$clean] = $newCreditor;
        }

        return $newCreditor;
    }
}
