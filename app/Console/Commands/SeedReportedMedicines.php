<?php

namespace App\Console\Commands;

use App\Models\Medicines;
use App\Models\ReportedMedicine;
use Illuminate\Console\Command;

class SeedReportedMedicines extends Command
{
    protected $signature = 'reported-medicines:seed';
    protected $description = 'Seed default reported medicines (Morfina, Morfikaf, MST Continus)';

    public function handle()
    {
        $targets = [
            ['code' => '000600196', 'search' => 'MORFINA%INJ', 'label' => 'MORFINA 10MGML INJ'],
            ['code' => '000600264', 'search' => 'MORFIKAF%SR%15', 'label' => 'MORFIKAF-SR 15MG @60'],
            ['code' => '000600267', 'search' => 'MORFIKAF%IR%10', 'label' => 'MORFIKAF-IR 10MG @30'],
            ['code' => '000600268', 'search' => 'MORFIKAF%SIRUP', 'label' => 'MORFIKAF SIRUP 10MG5ML'],
            ['code' => '008600026', 'search' => 'MST%CONTINUS%10', 'label' => 'MST CONTINUS TAB 10MG'],
            ['code' => '008600027', 'search' => 'MST%CONTINUS%15', 'label' => 'MST CONTINUS TAB 15MG'],
        ];

        $added = 0;
        $existing = 0;
        $notFound = 0;

        foreach ($targets as $target) {
            $medicine = Medicines::where('code', $target['code'])->first();
            if (!$medicine) {
                $medicine = Medicines::where('name', 'LIKE', '%' . $target['search'] . '%')->first();
            }

            if (!$medicine) {
                $this->error("❌ Obat tidak ditemukan: {$target['label']} (Kode: {$target['code']})");
                $notFound++;
                continue;
            }

            $record = ReportedMedicine::where('medicine_id', $medicine->id)->first();
            if ($record) {
                $this->warn("⚠️ Sudah ada: [{$medicine->code}] {$medicine->name} (ID: {$medicine->id})");
                $existing++;
            } else {
                ReportedMedicine::create([
                    'medicine_id' => $medicine->id,
                    'notes' => 'Wajib Lapor',
                ]);
                $this->info("✅ Berhasil ditambahkan: [{$medicine->code}] {$medicine->name} (ID: {$medicine->id})");
                $added++;
            }
        }

        $this->newLine();
        $this->info("Selesai! Ditambahkan: {$added}, Sudah Ada: {$existing}, Tidak Ditemukan: {$notFound}");

        return 0;
    }
}
