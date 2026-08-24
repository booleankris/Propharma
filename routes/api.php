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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post("/midtrans-feedback",[MidtransController::class,"midtransNotification"]);
Route::post("/scanticket",[LandingpageController::class,"scanTicket"]);
Route::post("/midtrans-ticket-feedback",[MidtransController::class,"midtransNotificationTicket"]);

use App\Http\Controllers\Api\MobileSyncController;

Route::prefix('mobile')->group(function () {
    Route::get('/products', [MobileSyncController::class, 'getProducts']);
    Route::post('/members/check', [MobileSyncController::class, 'checkMember']);
    Route::post('/members/checkout', [MobileSyncController::class, 'checkoutPoints']);
    Route::get('/members/{phone}/history', [MobileSyncController::class, 'memberHistory']);
    Route::post('/transactions/checkout', [MobileSyncController::class, 'transactionCheckout']);
});