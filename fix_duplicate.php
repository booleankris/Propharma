<?php

/**
 * ============================================================================
 * PROPHARMA - DIRECT STOCK & KARTU STOCK REPAIR SCRIPT
 * ============================================================================
 * Skrip ini menyasar LANGSUNG tabel yang dibaca oleh menu "Kartu Stock":
 * 1. items_log               -> Menghapus log mutasi status 2 duplikat (+2, +2 atau +10, +10)
 * 2. medicine_transfer_items -> Menghapus mutasi etalase berlebih (yang membuat Stok Etalase 6 / 30)
 * 3. medicines.stock         -> Mengoreksi master stok fisik
 * 4. batches.stock           -> Mengoreksi stok batch
 * 5. receiving_items         -> Menghapus item penerimaan faktur duplikat jika masih ada
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
use App\Models\Receiving;
use App\Models\ReceivingDetails;
use App\Models\ReceivingItems;
use Illuminate\Support\Facades\DB;

$code = null;
$medSearch = null;
$isDryRun = in_array('--dry-run', $argv);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $isDryRun = true;
    } else if (str_starts_with($arg, '--medicine=')) {
        $medSearch = trim(substr($arg, 11));
    } else if (!$code && substr($arg, 0, 2) !== '--') {
        $code = trim($arg);
    }
}

if (!$code && !$medSearch) {
    $code = 'NT-26-09/0120';
}

echo "================================================================================\n";
echo "   PROPHARMA - PEMULIHAN PRESISI KARTU STOCK & STOK ETALASE\n";
echo "================================================================================\n";
if ($medSearch) {
    echo "Target Obat   : [{$medSearch}]\n";
} else {
    echo "Target Faktur : [{$code}]\n";
}
echo "Mode          : " . ($isDryRun ? "DRY-RUN (Simulasi)" : "EKSEKUSI NYATA (Live Database)") . "\n";
echo "Waktu         : " . date('Y-m-d H:i:s') . "\n";
echo "--------------------------------------------------------------------------------\n\n";

$rd = null;
$recCode = null;

if (!$medSearch) {
    // 1. Cari ReceivingDetails
    $rd = ReceivingDetails::with(['receiving', 'creditor'])
        ->where('receiving_details_code', $code)
        ->orWhere('invoice_number', $code)
        ->first();

    if (!$rd) {
        echo "[ERROR] Faktur {$code} tidak ditemukan di tabel receiving_details.\n";
        exit(1);
    }

    $recCode = $rd->receiving->code ?? null;
    echo "[INFO] Faktur Ditemukan:\n";
    echo "  - ID Detail          : {$rd->id}\n";
    echo "  - No Terima (NT)     : {$rd->receiving_details_code}\n";
    echo "  - No Faktur          : {$rd->invoice_number}\n";
    echo "  - Kode Transaksi Rec : " . ($recCode ?: '-') . "\n\n";
}

// 2. Cari ItemsLog yang relevan
$logQuery = ItemsLog::where('status', 2)->with(['medicines', 'batches'])->orderBy('id', 'asc');

if ($medSearch) {
    $logQuery->whereHas('medicines', function ($q) use ($medSearch) {
        $q->where('name', 'like', "%{$medSearch}%");
    });
} else {
    $logQuery->where(function ($q) use ($rd, $recCode) {
        $q->whereHas('receiving.receiving_details', function ($rq) use ($rd) {
            $rq->where('id', $rd->id)
               ->orWhere('receiving_details_code', $rd->receiving_details_code)
               ->orWhere('invoice_number', $rd->invoice_number);
        });
        if ($recCode) {
            $q->orWhere('transaction_code', $recCode);
        }
        $q->orWhere('transaction_code', $rd->receiving_details_code)
          ->orWhere('transaction_code', $rd->invoice_number);
    });
}

$allLogs = $logQuery->get();

echo "[1/4] Memeriksa items_log (Kartu Stock)...\n";
echo "Total log pembelian (status 2) terhubung dengan faktur ini: " . $allLogs->count() . " baris.\n\n";

// Kelompokkan log per obat
$logsByMedicine = $allLogs->groupBy('medicine_id');
$dupesToFix = [];

foreach ($logsByMedicine as $medId => $medLogs) {
    $medName = $medLogs->first()->medicines->name ?? "Obat #{$medId}";
    $count = $medLogs->count();

    if ($count <= 1) {
        echo "  [OK] {$medName} (ID: {$medId}) -> {$count} baris log. Normal.\n";
        continue;
    }

    $masterLog = $medLogs->first();
    $duplicateLogs = $medLogs->slice(1);
    $excessQty = (float) $duplicateLogs->sum('qty');

    echo "  [DUPLIKAT] {$medName} (ID: {$medId}) -> Ditemukan {$count} baris log!\n";
    echo "      - Baris Asli (Pertahankan) : ID #{$masterLog->id} (Qty: +{$masterLog->qty}, Saldo: {$masterLog->qty_before} -> {$masterLog->qty_after})\n";
    foreach ($duplicateLogs as $dupLog) {
        echo "      - Baris Duplikat (Hapus)   : ID #{$dupLog->id} (Qty: +{$dupLog->qty}, Kode: {$dupLog->code}, Trans: {$dupLog->transaction_code})\n";
    }
    echo "      - Total Kelebihan Qty      : {$excessQty}\n\n";

    $dupesToFix[$medId] = [
        'medicine_id' => $medId,
        'medicine_name' => $medName,
        'master_log' => $masterLog,
        'duplicate_logs' => $duplicateLogs,
        'excess_qty' => $excessQty,
        'batches_id' => $masterLog->batches_id,
    ];
}

// 3. Periksa juga receiving_items duplikat jika masih ada
$duplicateRI = collect();
if ($rd) {
    $receivingItems = ReceivingItems::where('receiving_details_id', $rd->id)->orderBy('id', 'asc')->get();
    $riGrouped = $receivingItems->groupBy(fn($i) => ($i->order_items->medicine_id ?? 0) . '_' . ($i->batch ?? '0'));
    foreach ($riGrouped as $group) {
        if ($group->count() > 1) {
            $duplicateRI = $duplicateRI->merge($group->slice(1));
        }
    }
}

// 4. Jika tidak ada duplikat di items_log maupun receiving_items
if (empty($dupesToFix) && $duplicateRI->isEmpty()) {
    echo "================================================================================\n";
    echo "[SELESAI] Tidak ditemukan baris duplikat di Kartu Stock maupun Faktur ini.\n";
    echo "================================================================================\n";
    exit(0);
}

// 5. Tampilkan Rincian Koreksi
echo "================================================================================\n";
echo "[2/4] RINCIAN RENCANA PERBAIKAN\n";
echo "================================================================================\n";
printf("%-10s | %-28s | %-12s | %-15s | %-15s\n", "Med ID", "Nama Obat", "Kelebihan", "Hapus Log", "Hapus Mutasi");
echo str_repeat("-", 90) . "\n";
foreach ($dupesToFix as $item) {
    printf("%-10s | %-28s | %-12s | %-15s | %-15s\n",
        $item['medicine_id'],
        substr($item['medicine_name'], 0, 28),
        "-{$item['excess_qty']}",
        $item['duplicate_logs']->count() . " baris",
        "Disesuaikan"
    );
}
echo str_repeat("-", 90) . "\n\n";

if ($isDryRun) {
    echo "[DRY-RUN] Simulasi selesai. Database tidak diubah.\n";
    echo "Jalankan tanpa --dry-run untuk mengeksekusi perbaikan secara nyata:\n";
    echo "  php fix_duplicate.php {$code}\n\n";
    exit(0);
}

// 6. EKSEKUSI DATABASE
echo "[3/4] MENGEKSEKUSI PERBAIKAN DATABASE...\n";
echo "================================================================================\n";

DB::beginTransaction();

try {
    foreach ($dupesToFix as $medId => $item) {
        $medName = $item['medicine_name'];
        $excessQty = $item['excess_qty'];

        echo "Memproses {$medName} (ID: {$medId}):\n";

        // A. HAPUS BARIS DUPLIKAT DI items_log
        $deletedLogIds = [];
        foreach ($item['duplicate_logs'] as $dupLog) {
            $deletedLogIds[] = $dupLog->id;
            $dupLog->delete();
        }
        echo "  1. [items_log] Berhasil menghapus " . count($deletedLogIds) . " log duplikat (ID: " . implode(', ', $deletedLogIds) . ").\n";

        // B. KOREKSI MUTASI ETALASE DI medicine_transfer_items
        // Cari transfer items untuk obat ini di farmasi bersangkutan
        $transferItems = MedicineTransferItems::whereHas('batches', function ($b) use ($medId) {
                $b->where('medicine_id', $medId);
            })
            ->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
            })
            ->orderBy('id', 'desc')
            ->get();

        $curEtalaseStock = $transferItems->sum('qty');
        $qtyToDeduct = $excessQty;
        $deletedTransferCount = 0;

        foreach ($transferItems as $ti) {
            if ($qtyToDeduct <= 0) break;
            if ($ti->qty <= $qtyToDeduct) {
                $qtyToDeduct -= $ti->qty;
                $ti->delete();
                $deletedTransferCount++;
            } else {
                $ti->decrement('qty', $qtyToDeduct);
                $qtyToDeduct = 0;
            }
        }
        $newEtalaseStock = MedicineTransferItems::whereHas('batches', fn($b) => $b->where('medicine_id', $medId))->where('status', 1)->sum('qty');
        echo "  2. [Etalase] Stok etalase dikoreksi: {$curEtalaseStock} -> {$newEtalaseStock} (-{$excessQty}).\n";

        // C. KOREKSI MASTER STOK OBAT
        $medModel = Medicines::find($medId);
        if ($medModel) {
            $oldStock = $medModel->stock;
            $medModel->decrement('stock', $excessQty);
            $newStock = $medModel->fresh()->stock;
            echo "  3. [Medicines] Stok master obat dikurangi: {$oldStock} -> {$newStock} (-{$excessQty}).\n";
        }

        // D. KOREKSI STOK BATCH
        if ($item['batches_id']) {
            $batchModel = Batches::find($item['batches_id']);
            if ($batchModel && $batchModel->stock >= $excessQty) {
                $oldBStock = $batchModel->stock;
                $batchModel->decrement('stock', $excessQty);
                $newBStock = $batchModel->fresh()->stock;
                echo "  4. [Batches #{$batchModel->id}] Stok batch dikurangi: {$oldBStock} -> {$newBStock} (-{$excessQty}).\n";
            }
        }

        echo "\n";
    }

    // E. HAPUS BARIS receiving_items DUPLIKAT JIKA MASIH ADA
    if ($duplicateRI->isNotEmpty()) {
        $countRI = 0;
        foreach ($duplicateRI as $ri) {
            $ri->delete();
            $countRI++;
        }
        echo "  5. [receiving_items] Menghapus {$countRI} baris item faktur duplikat.\n";
    }

    // F. BERSIHKAN HEADER TRANSFER KOSONG
    $emptyHeaders = MedicineTransfers::doesntHave('items')->delete();
    if ($emptyHeaders > 0) {
        echo "  6. [Transfers] Membersihkan {$emptyHeaders} header transfer kosong.\n";
    }

    DB::commit();

    echo "================================================================================\n";
    echo "[BERHASIL 100%] KARTU STOCK & STOK ETALASE TELAH SEPENUHNYA DIPULIHKAN!\n";
    echo "================================================================================\n";
    echo "Silakan refresh halaman 'Kartu Stock' di browser Anda.\n";
    echo "- Baris duplikat di tabel Kartu Stock kini telah hilang (hanya tersisa 1 baris asli).\n";
    echo "- Nilai 'BELI', 'STOK ETALASE', dan 'SALDO' di bagian atas kini telah kembali normal.\n";
    echo "================================================================================\n";
    exit(0);

} catch (\Throwable $e) {
    DB::rollBack();
    echo "\n[ERROR CRITICAL] " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . " in " . $e->getFile() . "\n";
    echo "Seluruh perubahan dibatalkan (Rollback). Database aman.\n";
    exit(1);
}
