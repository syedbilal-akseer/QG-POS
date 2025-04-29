<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\CRM\PlanController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\CRM\MarketVisitReportController;

// ---------------------------- Auth Routes ----------------------------------------
Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout', [LoginController::class, 'logout'])->middleware(['auth:sanctum']);
});

// ---------------------------- Profile Routes ----------------------------------------

Route::middleware(['auth:sanctum'])->group(function () {

    // Profile Routes
    Route::prefix('profile')->name('api.profile.')->group(function () {
        Route::post('/get', function (Request $request) {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'User profile retrieved successfully.',
                'data' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'profile_photo_url' => asset('storage/' . $user->profile_photo),
                    'off_days' => explode(', ', $user->off_days),
                ],
            ], 200);
        });

        Route::post('/update-password', [ProfileController::class, 'updatePassword'])->name('passwordUpdate');
        Route::post('/update-profile-photo', [ProfileController::class, 'updateProfilePhoto'])->name('profilePhotoUpdate');
    });

    // Orders Routes
    Route::prefix('orders')->name('api.orders.')->group(function () {
        Route::get('/all', [OrderController::class, 'orders'])->name('index');
        Route::post('/search', [OrderController::class, 'orderSearch'])->name('orderSearch');
        Route::post('/place-order', [OrderController::class, 'orderPlace'])->name('orderPlace');
        Route::post('/history', [OrderController::class, 'orderHistory'])->name('orderHistory');
        Route::post('/details', [OrderController::class, 'orderDetails'])->name('orderDetails');
        Route::post('/filter', [OrderController::class, 'orderFilter'])->name('orderFilter');
        Route::post('/export', [OrderController::class, 'orderExport'])->name('orderExport');
    });

    // Products Routes
    Route::prefix('products')->name('api.products.')->group(function () {
        Route::get('/all', [ProductController::class, 'products'])->name('index');
        Route::post('/search', [ProductController::class, 'searchProduct'])->name('searchProduct');
        Route::post('/get', [ProductController::class, 'getProduct'])->name('get');
    });

    // Customers Routes
    Route::prefix('customers')->name('api.customers.')->group(function () {
        Route::post('/all', [CustomerController::class, 'customers'])->name('index');
        Route::post('/get', [CustomerController::class, 'getCustomer'])->name('get');
        Route::post('/get-products', [CustomerController::class, 'getCustomerProducts'])->name('getCustomerProducts');
        Route::post('/search', [CustomerController::class, 'searchCustomer'])->name('searchCustomer');
        Route::post('/search/products', [CustomerController::class, 'searchCustomerProducts'])->name('searchCustomerProducts');
        Route::post('/store/customer', [CustomerController::class, 'createCustomer'])->name('createCustomer');
    });

    // CRM Routes
    Route::prefix('crm')->name('api.crm.')->group(function () {
        Route::post('/get-monthly-plans', [PlanController::class, 'monthlyTourPlans'])->name('index');
        Route::post('/get-monthly-plan', [PlanController::class, 'monthlyTourPlan'])->name('get');
        Route::post('/store-monthly-plan', [PlanController::class, 'storeMonthlyTourPlan'])->name('store');
        Route::post('/update-monthly-plan', [PlanController::class, 'updateMonthlyTourPlan'])->name('update');

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
    });
});
