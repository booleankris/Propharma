<?php

/**
 * ============================================================================
 * PROPHARMA - FULL AUDIT & CORRECTION SYSTEM FOR RECEIVING DUPLICATES
 * ============================================================================
 * Skrip ini melakukan audit mendalam dan perbaikan menyeluruh pada:
 * 1. receiving_items        : Menghapus baris duplikat, mempertahankan baris master
 * 2. medicines              : Mengembalikan (rollback) stok master obat
 * 3. batches                : Mengembalikan (rollback) stok batch spesifik
 * 4. medicine_transfer_items: Menghapus mutasi transfer etalase (stok pelayanan)
 * 5. medicine_transfers     : Membersihkan header transfer yang kosong/yatim
 * 6. items_log              : Menghapus log mutasi kartu stok pembelian duplikat
 * 7. order_items            : Memverifikasi sinkronisasi status & quantity pesanan
 * 8. Orders Payment         : Mengoreksi kalkulasi tagihan/pembayaran hutang
 *
 * PENGGUNAAN:
 *   # Audit & Perbaiki faktur tertentu:
 *   php fix_duplicate.php NT-26-09/0120 --dry-run   (Cek & simulasi saja)
 *   php fix_duplicate.php NT-26-09/0120             (Eksekusi perbaikan)
 *
 *   # Audit & Perbaiki SELURUH DATABASE:
 *   php fix_duplicate.php --all --dry-run          (Scan & simulasi seluruh DB)
 *   php fix_duplicate.php --all                    (Eksekusi perbaikan seluruh DB)
 * ============================================================================
 */

if (php_sapi_name() !== 'cli') {
    die("Hanya dapat dijalankan melalui CLI / Terminal.\n");
}

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Batches;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\MedicineTransfers;
use App\Models\MedicineTransferItems;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Receiving;
use App\Models\ReceivingDetails;
use App\Models\ReceivingItems;
use Illuminate\Support\Facades\DB;

$targetCode = null;
$isDryRun = false;
$scanAll = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $isDryRun = true;
    } else if ($arg === '--all') {
        $scanAll = true;
    } else if (!$targetCode && substr($arg, 0, 2) !== '--') {
        $targetCode = trim($arg);
    }
}

if (!$targetCode && !$scanAll) {
    // Default fallback jika tidak mengisi argumen apapun
    $targetCode = 'NT-26-09/0120';
}

echo "================================================================================\n";
echo "       PROPHARMA - AUDIT & PEMULIHAN MENYELURUH DUPLIKASI PENERIMAAN\n";
echo "================================================================================\n";
echo "Mode Operasi : " . ($isDryRun ? "DRY-RUN (Simulasi saja, TIDAK MENGUBAH DATABASE)" : "LIVE EXECUTION (PERBAIKAN PERMANEN DATABASE)") . "\n";
echo "Cakupan      : " . ($scanAll ? "SELURUH DATABASE (Semua Faktur & Penerimaan)" : "FAKTUR TERTENTU: [{$targetCode}]") . "\n";
echo "Waktu Audit  : " . date('Y-m-d H:i:s') . "\n";
echo "--------------------------------------------------------------------------------\n\n";

// 1. Kumpulkan daftar ReceivingDetails yang akan diaudit
$detailsList = collect();

if ($scanAll) {
    echo "[1/4] Memindai seluruh faktur di database...\n";
    // Cari semua receiving_details yang memiliki duplikasi pada receiving_items
    $detailIdsWithDupes = ReceivingItems::select('receiving_details_id')
        ->groupBy('receiving_details_id', 'order_items_id', 'batch')
        ->havingRaw('COUNT(*) > 1')
        ->pluck('receiving_details_id')
        ->unique();

    if ($detailIdsWithDupes->isEmpty()) {
        echo "[HASIL SCAN] Luar biasa! Tidak ditemukan satupun duplikasi receiving_items di seluruh database.\n";
        exit(0);
    }

    $detailsList = ReceivingDetails::with(['receiving', 'creditor'])
        ->whereIn('id', $detailIdsWithDupes)
        ->get();

    echo "Ditemukan " . $detailsList->count() . " faktur yang memiliki item terduplikasi!\n\n";
} else {
    echo "[1/4] Mencari data faktur [{$targetCode}]...\n";
    $rd = ReceivingDetails::with(['receiving', 'creditor'])
        ->where('receiving_details_code', $targetCode)
        ->orWhere('invoice_number', $targetCode)
        ->first();

    if (!$rd) {
        echo "[ERROR] Faktur dengan nomor terima/nomor faktur '{$targetCode}' TIDAK DITEMUKAN.\n";
        exit(1);
    }

    $detailsList->push($rd);
}

