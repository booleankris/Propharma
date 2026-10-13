<?php

namespace App\Console\Commands;

use App\Models\MedicineCategory;
use App\Models\Medicines;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UpdateMedicinesSelective extends Command
{
    protected $signature = 'medicines:update-selective
                            {--file= : Path to the Excel file, relative to the application root or absolute}
                            {--dry-run : Show the summary without writing changes}';

    protected $description = 'Update selected medicine fields by code, skipping blank cells and showing a summary before applying changes.';

    private const FIELD_COLUMNS = [
        'name' => 'B',
        'raw_price' => 'C',
        'het_price' => 'D',
        'barcode' => 'E',
        'content' => 'F',
        'strip' => 'G',
        'dosage' => 'H',
        'packaging' => 'W',
        'unit' => 'X',
        'preparations' => 'Y',
        'medicine_category_id' => 'Z',
    ];

    public function handle(): int
    {
        $file = trim((string) $this->option('file'));
        if ($file === '') {
            $this->error('Tentukan file Excel dengan --file=PATH.');
            return self::FAILURE;
        }

        $path = str_starts_with($file, DIRECTORY_SEPARATOR) ? $file : base_path($file);
        if (!is_file($path) || !is_readable($path)) {
            $this->error("File tidak ditemukan atau tidak bisa dibaca: {$path}");
            return self::FAILURE;
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadEmptyCells(false);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $lastRow = (int) $sheet->getHighestRow();
            $fieldColumns = $this->resolveFieldColumns($sheet);
            $codeColumn = $fieldColumns['__code'] ?? 'A';
            unset($fieldColumns['__code']);

            if ($lastRow < 2) {
                $this->error('File tidak memiliki data mulai baris 2.');
                return self::FAILURE;
            }

            $medicines = Medicines::query()->get()->keyBy(
                fn (Medicines $medicine) => $this->normalizeCode((string) $medicine->code)
            );
            $categories = MedicineCategory::query()->get(['id', 'code', 'name']);
            $categoryLookup = [];
            $categoryNames = [];
            foreach ($categories as $category) {
                foreach ([$category->id, $category->code, $category->name] as $label) {
                    $key = $this->normalizeLabel((string) $label);
                    if ($key !== '') {
                        $categoryLookup[$key] = (int) $category->id;
                    }
                }
                $categoryNames[(int) $category->id] = $category->name;
            }

            $updates = [];
            $newCategories = [];
            $summary = [
                'rows_with_code' => 0,
                'matched' => 0,
                'rows_to_update' => 0,
                'not_found' => [],
                'fuzzy_categories' => [],
                'duplicate_codes' => [],
                'error_cells' => [],
                'fields' => array_fill_keys(array_merge(array_keys(self::FIELD_COLUMNS), ['net_price', 'pharmacy_net_price']), 0),
            ];
            $seenCodes = [];

            for ($rowNumber = 2; $rowNumber <= $lastRow; $rowNumber++) {
                $code = trim((string) $sheet->getCell("{$codeColumn}{$rowNumber}")->getFormattedValue());
                if ($code === '') {
                    continue;
                }
                $summary['rows_with_code']++;

                $codeKey = $this->normalizeCode($code);
                if (isset($seenCodes[$codeKey])) {
                    $summary['duplicate_codes'][] = "Baris {$rowNumber}: {$code}";
                    continue;
                }
                $seenCodes[$codeKey] = true;

                /** @var Medicines|null $medicine */
                $medicine = $medicines->get($codeKey);
                if (!$medicine) {
                    $summary['not_found'][] = "Baris {$rowNumber}: {$code}";
                    continue;
                }
                $summary['matched']++;

                $payload = [];
                $hnaSupplied = null;
                foreach ($fieldColumns as $attribute => $column) {
                    $cell = $sheet->getCell("{$column}{$rowNumber}");
                    if ($cell->getDataType() === DataType::TYPE_ERROR) {
                        $summary['error_cells'][] = "{$column}{$rowNumber}: {$cell->getFormattedValue()}";
                        continue;
                    }
                    $value = $attribute === 'barcode'
                        ? trim((string) $cell->getFormattedValue())
                        : $cell->getValue();

                    if ($this->isBlankCellValue($value)) {
                        continue;
                    }

                    if ($attribute === 'medicine_category_id') {
                        [$categoryId, $fuzzyCategoryId] = $this->resolveCategory(
                            (string) $value,
                            $categoryLookup,
                            $categoryNames
                        );
                        if ($categoryId === null) {
                            $categoryKey = $this->normalizeLabel((string) $value);
                            if ($categoryKey === '') {
                                continue;
                            }
                            if (!isset($newCategories[$categoryKey])) {
                                $newCategories[$categoryKey] = [
                                    'name' => $this->cleanCategoryName((string) $value),
                                    'count' => 0,
                                ];
                            }
                            $newCategories[$categoryKey]['count']++;
                            $payload['__new_category_key'] = $categoryKey;
                            continue;
                        }
                        if ($fuzzyCategoryId !== null) {
                            $summary['fuzzy_categories'][] = "{$value} → " . ($categoryNames[$fuzzyCategoryId] ?? (string) $fuzzyCategoryId);
                        }
                        $value = $categoryId;
                    } elseif (in_array($attribute, ['raw_price', 'het_price'], true)) {
                        $value = $this->parseAmount($value);
                        if ($value === null) {
                            $this->error("Nilai harga tidak valid pada {$column}{$rowNumber}.");
                            $spreadsheet->disconnectWorksheets();
                            return self::FAILURE;
                        }
                        if ($attribute === 'raw_price') {
                            $hnaSupplied = $value;
                        }
                    } elseif (in_array($attribute, ['content', 'strip'], true)) {
                        $count = $this->parseCount($value);
                        if ($count === null) {
                            $this->error("Nilai {$attribute} harus angka pada {$column}{$rowNumber}.");
                            $spreadsheet->disconnectWorksheets();
                            return self::FAILURE;
                        }
                        $value = $count;
                    } else {
                        $value = trim((string) $value);
                    }

                    $currentValue = $medicine->{$attribute};
                    if ((string) ($currentValue ?? '') !== (string) $value) {
                        $payload[$attribute] = $value;
                    }
                }

                if ($hnaSupplied !== null) {
                    $derivedPrices = [
                        'net_price' => (int) round($hnaSupplied * 1.11),
                        'pharmacy_net_price' => $hnaSupplied,
                    ];
                    foreach ($derivedPrices as $attribute => $value) {
                        if ((string) ($medicine->{$attribute} ?? '') !== (string) $value) {
                            $payload[$attribute] = $value;
                        }
                    }
                }

                if ($payload !== []) {
                    $updates[$medicine->id] = $payload;
                    $summary['rows_to_update']++;
                    foreach ($payload as $field => $_value) {
                        if ($field === '__new_category_key') {
                            $summary['fields']['medicine_category_id']++;
                        } elseif (array_key_exists($field, $summary['fields'])) {
                            $summary['fields'][$field]++;
                        }
                    }
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $this->info('Ringkasan pembaruan master obat');
            $this->table(['Ringkasan', 'Jumlah'], [
                ['Baris dengan kode barang', $summary['rows_with_code']],
                ['Kode cocok dengan master obat', $summary['matched']],
                ['Obat yang akan berubah', $summary['rows_to_update']],
                ['Kode tidak ditemukan', count($summary['not_found'])],
                ['Golongan cocok setelah normalisasi/typo', count($summary['fuzzy_categories'])],
                ['Kategori baru yang akan dibuat', count($newCategories)],
                ['Kode duplikat di file', count($summary['duplicate_codes'])],
                ['Sel error Excel dilewati', count($summary['error_cells'])],
            ]);

            $fieldRows = [];
            $fieldLabels = [
                'raw_price' => 'raw_price (HNA)',
                'het_price' => 'het_price (HET)',
                'net_price' => 'net_price (HNA + PPN 11%)',
                'pharmacy_net_price' => 'pharmacy_net_price (HNA)',
            ];
            foreach ($summary['fields'] as $field => $count) {
                if ($count > 0) {
                    $fieldRows[] = [$fieldLabels[$field] ?? $field, $count];
                }
            }
            $this->table(['Kolom yang berubah', 'Jumlah obat'], $fieldRows);

            foreach ([
                'not_found' => 'Kode tidak ditemukan',
                'fuzzy_categories' => 'Golongan dicocokkan ke kategori terdekat',
                'duplicate_codes' => 'Kode duplikat',
                'error_cells' => 'Sel error Excel dilewati (tidak mengubah nilai database)',
            ] as $key => $label) {
                if ($summary[$key] !== []) {
                    $this->warn($label . ' (contoh maksimal 10):');
                    foreach (array_slice($summary[$key], 0, 10) as $sample) {
                        $this->line(" - {$sample}");
                    }
                }
            }

            if ($newCategories !== []) {
                $this->warn('Kategori baru yang akan dibuat (maksimal 20):');
                foreach (array_slice($newCategories, 0, 20, true) as $category) {
                    $this->line(" - {$category['name']} ({$category['count']} obat)");
                }
            }

            if ($updates === []) {
                $this->info('Tidak ada perubahan yang perlu disimpan.');
                return self::SUCCESS;
            }

            if ($this->option('dry-run')) {
                $this->warn('Dry-run: database tidak diubah.');
                return self::SUCCESS;
            }

            $environmentWarning = app()->environment('production') ? ' PRODUCTION' : '';
            if (!$this->confirm("Terapkan pembaruan{$environmentWarning} untuk {$summary['rows_to_update']} obat?", false)) {
                $this->info('Dibatalkan. Database tidak diubah.');
                return self::SUCCESS;
            }

            DB::transaction(function () use ($updates, $newCategories): void {
                $newCategoryIds = [];
                foreach ($newCategories as $categoryKey => $categoryData) {
                    $category = MedicineCategory::query()->where('name', $categoryData['name'])->first();
                    if (!$category) {
                        $category = MedicineCategory::query()->create([
                            'code' => $this->generateCategoryCode(),
                            'name' => $categoryData['name'],
                            'status' => 0,
                        ]);
                    }
                    $newCategoryIds[$categoryKey] = (int) $category->id;
                }

                foreach ($updates as $medicineId => $payload) {
                    if (isset($payload['__new_category_key'])) {
                        $payload['medicine_category_id'] = $newCategoryIds[$payload['__new_category_key']];
                        unset($payload['__new_category_key']);
                    }
                    if (array_key_exists('raw_price', $payload)) {
                        $hna = (float) $payload['raw_price'];
                        $payload['net_price'] = (int) round($hna * 1.11);
                        $payload['pharmacy_net_price'] = $hna;
                    }
                    Medicines::query()->whereKey($medicineId)->update($payload);
                }
            });

            $this->info("Selesai. {$summary['rows_to_update']} obat diperbarui.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error('Gagal membaca atau memproses file: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function normalizeCode(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    private function normalizeLabel(string $value): string
    {
        $value = trim(preg_replace('/[\s\x{00a0}\x{200b}\x{FEFF}]+/u', ' ', $value) ?? $value);
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }
        $value = mb_strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function resolveCategory(string $value, array $categoryLookup, array $categoryNames): array
    {
        $normalized = $this->normalizeLabel($value);
        if (isset($categoryLookup[$normalized])) {
            return [$categoryLookup[$normalized], null];
        }

        $target = str_replace(' ', '', $normalized);
        $bestDistance = PHP_INT_MAX;
        $bestId = null;
        $ambiguous = false;

        foreach ($categoryNames as $id => $name) {
            $candidate = str_replace(' ', '', $this->normalizeLabel((string) $name));
            if ($candidate === '' || $target === '') {
                continue;
            }

            $distance = levenshtein($target, $candidate);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestId = (int) $id;
                $ambiguous = false;
            } elseif ($distance === $bestDistance) {
                $ambiguous = true;
            }
        }

        $threshold = max(1, (int) floor(strlen($target) * 0.08));
        if ($bestId !== null && !$ambiguous && $bestDistance <= $threshold) {
            return [$bestId, $bestId];
        }

        return [null, null];
    }

    private function cleanCategoryName(string $value): string
    {
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', trim($value)) ?? trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    private function generateCategoryCode(): string
    {
        $last = MedicineCategory::query()->orderByDesc('id')->lockForUpdate()->first();
        $lastCode = (string) ($last?->code ?? '');
        $next = (int) $lastCode + 1;
        $width = max(strlen($lastCode), 2);

        do {
            $code = str_pad((string) $next++, $width, '0', STR_PAD_LEFT);
        } while (MedicineCategory::query()->where('code', $code)->exists());

        return $code;
    }

    private function resolveFieldColumns($sheet): array
    {
        $aliases = [
            'code' => ['KODE BARANG', 'CODE BARANG', 'KODE', 'CODE'],
            'name' => ['NAMA BARANG', 'NAMA OBAT', 'NAME'],
            'raw_price' => ['HNA', 'HNA BARU', 'HNA BARUU', 'RAW PRICE'],
            'het_price' => ['HET', 'HET PRICE'],
            'barcode' => ['BARCODE', 'KODE BARCODE'],
            'content' => ['ISI BOX', 'ISI/BOX', 'CONTENT'],
            'strip' => ['ISI STRIP', 'ISI/STRIP', 'STRIP'],
            'dosage' => ['DOSIS', 'DOSAGE'],
            'packaging' => ['KEMASAN', 'PACKAGING'],
            'unit' => ['SATUAN', 'UNIT'],
            'preparations' => ['SEDIAAN', 'PREPARATIONS'],
            'medicine_category_id' => ['GOLONGAN', 'MEDICINE CATEGORY ID', 'MEDICINE CATEGORY'],
        ];
        $resolved = self::FIELD_COLUMNS;
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $headerMap = [];

        for ($columnIndex = 1; $columnIndex <= $highestColumn; $columnIndex++) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $header = $this->normalizeLabel((string) $sheet->getCell("{$column}1")->getFormattedValue());
            if ($header !== '') {
                $headerMap[$header] = $column;
            }
        }

        foreach ($aliases as $attribute => $labels) {
            foreach ($labels as $label) {
                $column = $headerMap[$this->normalizeLabel($label)] ?? null;
                if ($column !== null) {
                    if ($attribute === 'code') {
                        $resolved['__code'] = $column;
                    } else {
                        $resolved[$attribute] = $column;
                    }
                    break;
                }
            }
        }

        return $resolved;
    }

    private function isBlankCellValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        $text = trim((string) $value);
        return $text === '' || in_array(mb_strtoupper($text), ['-', '—', '–', 'N/A', 'NA', 'N.A.'], true);
    }

    private function parseAmount(mixed $value): ?float
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            return (float) $value;
        }

        $value = preg_replace('/[^0-9,.\-]/u', '', trim((string) $value)) ?? '';
        if ($value === '' || !preg_match('/\d/', $value)) {
            return null;
        }

        $comma = strrpos($value, ',');
        $dot = strrpos($value, '.');
        if ($comma !== false && $dot !== false) {
            $decimalSeparator = $comma > $dot ? ',' : '.';
            $thousandsSeparator = $decimalSeparator === ',' ? '.' : ',';
            $value = str_replace($thousandsSeparator, '', $value);
            $value = str_replace($decimalSeparator, '.', $value);
        } elseif ($comma !== false) {
            $decimals = strlen($value) - $comma - 1;
            $value = $decimals > 0 && $decimals <= 2
                ? str_replace(',', '.', $value)
                : str_replace(',', '', $value);
        } elseif ($dot !== false) {
            $decimals = strlen($value) - $dot - 1;
            if ($decimals === 3) {
                $value = str_replace('.', '', $value);
            }
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function parseCount(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        $text = trim((string) $value);
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(?:\/\s*BOTOL)?$/iu', $text, $matches)) {
            return (int) round((float) str_replace(',', '.', $matches[1]));
        }

        return null;
    }
}
