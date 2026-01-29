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
use App\Http\Controllers\Master\CreditorsController;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandingpageController;
use App\Http\Controllers\SquadController;
use App\Http\Controllers\Admin\TicketingController;
use App\Http\Controllers\Admin\TicketingTransactionController;
use App\Http\Controllers\AdminItemController;
use App\Http\Controllers\CreditorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemsController;
use App\Http\Controllers\Master\CategoriesController;
use App\Http\Controllers\Master\CompositionsController;
use App\Http\Controllers\Master\DebtorsController;
use App\Http\Controllers\Master\DoctorsController;
use App\Http\Controllers\Master\FactoriesController;
use App\Http\Controllers\Master\MedicineController;
use App\Http\Controllers\Master\ParametersController;
use App\Http\Controllers\Master\PatientsController;
use App\Http\Controllers\Orders\OrdersController;
use App\Http\Controllers\Orders\ReceivingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\Sales\SalesDataController;
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

    // Route::get('/transaction', [SalesController::class, 'index'])->name('transaction');

    // Initiate New Transaction
    Route::post('/createtransaction/', [SalesController::class, 'createTransaction'])->name('transaction.createnew');
    Route::post('/addtocart', [SalesController::class, 'addToCart'])
        ->name('transaction.addToCart');
    Route::post('removecart', [SalesController::class, 'removeItem'])->name('transaction.removeItem');

    Route::get('/transaction/{slug}/', [SalesController::class, 'index'])->name('transaction');
    Route::post('/transaction/checkout', [SalesController::class, 'checkout'])->name('transaction.checkout');
    Route::post('/gettransaction/', [SalesController::class, 'getTransactionItem'])->name('transaction.getTransactionItem');
    Route::get('/transaction/cartItem/{id}', [SalesController::class, 'getCartItem']);
    Route::get('/transaction/cartItem/{id}', [SalesController::class, 'getCartItem']);
    Route::post('/transaction/update-cart', [SalesController::class, 'updateCart'])
        ->name('transaction.updateCart');

    // ------ Modal Data On Transaction (For Modal) -----


    // Medicine Data 
    Route::get('/transactions/getmedicinemaster', [SalesController::class, 'openMedicineMaster']);

    // Transaction Data
    Route::get('/transactions/gettransactiondata', [SalesController::class, 'openTransactionData']);
    Route::get('/transactions/{id}/items', [SalesController::class, 'getTransactionItems'])->name('transactions.items');


    // ------ Modal Data On Transaction (For Modal) -----

    Route::delete('/transaction/cartItem/{id}', [SalesController::class, 'deleteCartItem'])
        ->name('transaction.cartItem.delete');

    // Route::post('addtocart', [SalesController::class, 'addToCart'])->name('sales.addToCart');
    Route::post('moreitem', [SalesController::class, 'moreItem'])->name('sales.moreItem');
    Route::post('lessitem', [SalesController::class, 'lessItem'])->name('sales.lessItem');
    Route::post('removeitem/{id}', [SalesController::class, 'removeItem'])->name('sales.removeItem');
    Route::post('checkoutitem', [SalesController::class, 'checkoutItem'])->name('sales.checkout');
    Route::post('sendembalase', [SalesController::class, 'sendEmbalase'])->name('sales.sendembalase');
    Route::post('deleteTransaction', [SalesController::class, 'deleteTransaction'])->name('sales.deletetransaction');


    // ================================================================== Add Data =========================================================================
    Route::post('/addpatient', [SalesController::class, 'addPatient'])
        ->name('transaction.addPatient');
    Route::post('/adddoctor', [SalesController::class, 'addDoctor'])
        ->name('transaction.addDoctor');

    // ================================================================== Add Data =========================================================================

    // Master
    Route::resource('creditors', CreditorsController::class)->except(['show']);
    Route::resource('debtors', DebtorsController::class)->except(['show']);
    Route::resource('patients', PatientsController::class)->except(['show']);
    Route::resource('doctors', DoctorsController::class)->except(['show']);
    Route::resource('compositions', CompositionsController::class)->except(['show']);
    Route::resource('factories', FactoriesController::class)->except(['show']);
    Route::resource('categories', CategoriesController::class)->except(['show']);
    Route::resource('medicines', MedicineController::class)->except(['show']);
    Route::resource('parameters', ParametersController::class)->except(['show']);

    // Sales Data
    Route::get('/data/sales', [SalesDataController::class, 'index'])->name('salesdata.index');
    Route::get('/retur', [SalesDataController::class, 'retur'])->name('salesdata.retur');
    Route::post('/returitem', [SalesDataController::class, 'returItem'])->name('salesdata.returItem');

    Route::get('/sales/transaction/{id}/items', [SalesDataController::class, 'transactionItems']);
    Route::get('/salesdata/returdata', [SalesDataController::class, 'returdata'])->name('salesdata.returdata');
    Route::get('/salesdata/getreturmedicine', [SalesDataController::class, 'getReturMedicines'])->name('salesdata.retur.medicines');

    // Reports  
    Route::get('/reports/transactions', [ReportsController::class, 'transactions'])->name('reports.transactions');
    Route::get('/reports/medicines', [ReportsController::class, 'medicines'])->name('reports.medicines');
    Route::get('/reports/doctors', [ReportsController::class, 'doctors'])->name('reports.doctors');
    Route::get('/reports/patients', [ReportsController::class, 'patients'])->name('reports.patients');

    // Patient Export
    Route::post('/reports/export/patients', [ReportsController::class, 'exportPatients'])
        ->name('reports.export.patients');

    // Check Export Status
    Route::get('/reports/export/status/{id}', [ReportsController::class, 'exportStatus'])
        ->name('reports.export.status');

    // Transaction Export
    Route::post('/reports/export/transactions', [ReportsController::class, 'exportTransactions'])
        ->name('reports.export.transactions');

    Route::get('/reports/export/transactions/status/{id}', [ReportsController::class, 'transactionExportStatus'])
        ->name('reports.export.transactions.status');

    Route::get('/reports/export/transactions/download/{id}', [ReportsController::class, 'transactionExportDownload'])
        ->name('reports.export.transactions.download');

    // Medicine Export
    Route::post('/reports/export/medicines', [ReportsController::class, 'exportMedicines']);
    Route::get('/reports/export/medicines/status/{id}', [ReportsController::class, 'exportMedicinesStatus']);
    Route::get('/reports/export/medicines/download/{id}', [ReportsController::class, 'exportMedicinesDownload']);

    // Order
    Route::get('/createorder', [OrdersController::class, 'createOrder'])->name('orders.create');
    Route::get('/orders', [OrdersController::class, 'order'])->name('orders.order');
    Route::post('/orders/additemorder', [OrdersController::class, 'addItemOrder'])->name('orders.addItemOrder');
    Route::get('/orders/search', [OrdersController::class, 'searchMedicine'])->name('orders.searchmedicine');
    Route::get('/orders/items', [OrdersController::class, 'orderItems'])
    ->name('orders.orderitems');
    Route::post('/orders/updateitems', [OrdersController::class, 'updateOrderItem'])
    ->name('orders.updateOrderItem');
    Route::post('/orders/deleteitems', [OrdersController::class, 'deleteOrderItem'])
    ->name('orders.deleteOrderItem');
    Route::post('/orders/completeorder', [OrdersController::class, 'completeOrder'])
    ->name('orders.completeOrder');

    // Receiving
    Route::get('/receiving', [ReceivingController::class, 'index'])->name('receiving.index');
    Route::get('/createreceiving', [ReceivingController::class, 'createReceiving'])->name('receiving.create');





    Route::get('/compositions/select', [CompositionsController::class, 'select'])->name('composition.select');
    Route::get('/factories/select', [FactoriesController::class, 'select'])->name('factories.select');
    Route::get('/categories/select', [CategoriesController::class, 'select'])->name('categories.select');
    Route::get('/creditors/select', [CreditorsController::class, 'select'])->name('creditors.select');
});


Route::get('/products/search', [SalesController::class, 'search'])
    ->middleware('auth')
    ->name('products.search');

Route::get('/debtors/search', [SalesController::class, 'searchDebtors'])
    ->middleware('auth')
    ->name('debtors.search');

Route::get('/patients/search', [SalesController::class, 'searchPatients'])
    ->middleware('auth')
    ->name('patients.search');

Route::get('/doctors/search', [SalesController::class, 'searchDoctors'])
    ->middleware('auth')
    ->name('doctors.search');
