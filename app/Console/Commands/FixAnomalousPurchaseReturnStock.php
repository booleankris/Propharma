<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseReturnItem;
use App\Models\Medicines;
use App\Models\Batches;
use App\Models\ItemsLog;
use Illuminate\Support\Facades\DB;

class FixAnomalousPurchaseReturnStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'retur:fix-typo-stock 
                            {--dry-run : Simulasi pemeriksaan tanpa mengubah database}
                            {--pharmacy= : Filter pharmacy_id tertentu}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mendeteksi dan mengoreksi data retur yang salah ketik (misal Qty diisi jumlah butir saat satuan BOX) dan memulihkan kelebihan potong stok fisik.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $pharmacyFilter = $this->option('pharmacy');

        $this->info("==================================================================");
        $this->info("   DETEKSI & KOREKSI OTOMATIS DATA RETUR SALAH KETIK SATUAN");
        $this->info("==================================================================");
        if ($isDryRun) {
            $this->warn("MODE DRY-RUN: Database TIDAK akan dimodifikasi.");
        }
        if ($pharmacyFilter) {
            $this->info("Filter Cabang: Pharmacy ID {$pharmacyFilter}");
        }

        $query = PurchaseReturnItem::with(['purchaseReturn', 'medicine'])
            ->whereHas('purchaseReturn', function ($q) use ($pharmacyFilter) {
                $q->where('status', '!=', 'dibatalkan');
                if ($pharmacyFilter) {
                    $q->where('pharmacy_id', $pharmacyFilter);
                }
            })
            ->whereIn('status', ['dikonfirmasi', 'sudah_diambil', 'sudah_potong'])
            ->orderBy('id', 'asc');

        $items = $query->get();
        $this->info("Memeriksa " . $items->count() . " item retur...");

        $anomaliesCount = 0;
        $fixedCount = 0;

        foreach ($items as $item) {
            $p = $item->purchaseReturn;
            if (!$p) continue;

            $med = $item->medicine ?: Medicines::find($item->medicine_id);
            if (!$med) continue;

            $content = (int) ($med->content ?: 1);
            if ($content <= 1) {
                // Obat tanpa kemasan isi > 1 tidak mengalami masalah salah kali
                continue;
            }

            $inputQty = (float) $item->qty;
            $unit = strtoupper(trim((string) $item->unit));
            $medPack = strtoupper(trim((string) $med->packaging));
            $isPackUnit = in_array($unit, ['BOX', 'DUS', 'PACK', 'KEMASAN']) || $unit === $medPack || $item->retur_type === 'packaging';

            if (!$isPackUnit) {
                continue;
            }

            // Ambil catatan log kartu stok untuk item retur ini (prioritaskan batch_id yang sesuai)
            $log = ItemsLog::where('transaction_code', $p->return_no)
                ->where('medicine_id', $med->id)
                ->where('status', 4)
                ->when($item->batch_id, fn($q) => $q->where('batches_id', $item->batch_id))
                ->first()
                ?: ItemsLog::where('transaction_code', $p->return_no)
                    ->where('medicine_id', $med->id)
                    ->where('status', 4)
                    ->first();

            $loggedQty = (float) ($log ? $log->qty : 0);

            // Deteksi Kasus Anomali:
            // Kasus 1: Input Qty sama persis dengan isi kemasan (misal input Qty: 50 untuk obat isi 50)
            // Kasus 2: Log Qty terpotong kelipatan kuadrat (misal 50 * 50 = 2500)
            $isExactContentTypo = ($inputQty == $content && $loggedQty >= ($content * $content));
            $isMultipleContentTypo = ($inputQty > 1 && ($inputQty % $content == 0) && $loggedQty == ($inputQty * $content) && $inputQty >= $content);

            if ($isExactContentTypo || $isMultipleContentTypo) {
                $anomaliesCount++;

                // Qty fisik yang sebenarnya dimaksudkan (jumlah tablet eceran)
                // Jika input Qty: 50 pada obat @50, sebenarnya adalah 1 BOX (50 butir)
                $correctBoxes = $inputQty / $content;
                $correctPhysicalQty = $inputQty; // yaitu 50 tablet
                $excessDeducted = $loggedQty - $correctPhysicalQty;

                $this->line("------------------------------------------------------------------");
                $this->warn("[ANOMALI TERDETEKSI] Item ID: {$item->id} | No: {$p->return_no}");
                $this->line("Obat: {$med->name} (Isi @{$content} {$med->unit}/{$unit})");
                $this->line("Inputan Tercatat: Qty {$inputQty} {$unit} | Terpotong di Log: {$loggedQty} {$med->unit}");
                $this->info("Koreksi Seharusnya: {$correctBoxes} {$unit} (= {$correctPhysicalQty} {$med->unit}). Kelebihan Potong: {$excessDeducted} {$med->unit}");

                if ($excessDeducted <= 0) {
                    $this->line("Stok sudah sesuai atau belum terpotong lebih. Dilewati.");
                    continue;
                }

                $batch = Batches::find($item->batch_id) ?: Batches::where('medicine_id', $med->id)->where('pharmacy_id', $p->pharmacy_id)->first();

                if (!$isDryRun) {
                    DB::transaction(function () use ($item, $med, $batch, $log, $correctBoxes, $content, $correctPhysicalQty, $excessDeducted) {
                        // 1. Kembalikan kelebihan pemotongan ke master stok obat dan batch
                        $med->increment('stock', $excessDeducted);
                        if ($batch) {
                            $batch->increment('stock', $excessDeducted);
                        }

                        // 2. Koreksi log kartu stok (ItemsLog)
                        if ($log) {
                            $qtyBefore = (float) $log->qty_before;
                            $log->update([
                                'qty' => $correctPhysicalQty,
                                'qty_after' => $qtyBefore - $correctPhysicalQty,
                            ]);
                        }

                        // 3. Koreksi data item retur
                        $item->qty = $correctBoxes;
                        $item->retur_type = 'packaging';
                        $item->stock_unit_factor = $content;
                        $item->save();
                    });

                    $this->info("✓ BERHASIL DIKOREKSI: {$excessDeducted} {$med->unit} telah dikembalikan ke stok gudang/batch.");
                    $fixedCount++;
                } else {
                    $this->info("[SIMULASI] Akan mengembalikan {$excessDeducted} {$med->unit} ke stok gudang/batch.");
                    $fixedCount++;
                }
            }
        }

        $this->info("==================================================================");
        $this->info("PEMERIKSAAN SELESAI!");
        $this->info("Total Anomali Ditemukan: {$anomaliesCount}");
        $this->info("Total Dikoreksi / Dipulihkan: {$fixedCount}");
        $this->info("==================================================================");

        return 0;
    }
}

