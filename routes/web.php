<?php

use App\Livewire\CRM;
use App\Models\Order;
use App\Livewire\ListUsers;
use App\Livewire\ListOrders;
use App\Models\OracleProduct;
use App\Livewire\ListProducts;
use App\Models\OracleCustomer;
use App\Livewire\ListCustomers;
use App\Models\OracleOrderLine;
use App\Models\OracleOrderType;
use App\Models\OracleWarehouse;
use App\Models\OracleOrderHeader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppController;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\OrderRecieptsController;



Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        
        // Check if user is already on the appropriate page to prevent infinite redirects
        if ($user->role->name === 'supply-chain' && !request()->is('app/supply-chain/orders*')) {
            return redirect()->route('orders.supply-chain.all');
        }

        if ($user->role->name === 'user' && !request()->is('app/monthly-tour-plans*')) {
            return redirect()->route('monthlyTourPlans.all');
        }

        // If the user is an admin or any other role
        if (!in_array($user->role->name, ['supply-chain', 'user'])) {
            return redirect()->route('dashboard');
        }
    }

    return redirect('login');
});



Route::prefix('app')->middleware(['auth'])->group(function () {
    // Apply middleware to restrict access to the orders route
    Route::middleware(['checkRole:supply-chain'])->group(function () {
        Route::get('/supply-chain/orders', ListOrders::class)->name('orders.supply-chain.all');
    });

    // Admins have access to all routes including orders
    Route::middleware(['checkRole:admin'])->group(function () {
        Route::get('/dashboard', [AppController::class, 'index'])->name('dashboard');
        Route::get('/products', ListProducts::class)->name('products.all');
        Route::get('/customers', ListCustomers::class)->name('customers.all');
        Route::get('/users', ListUsers::class)->name('users.all');
        Route::get('/orders', ListOrders::class)->name('orders.all');
        Route::get('/reciepts', [OrderRecieptsController::class, 'index'])->name('reciepts');
    });

    Route::get('/sales-teams', CRM\ListSalesTeam::class)->name('salesteam.all');

    Route::get('/manage-tour-plans', CRM\Manage\MonthlyTourPlanApproval::class)->name('manage.tourplans');

    Route::get('/monthly-tour-plans', CRM\MonthlyPlan\ListMonthlyTourPlans::class)->name('monthlyTourPlans.all');
    Route::get('/monthly-tour-plans-old', CRM\MonthlyPlan\OldListMonthlyTourPlans::class)->name('oldMonthlyTourPlans.all');
    Route::get('/plan-details/{monthlyTourPlan}', CRM\MonthlyPlan\PlanDetails::class)->name('monthlyTourPlans.planDetails');
    Route::get('/plan/{monthlyTourPlan?}', CRM\MonthlyPlan\CreatePlan::class)->name('monthlyTourPlans.addNewPlan');
    Route::get('/day-tour-plan/{dayTourPlan}', CRM\MonthlyPlan\DayTourDetails::class)->name('dayTourPlans.details');

    // Visits Routes
    Route::get('/visits', CRM\Visit\ManageVisit::class)->name('visits.all');
    Route::get('/plan/mvr/{dayTourPlan?}/{visitId?}', CRM\Visit\CreateMvr::class)->name('visit.createMvr');
    Route::get('/visits-reports/{plan}', CRM\Visit\VisitReportDetails::class)->name('visit.reportDetails');
    Route::get('/visit-details/{visit}', CRM\Visit\VisitDetails::class)->name('visit.details');
    Route::get('/visit/{visit}/expenses', CRM\Visit\ViewVisitExpenses::class)->name('visit.viewExpenses');

    // Expenses Routes
    Route::get('/expense-detail/{expense}', CRM\Expenses\ExpenseDetail::class)->name('expense.details');
    Route::get('/add-expense/{visit}/{expenseId?}', CRM\Expenses\AddExpense::class)->name('expense.addExpense');



    Route::get('/notifications/unread-count', function () {
        return response()->json([
            'count' => auth()->user()->unreadNotifications->count(),
        ]);
    })->name('app.notifications.unread');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/profile/update-image', [ProfileController::class, 'updateImage'])->name('profile.update.image');
});

