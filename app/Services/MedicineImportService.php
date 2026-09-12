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
     * Import medicines master from Excel file.
     *
     * Expected columns (Row 2 onwards):
     * B: Code barang
     * C: Name (Nama obat)
     * O: HNA (raw_price)
     * P: HET (het_price)
     * Q: Barcode
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

        if ($highestRow < 2) {
            return [
                'success' => false,
                'message' => 'File Excel kosong atau tidak memiliki data pada baris 2.',
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

        // 2. Read rows with column letter keys (B, C, O, P, etc.)
        $rows = $sheet->rangeToArray("A2:{$highestCol}{$highestRow}", null, false, false, true);

        $totalRows = 0;
        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $skippedRows = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $rowIndex => $row) {
                $name = trim((string) ($row['C'] ?? ''));

                // If name is empty, skip row
                if ($name === '') {
                    continue;
                }

                $totalRows++;

                $rawCode = trim((string) ($row['B'] ?? ''));
                $barcode = trim((string) ($row['Q'] ?? ''));
                $hna = $this->parsePrice($row['O'] ?? null);
                $het = $this->parsePrice($row['P'] ?? null);
                $pbfName = trim((string) ($row['R'] ?? ''));
                $spType = trim((string) ($row['AC'] ?? ''));
                $content = trim((string) ($row['AE'] ?? ''));
                $strip = trim((string) ($row['AF'] ?? ''));
                $dosage = trim((string) ($row['AG'] ?? ''));
                $factoryName = trim((string) ($row['AH'] ?? ''));
                $packaging = trim((string) ($row['AI'] ?? ''));
                $unit = trim((string) ($row['AJ'] ?? ''));
                $preparations = trim((string) ($row['AK'] ?? ''));
                $categoryName = trim((string) ($row['AL'] ?? ''));
                $compositionName = trim((string) ($row['AN'] ?? ''));

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

                $netPrice = $hna > 0 ? (string) floor($hna * 1.11) : '0';

                $payload = [
                    'code'                 => $code,
                    'name'                 => $name,
                    'barcode'              => $barcode !== '' ? $barcode : null,
                    'pharmacy_id'          => 1,
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
                    'minimal_stock'        => 0,
                    'status'               => 1,
                ];

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
            $key = mb_strtoupper(trim((string) $c->name));
            if ($key !== '') {
                $map[$key] = $c;
            }
        }
        return $map;
    }

    /**
     * Resolve category ID.
     */
    private function resolveCategoryId(string $name, array &$map): int
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

        // Check partial match (e.g. "OBAT KERAS" matches "OBAT KERAS (G)")
        foreach ($map as $catName => $catModel) {
            if (str_contains($catName, $key) || str_contains($key, $catName)) {
                return $catModel->id;
            }
        }

        // Auto-create category
        $maxId = MedicineCategory::max('id') ?? 0;
        $newCode = str_pad($maxId + 1, 2, '0', STR_PAD_LEFT);
        $newCat = MedicineCategory::create([
            'code'   => $newCode,
            'name'   => $trimmed,
            'status' => 1,
        ]);
        $map[$key] = $newCat;

        return $newCat->id;
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
