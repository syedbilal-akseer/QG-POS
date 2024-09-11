<?php

use Carbon\Carbon;
use App\Models\Order;
use App\Enums\RoleEnum;
use App\Models\ItemPrice;
use App\Models\OracleItem;
use App\Models\OracleProduct;
use App\Models\OracleCustomer;
use App\Models\OracleItemPrice;
use App\Models\OracleOrderLine;
use App\Models\OracleWarehouse;
use App\Models\OracleOrderHeader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppController;
use App\Http\Controllers\ProfileController;

Route::get('/', function () {
    // return view('test');
    if (Auth::check()) {
        // Check the user's role and redirect accordingly
        if (Auth::user()->role->value === 'supply-chain') {
            return redirect()->route('orders.supply-chain.all');
        }

        return redirect()->route('dashboard');
    }
    return redirect('login');
});

Route::prefix('app')->middleware(['auth'])->group(function () {
    // Apply middleware to restrict access to the orders route
    Route::middleware(['checkRole:supply-chain'])->group(function () {
        Route::get('/supply-chain/orders', [AppController::class, 'orders'])->name('orders.supply-chain.all');
    });

    // Admins have access to all routes including orders
    Route::middleware(['checkRole:admin'])->group(function () {
        Route::get('/dashboard', [AppController::class, 'index'])->name('dashboard');
        Route::get('/products', [AppController::class, 'products'])->name('products.all');
        Route::get('/customers', [AppController::class, 'customers'])->name('customers.all');
        Route::get('/users', [AppController::class, 'users'])->name('users.all');
        Route::get('/orders', [AppController::class, 'orders'])->name('orders.all');
    });
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/profile/update-image', [ProfileController::class, 'updateImage'])->name('profile.update.image');
});

Route::get('/testing', function () {
    // Run a simple query to fetch all records from qg_pos_item_master table
    // $results = OracleWarehouse::all();
    // $results = OracleOrderHeaderIfaceAllDocumentRef::all();
    // $results = OracleOrderLineIfaceAllDocumentRef::all();
    // $results = OracleOrderLineIfaceAllRef::all();
    // $results = ItemPrice::all();
    $results = OracleOrderHeader::all();
    // $results = OracleOrderLine::all();
    // $results = OracleOrderLine::all();
    // $results = OracleItem::all();
    // $results = OracleItemPrice::all();
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

    // $results = DB::connection('oracle')->table('apps.oe_lines_iface_all')
    // ->select('orig_sys_document_ref')  // Specify the column name if needed
    // ->get();


    // // Display the duplicates
    // foreach ($duplicates as $duplicate) {
    //     echo "Price List ID: {$duplicate->price_list_id}, Item ID: {$duplicate->item_id}, Count: {$duplicate->total_count}\n";
    // }

    // $results =  OracleProduct::all();;

    // return auth()->user()->role->isAdmin();

    // return RoleEnum::names();

    // DB::transaction(function () {
    //     // Insert into OracleOrderHeader
    //     $header = OracleOrderHeader::create([
    //         'order_source_id' => 0, // hard coded value
    //         'orig_sys_document_ref' => 300000003,
    //         'org_id' => 104,
    //         'sold_from_org_id' => 104,
    //         'ship_from_org_id' => 121,
    //         'ordered_date' => Carbon::now(),
    //         'order_type_id' => 1011,
    //         'sold_to_org_id' => 1641,
    //         'payment_term_id' => 1004,
    //         'operation_code' => 'INSERT',
    //         'created_by' => 0, // hard coded value
    //         'creation_date' => Carbon::now(),
    //         'last_updated_by' => 0, // hard coded value
    //         'last_update_date' => Carbon::now(),
    //         'customer_po_number' => '300000003',
    //         'ship_to_org_id' => 3396,
    //         'BOOKED_FLAG' => 'Y',
    //     ]);

    //     logger($header);
    //     // Insert into OracleOrderLine
    //     $lines =  OracleOrderLine::create([
    //         'order_source_id' => 0, // hard coded value
    //         'orig_sys_document_ref' => '300000003',
    //         'orig_sys_line_ref' => '300000003-1',
    //         'line_number' => 1,
    //         'inventory_item_id' => 9066,
    //         'ordered_quantity' => 1,
    //         'ship_from_org_id' => 121,
    //         'org_id' => 104,
    //         'unit_selling_price' => 100,
    //         'price_list_id' => null, // assuming you want to skip this
    //         'payment_term_id' => 1004,
    //         'created_by' => 0, // hard coded value
    //         'creation_date' => Carbon::now(),
    //         'last_updated_by' => 0, // hard coded value
    //         'last_update_date' => Carbon::now(),
    //         'line_type_id' => 1009,
    //         'operation_code' => 'INSERT',
    //     ]);

    //     logger($lines);
    // });

    // Return the results as JSON for easy viewing
    return response()->json($results);

    // Order::chunk(100, function ($orders) {
    //     foreach ($orders as $order) {
    //         // Generate a unique order number
    //         do {
    //             $orderNumber = mt_rand(10000000, 99999999);
    //         } while (Order::where('order_number', $orderNumber)->exists());

    //         $order->order_number = $orderNumber;
    //         $order->save();
    //     }
    // });
});

// Route::get('/create-users', function () {
//     $users = [
//         [
//             'name' => 'Kashif Hanif',
//             'email' => 'kashifhanif@quadri-group.com',
//             'password' => bcrypt('Kashif1122@'),
//             'role' => RoleEnum::from('supply-chain')
//         ],
//         [
//             'name' => 'Muhammad Asim',
//             'email' => 'muhammad.asim@quadri-group.com',
//             'password' => bcrypt('MAsim1122@'),
//             'role' => RoleEnum::from('supply-chain')
//         ],
//         [
//             'name' => 'Tajamul Ahmed',
//             'email' => 'tajamul.ahmed@quadri-group.com',
//             'password' => bcrypt('TAhmed1122@'),
//             'role' => RoleEnum::from('supply-chain')
//         ],
//         [
//             'name' => 'Order Management',
//             'email' => 'ome@quadri-group.com',
//             'password' => bcrypt('Quadri1122@'),
//             'role' => RoleEnum::from('supply-chain')
//         ],
//         [
//             'name' => 'SCM',
//             'email' => 'scmexecutiveho@quadri-group.com ',
//             'password' => bcrypt('Quadri1122@'),
//             'role' => RoleEnum::from('supply-chain')
//         ]
//     ];

//     foreach ($users as $user) {
//         App\Models\User::create($user);
//     }

//     return 'Users created successfully.';
// });

require __DIR__ . '/auth.php';