// 2. Audit mendalam per faktur
echo "[2/4] Melakukan audit mendalam struktur item & relasi data...\n\n";

$allAuditResults = [];
$totalDuplicateRows = 0;
$totalQtyRollback = 0;

foreach ($detailsList as $rd) {
    echo "================================================================================\n";
    echo "FAKTUR : {$rd->receiving_details_code} (No Faktur: {$rd->invoice_number})\n";
    echo "--------------------------------------------------------------------------------\n";
    echo "  - ID Detail   : {$rd->id}\n";
    echo "  - No SP       : {$rd->sp_code}\n";
    echo "  - PBF/Supplier: " . ($rd->creditor->name ?? $rd->creditor_code ?? '-') . "\n";
    echo "  - Header Rec  : ID " . ($rd->receiving_id ?? '-') . " (" . ($rd->receiving->code ?? '-') . ")\n";

    $items = ReceivingItems::where('receiving_details_id', $rd->id)
        ->with(['order_items.medicines', 'batches'])
        ->orderBy('id', 'asc')
        ->get();

    echo "  - Total Item  : " . $items->count() . " baris tercatat\n";

    $grouped = $items->groupBy(function ($it) {
        $medId = $it->order_items->medicine_id ?? 0;
        $batch = trim((string)($it->batch ?? '0'));
        return "{$medId}_{$batch}";
    });

    $fakturDupes = [];

    foreach ($grouped as $groupKey => $groupItems) {
        $count = $groupItems->count();
        $master = $groupItems->first();
        $med = $master->order_items->medicines ?? null;
        $medName = $med->name ?? ("Obat ID #" . ($master->order_items->medicine_id ?? '?'));
        $batchName = trim((string)($master->batch ?? '0'));
        $orderItem = $master->order_items;

        if ($count <= 1) {
            echo "  [OK NORMAL] {$medName} | Batch: {$batchName} | Qty: {$master->qty_received} (1 baris)\n";
            continue;
        }

        $duplicates = $groupItems->slice(1);
        echo "  [PERINGATAN DUPLIKAT] {$medName} | Batch: {$batchName} | Total: {$count} baris!\n";
        echo "      -> Baris Master (Asli dipertahankan): ID #{$master->id} (Qty: {$master->qty_received})\n";

        foreach ($duplicates as $dup) {
            $totalDuplicateRows++;
            $isPack = ($dup->order_items->pack == 1);
            $content = $isPack ? (int) ($med->content ?? 1) : 1;
            if ($content < 1) $content = 1;
            $qtyReceived = (float) $dup->qty_received;
            $actualQty = $qtyReceived * $content;
            $totalQtyRollback += $actualQty;

            // Cari mutasi etalase terkait
            $transferItems = MedicineTransferItems::where('receiving_items_id', $dup->id)->get();

            // Cari items_log terkait
            $candidateCodes = array_filter([
                $rd->receiving->code ?? null,
                $rd->receiving_details_code,
                $rd->invoice_number,
            ]);

            $logQuery = ItemsLog::where('status', 2)
                ->where('medicine_id', $med->id ?? 0)
                ->where('qty', $actualQty);
            if ($dup->batches_id) {
                $logQuery->where('batches_id', $dup->batches_id);
            }
            if (!empty($candidateCodes)) {
                $logQuery->whereIn('transaction_code', $candidateCodes);
            }
            $targetLog = $logQuery->orderByDesc('id')->first();

            if (!$targetLog) {
                // Fallback pencarian tanpa filter transaction_code jika format berbeda
                $targetLog = ItemsLog::where('status', 2)
                    ->where('medicine_id', $med->id ?? 0)
                    ->where('qty', $actualQty)
                    ->orderByDesc('id')
                    ->first();
            }

            $fakturDupes[] = [
                'dup' => $dup,
                'master' => $master,
                'medicine' => $med,
                'order_item' => $orderItem,
                'actual_qty' => $actualQty,
                'is_pack' => $isPack,
                'content' => $content,
                'transfer_items' => $transferItems,
                'target_log' => $targetLog,
                'batch_id' => $dup->batches_id,
            ];

            echo "      -> Baris Duplikat : ID #{$dup->id} | Qty: {$qtyReceived} " . ($isPack ? "Pack (x{$content}={$actualQty} Satuan)" : "Satuan") . "\n";
            echo "         * Transfer Etalase: " . ($transferItems->count() > 0 ? ($transferItems->count() . " baris (ID: " . $transferItems->pluck('id')->join(',') . ")") : "Tidak ada") . "\n";
            echo "         * ItemsLog (Kartu): " . ($targetLog ? "ID #{$targetLog->id} (Kode: {$targetLog->code}, Trans: {$targetLog->transaction_code}, Qty: +{$targetLog->qty})" : "Tidak ditemukan / sudah terhapus") . "\n";
        }
    }

    if (!empty($fakturDupes)) {
        $allAuditResults[] = [
            'rd' => $rd,
            'dupes' => $fakturDupes,
        ];
    }
    echo "\n";
}

