<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerReceipt;
use App\Models\Customer;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\OracleBankMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Traits\LogsActivity;
use Illuminate\Support\Facades\Validator;

class ReceiptController extends Controller
{
    use LogsActivity;
    /**
     * Display a listing of receipts.
     */
    public function index(Request $request)
    {
        $query = CustomerReceipt::with(['customer', 'createdBy', 'enteredBy'])
            ->orderBy('created_at', 'desc');

        // PDC Stats from Oracle View
        $allowedOuIds = auth()->user()->getAllowedReceiptOuIds();
        $unreconQuery = \App\Models\OracleUnreconReceipt::query();
        if (!empty($allowedOuIds)) {
            $unreconQuery->whereIn('org_id', $allowedOuIds);
        }

        $pdcStats = [
            'count' => (clone $unreconQuery)->count(),
            'total' => (clone $unreconQuery)->sum('receipt_amount'),
        ];

        // Apply location-based filtering for CMD-KHI and CMD-LHR roles
        $user = auth()->user();
        if (!$user->isAdmin()) {
            // Check if CMD user has assigned salespeople
            if (($user->isCmdKhi() || $user->isCmdLhr()) && !$user->hasAllSalespeopleAccess()) {
                $assignedSalespeopleIds = $user->getAssignedSalespeopleIds();

                // Filter receipts by assigned salespeople (created_by)
                if (!empty($assignedSalespeopleIds)) {
                    $query->whereIn('created_by', $assignedSalespeopleIds);

                    // Still apply OU filtering for location
                    $allowedOuIds = $user->getAllowedReceiptOuIds();
                    if (!empty($allowedOuIds)) {
                        $query->whereHas('customer', function ($customerQuery) use ($allowedOuIds) {
                            $customerQuery->whereIn('ou_id', $allowedOuIds);
                        });
                    }
                } else {
                    // If no matching users found, show no receipts
                    $query->where('id', -1);
                }
            }
            // Regular OU filtering for other non-admin users or CMD users with "All" access
            else {
                $allowedOuIds = $user->getAllowedReceiptOuIds();

                if (!empty($allowedOuIds)) {
                    $query->whereHas('customer', function ($customerQuery) use ($allowedOuIds) {
                        $customerQuery->whereIn('ou_id', $allowedOuIds);
                    });
                } else {
                    // If no allowed OU IDs, show no receipts
                    $query->where('id', -1);
                }
            }
        }

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'LIKE', "%{$search}%")
                  ->orWhere('cheque_no', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('customer_name', 'LIKE', "%{$search}%")
                                   ->orWhere('customer_id', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($request->filled('location')) {
            $location = $request->location;
            $query->whereHas('customer', function ($q) use ($location) {
                if ($location === 'karachi') {
                    $q->whereIn('ou_id', [102, 103, 104, 105, 106]);
                } elseif ($location === 'lahore') {
                    $q->whereIn('ou_id', [108, 109]);
                }
            });
        }

        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }

        if ($request->filled('receipt_type')) {
            $query->where('receipt_type', $request->receipt_type);
        }

        if ($request->filled('status')) {
            if ($request->status == 'pending') {
                $query->whereNull('oracle_entered_at');
            } elseif ($request->status == 'pushed') {
                $query->whereNotNull('oracle_entered_at');
            }
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Customer
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Salesperson (the user who created the receipt)
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // Pushed by (the user who entered the receipt to Oracle)
        if ($request->filled('oracle_entered_by')) {
            $query->where('oracle_entered_by', $request->oracle_entered_by);
        }

        // Pushed date range (oracle_entered_at)
        if ($request->filled('pushed_from')) {
            $query->whereDate('oracle_entered_at', '>=', $request->pushed_from);
        }
        if ($request->filled('pushed_until')) {
            $query->whereDate('oracle_entered_at', '<=', $request->pushed_until);
        }

        // Receipt number (exact / partial match)
        if ($request->filled('receipt_number')) {
            $query->where('receipt_number', 'LIKE', '%' . $request->receipt_number . '%');
        }

        $receipts = $query->paginate(20);
        $banks = Bank::active()->get();

        // Dropdown data for the new filters — only customers / users that can
        // actually appear in this user's view (respects OU + role scoping).
        $allowedOuIds = $user->getAllowedReceiptOuIds();
        $customersQuery = Customer::query()
            ->whereNotNull('customer_name')
            ->orderBy('customer_name');
        if (!$user->isAdmin() && !empty($allowedOuIds)) {
            $customersQuery->whereIn('ou_id', $allowedOuIds);
        }
        $customerOptions = $customersQuery->limit(2000)->pluck('customer_name', 'id')->toArray();

        $salespeople = User::query()
            ->whereIn('id', CustomerReceipt::query()->whereNotNull('created_by')->distinct()->pluck('created_by'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $pushers = User::query()
            ->whereIn('id', CustomerReceipt::query()->whereNotNull('oracle_entered_by')->distinct()->pluck('oracle_entered_by'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        // Build stats query with role-based scoping
        $statsQuery = CustomerReceipt::query();
        $isCmd = $user->isCmdKhi() || $user->isCmdLhr();

        if ($user->isAdmin()) {
            // Admin: global totals — no filter
        } elseif ($isCmd && !$user->hasAllSalespeopleAccess()) {
            // CMD with specific salespeople: scope to their assigned team
            $assignedSalespeopleIds = $user->getAssignedSalespeopleIds();
            if (!empty($assignedSalespeopleIds)) {
                $statsQuery->whereIn('created_by', $assignedSalespeopleIds);
                $allowedOuIds = $user->getAllowedReceiptOuIds();
                if (!empty($allowedOuIds)) {
                    $statsQuery->whereHas('customer', fn($q) => $q->whereIn('ou_id', $allowedOuIds));
                }
            } else {
                $statsQuery->where('id', -1);
            }
        } elseif ($isCmd) {
            // CMD with All-access: scope to their OU
            $allowedOuIds = $user->getAllowedReceiptOuIds();
            if (!empty($allowedOuIds)) {
                $statsQuery->whereHas('customer', fn($q) => $q->whereIn('ou_id', $allowedOuIds));
            } else {
                $statsQuery->where('id', -1);
            }
        } else {
            // All other roles (account-user, sales-head, etc.): own receipts only
            $statsQuery->where('created_by', $user->id);
        }

        $stats = [
            'total_receipts'   => (clone $statsQuery)->count(),
            'pending_receipts' => (clone $statsQuery)->whereNull('oracle_entered_at')->count(),
            'pending_amount'   => (clone $statsQuery)->whereNull('oracle_entered_at')->sum('receipt_amount'),
            'pushed_receipts'  => (clone $statsQuery)->whereNotNull('oracle_entered_at')->count(),
            'pushed_amount'    => (clone $statsQuery)->whereNotNull('oracle_entered_at')->sum('receipt_amount'),
            'total_amount'     => (clone $statsQuery)->sum('receipt_amount'),
            'pdc_count'        => $pdcStats['count'],
            'pdc_amount'       => $pdcStats['total'],
        ];

        return view('admin.receipts.index', compact(
            'receipts', 'banks', 'stats',
            'customerOptions', 'salespeople', 'pushers'
        ));
    }

    /**
     * Export receipts to Excel.
     */
    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:pending,pushed,all'
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $status = $request->input('status');

        $filename = 'receipts_export_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new \App\Exports\ReceiptsExport($startDate, $endDate, $status), $filename);
    }

    /**
     * Show the form for creating a new receipt.
     */
    public function create(Request $request)
    {
        // Initialize variables with defaults
        $operatingUnits = collect();
        $oracleBanks = collect();
        
        // Get customers from local SQL table with OU fields
        $customers = Customer::select('customer_id', 'customer_name', 'overall_credit_limit', 'ou_id', 'ou_name')
            ->orderBy('customer_name')
            ->get();

        // Pre-selected customer for "Add More Receipt" functionality
        $preSelectedCustomerId = $request->get('customer_id') ?? old('customer_id');
        $fromEdit = $request->get('from_edit') ?? old('from_edit');
            
        // Load all banks with all columns needed for 3-column search
        $oracleBanks = Bank::select(
            'bank_account_id', 'bank_name', 'bank_account_name', 'bank_account_num',
            'org_id', 'status', 'bank_branch_name', 'receipt_method', 'receipt_method_id',
            'receipt_class_id', 'receipt_classes', 'iban_number'
        )->orderBy('bank_name')->get();

        // Resolve pre-selected customer name for display
        $preSelectedCustomer = null;
        if ($preSelectedCustomerId) {
            $preSelectedCustomer = Customer::select('customer_id', 'customer_name', 'ou_id', 'ou_name', 'overall_credit_limit')
                ->where('customer_id', $preSelectedCustomerId)->first();
        }

        return view('admin.receipts.create', compact(
            'customers', 'oracleBanks', 'preSelectedCustomerId', 'preSelectedCustomer', 'fromEdit'
        ));
    }

    /**
     * Store a newly created receipt.
     */
    public function store(Request $request)
    {
        $validator = $this->validateReceipt($request);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        // Get customer details from local SQL table
        $customer = Customer::where('customer_id', $validated['customer_id'])->first();
        if (!$customer) {
            return redirect()->back()->withErrors(['customer_id' => 'Customer not found'])->withInput();
        }

        // Handle cheque image upload
        $chequeImagePath = null;
        if ($request->hasFile('cheque_image')) {
            $chequeImagePath = $request->file('cheque_image')->store('cheque-images', 'public');
        }

        // Generate receipt number
        $currentYear = date('Y');
        $receiptNumber = CustomerReceipt::generateReceiptNumber($currentYear);

        // Determine receipt type
        $receiptType = $this->determineReceiptType($validated);

        // Create the receipt
        $receipt = CustomerReceipt::create([
            'customer_id' => $validated['customer_id'],
            'receipt_number' => $receiptNumber,
            'receipt_year' => $currentYear,
            'overall_credit_limit' => $customer->overall_credit_limit,
            'outstanding' => $validated['outstanding'] ?? $customer->overall_credit_limit,
            'cash_amount' => $validated['cash_amount'] ?? null,
            'currency' => $validated['currency'],
            'cash_maturity_date' => $validated['cash_maturity_date'] ?? null,
            'cheque_no' => $validated['cheque_no'] ?? null,
            'cheque_amount' => $validated['cheque_amount'] ?? null,
            'maturity_date' => $validated['maturity_date'] ?? null,
            'cheque_comments' => $validated['cheque_comments'] ?? null,
            'is_third_party_cheque' => $validated['is_third_party_cheque'] ?? false,
            'remittance_bank_id' => $validated['remittance_bank_id'] ?? null,
            'remittance_bank_name' => $validated['remittance_bank_name'] ?? null,
            'customer_bank_id' => $validated['customer_bank_id'] ?? null,
            'customer_bank_name' => $validated['customer_bank_name'] ?? null,
            'cheque_image' => $chequeImagePath ? asset('storage/' . $chequeImagePath) : null,
            'description' => $validated['description'],
            'receipt_type' => $receiptType,
            'created_by' => auth()->id(),
            // Oracle-specific fields
            'ou_id' => $validated['ou_id'],
            'receipt_amount' => $validated['receipt_amount'],
            'receipt_date' => $validated['receipt_date'],
            'status' => $validated['status'] ?? null,
            'comments' => $validated['comments'] ?? null,
            'creation_date' => $validated['creation_date'] ?? now(),
            'bank_account_id' => $validated['cash_bank_account_id'] ?? $validated['bank_account_id'] ?? null,
        ]);

        $this->logActivity('Receipts', 'create', "Receipt #{$receiptNumber} created for customer {$customer->customer_name}.", [
            'receipt_id' => $receipt->id,
            'amount' => $validated['receipt_amount'],
            'currency' => $validated['currency']
        ]);

        // Push notification to admin / CMD users (they push receipts to Oracle).
        app(\App\Services\OrderReceiptNotifier::class)->receiptCreated($receipt);

        return redirect()->route('admin.receipts.index')->with('success', 'Receipt created successfully!');
    }

    /**
     * Display the specified receipt.
     */
    public function show($id)
    {
        $receipt = CustomerReceipt::with(['customer', 'createdBy', 'cheques', 'remittanceBank', 'customerBank'])->findOrFail($id);
        return view('admin.receipts.show', compact('receipt'));
    }

    /**
     * Show the form for editing the specified receipt.
     */
    public function edit($id)
    {
        $receipt = CustomerReceipt::with(['customer', 'cheques'])->findOrFail($id);
        
        // Initialize variables with defaults
        $operatingUnits = collect();
        $oracleBanks = collect();
        
        // Get customers from local SQL table with OU fields
        $customers = Customer::select('customer_id', 'customer_name', 'overall_credit_limit', 'ou_id', 'ou_name')
            ->orderBy('customer_name')
            ->get();
            
        $oracleBanks = Bank::select(
            'bank_account_id', 'bank_name', 'bank_account_name', 'bank_account_num',
            'org_id', 'status', 'bank_branch_name', 'receipt_method', 'receipt_method_id',
            'receipt_class_id', 'receipt_classes', 'iban_number'
        )->orderBy('bank_name')->get();

        return view('admin.receipts.edit', compact('receipt', 'customers', 'oracleBanks'));
    }

    /**
     * Update the specified receipt.
     */
    public function update(Request $request, $id)
    {
        $receipt = CustomerReceipt::findOrFail($id);
        $validator = $this->validateReceipt($request, $id);

        if ($validator->fails()) {
            // Handle AJAX validation errors
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        // Handle cheque image upload
        if ($request->hasFile('cheque_image')) {
            // Delete old image if exists
            if ($receipt->cheque_image) {
                $oldPath = str_replace(asset('storage/'), '', $receipt->cheque_image);
                Storage::disk('public')->delete($oldPath);
            }
            $validated['cheque_image'] = asset('storage/' . $request->file('cheque_image')->store('cheque-images', 'public'));
        }

        // Map cash_bank_account_id → bank_account_id column if provided
        if (!empty($validated['cash_bank_account_id'])) {
            $validated['bank_account_id'] = $validated['cash_bank_account_id'];
        }
        unset($validated['cash_bank_account_id'], $validated['cheques']);

        $receipt->update($validated);

        // Handle multiple cheques update/creation
        if ($request->has('cheques') && is_array($request->cheques)) {
            // Store existing cheques data before deletion
            $existingCheques = $receipt->cheques()->get()->keyBy(function($cheque, $index) {
                return $index; // Use array index as key
            });
            
            // Delete existing cheques
            $receipt->cheques()->delete();
            
            // Create/update cheques from the form data
            foreach ($request->cheques as $index => $chequeData) {
                // Skip empty cheque data
                if (empty($chequeData['cheque_no']) && empty($chequeData['cheque_amount'])) {
                    continue;
                }
                
                // Handle cheque images - preserve existing if no new images uploaded
                $chequeImages = [];
                
                // First, get existing images if this cheque existed before
                if (isset($existingCheques[$index]) && $existingCheques[$index]->cheque_images) {
                    $chequeImages = is_array($existingCheques[$index]->cheque_images) 
                        ? $existingCheques[$index]->cheque_images 
                        : [];
                }
                
                // Add new images if uploaded
                if ($request->hasFile("cheques.{$index}.cheque_images")) {
                    $imageFiles = $request->file("cheques.{$index}.cheque_images");
                    if (is_array($imageFiles)) {
                        foreach ($imageFiles as $imageFile) {
                            if ($imageFile && $imageFile->isValid()) {
                                $imagePath = $imageFile->store('cheque-images', 'public');
                                $chequeImages[] = asset('storage/' . $imagePath);
                            }
                        }
                    }
                }
                
                \App\Models\ReceiptCheque::create([
                    'customer_receipt_id' => $receipt->id,
                    'bank_name' => $chequeData['bank_name'] ?? '',
                    'instrument_id' => $chequeData['instrument_id'] ?? null,
                    'instrument_name' => $chequeData['instrument_name'] ?? null,
                    'instrument_account_name' => $chequeData['instrument_account_name'] ?? null,
                    'instrument_account_num' => $chequeData['instrument_account_num'] ?? null,
                    'org_id' => $chequeData['org_id'] ?? null,
                    'cheque_no' => $chequeData['cheque_no'],
                    'cheque_amount' => $chequeData['cheque_amount'],
                    'cheque_date' => $chequeData['cheque_date'] ?? null,
                    'reference' => $chequeData['reference'] ?? null,
                    'comments' => $chequeData['comments'] ?? null,
                    'is_third_party_cheque' => isset($chequeData['is_third_party_cheque']) ? (bool)$chequeData['is_third_party_cheque'] : false,
                    'maturity_date' => $chequeData['maturity_date'] ?? null,
                    'cheque_images' => $chequeImages, // Will preserve existing or add new images
                    'status' => 'pending',
                ]);
            }
        }

        // Update receipt type AFTER cheques are processed
        $receipt->refresh(); // Reload to get updated cheques
        $receiptType = $this->determineReceiptTypeAfterUpdate($receipt);
        $receipt->update(['receipt_type' => $receiptType]);

        $this->logActivity('Receipts', 'update', "Receipt #{$receipt->receipt_number} updated.", [
            'receipt_id' => $receipt->id,
            'amount' => $receipt->receipt_amount
        ]);

        // Handle AJAX requests (for "Add More Receipt" functionality)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Receipt updated successfully!',
                'receipt_id' => $receipt->id
            ]);
        }

        // Handle "Add More Receipt" requests (non-AJAX)
        if ($request->has('add_more_receipt') && $request->add_more_receipt == '1') {
            $customerId = $request->add_more_customer_id;
            $createRoute = request()->routeIs('admin.receipts.*') ? 'admin.receipts.create' : 'reciepts.create';
            
            return redirect()->route($createRoute)
                ->with('success', 'Receipt updated successfully!')
                ->with('info', 'Creating new receipt for the same customer...')
                ->withInput(['customer_id' => $customerId, 'from_edit' => true]);
        }

        $editRoute = request()->routeIs('admin.receipts.*')
            ? route('admin.receipts.edit', $receipt->id)
            : route('reciepts.edit', $receipt->id);

        return redirect($editRoute)->with('success', 'Receipt updated successfully!');
    }

    /**
     * Remove the specified receipt.
     */
    public function destroy($id)
    {
        $receipt = CustomerReceipt::findOrFail($id);

        // Delete cheque image if exists
        if ($receipt->cheque_image) {
            $imagePath = str_replace(asset('storage/'), '', $receipt->cheque_image);
            Storage::disk('public')->delete($imagePath);
        }

        $receipt->delete();

        $this->logActivity('Receipts', 'delete', "Receipt #{$receipt->receipt_number} deleted.", [
            'receipt_id' => $receipt->id,
            'amount' => $receipt->receipt_amount
        ]);

        return redirect()->route('admin.receipts.index')->with('success', 'Receipt deleted successfully!');
    }

    /**
     * Validate receipt data.
     */
    private function validateReceipt(Request $request, $receiptId = null)
    {
        $rules = [
            'customer_id' => 'required|string',
            'outstanding' => 'nullable|numeric|min:0',
            'cash_amount' => 'nullable|numeric|min:0',
            'currency' => 'required|in:PKR,USD',
            'cash_maturity_date' => 'nullable|date',
            'cheque_no' => 'nullable|string|max:255',
            'cheque_amount' => 'nullable|numeric|min:0',
            'maturity_date' => 'nullable|date',
            'cheque_comments' => 'nullable|string|max:1000',
            'is_third_party_cheque' => 'nullable|boolean',
            'remittance_bank_id' => 'nullable|exists:banks,id',
            'remittance_bank_name' => 'nullable|string|max:255',
            'customer_bank_id' => 'nullable|exists:banks,id',
            'customer_bank_name' => 'nullable|string|max:255',
            'cheque_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'required|string|max:1000',
            // Oracle-specific validation rules (optional for backward compatibility)
            'ou_id' => 'nullable|string',
            'receipt_amount' => 'nullable|numeric|min:0.01',
            'receipt_date' => 'nullable|date',
            'status' => 'nullable|string',
            'comments' => 'nullable|string|max:2000',
            'creation_date' => 'nullable|date',
            'bank_account_id' => 'nullable|string',
            'cash_bank_account_id' => 'nullable|string',
            // Per-cheque fields
            'cheques' => 'nullable|array',
            'cheques.*.instrument_id' => 'nullable|string',
            'cheques.*.instrument_name' => 'nullable|string|max:255',
            'cheques.*.instrument_account_name' => 'nullable|string|max:255',
            'cheques.*.instrument_account_num' => 'nullable|string|max:255',
            'cheques.*.org_id' => 'nullable|string',
            'cheques.*.cheque_no' => 'nullable|string|max:255',
            'cheques.*.cheque_amount' => 'nullable|numeric|min:0',
            'cheques.*.cheque_date' => 'nullable|date',
            'cheques.*.maturity_date' => 'nullable|date',
            'cheques.*.comments' => 'nullable|string|max:1000',
            'cheques.*.is_third_party_cheque' => 'nullable|boolean',
        ];

        $messages = [];

        $validator = Validator::make($request->all(), $rules, $messages);

        // Custom validation
        $validator->after(function ($validator) use ($request) {
            $hasCash = $request->filled('cash_amount') && $request->cash_amount > 0;
            $hasCheque = $request->filled('cheque_no');
            $hasChequeAmount = $request->filled('cheque_amount') && $request->cheque_amount > 0;

            // if (!$hasCash && !$hasCheque) {
            //     $validator->errors()->add('payment', 'Either cash amount or cheque number must be provided.');
            // }

            // if ($hasCheque && !$hasChequeAmount) {
            //     $validator->errors()->add('cheque_amount', 'Cheque amount is required when cheque number is provided.');
            // }
        });

        return $validator;
    }

    /**
     * Determine receipt type based on provided data.
     */
    private function determineReceiptType(array $data): string
    {
        $hasCash = isset($data['cash_amount']) && $data['cash_amount'] > 0;
        
        // Check for cheques in the new multi-cheque system
        $hasCheque = false;
        if (isset($data['cheques']) && is_array($data['cheques'])) {
            foreach ($data['cheques'] as $cheque) {
                if (isset($cheque['cheque_no']) && !empty($cheque['cheque_no']) && 
                    isset($cheque['cheque_amount']) && $cheque['cheque_amount'] > 0) {
                    $hasCheque = true;
                    break;
                }
            }
        }
        
        // Fallback to old single cheque fields for backward compatibility
        if (!$hasCheque) {
            $hasCheque = isset($data['cheque_no']) && !empty($data['cheque_no']) && 
                        isset($data['cheque_amount']) && $data['cheque_amount'] > 0;
        }

        if ($hasCash && $hasCheque) {
            return 'cash_and_cheque';
        } elseif ($hasCheque) {
            return 'cheque_only';
        } else {
            return 'cash_only';
        }
    }

    /**
     * Determine receipt type based on actual receipt object after update.
     */
    private function determineReceiptTypeAfterUpdate($receipt): string
    {
        $hasCash = $receipt->cash_amount > 0;
        
        // Check for actual cheques in database
        $hasCheque = $receipt->cheques()
            ->where('cheque_amount', '>', 0)
            ->whereNotNull('cheque_no')
            ->where('cheque_no', '!=', '')
            ->exists();

        if ($hasCash && $hasCheque) {
            return 'cash_and_cheque';
        } elseif ($hasCheque) {
            return 'cheque_only';
        } else {
            return 'cash_only';
        }
    }
}
