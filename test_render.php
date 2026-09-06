<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first();
auth()->login($user);

$transfer = App\Models\MedicineTransfers::create(['code' => 'TEST-001', 'user_id' => $user->id, 'status' => 2]);

$denied = App\Models\MedicineTransfers::where('id', $transfer->id)->paginate(10);
$pending = App\Models\MedicineTransfers::where('id', 0)->paginate(10);
$accepted = App\Models\MedicineTransfers::where('id', 0)->paginate(10);

try {
    $out = view('kasir.transfers.transfers', compact('pending', 'accepted', 'denied'))->render();
    if (strpos($out, 'TEST-001') !== false) {
        echo "RENDERED SUCCESSFULLY\n";
    } else {
        echo "NOT RENDERED\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
$transfer->delete();
