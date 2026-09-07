<?php

namespace App\Exports\Report;

use App\Models\Medicines;
use App\Models\Pharmacies;
use App\Models\ReportedMedicine;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportedMedicinesExport implements WithMultipleSheets
{
    protected $startDate;
    protected $endDate;
    protected $pharmacyId;
    protected $pharmacyName;

    public function __construct($startDate, $endDate, $pharmacyId = null)
    {
        $this->startDate  = $startDate;
        $this->endDate    = $endDate;
        $this->pharmacyId = $pharmacyId;

        if ($pharmacyId) {
            $pharmacy = Pharmacies::find($pharmacyId);
            $this->pharmacyName = $pharmacy ? $pharmacy->name : "APOTEK ID {$pharmacyId}";
        } else {
            $this->pharmacyName = "SEMUA CABANG";
        }
    }

    public function sheets(): array
    {
        $sheets = [];
        $reported = ReportedMedicine::with('medicine')->get();

        $usedTitles = [];

        foreach ($reported as $item) {
            if (!$item->medicine) {
                continue;
            }

            // Bersihkan nama untuk nama tab sheet (max 31 char, tanpa karakter terlarang)
            $rawName = preg_replace('/[\*\:\?\/\x5c\[\]]/', '', $item->medicine->name);
            $baseTitle = mb_substr(trim($rawName) ?: 'OBAT', 0, 28);
            $sheetTitle = $baseTitle;
            $counter = 1;

            while (isset($usedTitles[strtoupper($sheetTitle)])) {
                $sheetTitle = mb_substr($baseTitle, 0, 26) . '_' . $counter;
                $counter++;
            }
            $usedTitles[strtoupper($sheetTitle)] = true;

            $sheets[] = new ReportedMedicineSheet(
                $item->medicine,
                $this->startDate,
                $this->endDate,
                $this->pharmacyId,
                $this->pharmacyName,
                $sheetTitle
            );
        }

        // Jika belum ada obat wajib lapor yang terdaftar, buat satu sheet kosong informatif
        if (empty($sheets)) {
            $sheets[] = new ReportedMedicineSheet(
                null,
                $this->startDate,
                $this->endDate,
                $this->pharmacyId,
                $this->pharmacyName,
                'BELUM ADA OBAT'
            );
        }

        return $sheets;
    }
}
