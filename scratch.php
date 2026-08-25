<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MedicineCart;
use Illuminate\Support\Facades\DB;

$discrepancies = MedicineCart::select('transaction_id')
    ->groupBy('transaction_id')
    ->havingRaw('SUM(final_price) != SUM(total_price)')
    ->limit(5)
    ->get();

if ($discrepancies->isEmpty()) {
    echo "No discrepancies found between final_price and total_price + embalase.\n";
}

foreach ($discrepancies as $d) {
    echo "Transaction ID: {$d->transaction_id}\n";
    $carts = MedicineCart::where('transaction_id', $d->transaction_id)->get();
    foreach ($carts as $c) {
        echo "ID: {$c->id} | Qty: {$c->quantity} | item_price: {$c->item_price} | raw_total: {$c->raw_total} | total_price: {$c->total_price} | final_price: {$c->final_price} | embalase: {$c->embalase} | status: {$c->status} | cart_type: {$c->cart_type}\n";
    }
    
    $sums = MedicineCart::where('transaction_id', $d->transaction_id)
        ->selectRaw('SUM(final_price) as sum_final, SUM(total_price) as sum_total, SUM(embalase) as sum_emb')
        ->first();
    echo "--- SUMS ---\n";
    echo "SUM(final_price) = {$sums->sum_final}\n";
    echo "SUM(total_price) = {$sums->sum_total}\n";
    echo "SUM(embalase) = {$sums->sum_emb}\n\n";
}