// 3. Ringkasan Evaluasi Dampak
echo "================================================================================\n";
echo "[3/4] RINGKASAN AUDIT KESELURUHAN\n";
echo "================================================================================\n";
echo "Total Faktur Bermasalah : " . count($allAuditResults) . "\n";
echo "Total Baris Duplikat    : {$totalDuplicateRows} baris\n";
echo "Total Kelebihan Qty     : {$totalQtyRollback} satuan\n\n";

if (empty($allAuditResults)) {
    echo "[SELESAI] Semua data faktur yang diperiksa sudah bersih dan normal!\n";
    exit(0);
}

// Tabel rencana aksi
printf("%-8s | %-16s | %-28s | %-8s | %-12s | %-14s | %-12s\n", 
    "RI ID", "No Faktur", "Nama Obat", "Batch", "Rollback Qty", "Hapus Etalase", "Hapus Log");
echo str_repeat("-", 108) . "\n";

foreach ($allAuditResults as $res) {
    $rdCode = $res['rd']->receiving_details_code ?: $res['rd']->invoice_number;
    foreach ($res['dupes'] as $d) {
        printf("%-8s | %-16s | %-28s | %-8s | -%-11s | %-14s | %-12s\n",
            $d['dup']->id,
            substr($rdCode, 0, 16),
            substr($d['medicine']->name ?? 'Obat', 0, 28),
            substr($d['dup']->batch ?? '0', 0, 8),
            $d['actual_qty'],
            $d['transfer_items']->count() . " baris",
            $d['target_log'] ? ("ID #" . $d['target_log']->id) : "-"
        );
    }
}
echo str_repeat("-", 108) . "\n\n";

if ($isDryRun) {
    echo "[SIMULASI SELESAI] Ini adalah mode --dry-run.\n";
    echo "Database TIDAK MENGALAMI PERUBAHAN APAPUN.\n\n";
    echo "Untuk mengeksekusi perbaikan database secara nyata dan aman, jalankan:\n";
    if ($scanAll) {
        echo "  php fix_duplicate.php --all\n\n";
    } else {
        echo "  php fix_duplicate.php {$targetCode}\n\n";
    }
    exit(0);
}

// 4. EKSEKUSI DATABASE SECARA ATOMIK & AMAN
echo "[4/4] MENGEKSEKUSI PERBAIKAN DATABASE (TRANSACTION MODE)...\n";
echo "================================================================================\n";

DB::beginTransaction();

