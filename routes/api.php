<?php

use App\Http\Controllers\Api\MidtransController;
use App\Http\Controllers\LandingpageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/', function () {
    return response([
		'name'    => 'Test API',
		'version' => 'v1'
	]);
});

Route::get('/docs', function () {
    return view('docs.swagger');
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post("/midtrans-feedback",[MidtransController::class,"midtransNotification"]);
Route::post("/scanticket",[LandingpageController::class,"scanTicket"]);
Route::post("/midtrans-ticket-feedback",[MidtransController::class,"midtransNotificationTicket"]);

use App\Http\Controllers\Api\MobileSyncController;

Route::prefix('mobile')->group(function () {
    // Sync & Lookup APIs for Mobile & Web App
    Route::get('/pharmacies', [MobileSyncController::class, 'getPharmacies']);
    Route::get('/medicines/lookup', [MobileSyncController::class, 'lookupMedicine']);
    Route::get('/medicines/by-code/{code}', [MobileSyncController::class, 'getMedicineByCode']);
    Route::get('/medicines', [MobileSyncController::class, 'getMedicines']);

    // Existing Mobile App routes
    Route::get('/products', [MobileSyncController::class, 'getProducts']);
    Route::post('/members/check', [MobileSyncController::class, 'checkMember']);
    Route::post('/members/checkout', [MobileSyncController::class, 'checkoutPoints']);
    Route::get('/members/{phone}/history', [MobileSyncController::class, 'memberHistory']);
    Route::match(['GET', 'POST'], '/transactions', [MobileSyncController::class, 'transactionCheckout']);
    Route::match(['GET', 'POST'], '/transactions/checkout', [MobileSyncController::class, 'transactionCheckout']);
});