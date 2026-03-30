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
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CreditorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Master\CategoriesController;
use App\Http\Controllers\Master\CompositionsController;
use App\Http\Controllers\Master\DebtorsController;
use App\Http\Controllers\Master\DoctorsController;
use App\Http\Controllers\Master\FactoriesController;
use App\Http\Controllers\Master\ItemsController;
use App\Http\Controllers\Master\LocationsController;
use App\Http\Controllers\Master\MedicineController;
use App\Http\Controllers\Master\ParametersController;
use App\Http\Controllers\Master\PatientsController;
use App\Http\Controllers\Orders\OrdersController;
use App\Http\Controllers\Orders\ReceivingController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RejectController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ReturController;
use App\Http\Controllers\Sales\SalesDataController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SuppliesController;
use App\Http\Controllers\TransactionReportController;
use App\Http\Controllers\TransfersController;
use App\Models\Item;
use App\Models\TicketTransaction;
use PhpOffice\PhpSpreadsheet\Calculation\Statistical\Averages\Mean;


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



// Redirect root to login
Route::get('/', function () {
    return redirect()->route('login');
});

// Auth routes (login only)
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

    Route::resource('sales', SalesController::class);

    // Route::get('/transaction', [SalesController::class, 'index'])->name('transaction');

    // Initiate New Transaction
    Route::post('/createtransaction/', [SalesController::class, 'createTransaction'])->name('transaction.createnew');
    Route::post('/addtocart', [SalesController::class, 'addToCart'])
        ->name('transaction.addToCart');
    Route::post('removecart', [SalesController::class, 'removeItem'])->name('transaction.removeItem');

    Route::get('/transaction/{type}/{id?}', [SalesController::class, 'index'])->name('transaction');
    Route::post('/transaction/checkout', [SalesController::class, 'checkout'])->name('transaction.checkout');
    Route::post('/gettransaction/', [SalesController::class, 'getTransactionItem'])->name('transaction.getTransactionItem');
    Route::get('/getcart/cartItem/{id}', [SalesController::class, 'getCartItem']);
    Route::post('/getcart/update-cart', [SalesController::class, 'updateCart'])
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
    Route::get('/print/receipt/{id}', [PrintController::class, 'receipt'])->name('sales.print');
    Route::get('/print/fullreceipt/{id}', [PrintController::class, 'fullReceipt'])->name('salesrecipe.print');


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
    Route::resource('items', ItemsController::class)->except(['show']);
    Route::resource('locations', LocationsController::class)->except(['show']);


    // Master Addition
    Route::get('/medicines/{id}/edit-data', [MedicineController::class, 'editCreditor']);


    // Sales Data
    Route::get('/data/sales', [SalesDataController::class, 'index'])->name('salesdata.index');
    Route::get('/sales/transaction/{id}/items', [SalesDataController::class, 'transactionItems']);


    // Sales Reject
    Route::get('/reject', [RejectController::class, 'reject'])->name('sales.reject');
    Route::get('/getreject', [RejectController::class, 'getReject'])->name('sales.getreject');
    Route::get('reject/searchmedicine', [RejectController::class, 'searchMedicine'])->name('sales.searchmedicine');
    Route::post('reject/additemreject', [RejectController::class, 'addItemReject'])->name('sales.addItemReject');
    Route::post('/postrejecttion', [RejectController::class, 'postRejection'])->name('sales.rejection');

    // Sales Retur
    Route::get('/retur', [ReturController::class, 'retur'])->name('returdata.retur');
    Route::post('/returitem', [ReturController::class, 'returItem'])->name('returdata.returItem');
    Route::get('/salesdata/returdata', [ReturController::class, 'returdata'])->name('returdata.returdata');
    Route::get('/salesdata/getreturmedicine', [ReturController::class, 'getReturMedicines'])->name('returdata.medicines');
    Route::get('retur/batches', [ReturController::class, 'getBatchesByMedicine'])->name('returdata.batches');

    // Orders Retur
    Route::get('/returorder', [ReturController::class, 'returOrders'])->name('returdata.returorders');
    Route::post('/returorderitem', [ReturController::class, 'returOrderItems'])->name('returdata.returorderitems');
    Route::get('/salesdata/returorderdata', [ReturController::class, 'returOrderdata'])->name('returdata.returorderdata');
    Route::get('/salesdata/getreturordermedicine', [ReturController::class, 'getReturOrderMedicines'])->name('returdata.ordermedicines');

    // Supplies
    Route::get('/supplies', [SuppliesController::class, 'supplies'])->name('supplies.index');
    Route::get('/getsupplies', [SuppliesController::class, 'getSupplies'])->name('supplies.getSupplies');
    // Stock Data
    Route::get('/stock-data',        [SuppliesController::class, 'stockData'])->name('supplies.stockData');
    Route::get('/stock-data/get',    [SuppliesController::class, 'getStockData'])->name('supplies.getStockData');
    Route::get('/stock-data/export', [SuppliesController::class, 'exportStock'])->name('supplies.exportStockData');

    Route::get('/stockopname', [SuppliesController::class, 'stockOpname'])->name('supplies.stockOpname');
    Route::get('/getmedicines', [SuppliesController::class, 'getMedicines'])->name('supplies.medicines');
    Route::get('/medicineStockLog', [SuppliesController::class, 'medicineStockLog'])->name('supplies.medicineStockLog');
    Route::post('/saveopname', [SuppliesController::class, 'Opname'])->name('supplies.opname');
    Route::get('/export-stock', [SuppliesController::class, 'printStockData'])->name('supplies.printstockdata');
    Route::get('/export-stockopname', [SuppliesController::class, 'printStockOpname'])->name('supplies.printstockopname');
    // Stock Detail
    Route::get('/stock-detail', [SuppliesController::class, 'stockDetail'])->name('supplies.stockDetail');
    Route::get('/getstockdetail', [SuppliesController::class, 'getStockDetail'])->name('supplies.getStockDetail');

    // Transfers
    Route::get('/transfers/create', [TransfersController::class, 'transfersCreate'])->name('transfers.create');
    Route::post('/transfer', [TransfersController::class, 'transfer'])->name('transfer');
    Route::get('/search/getbatches', [TransfersController::class, 'searchBatches'])->name('search.getbatches');
    Route::get('/etalases',        [TransfersController::class, 'index'])->name('etalases.index');
    Route::post('/etalases',       [TransfersController::class, 'store'])->name('etalases.store');
    Route::put('/etalases/{etalase}', [TransfersController::class, 'update']);
    Route::get('/transfers/incoming', [TransfersController::class, 'incomingTransfers'])->name('transfers.incoming');
    Route::post('/transfers/{transfer}/accept', [TransfersController::class, 'acceptTransfer'])->name('transfers.accept');
    Route::post('/transfers/{transfer}/deny', [TransfersController::class, 'denyTransfer'])->name('transfers.deny');

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
    Route::get('/orders/{id}/creditors', [OrdersController::class, 'getCreditors']);
    Route::get('/orders/{order}/printspb', [OrdersController::class, 'printSPB'])
        ->name('orders.print');
    Route::get('/orders/printorder/{id}', [OrdersController::class, 'printOrder'])->name('orders.printorder');
    Route::get('/orders/print-preview/{order_id}', [OrdersController::class, 'printPreview']);



    // Receiving
    Route::get('/receiving', [ReceivingController::class, 'index'])->name('receiving.index');
    Route::get('/receive/{id}', [ReceivingController::class, 'receive'])->name('receiving.receive');

    Route::get('/createreceiving', [ReceivingController::class, 'createReceiving'])->name('receiving.create');
    Route::get('/searchbpba', [ReceivingController::class, 'searchBPBA'])->name('receiving.searchbpba');
    Route::get('/receiving/getorderitems/items', [ReceivingController::class, 'getOrderItems'])->name('receiving.getorderitems');
    Route::post('/receiving/addreceivingitem', [ReceivingController::class, 'addReceivingItem'])->name('receiving.addreceivingitem');
    Route::get('/receiving/print/{id}', [ReceivingController::class, 'printReceiving']);
    Route::get('/invoice/print/{id}', [ReceivingController::class, 'printInvoice']);

    Route::post('/receiving/completeorder', [ReceivingController::class, 'completeOrder'])
        ->name('receiving.completeOrder');
    Route::get('/receiving/orderlist', [ReceivingController::class, 'orderList'])->name('receiving.orderlist');
    Route::get('/searchreceivingdetails', [ReceivingController::class, 'searchReceivingDetails'])->name('receiving.searchreceivingdetails');
    Route::get('/receiving/history', [ReceivingController::class, 'history'])->name('receiving.history');
    Route::get('/receiving/gethistory', [ReceivingController::class, 'gethistory'])->name('receiving.gethistory');
    Route::get('/receiving/orderhistory', [ReceivingController::class, 'orderhistory'])->name('receiving.orderhistory');
    Route::get('/receiving/getorderhistory', [ReceivingController::class, 'getorderhistory'])->name('receiving.getorderhistory');

    Route::get('/receiving/orders', [ReceivingController::class, 'orders'])->name('receiving.orders');



    Route::get('/compositions/select', [CompositionsController::class, 'select'])->name('composition.select');
    Route::get('/factories/select', [FactoriesController::class, 'select'])->name('factories.select');
    Route::get('/categories/select', [CategoriesController::class, 'select'])->name('categories.select');
    Route::get('/creditors/select', [CreditorsController::class, 'select'])->name('creditors.select');
    Route::get('locations/select', [LocationsController::class, 'select'])->name('locations.select');
    Route::get('/items/select', [ItemsController::class, 'select'])->name('items.select');
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
