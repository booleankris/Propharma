<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Medicines;
use App\Models\Batches;
use App\Models\MedicineTransferItems;
use App\Models\Pharmacies;
use Illuminate\Support\Facades\DB;

class CheckEmptyStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:check-empty 
                            {--pharmacy=1 : ID Apotek Cabang Pelayanan (default: 1)}
                            {--type=all : Filter data: "all" (gudang & etalase 0), "gudang" (hanya gudang 0), "etalase" (hanya etalase 0)}
                            {--limit=25 : Jumlah baris sampel yang ditampilkan di terminal}
                            {--export : Simpan hasil ke file CSV}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cek daftar obat yang tidak memiliki stok di Gudang PMI maupun di Etalase Kasir Cabang';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pharmacyId = (int) $this->option('pharmacy');
        $type = strtolower((string) $this->option('type'));
        $limit = (int) $this->option('limit');
        $isExport = (bool) $this->option('export');
        $warehouseId = 9; // Gudang PMI

        $pharmacy = Pharmacies::find($pharmacyId);
        $pharmacyName = $pharmacy ? $pharmacy->name : "Apotek ID {$pharmacyId}";

        $this->info("==========================================================");
        $this->info("   PENGECEKAN STOK OBAT (GUDANG PMI & ETALASE KASIR)");
        $this->info("==========================================================");
        $this->line("Target Apotek Pelayanan : {$pharmacyName} (ID: {$pharmacyId})");
        $this->line("Target Gudang           : GUDANG PMI (ID: {$warehouseId})");
        $this->newLine();

        $this->comment("Sedang mengkalkulasi data stok seluruh master obat...");

        // Query master obat beserta subquery stok gudang & stok etalase
        $query = Medicines::query()
            ->select([
                'medicines.id',
                'medicines.code',
                'medicines.name',
                'medicines.unit',
            ])
            ->selectRaw('COALESCE(bs.storage_stock, 0) as storage_stock')
            ->selectRaw('COALESCE(cs.counter_stock, 0) as counter_stock')
            // Subquery Stok Gudang
            ->leftJoinSub(
                DB::table('batches')
                    ->select('medicine_id', DB::raw('COALESCE(SUM(stock), 0) as storage_stock'))
                    ->where('pharmacy_id', $warehouseId)
                    ->where('status', 1)
                    ->groupBy('medicine_id'),
                'bs',
                'bs.medicine_id',
                '=',
                'medicines.id'
            )
            // Subquery Stok Counter / Etalase Pelayanan
            ->leftJoinSub(
                DB::table('medicine_transfer_items as mt')
                    ->join('batches as b', 'b.id', '=', 'mt.batches_id')
                    ->select('b.medicine_id', DB::raw('COALESCE(SUM(mt.qty), 0) as counter_stock'))
                    ->where('mt.status', 1)
                    ->where('b.pharmacy_id', $pharmacyId)
                    ->where(function ($q) {
                        $q->whereNull('mt.source_type')->orWhere('mt.source_type', '!=', 'retur_gudang');
                    })
                    ->groupBy('b.medicine_id'),
                'cs',
                'cs.medicine_id',
                '=',
                'medicines.id'
            );

        $totalMaster = Medicines::count();

        // Hitung statistik
        $emptyGudangCount = (clone $query)->whereRaw('COALESCE(bs.storage_stock, 0) <= 0')->count();
        $emptyEtalaseCount = (clone $query)->whereRaw('COALESCE(cs.counter_stock, 0) <= 0')->count();
        $emptyBothCount = (clone $query)
            ->whereRaw('COALESCE(bs.storage_stock, 0) <= 0')
            ->whereRaw('COALESCE(cs.counter_stock, 0) <= 0')
            ->count();

        $readyBothCount = $totalMaster - $emptyBothCount;

        // Tampilkan Summary Statistik
        $this->newLine();
        $this->table(
            ['Metrik Stok', 'Jumlah Obat', 'Persentase'],
            [
                ['Total Master Obat', number_format($totalMaster), '100%'],
                ['Obat yang Ada Stok (Gudang / Etalase)', number_format($readyBothCount), round(($readyBothCount / max(1, $totalMaster)) * 100, 1) . '%'],
                ['Obat Tanpa Stok Gudang (Gudang = 0)', number_format($emptyGudangCount), round(($emptyGudangCount / max(1, $totalMaster)) * 100, 1) . '%'],
                ['Obat Tanpa Stok Etalase (Etalase = 0)', number_format($emptyEtalaseCount), round(($emptyEtalaseCount / max(1, $totalMaster)) * 100, 1) . '%'],
                ['⚠️  KOSONG DI KEDUANYA (Gudang = 0 & Etalase = 0)', number_format($emptyBothCount), round(($emptyBothCount / max(1, $totalMaster)) * 100, 1) . '%'],
            ]
        );

        // Filter list sesuai opsi --type
        $listQuery = clone $query;
        if ($type === 'gudang') {
            $listQuery->whereRaw('COALESCE(bs.storage_stock, 0) <= 0');
            $filterTitle = "Daftar Obat Tanpa Stok Gudang (Gudang = 0)";
        } elseif ($type === 'etalase') {
            $listQuery->whereRaw('COALESCE(cs.counter_stock, 0) <= 0');
            $filterTitle = "Daftar Obat Tanpa Stok Etalase (Etalase = 0)";
        } else {
            $listQuery->whereRaw('COALESCE(bs.storage_stock, 0) <= 0')
                      ->whereRaw('COALESCE(cs.counter_stock, 0) <= 0');
            $filterTitle = "Daftar Obat yang Kosong di Keduanya (Gudang = 0 & Etalase = 0)";
        }

        $filteredCount = (clone $listQuery)->count();

        $this->newLine();
        $this->info("▶ {$filterTitle} (Total: " . number_format($filteredCount) . " obat)");

        if ($filteredCount > 0) {
            $samples = $listQuery->orderBy('medicines.id', 'asc')->take($limit)->get();
            $tableRows = [];
            foreach ($samples as $idx => $m) {
                $tableRows[] = [
                    $idx + 1,
                    $m->code ?? '-',
                    $m->name ?? '-',
                    $m->unit ?? '-',
                    (int)$m->storage_stock,
                    (int)$m->counter_stock,
                ];
            }

            $this->table(
                ['No', 'Kode Obat', 'Nama Obat', 'Satuan', 'Stok Gudang', 'Stok Etalase'],
                $tableRows
            );

            if ($filteredCount > $limit) {
                $this->line("... dan " . number_format($filteredCount - $limit) . " obat lainnya (Gunakan '--limit=" . $filteredCount . "' untuk melihat semua, atau '--export' untuk simpan ke CSV).");
            }
        } else {
            $this->info("✓ Hebat! Tidak ada obat yang kosong untuk kriteria ini.");
        }

        // Export ke CSV jika diminta
        if ($isExport && $filteredCount > 0) {
            $fileName = "obat_kosong_" . $type . "_" . date('Ymd_His') . ".csv";
            $filePath = storage_path('app/' . $fileName);
            
            $file = fopen($filePath, 'w');
            fputcsv($file, ['ID', 'Kode Obat', 'Nama Obat', 'Satuan', 'Stok Gudang', 'Stok Etalase']);
            
            $listQuery->chunk(500, function($medicines) use ($file) {
                foreach ($medicines as $m) {
                    fputcsv($file, [
                        $m->id,
                        $m->code,
                        $m->name,
                        $m->unit,
                        (int)$m->storage_stock,
                        (int)$m->counter_stock,
                    ]);
                }
            });
            fclose($file);

            $this->newLine();
            $this->info("✓ Hasil export telah disimpan di: " . $filePath);
        }

        $this->newLine();
        $this->comment("💡 TIPS:");
        $this->line("1. Untuk mengisi stok etalase kasir seluruh cabang: php artisan stock:init-dummy --all --all-pharmacies --qty=100 --force");
        $this->line("2. Untuk mengisi stok gudang PMI: php artisan stock:init-gudang --qty=100 --force");
        $this->newLine();

        return 0;
    }
}
