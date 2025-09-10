<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\VideosController;
use App\Http\Controllers\Admin\SlidersController;
use App\Http\Controllers\Admin\ArticlesController;
use App\Http\Controllers\Admin\PermissionsController;
use App\Http\Controllers\Admin\SquadMembersController;
use App\Http\Controllers\Admin\SquadOffcicialController;
use App\Http\Controllers\Admin\TeamsController;
use App\Http\Controllers\Admin\MatchDayController;
use App\Http\Controllers\Admin\MatchController;
use App\Http\Controllers\Admin\ScanningController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandingpageController;
use App\Http\Controllers\SquadController;
use App\Http\Controllers\Admin\TicketingController;
use App\Http\Controllers\Admin\TicketingTransactionController;
use App\Http\Controllers\AdminItemController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemsController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\TransactionReportController;
use App\Models\Item;
use App\Models\TicketTransaction;

Route::get('/', function () {
    return view('auth.login');
});
Route::get('/teamregistration', [LandingpageController::class, 'index'])->name('teamregistration');
Route::post('/teamregister', [LandingpageController::class, 'teamRegister'])->name('teamregister');
Route::get('/teamregistersuccess', [LandingpageController::class, 'teamRegisterSuccess'])->name('teamregister.success');
Route::get('/team/{name}/{code}', [LandingpageController::class, 'squadRegistration'])->name('squadregistration');
Route::post('addsquad/team/{name}/{code}', [SquadController::class, 'SquadOfficialStore'])->name('SquadOfficial.store');
Route::delete('/squad-official/{id}', [SquadController::class, 'SquadOfficialDestroy'])->name('SquadOfficial.destroy');

Route::get('/payment/{slug}/{code}', [LandingpageController::class, 'dpSquadRegister'])->name('dp.payment.squad');

Route::post('addsquadmember/team/{name}/{code}', [SquadController::class, 'SquadMemberStore'])->name('SquadMember.store');
Route::delete('/squad-member/{id}', [SquadController::class, 'SquadMemberDestroy'])->name('SquadMember.destroy');

Route::get('/download/{filename}', [SquadController::class, 'download'])->name('file.download');
Route::post('/upload/{name}/{code}', [SquadController::class, 'upload'])->name('file.upload');

Route::post('/recreate/payment', [SquadController::class, 'recreatePayment'])->name('payment.recreate');
Route::get('/matches', [LandingpageController::class, 'matches'])->name('matches');
Route::get('/ticket', [LandingpageController::class, 'tickets'])->name('ticket');
Route::get('/checkout/{id}', [LandingpageController::class, 'checkout'])->name('checkout');
Route::post('/buyticket/{id}', [LandingpageController::class, 'buyTicket'])->name('buyticket');
Route::get('/checkticket/{code}', [LandingpageController::class, 'checkTicket'])->name('check.ticket');


Auth::routes([
    'register' => false,
    'verify' => false,
]);


Route::middleware(['auth', 'role:administrator'])->group(function () {

    // Admin Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');


    Route::resource('users', UserController::class);
    Route::patch('permissions/sort-module', [PermissionsController::class, 'sortModule'])->name('permissions.sort-module');
    Route::resource('permissions', PermissionsController::class, ['except' => [
        'create',
        'show',
        'edit'
    ]]);
    // Route::get('')
    Route::resource('teams.squadofficials', SquadOffcicialController::class);
    Route::resource('teams.squadmembers', SquadMembersController::class);
    Route::delete('teams/{id}', [TeamsController::class, 'delete'])->name('teams.delete');
    Route::get('teams/download/{id}', [TeamsController::class, 'download'])->name('teams.download');
    Route::get('downloadteam/', [TeamsController::class, 'downloadteam'])->name('allteam.download');
    Route::resource('roles', RolesController::class);
    Route::resource('adminitems', AdminItemController::class);

    Route::resource('teams', TeamsController::class);
    Route::resource('matchday', MatchDayController::class);
    Route::resource('matchday.matches', MatchController::class);
    Route::resource('tickets', TicketController::class);
    Route::get('downloadticket/', [ScanningController::class, 'downloadticket'])->name('tickets.download');
    Route::resource('scanning', ScanningController::class);
    Route::resource('report', TransactionReportController::class);
    Route::post('searchreport/', [TransactionReportController::class, 'searchreport'])->name('searchreport');

});
Route::middleware(['auth', 'role:Kasir'])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    // Global Feature
    Route::prefix('account')->name('account.')->group(function () {
        Route::get('profile/{edit?}', [HomeController::class, 'profile'])->name('profile');
        Route::put('profile/edit', [HomeController::class, 'updateProfile'])->name('profile-update');
    });

    Route::resource('items', ItemsController::class);
    Route::resource('sales', SalesController::class);
    Route::resource('transactions', TicketingTransactionController::class);

    // Route::get('/transaction', [SalesController::class, 'index'])->name('transaction');

    // Initiate New Transaction
    Route::post('/createtransaction/', [SalesController::class, 'createTransaction'])->name('transaction.createnew');
    Route::post('/addtocart', [SalesController::class, 'addToCart'])
    ->name('transaction.addToCart');
    Route::post('removecart', [SalesController::class, 'removeItem'])->name('transaction.removeItem');

    Route::get('/transaction/{slug}/', [SalesController::class, 'index'])->name('transaction');
    Route::post('/transaction/checkout', [SalesController::class, 'checkout'])->name('transaction.checkout');
    Route::post('/gettransaction/', [SalesController::class, 'getTransactionItem'])->name('transaction.getTransactionItem');
    
    // Route::post('addtocart', [SalesController::class, 'addToCart'])->name('sales.addToCart');
    Route::post('moreitem', [SalesController::class, 'moreItem'])->name('sales.moreItem');
    Route::post('lessitem', [SalesController::class, 'lessItem'])->name('sales.lessItem');
    Route::post('removeitem/{id}', [SalesController::class, 'removeItem'])->name('sales.removeItem');
    Route::post('checkoutitem', [SalesController::class, 'checkoutItem'])->name('sales.checkout');
});
Route::get('/products/search', [SalesController::class, 'search'])
    ->middleware('auth') // optional
    ->name('products.search');
