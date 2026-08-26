<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/', 'GET');
$response = $kernel->handle($request);

$search = '';
$startDate = '2026-08-01';
$endDate = '2026-08-25';
$pharmacyId = 1;

$time_start = microtime(true);

$query = \App\Models\MedicineTransferItems::query()
    ->select('medicine_transfer_items.*')
    ->join('medicine_transfers', 'medicine_transfer_items.medicine_transfer_id', '=', 'medicine_transfers.id')
    ->join('users', 'medicine_transfers.user_id', '=', 'users.id')
    ->join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id');

if ($search) {
    $query->join('medicines', 'batches.medicine_id', '=', 'medicines.id');
}

if ($startDate && $endDate) {
    $query->whereBetween('medicine_transfers.created_at', [
        $startDate . ' 00:00:00',
        $endDate . ' 23:59:59'
    ]);
}

if ($search) {
    $query->where('medicines.name', 'like', "%{$search}%");
}

$query->where(function($q) use ($pharmacyId) {
    $q->where('users.pharmacy_id', $pharmacyId)
      ->orWhere('batches.pharmacy_id', $pharmacyId);
});

$results = $query->orderBy('medicine_transfer_items.id', 'desc')->limit(100)->get();

$time_end = microtime(true);
$execution_time = ($time_end - $time_start);
echo 'Total Execution Time: '.$execution_time." Seconds\n";
echo "Count: " . count($results) . "\n";
