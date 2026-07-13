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
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\OrderRecieptsController;

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        
        // Get role name safely - handle both old string role and new role relationship
        $roleName = $user->role?->name ?? $user->role ?? null;
        
        // Check if user is already on the appropriate page to prevent infinite redirects
        if ($roleName === 'supply-chain' && !request()->is('app/supply-chain/orders*')) {
            return redirect()->route('orders.supply-chain.all');
        }

        // Sales-head (or anyone treated as sales-head via the email override
        // on User::isSalesHead()) lands on the dashboard first — even if their
        // role attribute is 'user'. Without this, the next branch would force
        // them into Monthly Tour Plans.
        if (method_exists($user, 'isSalesHead') && $user->isSalesHead()) {
            if (!request()->is('dashboard*')) {
                return redirect()->route('dashboard');
            }
            // already on dashboard — let the request through
        }

        if ($roleName === 'user' && !request()->is('app/monthly-tour-plans*')) {
            return redirect()->route('monthlyTourPlans.all');
        }

        if (in_array($roleName, ['cmd-khi', 'cmd-lhr']) && !request()->is('dashboard*')) {
            return redirect()->route('dashboard');
        }

        if ($roleName === 'scm-lhr' && !request()->is('app/scm-lhr/orders*')) {
            return redirect()->route('orders.scm-lhr.all');
        }

        if ($roleName === 'inventory-manager' && !request()->is('wms*')) {
            return redirect()->route('wms.locations');
        }

        if ($roleName === 'invoice-manager' && !request()->is('app/admin/invoices*')) {
            return redirect()->route('invoices.view');
        }

        // If the user is an admin or any other role NOT covered above, send to dashboard.
        // invoice-manager is excluded — dashboard is gated to admin/cmd-* and would redirect-loop.
        if (!in_array($roleName, ['supply-chain', 'user', 'cmd-khi', 'cmd-lhr', 'scm-lhr', 'inventory-manager', 'invoice-manager'])) {
            return redirect()->route('dashboard');
        }
    }

    return redirect('login');
});

// Public PDF streaming for invoice files stored on the local disk.
// The Invoice API returns pdf_path as a URL like
//   http://host/invoices/customers/12345/file.pdf
// — this route serves those files inline from storage/app/invoices.
//
// Path traversal protection: realpath() resolves any "../" tricks and we
// reject anything that escapes the invoices base directory.
Route::get('/invoices/{path}', function (string $path) {
    $base = storage_path('app' . DIRECTORY_SEPARATOR . 'invoices');
    $full = realpath($base . DIRECTORY_SEPARATOR . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path));
    $baseReal = realpath($base);

    if (!$full || !$baseReal || !str_starts_with($full, $baseReal) || !is_file($full)) {
        abort(404, 'Invoice file not found.');
    }

    return response()->file($full, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . basename($full) . '"',
        'Cache-Control'       => 'private, max-age=300',
    ]);
})->where('path', '.+\.pdf')->name('invoices.serve-pdf');

// Session-authenticated FCM token registration for the portal.
// Mobile clients use /api/profile/fcm-token (Sanctum); the portal uses this.
Route::middleware(['auth'])->post('/fcm-token', function (\Illuminate\Http\Request $request) {
    $request->validate(['fcm_token' => 'required|string|min:10|max:4096']);
    $user = auth()->user();
    $user->update([
        'fcm_token'            => $request->input('fcm_token'),
        'fcm_token_updated_at' => now(),
    ]);
    return response()->json(['success' => true]);
})->name('fcm-token.update');


