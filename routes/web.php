<?php

use App\Enums\RoleEnum;
use App\Models\ItemPrice;
use App\Models\OracleItem;
use App\Models\OracleProduct;
use App\Models\OracleCustomer;
use App\Models\OracleItemPrice;
use App\Models\OracleOrderLine;
use App\Models\OracleOrderHeader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppController;
use App\Http\Controllers\ProfileController;

Route::get('/', function () {
    // return view('test');
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect('login');
});

Route::prefix('app')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', [AppController::class, 'index'])->name('dashboard');

    Route::get('/products', [AppController::class, 'products'])->name('products.all');

    Route::get('/customers', [AppController::class, 'customers'])->name('customers.all');

    Route::get('/orders', [AppController::class, 'orders'])->name('orders.all');

    Route::get('/users', [AppController::class, 'users'])->name('users.all');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/profile/update-image', [ProfileController::class, 'updateImage'])->name('profile.update.image');
});

Route::get('/testing', function () {
    // Run a simple query to fetch all records from qg_pos_item_master table
    // $results = ItemPrice::all();
    // $results = OracleOrderHeader::all();
    $results = OracleOrderLine::all();
    // $results = OracleItemPrice::all();
    // $results = OracleItem::all();
    // $results = OracleCustomer::all();
    // $results = OracleCustomer::where('customer_id', '2529')->first();
    // $results = OracleCustomer::where('price_list_id', null)->get(['customer_id', 'customer_name']);

    // DB::table('order_items')
    // ->join('items', 'order_items.inventory_item_id', '=', 'items.inventory_item_id')
    // ->join('item_prices', 'items.inventory_item_id', '=', 'item_prices.item_id')
    // ->update([
    //     'order_items.uom' => DB::raw('item_prices.uom')
    // ]);

    // $duplicates = DB::connection('oracle')->table('apps.qg_pos_item_price')
    //     ->select('price_list_id', 'item_id', DB::raw('COUNT(*) as total_count'))
    //     ->groupBy('price_list_id', 'item_id')
    //     ->havingRaw('COUNT(*) > 1')
    //     ->get();

    // // Display the duplicates
    // foreach ($duplicates as $duplicate) {
    //     echo "Price List ID: {$duplicate->price_list_id}, Item ID: {$duplicate->item_id}, Count: {$duplicate->total_count}\n";
    // }

    // $results =  OracleProduct::all();;

    // return auth()->user()->role->isAdmin();

    // return RoleEnum::names();

    // Return the results as JSON for easy viewing
    // return response()->json($results);
});

Route::get('/update-oum', function () {
    DB::table('order_items')
    ->join('items', 'order_items.inventory_item_id', '=', 'items.inventory_item_id')
    ->join('item_prices', 'items.inventory_item_id', '=', 'item_prices.item_id')
    ->update([
        'order_items.uom' => DB::raw('item_prices.uom')
    ]);
});

require __DIR__ . '/auth.php';
