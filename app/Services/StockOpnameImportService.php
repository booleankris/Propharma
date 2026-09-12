<?php

namespace App\Services;

use App\Models\Batches;
use App\Models\Etalases;
use App\Models\ExportJob;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\MedicineTransfers;
use App\Models\MedicineTransferItems;
use App\Models\StockOpname;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class StockOpnameImportService
{
    /**
     * Parse and analyze uploaded Excel file without applying database changes.
     *
     * @param string $filePath
     * @param int $pharmacyId
     * @param string $targetMode 'pelayanan' or 'gudang'
     * @return array
     */
    public function analyze(string $filePath, int $pharmacyId, string $targetMode = 'pelayanan'): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();

        $matcher = new SmartEtalaseMatcher($pharmacyId);
        $defaultEtalase = Etalases::where(function ($q) use ($pharmacyId) {
            $q->where('pharmacy_id', $pharmacyId)->orWhereNull('pharmacy_id');
        })->first();

        // Preload all medicines indexed by trimmed uppercase code
        $medicines = Medicines::select('id', 'code', 'name', 'unit')->get();
        $medMap = [];
        foreach ($medicines as $med) {
            $code = strtoupper(trim((string) $med->code));
            if ($code !== '') {
                $medMap[$code] = $med;
            }
        }

        // Determine column indexes from row 1 header, or default
        // Standard expected: A=No, B=Kode Barang, C=Stok Fisik, D=ED, E=Etalase
        $colMap = [
            'code'    => 1, // Col B
            'stock'   => 2, // Col C
            'ed'      => 3, // Col D
            'etalase' => 4, // Col E
        ];

        $headerRow = $sheet->rangeToArray("A1:{$highestCol}1", null, true, false)[0] ?? [];
        foreach ($headerRow as $idx => $headerVal) {
            $headerStr = mb_strtolower(trim((string) $headerVal));
            if (str_contains($headerStr, 'kode') || str_contains($headerStr, 'code')) {
                $colMap['code'] = $idx;
            } elseif (str_contains($headerStr, 'stok') || str_contains($headerStr, 'fisik') || str_contains($headerStr, 'qty')) {
                $colMap['stock'] = $idx;
            } elseif (str_contains($headerStr, 'expired') || str_contains($headerStr, 'kadaluarsa') || $headerStr === 'ed') {
                $colMap['ed'] = $idx;
            } elseif (str_contains($headerStr, 'etalase') || str_contains($headerStr, 'rak') || str_contains($headerStr, 'lokasi')) {
                $colMap['etalase'] = $idx;
            }
        }

        $parsedRows = [];
        $anomalies = [];
        $totalRows = 0;
        $medicinesMatchedCount = 0;
        $medicinesUnmatchedCount = 0;
        $edValidCount = 0;
        $edDefaultedCount = 0;
        $etalasesMatchedCount = 0;
        $etalasesCorrectedCount = 0;
        $etalasesUnmatchedCount = 0;

        for ($rowIdx = 2; $rowIdx <= $highestRow; $rowIdx++) {
            $rowData = $sheet->rangeToArray("A{$rowIdx}:{$highestCol}{$rowIdx}", null, true, false)[0] ?? [];

            $rawCode = trim((string) ($rowData[$colMap['code']] ?? ''));
            $rawStock = trim((string) ($rowData[$colMap['stock']] ?? ''));
            $rawEd = trim((string) ($rowData[$colMap['ed']] ?? ''));
            $rawEtalase = trim((string) ($rowData[$colMap['etalase']] ?? ''));

            // Skip empty rows
            if ($rawCode === '' && $rawStock === '' && $rawEd === '' && $rawEtalase === '') {
                continue;
            }

            $totalRows++;
            $rowErrors = [];
            $rowWarnings = [];
            $isValid = true;

            // 1. Medicine lookup
            $medCodeUpper = strtoupper($rawCode);
            $matchedMed = $medMap[$medCodeUpper] ?? null;

            if ($matchedMed) {
                $medicinesMatchedCount++;
            } else {
                $medicinesUnmatchedCount++;
                $isValid = false;
                $rowErrors[] = "Kode obat '{$rawCode}' tidak ditemukan di database.";
            }

            // 2. Physical stock parse
            $cleanStock = str_replace([' ', ','], ['', '.'], $rawStock);
            if ($cleanStock === '' || !is_numeric($cleanStock) || (float) $cleanStock < 0) {
                $isValid = false;
                $rowErrors[] = "Stok fisik '{$rawStock}' tidak valid (harus angka >= 0).";
                $stockPhysic = 0;
            } else {
                $stockPhysic = (int) round((float) $cleanStock);
            }

            // 3. Expired Date parse (supports Jun-26, 06/26, Indonesian months, Excel serial dates, full dates)
            $edCell = $sheet->getCellByColumnAndRow($colMap['ed'] + 1, $rowIdx);
            $formattedEd = trim((string) $edCell->getFormattedValue());

            $parsedEd = self::parseSmartExpiredDate($rawEd, $formattedEd);

            if ($rawEd === '' && $formattedEd === '') {
                $parsedEd = now()->addYears(2)->toDateString();
                $edDefaultedCount++;
                $rowWarnings[] = "Tanggal ED kosong, menggunakan default 2 tahun (" . Carbon::parse($parsedEd)->format('d/m/Y') . ").";
            } elseif ($parsedEd !== null) {
                $edValidCount++;
                // If input was a month-year format like Jun-26, add an informative note
                $displayEd = $formattedEd ?: $rawEd;
                if (preg_match('/^[a-zA-Z]{3,}[-\s\/]\d{2,4}$/u', $displayEd) || preg_match('/^\d{1,2}[-\/]\d{2,4}$/', $displayEd)) {
                    $rowWarnings[] = "Format bulan-tahun '{$displayEd}' otomatis disesuaikan ke tanggal akhir bulan (" . Carbon::parse($parsedEd)->format('d/m/Y') . ").";
                }
            } else {
                $parsedEd = now()->addYears(2)->toDateString();
                $edDefaultedCount++;
                $rowWarnings[] = "Format tanggal ED '{$rawEd}' tidak valid, menggunakan default (" . Carbon::parse($parsedEd)->format('d/m/Y') . ").";
            }

            // 4. Etalase resolution
            $resolvedEtalasesId = null;
            $resolvedEtalaseName = null;
            $etalaseInfo = null;

            if ($targetMode === 'pelayanan') {
                $match = $matcher->match($rawEtalase);
                if ($match['matched']) {
                    $etalasesMatchedCount++;
                    $resolvedEtalasesId = $match['etalases_id'];
                    $resolvedEtalaseName = $match['matched_name'];

                    if ($match['was_corrected']) {
                        $etalasesCorrectedCount++;
                        $rowWarnings[] = "Etalase '{$rawEtalase}' disesuaikan menjadi '{$match['matched_name']}'.";
                        $etalaseInfo = [
                            'original'  => $rawEtalase,
                            'corrected' => $match['matched_name'],
                            'status'    => 'corrected',
                        ];
                    } else {
                        $etalaseInfo = [
                            'original'  => $rawEtalase,
                            'corrected' => $match['matched_name'],
                            'status'    => 'exact',
                        ];
                    }
                } else {
                    $etalasesUnmatchedCount++;
                    if ($rawEtalase === '') {
                        $resolvedEtalasesId = $defaultEtalase ? $defaultEtalase->id : null;
                        $resolvedEtalaseName = $defaultEtalase ? $defaultEtalase->name : '-';
                        $rowWarnings[] = "Etalase kosong, menggunakan etalase utama '" . ($defaultEtalase->name ?? 'Default') . "'.";
                        $etalaseInfo = [
                            'original'  => '-',
                            'corrected' => $resolvedEtalaseName,
                            'status'    => 'defaulted',
                        ];
                    } else {
                        // Suggestion if any
                        $suggestText = $match['suggestion'] ? " (Saran: {$match['suggestion']}?)" : "";
                        $rowWarnings[] = "Etalase '{$rawEtalase}' tidak dikenal{$suggestText}. Dialihkan ke '" . ($defaultEtalase->name ?? 'Default') . "'.";
                        $resolvedEtalasesId = $defaultEtalase ? $defaultEtalase->id : null;
                        $resolvedEtalaseName = $defaultEtalase ? $defaultEtalase->name : '-';
                        $etalaseInfo = [
                            'original'   => $rawEtalase,
                            'corrected'  => $resolvedEtalaseName,
                            'suggestion' => $match['suggestion'],
                            'status'     => 'unmatched_fallback',
                        ];
                    }
                }
            } else {
                // Gudang mode: etalase not mandatory
                $resolvedEtalaseName = 'GUDANG PMI';
                $etalaseInfo = [
                    'original'  => $rawEtalase ?: 'GUDANG PMI',
                    'corrected' => 'GUDANG PMI',
                    'status'    => 'gudang',
                ];
            }

            if (!empty($rowErrors) || !empty($rowWarnings)) {
                $anomalies[] = [
                    'row'      => $rowIdx,
                    'code'     => $rawCode,
                    'name'     => $matchedMed ? $matchedMed->name : '-',
                    'errors'   => $rowErrors,
                    'warnings' => $rowWarnings,
                ];
            }

            $parsedRows[] = [
                'row_index'            => $rowIdx,
                'is_valid'             => $isValid,
                'medicine_id'          => $matchedMed ? $matchedMed->id : null,
                'medicine_code'        => $rawCode,
                'medicine_name'        => $matchedMed ? $matchedMed->name : '-',
                'medicine_unit'        => $matchedMed ? $matchedMed->unit : '-',
                'stock'                => $stockPhysic,
                'raw_stock'            => $rawStock,
                'expired_date'         => $parsedEd,
                'raw_ed'               => $rawEd,
                'etalases_id'          => $resolvedEtalasesId,
                'etalase_name'         => $resolvedEtalaseName,
                'etalase_info'         => $etalaseInfo,
                'errors'               => $rowErrors,
                'warnings'             => $rowWarnings,
            ];
        }

        // Cache parsed rows to storage with token
        $token = 'so_import_' . Str::random(24);
        $tempDir = storage_path('app/opname_imports');
        if (!File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $cacheData = [
            'token'        => $token,
            'pharmacy_id'  => $pharmacyId,
            'target_mode'  => $targetMode,
            'created_at'   => now()->toDateTimeString(),
            'rows'         => $parsedRows,
        ];
        File::put("{$tempDir}/{$token}.json", json_encode($cacheData));

        return [
            'success'   => true,
            'token'     => $token,
            'stats'     => [
                'total_rows'               => $totalRows,
                'valid_rows'               => count(array_filter($parsedRows, fn($r) => $r['is_valid'])),
                'error_rows'               => count(array_filter($parsedRows, fn($r) => !$r['is_valid'])),
                'medicines_matched_count'  => $medicinesMatchedCount,
                'medicines_unmatched_count'=> $medicinesUnmatchedCount,
                'ed_valid_count'           => $edValidCount,
                'ed_defaulted_count'       => $edDefaultedCount,
                'etalases_matched_count'   => $etalasesMatchedCount,
                'etalases_corrected_count' => $etalasesCorrectedCount,
                'etalases_unmatched_count' => $etalasesUnmatchedCount,
            ],
            'anomalies'    => array_slice($anomalies, 0, 50),
            'preview_rows' => array_slice($parsedRows, 0, 15),
        ];
    }

    /**
     * Execute Stock Opname Import from cached token.
     *
     * @param string $token
     * @param int $pharmacyId
     * @param string $targetMode
     * @param int $userId
     * @param int|null $jobId
     * @return array
     */
    public function execute(string $token, int $pharmacyId, string $targetMode, int $userId, ?int $jobId = null): array
    {
        $filePath = storage_path("app/opname_imports/{$token}.json");
        if (!File::exists($filePath)) {
            throw new \Exception("Sesi impor tidak ditemukan atau sudah kedaluwarsa. Silakan unggah ulang file.");
        }

        $payload = json_decode(File::get($filePath), true);
        $rows = $payload['rows'] ?? [];

        $exportJob = $jobId ? ExportJob::find($jobId) : null;
        if ($exportJob) {
            $exportJob->markProcessing();
        }

        $warehouseId = getWarehousePharmacyId();
        $counterPharmacyId = isWarehousePharmacy($pharmacyId) ? 1 : $pharmacyId;
        $batchTargetPharmacyId = ($targetMode === 'gudang') ? $warehouseId : $counterPharmacyId;

        $validRows = array_filter($rows, fn($r) => !empty($r['is_valid']) && !empty($r['medicine_id']));
        $totalValid = count($validRows);
        $processedCount = 0;
        $touchedMedicineIds = [];

        DB::beginTransaction();

        try {
            foreach ($validRows as $row) {
                $medicineId = (int) $row['medicine_id'];
                $stockPhysic = (int) $row['stock'];
                $expiredDate = $row['expired_date'];
                $etalasesId = $row['etalases_id'];

                // 1. Resolve target batch
                // Look for existing batch with matching medicine_id, pharmacy_id, and expired_date
                $batch = Batches::lockForUpdate()
                    ->where('medicine_id', $medicineId)
                    ->where('pharmacy_id', $batchTargetPharmacyId)
                    ->whereDate('expired_date', $expiredDate)
                    ->orderBy('id', 'asc')
                    ->first();

                if (!$batch) {
                    $edSlug = Carbon::parse($expiredDate)->format('Ymd');
                    $batch = Batches::create([
                        'medicine_id'  => $medicineId,
                        'pharmacy_id'  => $batchTargetPharmacyId,
                        'name'         => "OPN-{$edSlug}",
                        'expired_date' => $expiredDate,
                        'stock'        => 0,
                    ]);
                }

                // 2. Process according to target_mode
                if ($targetMode === 'gudang') {
                    $storageBefore = (int) $batch->stock;
                    $discrepancy   = $stockPhysic - $storageBefore;
                    $status        = $discrepancy >= 0 ? 5 : 6;

                    $batch->stock = $stockPhysic;
                    $batch->save();

                    StockOpname::create([
                        'users_id'          => $userId,
                        'batches_id'        => $batch->id,
                        'stock_physical'    => $stockPhysic,
                        'stock_discrepancy' => $discrepancy,
                        'stock_total'       => $stockPhysic,
                        'date'              => now()->toDateString(),
                        'status'            => $status,
                    ]);

                    ItemsLog::create([
                        'batches_id'       => $batch->id,
                        'transaction_code' => $this->generateOpnameCode(),
                        'code'             => $this->generateItemsLogCode(),
                        'type'             => "SO",
                        'medicine_id'      => $medicineId,
                        'qty'              => abs($discrepancy),
                        'qty_before'       => $storageBefore,
                        'qty_after'        => $stockPhysic,
                        'total'            => $discrepancy,
                        'date'             => now()->toDateTimeString(),
                        'status'           => $status,
                        'user_id'          => $userId,
                    ]);
                } else {
                    // Pelayanan mode
                    $transfers = MedicineTransferItems::where('batches_id', $batch->id)
                        ->where('status', 1)
                        ->where(function ($q) {
                            $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                        })
                        ->get();

                    $counterBefore = (int) $transfers->sum('qty');
                    $discrepancy   = $stockPhysic - $counterBefore;
                    $status        = $discrepancy >= 0 ? 5 : 6;

                    if ($transfers->isNotEmpty()) {
                        $primary = $transfers->first();
                        $primary->qty = $stockPhysic;
                        if ($etalasesId) {
                            $primary->etalases_id = $etalasesId;
                        }
                        $primary->save();

                        foreach ($transfers->slice(1) as $secondary) {
                            if ($secondary->qty != 0) {
                                $secondary->qty = 0;
                                $secondary->save();
                            }
                        }
                    } else {
                        $transferHeader = MedicineTransfers::create([
                            'code'    => $this->generateTransfersCode(),
                            'status'  => 1,
                            'user_id' => $userId,
                        ]);

                        MedicineTransferItems::create([
                            'medicine_transfer_id' => $transferHeader->id,
                            'batches_id'           => $batch->id,
                            'source_batches_id'    => $batch->id,
                            'qty'                  => $stockPhysic,
                            'status'               => 1,
                            'source_type'          => 'pelayanan',
                            'etalases_id'          => $etalasesId,
                        ]);
                    }

                    StockOpname::create([
                        'users_id'          => $userId,
                        'batches_id'        => $batch->id,
                        'stock_physical'    => $stockPhysic,
                        'stock_discrepancy' => $discrepancy,
                        'stock_total'       => $stockPhysic,
                        'date'              => now()->toDateString(),
                        'status'            => $status,
                    ]);

                    ItemsLog::create([
                        'batches_id'       => $batch->id,
                        'transaction_code' => $this->generateOpnameCode(),
                        'code'             => $this->generateItemsLogCode(),
                        'type'             => "SO",
                        'medicine_id'      => $medicineId,
                        'qty'              => abs($discrepancy),
                        'qty_before'       => $counterBefore,
                        'qty_after'        => $stockPhysic,
                        'total'            => $discrepancy,
                        'date'             => now()->toDateTimeString(),
                        'status'           => $status,
                        'user_id'          => $userId,
                    ]);
                }

                $touchedMedicineIds[$medicineId] = true;
                $processedCount++;

                if ($exportJob && $totalValid > 0 && ($processedCount % 10 === 0 || $processedCount === $totalValid)) {
                    $percent = (int) round(($processedCount / $totalValid) * 90);
                    $exportJob->setProgress($percent);
                }
            }

            // Sync master medicines stock
            foreach (array_keys($touchedMedicineIds) as $mId) {
                $med = Medicines::find($mId);
                if ($med) {
                    $totalReal = $this->calculateRealtimeStock($mId, $pharmacyId, 'total');
                    $med->update(['stock' => $totalReal]);
                }
            }

            DB::commit();

            if ($exportJob) {
                $exportJob->markFinished("impor_selesai");
            }

            // Clean up temporary cache file
            if (File::exists($filePath)) {
                File::delete($filePath);
            }

            return [
                'success'         => true,
                'message'         => "Berhasil melakukan impor stok opname untuk {$processedCount} item obat.",
                'processed_count' => $processedCount,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Stock Opname Import Failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            if ($exportJob) {
                $exportJob->update([
                    'status'   => ExportJob::STATUS_FAILED,
                    'progress' => 0,
                ]);
            }

            throw $e;
        }
    }

    private function calculateRealtimeStock($medicineId, $pharmacyId, $type = 'total'): int
    {
        if (!$medicineId) return 0;

        $warehouseId = getWarehousePharmacyId();
        $canSeeWarehouse = canAccessWarehouseStock($pharmacyId);

        $storageStock = $canSeeWarehouse
            ? (int) Batches::where('medicine_id', $medicineId)
                ->where('pharmacy_id', $warehouseId)
                ->sum('stock')
            : 0;

        if ($type === 'storage') {
            return $storageStock;
        }

        $counterPharmacyId = isWarehousePharmacy($pharmacyId) ? 1 : $pharmacyId;

        $counterStock = (int) MedicineTransferItems::whereHas('batches', function ($b) use ($medicineId, $counterPharmacyId) {
                $b->where('medicine_id', $medicineId)
                  ->where('pharmacy_id', $counterPharmacyId);
            })
            ->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
            })
            ->sum('qty');

        if ($type === 'counter') {
            return $counterStock;
        }

        return $storageStock + $counterStock;
    }

    private function generateOpnameCode(): string
    {
        $now = Carbon::now();
        $year = $now->format('y');
        $month = $now->format('m');
        $prefix = "SO-{$year}{$month}";

        $lastCode = ItemsLog::where('code', 'like', "{$prefix}%")
            ->where('status', 5)
            ->orderBy('code', 'desc')
            ->value('code');

        $nextNumber = $lastCode ? ((int) substr($lastCode, -4)) + 1 : 1;
        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function generateItemsLogCode(): string
    {
        $now = Carbon::now();
        $year = $now->format('y');
        $month = $now->format('m');
        $prefix = "{$year}{$month}LOG-";

        $lastCode = ItemsLog::where('code', 'like', "{$prefix}%")
            ->orderBy('code', 'desc')
            ->value('code');

        $nextNumber = $lastCode ? ((int) substr($lastCode, -4)) + 1 : 1;
        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function generateTransfersCode(): string
    {
        $now = Carbon::now();
        $year = $now->format('y');
        $month = $now->format('m');
        $prefix = "{$year}{$month}MUT";

        $lastCode = MedicineTransfers::where('code', 'like', "{$prefix}%")
            ->orderBy('code', 'desc')
            ->value('code');

        $nextNumber = $lastCode ? ((int) substr($lastCode, -4)) + 1 : 1;
        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Smart parsing for Expired Date handling:
     * - Month-Year strings: "Jun-26", "jun-26", "JUN-26", "Juni 2026", "06/26", "06-26", "06/2026"
     * - Indonesian month abbreviations: "Agu-26", "Agt-26", "Mei-26", "Des-26", "Okt-26", "Peb-26"
     * - Excel Date serial numbers (e.g. 46174) with month-year or standard formats
     * - Full dates: "30/06/2026", "2026-06-30", "15-Agu-2026"
     *
     * In pharmaceutical practice, when only Month and Year are specified (e.g. Jun-26),
     * the medicine is valid until the last day of that month (e.g. 2026-06-30).
     */
    public static function parseSmartExpiredDate(?string $rawVal, ?string $formattedVal = null): ?string
    {
        $raw = trim((string) $rawVal);
        $formatted = trim((string) $formattedVal);

        if ($raw === '' && $formatted === '') {
            return null;
        }

        $monthMap = [
            'jan' => 1, 'januari' => 1, 'january' => 1,
            'feb' => 2, 'februari' => 2, 'february' => 2, 'peb' => 2, 'pebruari' => 2,
            'mar' => 3, 'maret' => 3, 'march' => 3,
            'apr' => 4, 'april' => 4,
            'mei' => 5, 'may' => 5,
            'jun' => 6, 'juni' => 6, 'june' => 6,
            'jul' => 7, 'juli' => 7, 'july' => 7,
            'agu' => 8, 'agustus' => 8, 'agt' => 8, 'aug' => 8, 'august' => 8,
            'sep' => 9, 'september' => 9, 'sept' => 9,
            'okt' => 10, 'oktober' => 10, 'oct' => 10, 'october' => 10,
            'nov' => 11, 'november' => 11,
            'des' => 12, 'desember' => 12, 'dec' => 12, 'december' => 12,
        ];

        // 1. Check if Excel stored it as a date serial number
        if (is_numeric($raw) && (int) $raw > 30000 && (int) $raw < 70000) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $raw);
                // If formattedVal is formatted as Month-Year (e.g. Jun-26, mmm-yy), set to end of month
                if ($formatted && preg_match('/^[a-zA-Z]{3,}[-\s\/]\d{2,4}$/u', $formatted)) {
                    return Carbon::instance($dt)->endOfMonth()->toDateString();
                }
                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {}
        }

        $candidates = array_unique(array_filter([$formatted, $raw]));

        foreach ($candidates as $candidate) {
            $clean = trim(preg_replace('/[.\s\/]+/', '-', $candidate));

            // Pattern: MonthName-Year (e.g. Jun-26, Juni-2026, Agu-26, Agt-26, Des-26)
            if (preg_match('/^([a-zA-Z]+)-(\d{2,4})$/u', $clean, $m)) {
                $monthKey = mb_strtolower($m[1], 'UTF-8');
                if (isset($monthMap[$monthKey])) {
                    $month = $monthMap[$monthKey];
                    $year = (int) $m[2];
                    if ($year < 100) $year += 2000;
                    return Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
                }
            }

            // Pattern: Year-MonthName (e.g. 26-Jun, 2026-Juni)
            if (preg_match('/^(\d{2,4})-([a-zA-Z]+)$/u', $clean, $m)) {
                $monthKey = mb_strtolower($m[2], 'UTF-8');
                if (isset($monthMap[$monthKey])) {
                    $month = $monthMap[$monthKey];
                    $year = (int) $m[1];
                    if ($year < 100) $year += 2000;
                    return Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
                }
            }

            // Pattern: MM-YY or MM-YYYY (e.g. 06-26, 6-26, 06-2026)
            if (preg_match('/^(\d{1,2})-(\d{2}|\d{4})$/', $clean, $m)) {
                $month = (int) $m[1];
                $year = (int) $m[2];
                if ($month >= 1 && $month <= 12) {
                    if ($year < 100) $year += 2000;
                    return Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
                }
            }

            // Pattern: YYYY-MM (e.g. 2026-06)
            if (preg_match('/^(\d{4})-(\d{1,2})$/', $clean, $m)) {
                $year = (int) $m[1];
                $month = (int) $m[2];
                if ($month >= 1 && $month <= 12) {
                    return Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
                }
            }

            // Pattern: Day-MonthName-Year (e.g. 15-Jun-2026, 15-Agu-26, 15-Agt-26)
            if (preg_match('/^(\d{1,2})-([a-zA-Z]+)-(\d{2,4})$/u', $clean, $m)) {
                $day = (int) $m[1];
                $monthKey = mb_strtolower($m[2], 'UTF-8');
                $year = (int) $m[3];
                if ($year < 100) $year += 2000;
                if (isset($monthMap[$monthKey])) {
                    $month = $monthMap[$monthKey];
                    return Carbon::create($year, $month, $day)->toDateString();
                }
            }

            // Standard Carbon parse (e.g. 2026-06-30, 30-06-2026)
            try {
                return Carbon::parse($clean)->toDateString();
            } catch (\Throwable $e) {}
        }

        return null;
    }
}