Route::prefix('app')->middleware(['auth'])->group(function () {
    // Dedicated full-page order viewer (replaces the in-list modal). Sits
    // outside any role-specific group so every role that can see the list
    // can also navigate to a single-order page; per-OU access is enforced
    // inside App\Livewire\ShowOrder::mount. Carries the same warehouse-
    // selector + Enter-to-Oracle action that the modal had.
    Route::get('/orders/{order}', App\Livewire\ShowOrder::class)->name('orders.show');

    // Apply middleware to restrict access to the orders route
    Route::middleware(['checkRole:supply-chain'])->group(function () {
        Route::get('/supply-chain/orders', ListOrders::class)->name('orders.supply-chain.all');
    });

    // SCM-LHR - Lahore warehouse orders access
    Route::middleware(['checkRole:scm-lhr'])->group(function () {
        Route::get('/scm-lhr/orders', ListOrders::class)->name('orders.scm-lhr.all');
    });

    // Sales Head - CRM, Orders (view+OU-scoped) + Receipts (read-only)
    Route::middleware(['checkRole:sales-head'])->group(function () {
        Route::get('/orders', ListOrders::class)->name('orders.all');

        // Receipts — view only. Edit/update/delete/enter-to-oracle routes are
        // gated to admin/cmd-* below, so they 403 for sales-head automatically.
        Route::get('/reciepts',          [OrderRecieptsController::class, 'index'])->name('reciepts.sales-head');
        Route::get('/reciepts/{id}',     [OrderRecieptsController::class, 'show'])->name('reciepts.show.sales-head');

        // CRM Routes for sales-head
        Route::get('/sales-teams', CRM\ListSalesTeam::class)->name('salesteam.all');
        Route::get('/manage-tour-plans', CRM\Manage\MonthlyTourPlanApproval::class)->name('manage.tourplans');
        Route::get('/monthly-tour-plans', CRM\MonthlyPlan\ListMonthlyTourPlans::class)->name('monthlyTourPlans.all');
        Route::get('/monthly-tour-plans-old', CRM\MonthlyPlan\OldListMonthlyTourPlans::class)->name('oldMonthlyTourPlans.all');
        Route::get('/plan-details/{monthlyTourPlan}', CRM\MonthlyPlan\PlanDetails::class)->name('monthlyTourPlans.planDetails');
        Route::get('/plan/{monthlyTourPlan?}', CRM\MonthlyPlan\CreatePlan::class)->name('monthlyTourPlans.addNewPlan');
        Route::get('/day-tour-plan/{dayTourPlan}', CRM\MonthlyPlan\DayTourDetails::class)->name('dayTourPlans.details');
        Route::get('/visits', CRM\Visit\ManageVisit::class)->name('visits.all');
        Route::get('/plan/mvr/{dayTourPlan?}/{visitId?}', CRM\Visit\CreateMvr::class)->name('visit.createMvr');
        Route::get('/visits-reports/{plan}', CRM\Visit\VisitReportDetails::class)->name('visit.reportDetails');
        Route::get('/visit-details/{visit}', CRM\Visit\VisitDetails::class)->name('visit.details');
        Route::get('/visit/{visit}/expenses', CRM\Visit\ViewVisitExpenses::class)->name('visit.viewExpenses');
        Route::get('/expense-detail/{expense}', CRM\Expenses\ExpenseDetail::class)->name('expense.details');
        Route::get('/add-expense/{visit}/{expenseId?}', CRM\Expenses\AddExpense::class)->name('expense.addExpense');
    });

    // Invoice Management Routes — Admin / Invoice-Manager get full CRUD.
    // view-* roles get read-only access (list / show / download) via the
    // separate route group below.
    Route::middleware(['checkRole:admin,invoice-manager'])->group(function () {
        Route::prefix('admin/invoices')->name('invoices.')->group(function () {
            Route::get('/upload', [App\Http\Controllers\InvoiceController::class, 'upload'])->name('upload');
            Route::post('/upload', [App\Http\Controllers\InvoiceController::class, 'store'])->name('store');
            Route::get('/whatsapp-queue-status', [App\Http\Controllers\InvoiceController::class, 'getQueueStatus'])->name('whatsapp-status');
            Route::get('/get/unsent', [App\Http\Controllers\InvoiceController::class, 'getUnsentInvoices'])->name('unsent');
            Route::get('/export', [App\Http\Controllers\InvoiceController::class, 'export'])->name('export');
            Route::post('/bulk-send-whatsapp', [App\Http\Controllers\InvoiceController::class, 'bulkSendWhatsApp'])->name('bulk-send');
            Route::get('/explorer/preview', [App\Http\Controllers\InvoiceController::class, 'previewDiskFile'])->name('preview-file');
            Route::post('/{invoice}/update-phone', [App\Http\Controllers\InvoiceController::class, 'updatePhone'])->name('update-phone');
            Route::post('/{invoice}/send-whatsapp', [App\Http\Controllers\InvoiceController::class, 'sendWhatsApp'])->name('send-whatsapp');
            Route::delete('/{invoice}', [App\Http\Controllers\InvoiceController::class, 'destroy'])->name('destroy');
        });
    });

    // Invoice send page (the "current" page with send / upload / delete actions)
    // — Admin / Invoice-Manager only. view-* roles must use the view-only page.
    Route::middleware(['checkRole:admin,invoice-manager'])->group(function () {
        Route::prefix('admin/invoices')->name('invoices.')->group(function () {
            Route::get('/', [App\Http\Controllers\InvoiceController::class, 'index'])->name('index');
        });
    });

    // ── Announcements (admin only) — admin composes a title + body and
    // the AnnouncementController fires FCM pushes to the chosen audience
    // (everyone with an fcm_token, or filtered by role). ──
    Route::middleware(['checkRole:admin'])->group(function () {
        Route::prefix('admin/announcements')->name('announcements.')->group(function () {
            Route::get('/',              [App\Http\Controllers\AnnouncementController::class, 'index'])->name('index');
            Route::get('/create',        [App\Http\Controllers\AnnouncementController::class, 'create'])->name('create');
            Route::post('/',             [App\Http\Controllers\AnnouncementController::class, 'store'])->name('store');
            Route::get('/{announcement}',[App\Http\Controllers\AnnouncementController::class, 'show'])->name('show');
            Route::delete('/{announcement}', [App\Http\Controllers\AnnouncementController::class, 'destroy'])->name('destroy');
        });
    });

    // ── App Version (admin only) — manages the per-platform min_supported
    // version the enforceAppVersion middleware checks on /api/* calls. ──
    Route::middleware(['checkRole:admin'])->group(function () {
        Route::prefix('admin/app-versions')->name('app-versions.')->group(function () {
            Route::get('/',                  [App\Http\Controllers\AppVersionController::class, 'index'])->name('index');
            Route::put('/{appVersion}',      [App\Http\Controllers\AppVersionController::class, 'update'])->name('update');
        });
    });

    // ── Vendors AP — bill upload + 2-stage approval (CMD → Director) ──
    // Uploader / view-only access is broad; the controller does fine-grained
    // edit/approve permission checks per bill state. account-user is the
    // intended day-to-day submitter alongside admins; cmd-* and director
    // need access for their queues.
    Route::middleware(['checkRole:admin,account-user,cmd-khi,cmd-lhr,director'])->group(function () {
        Route::prefix('admin/vendor-bills')->name('vendor-bills.')->group(function () {
            Route::get('/',                       [App\Http\Controllers\VendorBillController::class, 'index'])->name('index');
            Route::get('/search-vendors',         [App\Http\Controllers\VendorBillController::class, 'searchVendors'])->name('searchVendors');
            Route::get('/create',                 [App\Http\Controllers\VendorBillController::class, 'create'])->name('create');
            Route::post('/',                      [App\Http\Controllers\VendorBillController::class, 'store'])->name('store');
            Route::get('/{vendorBill}',           [App\Http\Controllers\VendorBillController::class, 'show'])->name('show');
            Route::get('/{vendorBill}/edit',      [App\Http\Controllers\VendorBillController::class, 'edit'])->name('edit');
            Route::put('/{vendorBill}',           [App\Http\Controllers\VendorBillController::class, 'update'])->name('update');
            Route::post('/{vendorBill}/approve',  [App\Http\Controllers\VendorBillController::class, 'approve'])->name('approve');
            Route::post('/{vendorBill}/reject',   [App\Http\Controllers\VendorBillController::class, 'reject'])->name('reject');
            Route::get('/attachment/{attachment}',[App\Http\Controllers\VendorBillController::class, 'attachment'])->name('attachment');
        });
    });

    // Documents browser — customer-wise nested folders (Invoices, Builties,
    // …). Read-only listing of files already managed by the modules above,
    // so the same RBAC set that gets to see invoices view + builty rows
    // applies here.
    Route::middleware(['checkRole:admin,invoice-manager,view-khi,view-lhr,view-all'])->group(function () {
        Route::prefix('admin/documents')->name('documents.')->group(function () {
            Route::get('/',                              [App\Http\Controllers\DocumentsController::class, 'index'])->name('index');
            Route::get('/customer/{customerCode}/files', [App\Http\Controllers\DocumentsController::class, 'files'])->name('files');
        });
    });

    // Builty management — same roles as the invoice send page; view-* roles
    // are NOT allowed to mutate, only the attach-existing modal is offered
    // on the invoice view page (which posts to the protected attach route).
    Route::middleware(['checkRole:admin,invoice-manager'])->group(function () {
        Route::prefix('admin/builties')->name('builties.')->group(function () {
            Route::get('/',                        [App\Http\Controllers\BuiltyController::class, 'index'])->name('index');
            Route::post('/',                       [App\Http\Controllers\BuiltyController::class, 'store'])->name('store');
            Route::post('/bulk',                   [App\Http\Controllers\BuiltyController::class, 'bulkStore'])->name('bulkStore');
            Route::get('/search-orders',           [App\Http\Controllers\BuiltyController::class, 'searchOrders'])->name('searchOrders');
            Route::get('/search-invoices',         [App\Http\Controllers\BuiltyController::class, 'searchInvoices'])->name('searchInvoices');
            Route::get('/search-customers',        [App\Http\Controllers\BuiltyController::class, 'searchCustomers'])->name('searchCustomers');
            Route::get('/next-number-preview',     [App\Http\Controllers\BuiltyController::class, 'nextNumberPreview'])->name('nextNumberPreview');
            Route::get('/search',                  [App\Http\Controllers\BuiltyController::class, 'searchBuilties'])->name('search');
            Route::get('/{builty}/file',           [App\Http\Controllers\BuiltyController::class, 'file'])->name('file');
            Route::delete('/{builty}',             [App\Http\Controllers\BuiltyController::class, 'destroy'])->name('destroy');
            // Attach an existing builty to an invoice + remerge PDF.
            Route::post('/attach-to-invoice/{invoice}', [App\Http\Controllers\BuiltyController::class, 'attachToInvoice'])->name('attachToInvoice');
        });
    });

    // Invoice read-only — Admin / Invoice-Manager + any view-* role.
    // The dedicated view URL hides every write action and exposes the extra
    // status / WhatsApp / date filters requested for the view page.
    Route::middleware(['checkRole:admin,invoice-manager,view-khi,view-lhr,view-all'])->group(function () {
        Route::prefix('admin/invoices')->name('invoices.')->group(function () {
            Route::get('/view',                      [App\Http\Controllers\InvoiceController::class, 'viewIndex'])->name('view');
            Route::get('/{invoice}',                 [App\Http\Controllers\InvoiceController::class, 'show'])->name('show');
            Route::get('/download/{invoice}',        [App\Http\Controllers\InvoiceController::class, 'download'])->name('download');
            Route::get('/customer/{customerCode}',   [App\Http\Controllers\InvoiceController::class, 'showCustomer'])->name('customer');
        });
    });

    // Price Upload role - Only Price Lists access
    Route::middleware(['checkRole:price-uploads'])->group(function () {
        // Price Lists Routes
        Route::prefix('admin/price-lists')->name('price-lists.')->group(function () {
            Route::get('/', [PriceListController::class, 'index'])->name('index');
            Route::get('/upload', [PriceListController::class, 'upload'])->name('upload');
            Route::post('/upload', [PriceListController::class, 'store'])->name('store');
            Route::get('/history', [PriceListController::class, 'uploadHistory'])->name('history');
            Route::get('/template', [PriceListController::class, 'downloadTemplate'])->name('template');
            Route::put('/{price}/update', [PriceListController::class, 'updatePrice'])->name('update');
            
            // Oracle Integration Routes
            Route::post('/sync-oracle', [PriceListController::class, 'syncOraclePrices'])->name('sync-oracle');
            Route::post('/process-oracle-comparison', [PriceListController::class, 'processWithOracleComparison'])->name('process-oracle-comparison');
            Route::get('/review-comparison', [PriceListController::class, 'reviewComparison'])->name('review-comparison');
            Route::post('/update-oracle', [PriceListController::class, 'updateOraclePrices'])->name('update-oracle');
            Route::get('/update-status', [PriceListController::class, 'getUpdateStatus'])->name('update-status');
            Route::post('/enter-to-oracle', [PriceListController::class, 'enterToOracle'])->name('enter-to-oracle');
            Route::post('/enter-new-prices', [PriceListController::class, 'enterNewPricesToOracle'])->name('enter-new-prices');

            Route::get('/api/inventory-item-id', [PriceListController::class, 'getInventoryItemId'])->name('api.inventory-item-id');
        });
    });

        // Inventory Manager - Legacy & WMS Digitization Split Modules
    Route::middleware(['checkRole:inventory-manager'])->group(function () {
        Route::get('/inventory-barcode', \App\Livewire\Inventory\BarcodeInventoryManager::class)->name('inventory.barcode');
    });

    // Customer Form Events — admin-only on the web portal. Salesperson selects
    // an event via the mobile app (API), not via these admin routes.
    Route::middleware(['checkRole:admin'])->group(function () {
        Route::resource('admin/customer-form-events', \App\Http\Controllers\Admin\CustomerFormEventController::class)
            ->names('admin.customer-form-events');
    });

    // Promotional items bulk upload.
    Route::middleware(['checkRole:admin'])->group(function () {
        Route::get('/admin/promotional-items/bulk-upload',  [\App\Http\Controllers\Admin\PromotionalItemBulkController::class, 'form'])->name('admin.promotional-items.bulk-upload-form');
        Route::post('/admin/promotional-items/bulk-upload', [\App\Http\Controllers\Admin\PromotionalItemBulkController::class, 'upload'])->name('admin.promotional-items.bulk-upload');
    });

    // Salesperson Targets — admin-only on the web portal. Salespersons see
    // their own target via the mobile dashboard API (GET /api/dashboard).
    Route::middleware(['checkRole:admin'])->group(function () {
        Route::get('/admin/salesperson-targets', [\App\Http\Controllers\Admin\SalespersonTargetController::class, 'index'])->name('admin.salesperson-targets.index');
    });
    Route::middleware(['checkRole:admin'])->group(function () {
        Route::get('/admin/salesperson-targets/upload',  [\App\Http\Controllers\Admin\SalespersonTargetController::class, 'uploadForm'])->name('admin.salesperson-targets.upload-form');
        Route::post('/admin/salesperson-targets/upload', [\App\Http\Controllers\Admin\SalespersonTargetController::class, 'upload'])->name('admin.salesperson-targets.upload');
    });

    // QR label printing — admin / inventory-manager can print packing-level QR sheets.
    Route::middleware(['checkRole:admin,inventory-manager'])->group(function () {
        Route::get('/admin/items/qr-labels', [\App\Http\Controllers\QrLabelController::class, 'bulk'])->name('items.qr-labels.bulk');
        Route::get('/admin/items/qr-labels/search-items', [\App\Http\Controllers\QrLabelController::class, 'searchItems'])->name('items.qr-labels.search');
        Route::post('/admin/items/qr-labels/store', [\App\Http\Controllers\QrLabelController::class, 'store'])->name('items.qr-labels.store');
        Route::get('/admin/items/{itemCode}/qr-labels', [\App\Http\Controllers\QrLabelController::class, 'showItem'])->name('items.qr-labels')->where('itemCode', '.*');
    });

    Route::middleware(['checkRole:inventory-manager'])->prefix('wms')->name('wms.')->group(function () {
        // 1. Locations & Racking Management
        Route::get('/locations', \App\Livewire\WMS\LocationManager::class)->name('locations');

        // 2. Goods Receipt Note (GRN) Generation
        Route::get('/grn', \App\Livewire\WMS\GrnManager::class)->name('grn');

        // 3. LPN / Stock & Partial Handling
        Route::get('/lpn', \App\Livewire\WMS\LpnManager::class)->name('lpn');

        // 4. Put-Away Workflow (NEW)
        Route::get('/putaway', \App\Livewire\WMS\PutawayWorkflow::class)->name('putaway');

        // 5. Directed Picking Workflow
        Route::get('/picking', \App\Livewire\WMS\PickingWorkflow::class)->name('picking');

        // 6. Traceability & Reporting
        Route::get('/traceability', \App\Livewire\WMS\TraceabilityReport::class)->name('traceability');

        // 7. LPN Label Printing (TCPDF)
        Route::get('/labels/batch/{grnLine}', [\App\Http\Controllers\WMS\WmsLabelController::class, 'batchByGrnLine'])->name('labels.batch');
        Route::get('/labels/single/{lpn}',   [\App\Http\Controllers\WMS\WmsLabelController::class, 'single'])->name('labels.single');
        Route::get('/labels/grn-qr/{grnLine}', [\App\Http\Controllers\WMS\WmsLabelController::class, 'grnQr'])->name('labels.grn-qr');

        // 8. Pick Slip PDF (static routes must come before parameterised)
        Route::get('/pick-slip/pending',          [\App\Http\Controllers\WMS\WmsPickSlipController::class, 'pending'])->name('pick-slip.pending');
        Route::get('/pick-slip/item/{itemCode}',  [\App\Http\Controllers\WMS\WmsPickSlipController::class, 'byItem'])->name('pick-slip.item');
        Route::get('/pick-slip/{lpn}',            [\App\Http\Controllers\WMS\WmsPickSlipController::class, 'single'])->name('pick-slip');
    });

    // Shared dashboard access for admin, cmd-khi, cmd-lhr, sales-head AND
    // any user with a view-* additional role (view-khi / view-lhr / view-all).
    Route::middleware(['checkRole:admin,cmd-khi,cmd-lhr,sales-head,view-khi,view-lhr,view-all'])->group(function () {
        Route::get('/dashboard', [AppController::class, 'index'])->name('dashboard');
    });

    // CMD-KHI / CMD-LHR / Admin / Sales-head — Customer Receipts access.
    // Sales-head and view-* roles are view-only (enforced inside the controller).
    Route::middleware(['checkRole:cmd-khi,cmd-lhr,admin,sales-head,view-khi,view-lhr,view-all'])->group(function () {
       Route::get('receipts/performance-comparison', [\App\Http\Controllers\Admin\ReceiptController::class, 'performanceComparison'])->name('admin.receipts.performance_comparison');
        Route::get('receipts/performance-comparison/export', [\App\Http\Controllers\Admin\ReceiptController::class, 'exportPerformanceComparison'])->name('admin.receipts.performance_comparison.export');
        Route::get('receipts/download-excel', [\App\Http\Controllers\Admin\ReceiptController::class, 'export'])->name('admin.receipts.download_excel');
        Route::resource('receipts', \App\Http\Controllers\Admin\ReceiptController::class)->names('admin.receipts');
    });

    // BI Dashboard — Admin, Sales Head, CMD-*, sales users, and any view-* role.
    Route::middleware(['checkRole:admin,sales-head,cmd-khi,cmd-lhr,user,hod,line-manager,view-khi,view-lhr,view-all'])->group(function () {
        Route::get('/admin/bi-dashboard', [App\Http\Controllers\Admin\BIDashboardController::class, 'index'])->name('admin.bi-dashboard');
        Route::get('/admin/bi-dashboard/diagnostic', [App\Http\Controllers\Admin\BIDashboardController::class, 'diagnostic'])->name('admin.bi-dashboard.diagnostic');
        Route::get('/admin/bi-dashboard/clear-cache', [App\Http\Controllers\Admin\BIDashboardController::class, 'clearCache'])->name('admin.bi-dashboard.clear-cache');
        Route::get('/admin/bi-dashboard/refresh-token', [App\Http\Controllers\Admin\BIDashboardController::class, 'refreshToken'])->name('admin.bi-dashboard.refresh-token');
        Route::post('/admin/bi-dashboard/save-structure', [App\Http\Controllers\Admin\BIDashboardController::class, 'saveReportStructure'])->name('admin.bi-dashboard.save-structure');
        Route::get('/admin/bi-dashboard/download-structure/{filename}', [App\Http\Controllers\Admin\BIDashboardController::class, 'downloadStructure'])->name('admin.bi-dashboard.download-structure');
    });

    // Admin / CMD-KHI / CMD-LHR + any view-* role — read-only list views.
    // Inner edit/delete groups still gate by admin/cmd only; Livewire components
    // apply OU filtering + write-blocks via the ViewRoleGuard trait.
    Route::middleware(['checkRole:admin,cmd-khi,cmd-lhr,view-khi,view-lhr,view-all'])->group(function () {
        Route::get('/products', ListProducts::class)->name('products.all');
        Route::get('/promotional-items', App\Livewire\ListPromotionalItems::class)->name('promotional-items.all');
        Route::get('/customers', ListCustomers::class)->name('customers.all');
        Route::get('/users', ListUsers::class)->name('users.all');
        Route::get('/user-locations', \App\Livewire\ListUserLocations::class)->name('user-locations.all');
        Route::get('/orders', ListOrders::class)->name('orders.all');
        Route::get('/customer-visits', App\Livewire\ListCustomerVisits::class)->name('customer-visits.all');
        Route::get('/activity-logs', \App\Livewire\ListActivityLogs::class)->name('activity-logs');
        Route::get('/reciepts', [OrderRecieptsController::class, 'index'])->name('reciepts');
        Route::get('/reciepts/{id}', [OrderRecieptsController::class, 'show'])->name('reciepts.show');
        // Sales-head is view-only — blocked from edit/update/delete/enterToOracle by the route group below.
        Route::middleware(['checkRole:admin,cmd-khi,cmd-lhr'])->group(function () {
            Route::get('/reciepts/{id}/edit', [OrderRecieptsController::class, 'edit'])->name('reciepts.edit');
            Route::put('/reciepts/{id}', [OrderRecieptsController::class, 'update'])->name('reciepts.update');
            Route::delete('/reciepts/{id}', [OrderRecieptsController::class, 'destroy'])->name('reciepts.destroy');
            Route::post('/reciepts/{id}/enter-to-oracle', [OrderRecieptsController::class, 'enterToOracle'])->name('reciepts.enter-to-oracle');
        });

        // Price Lists Routes (admin access)
        Route::prefix('admin/price-lists')->name('price-lists.')->group(function () {
            Route::get('/', [PriceListController::class, 'index'])->name('index');
            Route::get('/upload', [PriceListController::class, 'upload'])->name('upload');
            Route::post('/upload', [PriceListController::class, 'store'])->name('store');
            Route::get('/history', [PriceListController::class, 'uploadHistory'])->name('history');
            Route::get('/template', [PriceListController::class, 'downloadTemplate'])->name('template');
            Route::put('/{price}/update', [PriceListController::class, 'updatePrice'])->name('update');
            
            // Oracle Integration Routes
            Route::post('/sync-oracle', [PriceListController::class, 'syncOraclePrices'])->name('sync-oracle');
            Route::post('/process-oracle-comparison', [PriceListController::class, 'processWithOracleComparison'])->name('process-oracle-comparison');
            Route::get('/review-comparison', [PriceListController::class, 'reviewComparison'])->name('review-comparison');
            Route::post('/update-oracle', [PriceListController::class, 'updateOraclePrices'])->name('update-oracle');
            Route::get('/update-status', [PriceListController::class, 'getUpdateStatus'])->name('update-status');
            Route::post('/enter-to-oracle', [PriceListController::class, 'enterToOracle'])->name('enter-to-oracle');

            // Export Routes
            Route::get('/export', [PriceListController::class, 'exportIndex'])->name('export');
            Route::get('/export-comparison/{uploadId}', action: [PriceListController::class, 'exportComparison'])->name('export-comparison');
            Route::get('/export-upload-history/{uploadId}', [PriceListController::class, 'exportUploadHistory'])->name('export-upload-history');
        });
        
        // CRM Routes (admin access)
        Route::get('/sales-teams', CRM\ListSalesTeam::class)->name('salesteam.all');
        Route::get('/manage-tour-plans', CRM\Manage\MonthlyTourPlanApproval::class)->name('manage.tourplans');
        Route::get('/monthly-tour-plans', CRM\MonthlyPlan\ListMonthlyTourPlans::class)->name('monthlyTourPlans.all');
        Route::get('/monthly-tour-plans-old', CRM\MonthlyPlan\OldListMonthlyTourPlans::class)->name('oldMonthlyTourPlans.all');
        Route::get('/plan-details/{monthlyTourPlan}', CRM\MonthlyPlan\PlanDetails::class)->name('monthlyTourPlans.planDetails');
        Route::get('/plan/{monthlyTourPlan?}', CRM\MonthlyPlan\CreatePlan::class)->name('monthlyTourPlans.addNewPlan');
        Route::get('/day-tour-plan/{dayTourPlan}', CRM\MonthlyPlan\DayTourDetails::class)->name('dayTourPlans.details');
        Route::get('/visits', CRM\Visit\ManageVisit::class)->name('visits.all');
        Route::get('/plan/mvr/{dayTourPlan?}/{visitId?}', CRM\Visit\CreateMvr::class)->name('visit.createMvr');
        Route::get('/visits-reports/{plan}', CRM\Visit\VisitReportDetails::class)->name('visit.reportDetails');
        Route::get('/visit-details/{visit}', CRM\Visit\VisitDetails::class)->name('visit.details');
        Route::get('/visit/{visit}/expenses', CRM\Visit\ViewVisitExpenses::class)->name('visit.viewExpenses');
        Route::get('/expense-detail/{expense}', CRM\Expenses\ExpenseDetail::class)->name('expense.details');
        Route::get('/add-expense/{visit}/{expenseId?}', CRM\Expenses\AddExpense::class)->name('expense.addExpense');
    });
    
    // HCM Routes (Admin Only)
    Route::middleware(['auth'])->prefix('admin/hcm')->name('admin.hcm.')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Admin\HcmController::class, 'dashboard'])->name('dashboard');
        
        // Hiring
        Route::get('/hiring/requisition', [App\Http\Controllers\Admin\HcmController::class, 'requisition'])->name('hiring.requisition');
        Route::get('/hiring/candidates', [App\Http\Controllers\Admin\HcmController::class, 'candidates'])->name('hiring.candidates');
        Route::get('/hiring/onboarding', [App\Http\Controllers\Admin\HcmController::class, 'onboarding'])->name('hiring.onboarding');
        
        // Performance
        Route::get('/performance/dashboard', [App\Http\Controllers\Admin\HcmController::class, 'performance'])->name('performance.dashboard');
        Route::get('/performance/goals', [App\Http\Controllers\Admin\HcmController::class, 'goals'])->name('performance.goals');
        Route::get('/performance/appraisals', [App\Http\Controllers\Admin\HcmController::class, 'appraisals'])->name('performance.appraisals');
        
        // Integration
        Route::get('/integration', [App\Http\Controllers\Admin\HcmController::class, 'integration'])->name('integration');
    });

    // Other shared routes for all authenticated users
    Route::get('/notifications/unread-count', function () {
        return response()->json([
            'count' => auth()->user()->unreadNotifications->count(),
        ]);
    })->name('app.notifications.unread');

    // Reports — Mobile-order adoption pivoted from APPS.QG_SALES_ORDER_PERCENTAGE.
    // Gated to management roles (admin / sales-head / cmd-*), matching who
    // sees the aggregate dashboard block.
    Route::middleware(['checkRole:admin,sales-head,cmd-khi,cmd-lhr'])
        ->prefix('admin/reports')
        ->name('admin.reports.')
        ->group(function () {
            Route::get('/sales-order-percentage',        [\App\Http\Controllers\Reports\SalesOrderPercentageController::class, 'index'])
                ->name('sales-order-percentage');
            Route::get('/sales-order-percentage/export', [\App\Http\Controllers\Reports\SalesOrderPercentageController::class, 'export'])
                ->name('sales-order-percentage.export');

            Route::get('/receipts-percentage',           [\App\Http\Controllers\Reports\ReceiptsPercentageController::class, 'index'])
                ->name('receipts-percentage');
            Route::get('/receipts-percentage/export',    [\App\Http\Controllers\Reports\ReceiptsPercentageController::class, 'export'])
                ->name('receipts-percentage.export');
        });

    // Keep-alive route to prevent session timeout/page expiry. Returns the
    // current CSRF token so the client can refresh its <meta> tag and avoid
    // 419s after Laravel rotates the token (e.g. on login/logout elsewhere).
    Route::any('/keep-alive', function () {
        return response()->json([
            'token' => csrf_token(),
        ]);
    })->name('keep-alive');
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
