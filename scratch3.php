<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/', 'GET');
$response = $kernel->handle($request);

try {
    $export = new \App\Exports\TransfersExport(1, '2026-08-01', '2026-08-25', '', 'semua');
    $export->query()->limit(1)->get();
    echo "Query runs fine directly.\n";
    // Simulate chunking by Excel
    $export->query()->chunk(10, function($results) {
        echo "Chunking works.\n";
    });
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
