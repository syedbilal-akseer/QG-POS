<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\CRM\PlanController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerReceiptController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\CustomerVisitController;
use App\Http\Controllers\Api\OracleAnalysisController;
use App\Http\Controllers\Api\Auth\LoginController;
// use App\Http\Controllers\Api\MarketVisitReportController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\Api\PdcController;
use App\Http\Controllers\Api\PromotionalItemController;
use App\Http\Controllers\Api\TransporterController;
use App\Http\Controllers\Api\CustomerFormController;
use App\Http\Controllers\Api\InvoiceApiController;
use App\Http\Controllers\Api\BarcodeController;
use App\Http\Controllers\Api\DashboardController;

// ---------------------------- Auth Routes ----------------------------------------
Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout', [LoginController::class, 'logout'])->middleware(['auth:sanctum']);
});

// ---------------------------- Profile Routes ----------------------------------------

// enforceAppVersion runs after Sanctum auth so we can still reject anonymous
// hits with 401 first; outdated authenticated clients get 426 + a structured
// update-required payload (see App\Http\Middleware\EnforceAppVersion).
Route::middleware(['auth:sanctum', 'enforceAppVersion'])->group(function () {

    // Profile Routes
    Route::prefix('profile')->name('api.profile.')->group(function () {
        Route::post('/get', function (Request $request) {
            $user = $request->user();

            // Determine role - special case for nauman_ahmad
            $role = $user->email === 'nauman_ahmad@quadri-group.com' ? 'khi-sales-head' : $user->role;

            // Determine location: 1 = Karachi, 2 = Lahore
            $location = null;
            $khiOuIds = [102, 103, 104, 105, 106]; // Karachi OU IDs
            $lhrOuIds = [108, 109]; // Lahore OU IDs

            if ($user->role === 'user' || $user->role === 'khi-sales-head') {
                // For salesperson users, determine location from their customers' ou_id
                $customerOuId = \App\Models\Customer::where('salesperson', $user->name)
                    ->whereNotNull('ou_id')
                    ->value('ou_id');

                if ($customerOuId) {
                    if (in_array($customerOuId, $khiOuIds)) {
                        $location = 1; // Karachi
                    } elseif (in_array($customerOuId, $lhrOuIds)) {
                        $location = 2; // Lahore
                    }
                }
            } else {
                // For other roles, use organization mapping
                $userOrgs = $user->getOracleOrganizations();

                if (!empty(array_intersect($userOrgs, $khiOuIds))) {
                    $location = 1; // Karachi
                } elseif (!empty(array_intersect($userOrgs, $lhrOuIds))) {
                    $location = 2; // Lahore
                }
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'User profile retrieved successfully.',
                'data' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $role,
                    'location' => $location,
                    'profile_photo_url' => asset('storage/' . $user->profile_photo),
                    'off_days' => explode(', ', $user->off_days),
                ],
            ], 200);
        });

        Route::post('/update-password', [ProfileController::class, 'updatePassword'])->name('passwordUpdate');
        Route::post('/update-profile-photo', [ProfileController::class, 'updateProfilePhoto'])->name('profilePhotoUpdate');
        Route::post('/update-location', [ProfileController::class, 'updateLocation'])->name('locationUpdate');
        Route::post('/fcm-token', [ProfileController::class, 'updateFcmToken'])->name('fcmTokenUpdate');
    });

    // Orders Routes
    Route::prefix('orders')->name('api.orders.')->group(function () {
        Route::get('/all', [OrderController::class, 'orders'])->name('index');
        Route::post('/search', [OrderController::class, 'orderSearch'])->name('orderSearch');
        Route::post('/place-order', [OrderController::class, 'orderPlace'])->name('orderPlace');
        Route::post('/rebook',      [OrderController::class, 'orderRebook'])->name('orderRebook');
        Route::post('/history', [OrderController::class, 'orderHistory'])->name('orderHistory');
        Route::post('/details', [OrderController::class, 'orderDetails'])->name('orderDetails');
        Route::post('/filter', [OrderController::class, 'orderFilter'])->name('orderFilter');
        Route::post('/export', [OrderController::class, 'orderExport'])->name('orderExport');
        Route::post('/cancel-order', [OrderController::class, 'cancelOrder'])->name('cancelOrder');
    });

    // Products Routes
    Route::prefix('products')->name('api.products.')->group(function () {
        Route::get('/all', [ProductController::class, 'products'])->name('index');
        Route::get('/search', [ProductController::class, 'searchProduct'])->name('searchProduct');
        Route::get('/categories', [ProductController::class, 'categories'])->name('categories');
        Route::get('/get', [ProductController::class, 'getProduct'])->name('get');
        Route::post('/clear-cache', [ProductController::class, 'clearProductCache'])->name('clearCache');
    });

    // Customers Routes
    Route::prefix('customers')->name('api.customers.')->group(function () {
        Route::post('/all', [CustomerController::class, 'customers'])->name('index');
        Route::post('/get', [CustomerController::class, 'getCustomer'])->name('get');
        Route::post('/get-products', [CustomerController::class, 'getCustomerProducts'])->name('getCustomerProducts');
        Route::post('/search', [CustomerController::class, 'searchCustomer'])->name('searchCustomer');
        Route::post('/search/products', [CustomerController::class, 'searchCustomerProducts'])->name('searchCustomerProducts');
        Route::post('/store/customer', [CustomerController::class, 'createCustomer'])->name('createCustomer');
        Route::post('/sync', [CustomerController::class, 'syncCustomers'])->name('sync');
    });


    // Receipt History Route (as specifically requested)
    Route::get('/receipt-history', [CustomerReceiptController::class, 'receiptHistory'])->name('api.receipt-history');

    // Customer Receipts Routes
    Route::prefix('customer-receipts')->name('api.customer-receipts.')->group(function () {
        Route::get('/', [CustomerReceiptController::class, 'index'])->name('index');
        Route::post('/', [CustomerReceiptController::class, 'store'])->name('store');
        Route::get('/history', [CustomerReceiptController::class, 'receiptHistory'])->name('history');
        // Static routes MUST come before /{id} parameterised routes so 'cancel'
        // isn't matched as a receipt id.
        Route::post('/cancel', [CustomerReceiptController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/enter-oracle', [CustomerReceiptController::class, 'enterToOracle'])->name('enterOracle');
        Route::get('/{id}', [CustomerReceiptController::class, 'show'])->name('show');
        Route::put('/{id}', [CustomerReceiptController::class, 'update'])->name('update');
        Route::delete('/{id}', [CustomerReceiptController::class, 'destroy'])->name('destroy');
    });

    // Local Banks Routes
    Route::prefix('local-banks')->name('api.local-banks.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\LocalBankController::class, 'index'])->name('index');
        Route::get('/search', [\App\Http\Controllers\Api\LocalBankController::class, 'search'])->name('search');
        Route::get('/select', [\App\Http\Controllers\Api\LocalBankController::class, 'select'])->name('select');
        Route::get('/stats', [\App\Http\Controllers\Api\LocalBankController::class, 'stats'])->name('stats');
        Route::get('/{id}', [\App\Http\Controllers\Api\LocalBankController::class, 'show'])->name('show');
    });

    Route::prefix('banks')->name('api.banks.')->group(function () {
        // SQL Banks (Local Database)
        Route::get('/', [\App\Http\Controllers\Api\BankController::class, 'getBanks'])->name('index');
        Route::get('/search', [\App\Http\Controllers\Api\BankController::class, 'searchBanks'])->name('search');
        Route::get('/select', [\App\Http\Controllers\Api\BankController::class, 'getBanksForSelectDropdown'])->name('select');
        Route::get('/{id}', [\App\Http\Controllers\Api\BankController::class, 'getBank'])->name('show');

        // Oracle Banks (Oracle Database)
        Route::get('/oracle', [\App\Http\Controllers\Api\BankController::class, 'getOracleBanks'])->name('oracle');
        Route::get('/oracle/search', [\App\Http\Controllers\Api\BankController::class, 'searchOracleBanks'])->name('oracle.search');
        Route::get('/oracle/select', [\App\Http\Controllers\Api\BankController::class, 'getBanksForSelect'])->name('oracle.select');
        Route::get('/test-connection', [\App\Http\Controllers\Api\BankController::class, 'testConnection'])->name('test');
        Route::post('/clear-cache', [\App\Http\Controllers\Api\BankController::class, 'clearCache'])->name('clearCache');
    });


    // Oracle Testing Routes
    Route::prefix('oracle-test')->name('api.oracle-test.')->group(function () {
        Route::get('/connection', [\App\Http\Controllers\Api\TestOracleController::class, 'testOracleConnection'])->name('connection');
        Route::get('/schema-info', [\App\Http\Controllers\Api\TestOracleController::class, 'getOracleSchemaInfo'])->name('schema');
        Route::post('/create-sample-banks', [\App\Http\Controllers\Api\TestOracleController::class, 'createSampleBankData'])->name('sample-banks');
    });

    // CRM Routes
    Route::prefix('crm')->name('api.crm.')->group(function () {
        Route::post('/get-monthly-plans', [PlanController::class, 'monthlyTourPlans'])->name('index');
        Route::post('/get-monthly-plan', [PlanController::class, 'monthlyTourPlan'])->name('get');
        Route::post('/store-monthly-plan', [PlanController::class, 'storeMonthlyTourPlan'])->name('store');
        Route::post('/update-monthly-plan', [PlanController::class, 'updateMonthlyTourPlan'])->name('update');

        /*
        // Retrieve all Market Visit Reports
        Route::post('/get-market-visit-reports', [MarketVisitReportController::class, 'monthlyVisitReports']);

        // Retrieve a specific Market Visit Report
        Route::post('/get-market-visit-report', [MarketVisitReportController::class, 'monthlyVisitReport']);

        // Retrieve a specific Visit for a specific Market Visit Report
        Route::post('/market-visit-report/visit', [MarketVisitReportController::class, 'monthlyVisitReportVisit']);

        // Add a new Market Visit Report with Visits
        Route::post('/store-market-visit-report', [MarketVisitReportController::class, 'addMarketVisitReport']);

        // Update a Market Visit Report
        Route::post('/update-market-visit-report', [MarketVisitReportController::class, 'updateMarketVisitReport']);

        // Retrieve all Expenses for a specific Visit
        Route::post('/visit/get-expenses', [MarketVisitReportController::class, 'visitExpenses']);

        // Retrieve a specific Expense for a specific Visit
        Route::post('/visit/expense/', [MarketVisitReportController::class, 'visitExpense']);

        // Add a new expense to a visit
        Route::post('/store-visit/expenses', [MarketVisitReportController::class, 'addVisitExpense']);

        // Update an expense for a visit
        Route::post('/update-visit/expense', [MarketVisitReportController::class, 'updateVisitExpense']);
        */
    });

    // Attendance Routes
    Route::prefix('attendance')->name('api.attendance.')->group(function () {
        Route::post('/mark', [AttendanceController::class, 'markAttendance'])->name('mark');
        Route::get('/my-attendance', [AttendanceController::class, 'myAttendance'])->name('my-attendance');
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        Route::get('/summary', [AttendanceController::class, 'summary'])->name('summary');
        Route::get('/{id}', [AttendanceController::class, 'show'])->name('show');
        Route::put('/{id}', [AttendanceController::class, 'update'])->name('update');
        Route::delete('/{id}', [AttendanceController::class, 'destroy'])->name('delete');
    });

    // Leave Routes
    Route::prefix('leave')->name('api.leave.')->group(function () {
        Route::post('/mark', [LeaveController::class, 'markLeave'])->name('mark');
        Route::get('/', [LeaveController::class, 'index'])->name('index');
        Route::get('/summary', [LeaveController::class, 'summary'])->name('summary');
        Route::get('/{id}', [LeaveController::class, 'show'])->name('show');
        Route::put('/{id}', [LeaveController::class, 'update'])->name('update');
        Route::delete('/{id}', [LeaveController::class, 'destroy'])->name('delete');
        Route::patch('/{id}/status', [LeaveController::class, 'updateStatus'])->name('update-status');
    });

    // Customer Visit Routes
    Route::prefix('customer-visits')->name('api.customer-visits.')->group(function () {
        Route::post('/visit-mark', [CustomerVisitController::class, 'visitMark'])->name('visit-mark');
        Route::post('/start', [CustomerVisitController::class, 'startVisit'])->name('start');
        Route::post('/{visit}/end', [CustomerVisitController::class, 'endVisit'])->name('end');
        Route::post('/{visit}/cancel', [CustomerVisitController::class, 'cancel'])->name('cancel');
        Route::get('/current', [CustomerVisitController::class, 'getCurrentVisit'])->name('current');
        Route::get('/statistics', [CustomerVisitController::class, 'statistics'])->name('statistics');
        Route::get('/salespersons', [CustomerVisitController::class, 'getSalespersons'])->name('salespersons');
        Route::get('/', [CustomerVisitController::class, 'index'])->name('index');
        Route::get('/{visit}', [CustomerVisitController::class, 'show'])->name('show');
        Route::put('/{visit}', [CustomerVisitController::class, 'update'])->name('update');
    });

    // Oracle Analysis Routes (for data structure analysis)
    Route::prefix('oracle-analysis')->name('api.oracle-analysis.')->group(function () {
        Route::get('/shipping-users', [OracleAnalysisController::class, 'getShippingUsers'])->name('shipping-users');
        Route::get('/all-users', [OracleAnalysisController::class, 'getAllUsers'])->name('all-users');
        Route::get('/customer-data', [OracleAnalysisController::class, 'getCustomerData'])->name('customer-data');
        Route::get('/product-data', [OracleAnalysisController::class, 'getProductData'])->name('product-data');
        Route::get('/price-list-data', [OracleAnalysisController::class, 'getPriceListData'])->name('price-list-data');
        Route::get('/warehouse-data', [OracleAnalysisController::class, 'getWarehouseData'])->name('warehouse-data');
        Route::get('/user-customer-relationships', [OracleAnalysisController::class, 'getUserCustomerRelationships'])->name('user-customer-relationships');
    });

    // Price List API Routes
    Route::prefix('price-lists')->name('api.price-lists.')->group(function () {
        Route::get('/inventory-item-id', [PriceListController::class, 'getInventoryItemId'])->name('inventory-item-id');
    });

    // PDC (Post Dated Checks) Routes
    Route::prefix('pdc')->name('api.pdc.')->group(function () {
        Route::get('/summary', [PdcController::class, 'index'])->name('summary');
        Route::get('/details', [PdcController::class, 'details'])->name('details');
        Route::get('/search-cheque', [PdcController::class, 'searchCheque'])->name('search-cheque');
        Route::post('/return', [PdcController::class, 'submitReturn'])->name('return');
    });

        // -----------------------------------------------------------------------
    // Customer Forms Routes (HBM visitor form & Sales form)
    // form_type: 'HBM' | 'sales'
    // -----------------------------------------------------------------------
    Route::prefix('customer-forms')->name('api.customer-forms.')->group(function () {
        Route::get('/', [CustomerFormController::class, 'index'])->name('index');           // list (filter by form_type)
        Route::post('/store', [CustomerFormController::class, 'store'])->name('store');    // create
        Route::post('/search', [CustomerFormController::class, 'search'])->name('search'); // search
        Route::get('/{id}', [CustomerFormController::class, 'show'])->name('show');        // single record
        Route::put('/{id}', [CustomerFormController::class, 'update'])->name('update');    // update
        Route::delete('/{id}', [CustomerFormController::class, 'destroy'])->name('destroy'); // delete
        // Share a SINGLE filled form via WhatsApp text
        Route::post('/{id}/share-whatsapp', [CustomerFormController::class, 'shareFormViaWhatsApp'])->name('shareFormWhatsApp');
    });

    // Customer Form Events — mobile flow: pick event → list/add forms / export / share.
    Route::prefix('customer-form-events')->name('api.customer-form-events.')->group(function () {
        Route::get('/',                              [CustomerFormController::class, 'listEvents'])->name('list');
        Route::get('/{eventId}/forms',               [CustomerFormController::class, 'listEventForms'])->name('forms');
        Route::get('/{eventId}/export',              [CustomerFormController::class, 'exportEvent'])->name('export');
        Route::post('/{eventId}/share-whatsapp',     [CustomerFormController::class, 'shareEventViaWhatsApp'])->name('shareWhatsApp');
    });


    // Promotional Items Routes
    Route::prefix('promotional-items')->name('api.promotional-items.')->group(function () {
        Route::get('/all', [PromotionalItemController::class, 'index'])->name('index');
    });

    // Transporters Routes
    Route::prefix('transporters')->name('api.transporters.')->group(function () {
        Route::get('/', [TransporterController::class, 'index'])->name('index');
    });

    Route::prefix('invoices')->name('api.invoices.')->group(function () {
        Route::get('/', [InvoiceApiController::class, 'index'])->name('index');                    // all (scoped)
        Route::match(['get', 'post'], '/by-customer', [InvoiceApiController::class, 'byCustomer'])->name('byCustomer'); // by customer_code
        Route::match(['get', 'post'], '/search', [InvoiceApiController::class, 'search'])->name('search');           // search
        Route::post('/upload-builty', [InvoiceApiController::class, 'uploadBuilty'])->name('uploadBuilty'); // attach builty PDF
        Route::get('/{id}/download', [InvoiceApiController::class, 'download'])->name('download'); // PDF download (auto-merges builty if attached)
        Route::get('/{id}', [InvoiceApiController::class, 'show'])->name('show');                  // single invoice
    });

    // Mobile dashboard — per-salesperson + (admin) company aggregates.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('api.dashboard');

    // QR scanner endpoint for the mobile app.
    // Body: { "code": "<scanned QR JSON>" } — see BarcodeResolverService for accepted formats.
    Route::post('/scan/resolve', [BarcodeController::class, 'resolve'])->name('api.scan.resolve');

    // Get the QR JSON payload(s) for an item × level (mobile renders QRs client-side).
    // GET /api/scan/qr/0071-0009                  → all 4 levels
    // GET /api/scan/qr/0071-0009/CARTON           → just CARTON
    // GET /api/scan/qr/0071-0009/4                → level 4
    Route::get('/scan/qr/{itemCode}/{level?}', [BarcodeController::class, 'qr'])
        ->where('itemCode', '.*')
        ->name('api.scan.qr');
});
