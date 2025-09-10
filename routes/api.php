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