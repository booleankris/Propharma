<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(Illuminate\Http\Request::capture());
// let's look at MedicineCart item
$cart = \App\Models\MedicineCart::with('medicine')->latest()->first();
echo json_encode([
    'id' => $cart->id,
    'name' => $cart->medicine->name,
    'unit' => $cart->medicine->unit,
    'packaging' => $cart->medicine->packaging,
]);
