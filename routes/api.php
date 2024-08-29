<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Auth\LoginController;

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
                ],
            ], 200);
        });

        Route::post('/update-password', [ProfileController::class, 'updatePassword'])->name('passwordUpdate');
        Route::post('/update-profile-photo', [ProfileController::class, 'updateProfilePhoto'])->name('profilePhotoUpdate');
    });

    // Orders Routes
    Route::prefix('orders')->name('api.orders.')->group(function () {
        Route::post('/all', [ResourceController::class, 'orders'])->name('index');
        Route::post('/place-order', [ResourceController::class, 'placeOrder'])->name('placeOrder');
    });

    // Products Routes
    Route::prefix('products')->name('api.products.')->group(function () {
        Route::post('/all', [ResourceController::class, 'products'])->name('index');
    });

    // Customers Routes
    Route::prefix('customers')->name('api.customers.')->group(function () {
        Route::post('/all', [ResourceController::class, 'customers'])->name('index');
        Route::post('/get', [ResourceController::class, 'getCustomer'])->name('get');
    });
});