Route::get('/run-commands', function () {
    if (request('token') !== "my-unique-token") {
        abort(403, 'Unauthorized');
    }
    logger('Running');
    Artisan::call('schedule:run');

    logger('Done');
    if (app()->environment('local')) {
        // Return output only in development
        return response()->json(['output' => Artisan::output()]);
    }

    return response()->json(['message' => 'Scheduled tasks executed successfully.']);
});

Route::get('/testing', function () {
    // Run a simple query to fetch all records from qg_pos_item_master table
    // $results = OracleWarehouse::all();
    // $results = OracleOrderType::all();
    // $results = OracleOrderHeaderIfaceAllDocumentRef::all();
    // $results = OracleOrderLineIfaceAllDocumentRef::all();
    // $results = OracleOrderLineIfaceAllRef::all();
    // $results = ItemPrice::all();
    // $results = OracleOrderHeader::where('customer_po_number', '49849862')->first();
    // $results = OracleOrderHeader::all();
    // $results = OracleOrderLine::all();
    // $results = OracleOrderLine::all();
    // $results = OracleItem::all();
    // $results = OracleItemPrice::all();
    $results = OracleCustomer::all();
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
    //         // 'ship_from_org_id' => 121,
    //         'ordered_date' => Carbon::now(),
    //         'order_type_id' => 1011,
    //         'sold_to_org_id' => 1641,// c id
    //         'payment_term_id' => 1004,
    //         'operation_code' => 'INSERT',
    //         'created_by' => 0, // hard coded value
    //         'creation_date' => Carbon::now(),
    //         'last_updated_by' => 0, // hard coded value
    //         'last_update_date' => Carbon::now(),
    //         'customer_po_number' => '300000003',
    //         'ship_to_org_id' => 3396, // s id
    //         'BOOKED_FLAG' => 'Y',
    //     ]);

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

    // $results = DB::connection('oracle')->table('apps.oe_lines_iface_all')
    //     ->select('inventory_item_id', 'ordered_quantity', 'unit_selling_price')
    //     ->where('orig_sys_document_ref', 'like', '%202569%')
    //     ->get();


    // Return the results as JSON for easy viewing
    return response()->json($results); 
});

Route::get('oracle/order/{orderNumber}', function ($orderNumber) {
    // $response = OracleOrderHeader::with(['orderLines'])->where('customer_po_number', $orderNumber)->first();

    $result = DB::connection('oracle')->table('apps.oe_headers_iface_all as headers')
        ->join('apps.oe_lines_iface_all as lines', 'headers.orig_sys_document_ref', '=', 'lines.orig_sys_document_ref')
        ->select(
            'headers.orig_sys_document_ref',
            'headers.org_id',
            'headers.order_type_id',
            'headers.ordered_date',
            'lines.line_type_id',
            'lines.line_number',
            'lines.inventory_item_id',
            'lines.ordered_quantity',
            'lines.unit_selling_price',
            'lines.order_quantity_uom',
        )
        ->where('headers.orig_sys_document_ref', $orderNumber)
        ->get();

    // Transform the results
    $response = [
        'header' => [
            'orig_sys_document_ref' => $result->first()->orig_sys_document_ref ?? null,
            'org_id' => $result->first()->org_id ?? null,
            'order_type_id' => $result->first()->order_type_id ?? null,
            'ordered_date' => $result->first()->ordered_date ?? null,
        ],
        'lines' => $result->map(function ($item) {
            return [
                'line_type_id' => $item->line_type_id,
                'line_number' => $item->line_number,
                'inventory_item_id' => $item->inventory_item_id,
                'ordered_quantity' => $item->ordered_quantity,
                'unit_selling_price' => $item->unit_selling_price,
                'order_quantity_uom' => $item->order_quantity_uom,
            ];
        })->toArray(),
    ];

    return response()->json($response);
});
 
Route::get('/orders/export', [OrderController::class, 'orderExport']);

require __DIR__ . '/auth.php';
