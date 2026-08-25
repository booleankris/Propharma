<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MedicineCart;

$carts = MedicineCart::selectRaw('transaction_id, SUM(total_price) + SUM(embalase) as total_bought, SUM(final_price) as total_trans')
    ->groupBy('transaction_id')
    ->havingRaw('total_bought = 563000 OR total_trans = 159000')
    ->get();

foreach ($carts as $c) {
    echo "Transaction ID: {$c->transaction_id} | Total Bought: {$c->total_bought} | Total Trans: {$c->total_trans}\n";
}
