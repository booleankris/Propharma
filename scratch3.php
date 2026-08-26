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

$query = \App\Models\MedicineTransferItems::with([
    'transfer.users.pharmacy',
    'batches.medicines',
    'batches.pharmacy',
    'etalases'
]);

if ($startDate && $endDate) {
    $query->whereIn('medicine_transfer_id', function($q) use ($startDate, $endDate) {
        $q->select('id')->from('medicine_transfers')
          ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    });
}

if ($search) {
    $query->whereIn('batches_id', function($q) use ($search) {
        $q->select('id')->from('batches')
          ->whereIn('medicine_id', function($q2) use ($search) {
              $q2->select('id')->from('medicines')->where('name', 'like', "%{$search}%");
          });
    });
}

$query->where(function($q) use ($pharmacyId) {
    $q->whereIn('medicine_transfer_id', function($sub1) use ($pharmacyId) {
        $sub1->select('id')->from('medicine_transfers')
             ->whereIn('user_id', function($sub2) use ($pharmacyId) {
                 $sub2->select('id')->from('users')->where('pharmacy_id', $pharmacyId);
             });
    })->orWhereIn('batches_id', function($sub3) use ($pharmacyId) {
        $sub3->select('id')->from('batches')->where('pharmacy_id', $pharmacyId);
    });
});

$results = $query->orderBy('id', 'desc')->limit(100)->get();

$time_end = microtime(true);
$execution_time = ($time_end - $time_start);
echo 'Total Execution Time: '.$execution_time." Seconds\n";
echo "Count: " . count($results) . "\n";

try {
    $query->chunkById(10, function($results) {
        echo "Chunking works.\n";
    });
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