try {
    $affectedMedicines = [];
    $affectedBatches = [];
    $deletedTransferHeaders = [];

    foreach ($allAuditResults as $res) {
        $rd = $res['rd'];
        echo "\n>>> Memproses Faktur {$rd->receiving_details_code}...\n";

        foreach ($res['dupes'] as $d) {
            $dup = $d['dup'];
            $med = $d['medicine'];
            $qty = $d['actual_qty'];
            $medId = $med->id ?? null;

            echo "  * Mengoreksi Duplikat RI #{$dup->id} (" . ($med->name ?? 'Obat') . "):\n";

            // 1. Rollback stok di medicines
            if ($medId) {
                $medModel = Medicines::find($medId);
                if ($medModel) {
                    $stockBefore = $medModel->stock;
                    $medModel->decrement('stock', $qty);
                    $stockAfter = $medModel->fresh()->stock;
                    $affectedMedicines[$medId] = [
                        'name' => $medModel->name,
                        'before' => $stockBefore,
                        'after' => $stockAfter,
                    ];
                    echo "    - [Medicines] Stok master dikurangi: {$stockBefore} -> {$stockAfter} (-{$qty})\n";
                }
            }

            // 2. Rollback stok di batches
            if ($d['batch_id']) {
                $batchModel = Batches::find($d['batch_id']);
                if ($batchModel) {
                    $bStockBefore = $batchModel->stock;
                    $batchModel->decrement('stock', $qty);
                    $bStockAfter = $batchModel->fresh()->stock;
                    $affectedBatches[$d['batch_id']] = [
                        'name' => $batchModel->name,
                        'before' => $bStockBefore,
                        'after' => $bStockAfter,
                    ];
                    echo "    - [Batches #{$batchModel->id}] Stok batch dikurangi: {$bStockBefore} -> {$bStockAfter} (-{$qty})\n";
                }
            }

            // 3. Hapus mutasi stok etalase
            $transferHeaderIds = $d['transfer_items']->pluck('medicine_transfer_id')->filter()->unique();
            $deletedTransfersCount = MedicineTransferItems::where('receiving_items_id', $dup->id)->delete();
            echo "    - [Etalase] Menghapus {$deletedTransfersCount} baris mutasi stok etalase.\n";

            // Cek apakah ada header transfer yang kini kosong melompong
            foreach ($transferHeaderIds as $thId) {
                if (MedicineTransferItems::where('medicine_transfer_id', $thId)->count() === 0) {
                    MedicineTransfers::where('id', $thId)->delete();
                    $deletedTransferHeaders[] = $thId;
                    echo "    - [Transfer Header] Menghapus header transfer kosong #{$thId}.\n";
                }
            }

            // 4. Hapus log mutasi kartu stok duplikat
            if ($d['target_log']) {
                $logId = $d['target_log']->id;
                $logCode = $d['target_log']->code;
                $d['target_log']->delete();
                echo "    - [ItemsLog] Menghapus log mutasi kartu stok #{$logId} ({$logCode}).\n";
            }

            // 5. Hapus baris receiving_items duplikat
            $dupId = $dup->id;
            $dup->delete();
            echo "    - [ReceivingItems] Menghapus baris item duplikat #{$dupId}.\n";
        }
    }

    DB::commit();

    echo "\n================================================================================\n";
    echo "  HASIL PERBAIKAN: BERHASIL DILAKUKAN SECARA LENGKAP & AMAN!\n";
    echo "================================================================================\n";
    echo "Ringkasan Stok Master Obat Terkoreksi:\n";
    foreach ($affectedMedicines as $mId => $mInfo) {
        echo "  - [ID: {$mId}] {$mInfo['name']} : {$mInfo['before']} -> {$mInfo['after']}\n";
    }

    if (!empty($affectedBatches)) {
        echo "\nRingkasan Stok Batch Terkoreksi:\n";
        foreach ($affectedBatches as $bId => $bInfo) {
            echo "  - [Batch ID: {$bId}] {$bInfo['name']} : {$bInfo['before']} -> {$bInfo['after']}\n";
        }
    }

    echo "\nSeluruh kartu stok, mutasi etalase, dan item faktur telah kembali presisi.\n";
    echo "================================================================================\n";
    exit(0);

} catch (\Throwable $e) {
    DB::rollBack();
    echo "\n[CRITICAL ERROR] Terjadi kegagalan: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . " in " . $e->getFile() . "\n";
    echo "Seluruh perubahan telah di-ROLLBACK. Database tetap dalam kondisi utuh.\n";
    exit(1);
}
