<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Medicines;
use App\Models\Batches;
use App\Models\ItemsLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SyncPurchaseReturnManualStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'retur:sync-manual-stock 
                            {--dry-run : Simulasi tanpa mengubah database} 
                            {--pharmacy= : Filter pharmacy_id tertentu (default: semua cabang)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi dan potongkan stok batch gudang untuk seluruh retur manual yang belum terpotong di semua cabang.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $pharmacyFilter = $this->option('pharmacy');

        $this->info("==================================================================");
        $this->info("   SINKRONISASI STOK BATCH UNTUK RETUR MANUAL (SEMUA CABANG)");
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
            ->where(function ($q) {
                $q->whereIn('status', ['dikonfirmasi', 'sudah_diambil', 'sudah_potong', 'barang_kembali_pmi'])
                  ->orWhereHas('purchaseReturn', fn($pr) => $pr->where('is_manual', true));
            })
            ->orderBy('id', 'asc');

        $items = $query->get();
        $this->info("Ditemukan " . $items->count() . " item retur untuk diperiksa.");

        $processedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($items as $item) {
            $p = $item->purchaseReturn;
            if (!$p) {
                $skippedCount++;
                continue;
            }

            $med = $item->medicine ?: Medicines::find($item->medicine_id);
            if (!$med) {
                $this->error("[ID {$item->id}] Obat tidak ditemukan (Medicine ID: {$item->medicine_id}). Dilewati.");
                $errorCount++;
                continue;
            }

            $pharmacyId = (int) ($p->pharmacy_id ?: 9);
            $factor = $this->resolveStockFactor($item, $med);
            $qtyDeduct = (float) $item->qty * $factor;
            $actionDate = $item->confirmed_date ? $item->confirmed_date->toDateString() : ($item->status_date ? $item->status_date->toDateString() : now()->toDateString());

            // Tangani status 'barang_kembali_pmi': barang fisik kembali ke PMI, stok harus dipulihkan jika pernah terpotong
            if ($item->status === 'barang_kembali_pmi') {
                if ($item->stock_deducted) {
                    $batch = Batches::find($item->batch_id) ?: $this->findOrResolveBatch($item, $med, $pharmacyId);
                    $qtyToRestore = $qtyDeduct;
                    $this->warn("Barang Kembali PMI [Item ID: {$item->id}] {$med->name}: Memulihkan stok {$qtyToRestore} {$med->unit}.");
                    if (!$isDryRun) {
                        DB::transaction(function () use ($item, $p, $med, $batch, $qtyToRestore, $actionDate) {
                            $qtyBefore = (float) $med->stock;
                            $med->increment('stock', $qtyToRestore);
                            $qtyAfter = $qtyBefore + $qtyToRestore;
                            $batch->increment('stock', $qtyToRestore);

                            ItemsLog::create([
                                'transaction_code' => $p->return_no . '-BATAL',
                                'code' => $this->generateLogCode(),
                                'type' => 'RT_BATAL',
                                'medicine_id' => $med->id,
                                'qty' => $qtyToRestore,
                                'qty_before' => $qtyBefore,
                                'qty_after' => $qtyAfter,
                                'total' => $item->subtotal,
                                'date' => $actionDate,
                                'status' => 6,
                                'batches_id' => $batch->id,
                                'user_id' => $p->user_id ?: 1,
                            ]);

                            $item->stock_deducted = false;
                            $item->save();
                        });
                        $this->info("✓ BERHASIL: Stok {$med->name} telah dipulihkan ke batch [{$batch->name}].");
                    }
                    $processedCount++;
                } else {
                    $skippedCount++;
                }
                continue;
            }

            // Periksa apakah item ini sudah memiliki ItemsLog dengan batches_id yang valid
            $existingLogWithBatch = ItemsLog::where('transaction_code', $p->return_no)
                ->where('medicine_id', $med->id)
                ->where('status', 4)
                ->whereNotNull('batches_id')
                ->first();

            // Cek apakah item sudah pernah dipotong dan apakah qty yang dipotong sudah sesuai dengan faktor kemasan
            if ($item->batch_id && $item->stock_deducted && $existingLogWithBatch) {
                $previouslyDeducted = (float) $existingLogWithBatch->qty;
                if (abs($previouslyDeducted - $qtyDeduct) < 0.001) {
                    // Item ini sudah memiliki batch dan log yang valid dengan qty yang benar, lewati
                    $skippedCount++;
                    continue;
                }

                // Terdapat selisih pemotongan (misal sebelumnya dipotong 1 bukannya 30/100 box)
                $delta = $qtyDeduct - $previouslyDeducted;
                $batch = Batches::find($item->batch_id) ?: $this->findOrResolveBatch($item, $med, $pharmacyId);

                $this->warn("KOREKSI KEMASAN [Item ID: {$item->id}] {$med->name}:");
                $this->line("Sebelumnya dipotong {$previouslyDeducted}, seharusnya {$qtyDeduct} ({$item->qty} {$item->unit} @ {$factor} isi). Selisih: {$delta}");

                if (!$isDryRun) {
                    DB::transaction(function () use ($item, $med, $batch, $delta, $qtyDeduct, $existingLogWithBatch, $factor) {
                        if ($delta > 0) {
                            $med->decrement('stock', $delta);
                            $batch->decrement('stock', $delta);
                        } elseif ($delta < 0) {
                            $med->increment('stock', abs($delta));
                            $batch->increment('stock', abs($delta));
                        }

                        $existingLogWithBatch->update([
                            'qty' => $qtyDeduct,
                            'batches_id' => $batch->id,
                        ]);

                        $item->stock_unit_factor = $factor;
                        $item->retur_type = ($factor > 1) ? 'packaging' : 'unit';
                        $item->batch_id = $batch->id;
                        $item->stock_deducted = true;
                        $item->save();
                    });
                    $this->info("✓ BERHASIL: Koreksi selisih {$delta} untuk batch [{$batch->name}] berhasil diterapkan.");
                } else {
                    $this->info("[SIMULASI] Akan mengoreksi selisih pemotongan sebanyak {$delta}.");
                }

                $processedCount++;
                continue;
            }

            $this->line("------------------------------------------------------------------");
            $this->info("Memproses [Item ID: {$item->id}] No: {$p->return_no} | Cabang ID: {$pharmacyId}");
            $this->line("Obat: {$med->name} | Qty Retur: {$item->qty} {$item->unit} ({$qtyDeduct} satuan dasar @ factor {$factor})");

            // Cari batch yang tepat untuk dipotong di cabang ini
            $batch = $this->findOrResolveBatch($item, $med, $pharmacyId);

            if (!$batch) {
                $this->error("Gagal menemukan/membuat batch untuk obat {$med->name}.");
                $errorCount++;
                continue;
            }

            $this->line("Batch Dialokasikan: [ID: {$batch->id}] {$batch->name} (Stok Saat Ini: {$batch->stock})");

            if (!$isDryRun) {
                DB::transaction(function () use ($item, $p, $med, $batch, $qtyDeduct, $actionDate, $pharmacyId, $factor) {
                    $qtyBefore = (float) $med->stock;

                    // 1. Potong stok batch
                    $batch->decrement('stock', $qtyDeduct);

                    // 2. Jika stock_deducted pada item retur masih false, potong stok master medicines
                    if (!$item->stock_deducted) {
                        $med->decrement('stock', $qtyDeduct);
                        $qtyAfter = $qtyBefore - $qtyDeduct;
                    } else {
                        $qtyAfter = $qtyBefore;
                    }

                    // 3. Update atau buat ItemsLog kartu stok
                    $existingLog = ItemsLog::where('transaction_code', $p->return_no)
                        ->where('medicine_id', $med->id)
                        ->where('status', 4)
                        ->first();

                    if ($existingLog) {
                        $existingLog->update([
                            'batches_id' => $batch->id,
                            'qty' => $qtyDeduct,
                            'total' => $item->subtotal,
                        ]);
                    } else {
                        ItemsLog::create([
                            'transaction_code' => $p->return_no,
                            'code' => $this->generateLogCode(),
                            'type' => 'RT',
                            'medicine_id' => $med->id,
                            'qty' => $qtyDeduct,
                            'qty_before' => $qtyBefore,
                            'qty_after' => $qtyAfter,
                            'total' => $item->subtotal,
                            'date' => $actionDate,
                            'status' => 4,
                            'batches_id' => $batch->id,
                            'user_id' => $p->user_id ?: 1,
                        ]);
                    }

                    // 4. Update data item retur
                    $item->batch_id = $batch->id;
                    $item->retur_type = ($factor > 1) ? 'packaging' : 'unit';
                    $item->stock_unit_factor = $factor;
                    if (empty($item->batch_number) || $item->batch_number === '-') {
                        $item->batch_number = $batch->name;
                    }
                    if (empty($item->expired_date) && $batch->expired_date) {
                        $item->expired_date = $batch->expired_date;
                    }
                    $item->stock_deducted = true;
                    $item->save();
                });

                $this->info("✓ BERHASIL: Stok batch [{$batch->name}] telah dipotong sebanyak {$qtyDeduct}.");
            } else {
                $this->info("[SIMULASI] Akan memotong batch [{$batch->name}] sebanyak {$qtyDeduct}.");
            }

            $processedCount++;
        }

        $this->info("==================================================================");
        $this->info("SINKRONISASI SELESAI!");
        $this->info("Total Diproses & Dipotongkan: {$processedCount}");
        $this->info("Total Sudah Valid (Dilewati): {$skippedCount}");
        $this->info("Total Gagal/Error: {$errorCount}");
        $this->info("==================================================================");

        return 0;
    }

    /**
     * Resolusi faktor konversi kemasan ke satuan stok fisik dasar.
     */
    protected function resolveStockFactor(PurchaseReturnItem $item, Medicines $med): int
    {
        if ($item->retur_type === 'packaging' && (int)$item->stock_unit_factor > 1) {
            return (int) $item->stock_unit_factor;
        }

        $content = (int) ($med->content ?: 1);
        if ($content <= 1) {
            return 1;
        }

        $itUnit = strtoupper(trim((string) ($item->unit ?? '')));
        $medPack = strtoupper(trim((string) ($med->packaging ?? '')));
        $medUnit = strtoupper(trim((string) ($med->unit ?? '')));

        if (!empty($itUnit)) {
            if ($itUnit === $medPack || in_array($itUnit, ['BOX', 'DUS', 'PACK', 'KEMASAN'])) {
                return $content;
            }
            if (!empty($medUnit) && $itUnit !== $medUnit && !in_array($itUnit, ['TAB', 'KAPS', 'CAP', 'PCS', 'BTL', 'VIAL', 'AMP', 'TUBE', 'POT', 'SACHET', 'STRIP'])) {
                return $content;
            }
        }

        if ($item->retur_type === 'packaging') {
            return $content;
        }

        return 1;
    }

    /**
     * Cari atau tentukan batch yang tepat di farmasi cabang
     */
    protected function findOrResolveBatch(PurchaseReturnItem $item, Medicines $med, int $pharmacyId): Batches
    {
        $batch = null;

        // 1. Berdasarkan batch_id langsung jika ada
        if (!empty($item->batch_id)) {
            $batch = Batches::find($item->batch_id);
        }

        // 2. Berdasarkan nomor batch yang diketik manual di cabang ini
        if (!$batch && !empty($item->batch_number) && $item->batch_number !== '-') {
            $batchName = trim($item->batch_number);
            $batch = Batches::where('medicine_id', $med->id)
                ->where('pharmacy_id', $pharmacyId)
                ->where(function ($q) use ($batchName) {
                    $q->where('name', $batchName)
                      ->orWhereRaw('TRIM(name) = ?', [$batchName]);
                })
                ->first();

            // Fallback nama sama di farmasi manapun
            if (!$batch) {
                $batch = Batches::where('medicine_id', $med->id)
                    ->where('name', $batchName)
                    ->first();
            }
        }

        // 3. AUTO-FALLBACK: Ambil batch dengan stok > 0 di cabang ini (prioritas FIFO / ED terdekat)
        if (!$batch) {
            $batch = Batches::where('medicine_id', $med->id)
                ->where('pharmacy_id', $pharmacyId)
                ->where('stock', '>', 0)
                ->orderBy('expired_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->first();
        }

        // 4. Jika semua batch stoknya 0 / minus, ambil batch terbaru yang ada di cabang ini
        if (!$batch) {
            $batch = Batches::where('medicine_id', $med->id)
                ->where('pharmacy_id', $pharmacyId)
                ->orderBy('created_at', 'desc')
                ->first();
        }

        // 5. Jika sama sekali belum pernah ada batch di cabang ini, buat batch default
        if (!$batch) {
            $batch = Batches::create([
                'medicine_id' => $med->id,
                'pharmacy_id' => $pharmacyId,
                'name' => (!empty($item->batch_number) && $item->batch_number !== '-') ? trim($item->batch_number) : ('RET-' . date('ymd')),
                'expired_date' => $item->expired_date ?: now()->addYears(2)->toDateString(),
                'stock' => 0,
                'status' => 1,
            ]);
        }

        return $batch;
    }

    protected function generateLogCode(): string
    {
        $now = Carbon::now();
        $year = $now->format('y');
        $month = $now->format('m');
        $prefix = "{$year}{$month}LOG-";

        $lastCode = ItemsLog::where('code', 'like', "{$prefix}%")
            ->orderBy('code', 'desc')
            ->value('code');

        if ($lastCode) {
            $lastNumber = (int) substr($lastCode, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}

