<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerReceipt;
use App\Models\Customer;
use App\Models\ReceiptCheque;
use App\Models\OracleReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerReceiptController extends Controller
{
    /**
     * Create a new customer receipt.
     */
    public function store(Request $request): JsonResponse
    {
        // Add debug logging before validation
        \Log::info('=== API Store Request Debug ===', [
            'all_input' => $request->all(),
            'all_files' => $request->allFiles(),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method(),
            'has_files_check' => !empty($request->allFiles()),
            'specific_file_check' => $request->hasFile('cheques.0.cheque_images'),
            'array_file_check' => $request->hasFile('cheques'),
        ]);
        
        $validator = $this->validateReceipt($request);

        if ($validator->fails()) {
            \Log::error('=== API Validation Failed ===', [
                'errors' => $validator->errors(),
                'all_input' => $request->all(),
                'all_files' => $request->allFiles()
            ]);
            
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        
        \Log::info('=== API Validation Passed ===', [
            'validated_data' => $validated,
            'files_after_validation' => $request->allFiles()
        ]);

        // Get customer details
        $customer = Customer::where('customer_id', $validated['customer_id'])->first();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Customer not found',
            ], 404);
        }

        // Handle cheque image upload if provided
        $chequeImagePath = null;
        $chequeImageUrl = null;
        if ($request->hasFile('cheque_image')) {
            $chequeImagePath = $request->file('cheque_image')->store('cheque-images', 'public');
            $chequeImageUrl = asset('storage/' . $chequeImagePath);
        }

        // Determine receipt type based on provided data
        $receiptType = $this->determineReceiptType($validated);

        // Calculate outstanding (for now, use customer's credit limit - you can customize this logic)
        $outstanding = $customer->overall_credit_limit ?? 0;

        // Generate receipt number
        $currentYear = date('Y');
        $receiptNumber = CustomerReceipt::generateReceiptNumber($currentYear);

        // Determine receipt type based on payment methods
        $receiptType = $this->determineReceiptTypeFromData($validated);

        // Create receipt and cheques in transaction
        $receipt = DB::transaction(function () use ($validated, $customer, $receiptNumber, $currentYear, $outstanding, $receiptType, $request) {
            // Create the receipt
            $receipt = CustomerReceipt::create([
                'customer_id' => $validated['customer_id'],
                'receipt_number' => $receiptNumber,
                'receipt_year' => $currentYear,
                'overall_credit_limit' => $customer->overall_credit_limit,
                'outstanding' => $validated['outstanding'] ?? $outstanding,
                'cash_amount' => $validated['cash_amount'] ?? null,
                'currency' => $validated['currency'],
                'cash_maturity_date' => $validated['cash_maturity_date'] ?? null,
                'remittance_bank_id' => $validated['remittance_bank_id'] ?? null,
                'remittance_bank_name' => $validated['remittance_bank_name'] ?? null,
                'customer_bank_id' => $validated['customer_bank_id'] ?? null,
                'customer_bank_name' => $validated['customer_bank_name'] ?? null,
                'description' => $validated['description'],
                'receipt_type' => $receiptType,
                'created_by' => auth()->id(),
            ]);

            // Create cheques if provided
            if (isset($validated['cheques']) && is_array($validated['cheques'])) {
                // Log what we're receiving for cheque creation
                \Log::info('=== API Cheque Images Debug - Store Function ===', [
                    'cheques_count' => count($validated['cheques']),
                    'all_files' => $request->allFiles(),
                    'all_input_keys' => array_keys($request->all()),
                    'all_file_keys' => array_keys($request->allFiles()),
                    'validated_cheques' => $validated['cheques'],
                    'request_method' => $request->method(),
                    'content_type' => $request->header('Content-Type'),
                    'has_files' => !empty($request->allFiles()),
                    'raw_files_debug' => [
                        'cheques_0_cheque_images' => $request->hasFile('cheques_0_cheque_images'),
                        'cheques[0][cheque_images]' => $request->hasFile('cheques[0][cheque_images]'),
                        'cheques.0.cheque_images' => $request->hasFile('cheques.0.cheque_images'),
                        'cheque_images' => $request->hasFile('cheque_images'),
                        'images' => $request->hasFile('images'),
                        'files' => $request->hasFile('files')
                    ]
                ]);
                
                foreach ($validated['cheques'] as $index => $chequeData) {
                    \Log::info("API Processing cheque #{$index}", [
                        'cheque_data' => $chequeData,
                        'has_cheque_no' => !empty($chequeData['cheque_no']),
                        'has_cheque_amount' => !empty($chequeData['cheque_amount']),
                        'has_bank_name' => !empty($chequeData['bank_name'])
                    ]);
                    
                    $chequeImagePath = null;
                    
                    // Handle multiple cheque image uploads
                    $chequeImages = [];
                    
                    // Check for images in different formats
                    $imageFiles = [];
                    
                    // Debug: Check what's actually in the request (including Multer-style)
                    \Log::info("API Debug - Raw request inspection for cheque #{$index}", [
                        'request_has_any_files' => !empty($request->allFiles()),
                        'all_request_keys' => array_keys($request->all()),
                        'files_in_request' => array_keys($request->allFiles()),
                        'raw_files_structure' => $request->allFiles(),
                        'specific_file_checks' => [
                            "cheques_{$index}_cheque_images" => $request->hasFile("cheques_{$index}_cheque_images"),
                            "cheques[{$index}][cheque_images]" => $request->hasFile("cheques[{$index}][cheque_images]"),
                            "cheques[{$index}][cheque_image]" => $request->hasFile("cheques[{$index}][cheque_image]"),
                            "cheques.{$index}.cheque_images" => $request->hasFile("cheques.{$index}.cheque_images"),
                            "cheques.{$index}.cheque_image" => $request->hasFile("cheques.{$index}.cheque_image"),
                        ],
                        'multer_style_check' => [
                            'cheque_image_is_array' => is_array($request->file("cheques[{$index}][cheque_image]")),
                            'cheque_image_content' => $request->file("cheques[{$index}][cheque_image]"),
                        ]
                    ]);
                    
                    // List of possible image field formats to check (both array and Multer style)
                    $imageFormats = [
                        "cheques.{$index}.cheque_images",      // Dot notation array
                        "cheques[{$index}][cheque_images]",    // Bracket notation array  
                        "cheques.{$index}.cheque_image",       // Dot notation (single or Multer style)
                        "cheques[{$index}][cheque_image]",     // Bracket notation (single or Multer style)
                        "cheques_{$index}_cheque_images",      // Underscore array
                        "cheques_{$index}_cheque_image"        // Underscore (single or Multer style)
                    ];
                    
                    \Log::info("API Checking for images in these formats for cheque #{$index}", [
                        'formats_to_check' => $imageFormats,
                        'request_files' => array_keys($request->allFiles()),
                    ]);
                    
                    $foundImages = false;
                    foreach ($imageFormats as $format) {
                        if ($request->hasFile($format)) {
                            $foundImages = true;
                            $imageFiles = $request->file($format);
                            
                            \Log::info("API Found images in format: {$format} for cheque #{$index}", [
                                'format' => $format,
                                'is_array' => is_array($imageFiles),
                                'file_count' => is_array($imageFiles) ? count($imageFiles) : 1,
                                'file_details' => is_array($imageFiles) 
                                    ? array_map(function($file) { 
                                        return $file ? [
                                            'original_name' => $file->getClientOriginalName(),
                                            'size' => $file->getSize(),
                                            'mime_type' => $file->getMimeType(),
                                            'is_valid' => $file->isValid(),
                                            'error' => $file->getError()
                                        ] : 'null';
                                    }, $imageFiles)
                                    : ($imageFiles ? [
                                        'original_name' => $imageFiles->getClientOriginalName(),
                                        'size' => $imageFiles->getSize(),
                                        'mime_type' => $imageFiles->getMimeType(),
                                        'is_valid' => $imageFiles->isValid(),
                                        'error' => $imageFiles->getError()
                                    ] : 'null')
                            ]);
                            break; // Stop checking other formats once we found images
                        }
                    }
                    
                    if (!$foundImages) {
                        \Log::info("API No images found for cheque #{$index} in any format", [
                            'checked_formats' => $imageFormats
                        ]);
                    }
                    
                    // Handle array of files (cheque_images[] or cheque_image[] when multiple)
                    if (is_array($imageFiles)) {
                        \Log::info("API Processing array of images for cheque #{$index}", [
                            'format_used' => $format,
                            'file_count' => count($imageFiles),
                            'is_cheque_image_array' => strpos($format, 'cheque_image') !== false
                        ]);
                        
                        foreach ($imageFiles as $fileIndex => $imageFile) {
                            if ($imageFile && $imageFile->isValid()) {
                                try {
                                    $imagePath = $imageFile->store('cheque-images', 'public');
                                    $imageUrl = asset('storage/' . $imagePath);
                                    $chequeImages[] = $imageUrl;
                                    
                                    \Log::info("API Successfully uploaded image {$fileIndex} for cheque #{$index}", [
                                        'image_path' => $imagePath,
                                        'image_url' => $imageUrl,
                                        'file_size' => $imageFile->getSize(),
                                        'original_name' => $imageFile->getClientOriginalName(),
                                        'format_used' => $format
                                    ]);
                                } catch (\Exception $e) {
                                    \Log::error("API Failed to upload image {$fileIndex} for cheque #{$index}", [
                                        'error' => $e->getMessage(),
                                        'trace' => $e->getTraceAsString(),
                                        'file_name' => $imageFile->getClientOriginalName()
                                    ]);
                                }
                            } else {
                                \Log::warning("API Invalid image file {$fileIndex} for cheque #{$index}", [
                                    'is_valid' => $imageFile ? $imageFile->isValid() : false,
                                    'error_code' => $imageFile ? $imageFile->getError() : 'file_is_null',
                                    'file_name' => $imageFile ? $imageFile->getClientOriginalName() : 'null'
                                ]);
                            }
                        }
                    }
                    // Handle single file (backward compatibility)
                    elseif ($imageFiles && $imageFiles->isValid()) {
                        \Log::info("API Processing single image for cheque #{$index}", [
                            'format_used' => $format,
                            'file_name' => $imageFiles->getClientOriginalName()
                        ]);
                        
                        try {
                            $imagePath = $imageFiles->store('cheque-images', 'public');
                            $imageUrl = asset('storage/' . $imagePath);
                            $chequeImages[] = $imageUrl;
                            
                            \Log::info("API Successfully uploaded single image for cheque #{$index}", [
                                'image_path' => $imagePath,
                                'image_url' => $imageUrl,
                                'file_size' => $imageFiles->getSize(),
                                'original_name' => $imageFiles->getClientOriginalName(),
                                'format_used' => $format
                            ]);
                        } catch (\Exception $e) {
                            \Log::error("API Failed to upload single image for cheque #{$index}", [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'file_name' => $imageFiles->getClientOriginalName()
                            ]);
                        }
                    } elseif ($imageFiles) {
                        \Log::warning("API Invalid single image file for cheque #{$index}", [
                            'is_valid' => $imageFiles->isValid(),
                            'error_code' => $imageFiles->getError(),
                            'file_name' => $imageFiles->getClientOriginalName()
                        ]);
                    }

                    $createdCheque = ReceiptCheque::create([
                        'customer_receipt_id' => $receipt->id,
                        'bank_name' => $chequeData['bank_name'],
                        'instrument_id' => $chequeData['instrument_id'] ?? null,
                        'instrument_name' => $chequeData['instrument_name'] ?? null,
                        'instrument_account_name' => $chequeData['instrument_account_name'] ?? null,
                        'instrument_account_num' => $chequeData['instrument_account_num'] ?? null,
                        'org_id' => $chequeData['org_id'] ?? null,
                        'cheque_no' => $chequeData['cheque_no'],
                        'cheque_amount' => $chequeData['cheque_amount'],
                        'cheque_date' => $chequeData['cheque_date'],
                        'reference' => $chequeData['reference'] ?? null,
                        'comments' => $chequeData['comments'] ?? null,
                        'is_third_party_cheque' => filter_var($chequeData['is_third_party_cheque'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'maturity_date' => $chequeData['maturity_date'] ?? null,
                        'cheque_images' => $chequeImages,
                        'status' => 'pending',
                    ]);
                    
                    \Log::info("API Created cheque #{$index} with images", [
                        'cheque_id' => $createdCheque->id,
                        'cheque_no' => $createdCheque->cheque_no,
                        'bank_name' => $createdCheque->bank_name,
                        'final_images' => $chequeImages,
                        'final_images_count' => count($chequeImages),
                        'stored_images' => $createdCheque->cheque_images,
                        'stored_images_count' => is_array($createdCheque->cheque_images) ? count($createdCheque->cheque_images) : 0
                    ]);
                }
            }

            return $receipt;
        });

        // Load relationships
        $receipt->load(['customer', 'createdBy', 'remittanceBank', 'customerBank', 'cheques']);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customer receipt created successfully',
            'data' => $receipt,
        ], 200);
    }

    /**
     * Enter receipt into Oracle database (triggered by button click)
     */
    public function enterToOracle(Request $request, $receiptId): JsonResponse
    {
        $receipt = CustomerReceipt::with(['customer', 'cheques'])->find($receiptId);
        
        if (!$receipt) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Receipt not found',
            ], 404);
        }

        // Check if already entered to Oracle
        if ($receipt->oracle_status === 'entered') {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Receipt already entered to Oracle',
            ], 400);
        }

        // Validate required Oracle fields
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|string',
            'ou_id' => 'required|string', 
            'bank_account_id' => 'required|string',
            'receipt_amount' => 'required|numeric|min:0.01',
            'receipt_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation failed: ' . $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Update receipt with Oracle data from request
        $receipt->update([
            'customer_id' => $validated['customer_id'],
            'ou_id' => $validated['ou_id'],
            'bank_account_id' => $validated['bank_account_id'],
            'receipt_amount' => $validated['receipt_amount'],
            'receipt_date' => $validated['receipt_date'],
        ]);

        // Update cheque maturity dates if provided (to ensure Oracle gets the selected date)
        if ($request->has('cheque_maturity_date')) {
            $maturityDates = array_map('trim', explode(',', $request->cheque_maturity_date));
            $cheques = $receipt->cheques;
            
            // Only update if counts match to avoid mismatching incorrect cheques
            if (count($maturityDates) === $cheques->count()) {
                 foreach ($cheques as $index => $cheque) {
                     if (isset($maturityDates[$index]) && !empty($maturityDates[$index])) {
                         $cheque->update(['maturity_date' => $maturityDates[$index]]);
                     }
                 }
                 // Refresh receipt to get updated cheques for insertToOracle
                 $receipt->refresh();
            }
        }

        try {
            $oracleReceipt = $this->insertToOracle($receipt, $receipt->customer, $validated);
            
            // Update Laravel receipt with Oracle status
            $receipt->update([
                'oracle_status' => 'entered',
                'oracle_receipt_number' => $oracleReceipt->receipt_number,
                'oracle_entered_at' => now(),
                'oracle_entered_by' => auth()->id(),
            ]);
            
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Receipt entered to Oracle successfully',
                'data' => [
                    'receipt_id' => $receipt->id,
                    'oracle_receipt_number' => $oracleReceipt->receipt_number,
                    'oracle_status' => 'entered',
                ],
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Failed to enter receipt to Oracle: ' . $e->getMessage(), [
                'receipt_id' => $receipt->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to enter receipt to Oracle: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get receipt history for the authenticated user.
     */
    public function receiptHistory(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'status' => 401,
                'message' => 'Unauthorized',
            ], 401);
        }

        $query = CustomerReceipt::with(['customer', 'createdBy', 'remittanceBank', 'customerBank', 'cheques'])
            ->where('created_by', $user->id);

        // Filter by customer_id if provided
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by receipt type if provided
        if ($request->has('receipt_type')) {
            $query->where('receipt_type', $request->receipt_type);
        }

        // Filter by date range if provided
        if ($request->has('start_date')) {
            try {
                $startDate = \Carbon\Carbon::createFromFormat('d-m-Y', $request->start_date)->format('Y-m-d');
                $query->whereDate('created_at', '>=', $startDate);
            } catch (\Exception $e) {
                // Fallback if parsing fails, usually implies Y-m-d or invalid
                $query->whereDate('created_at', '>=', $request->start_date);
            }
        } elseif ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('end_date')) {
            try {
                $endDate = \Carbon\Carbon::createFromFormat('d-m-Y', $request->end_date)->format('Y-m-d');
                $query->whereDate('created_at', '<=', $endDate);
            } catch (\Exception $e) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
        } elseif ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by currency if provided
        if ($request->has('currency')) {
            $query->where('currency', $request->currency);
        }

        // Filter by year if provided
        if ($request->has('year')) {
            $query->where('receipt_year', $request->year);
        }

        // Filter by Oracle status if provided
        if ($request->has('oracle_status')) {
            $query->where('oracle_status', $request->oracle_status);
        }

        // Search by receipt number if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('receipt_number', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', function($customerQuery) use ($search) {
                      $customerQuery->where('customer_name', 'LIKE', "%{$search}%")
                                   ->orWhere('customer_id', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('cheques', function($chequeQuery) use ($search) {
                      $chequeQuery->where('cheque_no', 'LIKE', "%{$search}%")
                                  ->orWhere('bank_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Set per page limit (default 20, max 100)
        $perPage = min($request->get('per_page', 20), 100);
        
        $receipts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Add receipt_amount to each receipt item
        $receiptsData = $receipts->getCollection()->map(function ($receipt) {
            // Calculate total amount for this receipt (cash + all cheques)
            $totalAmount = ($receipt->cash_amount ?? 0);
            if ($receipt->cheques && $receipt->cheques->count() > 0) {
                $totalAmount += $receipt->cheques->sum('cheque_amount');
            }
            
            // Add receipt_amount to the receipt object (formatted as string with 2 decimals)
            $receipt->receipt_amount = number_format($totalAmount, 2, '.', '');
            return $receipt;
        });

        // Replace the collection in the paginator
        $receipts->setCollection($receiptsData);

        // Add summary statistics
        $totalReceipts = CustomerReceipt::where('created_by', $user->id)->count();
        $totalAmount = CustomerReceipt::where('created_by', $user->id)
            ->selectRaw('
                COALESCE(SUM(cash_amount), 0) as total_cash,
                COUNT(*) as total_count
            ')
            ->first();

        $totalChequeAmount = CustomerReceipt::where('created_by', $user->id)
            ->whereHas('cheques')
            ->with('cheques')
            ->get()
            ->sum(function($receipt) {
                return $receipt->cheques->sum('cheque_amount');
            });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Receipt history retrieved successfully',
            'data' => $receipts->items(),
            'pagination' => [
                'total' => $receipts->total(),
                'count' => $receipts->count(),
                'per_page' => $receipts->perPage(),
                'current_page' => $receipts->currentPage(),
                'total_pages' => $receipts->lastPage(),
                'next_page_url' => $receipts->nextPageUrl(),
                'prev_page_url' => $receipts->previousPageUrl(),
            ],
            'summary' => [
                'total_receipts' => $totalReceipts,
                'total_cash_amount' => $totalAmount->total_cash ?? 0,
                'total_cheque_amount' => $totalChequeAmount,
                'total_combined_amount' => ($totalAmount->total_cash ?? 0) + $totalChequeAmount,
                'user_name' => $user->name,
                'user_email' => $user->email,
            ],
        ], 200);
    }

    /**
     * Get all customer receipts with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomerReceipt::with(['customer', 'createdBy', 'remittanceBank', 'customerBank', 'cheques']);

        // Filter by customer_id if provided
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by receipt type if provided
        if ($request->has('receipt_type')) {
            $query->where('receipt_type', $request->receipt_type);
        }

        // Filter by date range if provided
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by currency if provided
        if ($request->has('currency')) {
            $query->where('currency', $request->currency);
        }

        // Filter by year if provided
        if ($request->has('year')) {
            $query->where('receipt_year', $request->year);
        }

        $receipts = $query->orderBy('created_at', 'desc')->paginate(20);

        // Transform receipts to use cheque_no as receipt_number for cheque-related receipts
        $receipts->getCollection()->transform(function ($receipt) {
            // For cheque_only, cheque_and_cash, use cheque_no if available
            if (in_array($receipt->receipt_type, ['cheque_only', 'cash_and_cheque']) && !empty($receipt->cheque_no)) {
                $receipt->receipt_number = $receipt->cheque_no;
            }
            return $receipt;
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customer receipts retrieved successfully',
            'data' => $receipts,
        ], 200);
    }

    /**
     * Get a specific customer receipt.
     */
    public function show($id): JsonResponse
    {
        $receipt = CustomerReceipt::with(['customer', 'createdBy', 'remittanceBank', 'customerBank', 'cheques'])->find($id);

        if (!$receipt) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Customer receipt not found',
            ], 404);
        }

        // Transform receipt to use cheque_no as receipt_number for cheque-related receipts
        if (in_array($receipt->receipt_type, ['cheque_only', 'cash_and_cheque']) && !empty($receipt->cheque_no)) {
            $receipt->receipt_number = $receipt->cheque_no;
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customer receipt retrieved successfully',
            'data' => $receipt,
        ], 200);
    }

    /**
     * Update a customer receipt.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $receipt = CustomerReceipt::find($id);

        if (!$receipt) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Customer receipt not found',
            ], 404);
        }

        $validator = $this->validateReceipt($request, $id);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Handle cheque image upload if provided
        if ($request->hasFile('cheque_image')) {
            // Delete old image if exists
            if ($receipt->cheque_image) {
                Storage::disk('public')->delete($receipt->cheque_image);
            }
            $validated['cheque_image'] = $request->file('cheque_image')->store('cheque-images', 'public');
        }

        // Update receipt type based on new data
        $validated['receipt_type'] = $this->determineReceiptTypeFromData($validated);

        $receipt->update($validated);
        $receipt->load(['customer', 'createdBy', 'remittanceBank', 'customerBank']);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customer receipt updated successfully',
            'data' => $receipt,
        ], 200);
    }

    /**
     * Delete a customer receipt.
     */
    public function destroy($id): JsonResponse
    {
        $receipt = CustomerReceipt::find($id);

        if (!$receipt) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Customer receipt not found',
            ], 404);
        }

        // Delete cheque image if exists
        if ($receipt->cheque_image) {
            Storage::disk('public')->delete($receipt->cheque_image);
        }

        $receipt->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customer receipt deleted successfully',
        ], 200);
    }

    /**
     * Validate receipt data based on different scenarios.
     */
    private function validateReceipt(Request $request, $receiptId = null): \Illuminate\Validation\Validator
    {
        // Debug what we're validating
        \Log::info('=== API Validation Debug ===', [
            'request_all' => $request->all(),
            'request_files' => $request->allFiles(),
            'cheques_data' => $request->input('cheques'),
            'has_cheques_input' => $request->has('cheques'),
            'cheques_is_array' => is_array($request->input('cheques')),
        ]);
        
        $rules = [
            'customer_id' => 'required|exists:customers,customer_id',
            'overall_credit_limit' => 'nullable|numeric|min:0',
            'outstanding' => 'nullable|numeric|min:0',
            'cash_amount' => 'nullable|numeric|min:0',
            'currency' => 'required|in:PKR,USD',
            'cash_maturity_date' => 'nullable|date',
            'remittance_bank_id' => 'nullable|string|max:255',
            'remittance_bank_name' => 'nullable|string|max:255',
            'customer_bank_id' => 'nullable|string|max:255',
            'customer_bank_name' => 'nullable|string|max:255',
            'description' => 'required|string|max:1000',
            
            // Multiple cheques validation
            'cheques' => 'nullable|array|max:10',
            'cheques.*.bank_name' => 'required_with:cheques|string|max:255',
            'cheques.*.instrument_id' => 'nullable|string|max:255',
            'cheques.*.instrument_name' => 'nullable|string|max:255',
            'cheques.*.instrument_account_name' => 'nullable|string|max:255',
            'cheques.*.instrument_account_num' => 'nullable|string|max:255',
            'cheques.*.org_id' => 'nullable|string|max:255',
            'cheques.*.cheque_no' => 'required_with:cheques|string|max:255',
            'cheques.*.cheque_amount' => 'required_with:cheques|numeric|min:0.01',
            'cheques.*.cheque_date' => 'required_with:cheques|date',
            'cheques.*.reference' => 'nullable|string|max:255',
            'cheques.*.comments' => 'nullable|string|max:1000',
            'cheques.*.is_third_party_cheque' => 'nullable|in:true,false,1,0',
            'cheques.*.maturity_date' => 'nullable|date',
            'cheques.*.cheque_images' => 'nullable|array|max:5',
            'cheques.*.cheque_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            // Support both array notation and Multer style
            'cheques.*.cheque_image' => 'nullable', // Can be single file OR array
            'cheques.*.cheque_image.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        $validator = Validator::make($request->all(), $rules);
        
        // Debug validation data structure
        \Log::info('=== API Validation Data Structure ===', [
            'validation_data_keys' => array_keys($validator->getData()),
            'validation_data' => $validator->getData(),
            'files_in_validation' => array_filter($validator->getData(), function($value) {
                return is_array($value) && isset($value[0]) && $value[0] instanceof \Illuminate\Http\UploadedFile;
            })
        ]);

        // Custom validation logic
        $validator->after(function ($validator) use ($request) {
            $hasCash = $request->filled('cash_amount') && $request->cash_amount > 0;
            $hasCheques = $request->filled('cheques') && is_array($request->cheques) && count($request->cheques) > 0;

            // If no cash and no cheques, error
            // if (!$hasCash && !$hasCheques) {
            //     $validator->errors()->add('payment', 'Either cash amount or at least one cheque must be provided.');
            // }

            // Validate each cheque
            if ($hasCheques) {
                foreach ($request->cheques as $index => $cheque) {
                    // Check for duplicate cheque numbers in the same receipt
                    $chequeNumbers = collect($request->cheques)->pluck('cheque_no')->filter();
                    if ($chequeNumbers->count() !== $chequeNumbers->unique()->count()) {
                        $validator->errors()->add('cheques', 'Duplicate cheque numbers are not allowed in the same receipt.');
                        break;
                    }
                    
                    // Custom validation for cheque_image (both formats)
                    $chequeImageField = "cheques[{$index}][cheque_image]";
                    if ($request->hasFile($chequeImageField)) {
                        $chequeImages = $request->file($chequeImageField);
                        
                        // Handle both single file and array of files
                        if (is_array($chequeImages)) {
                            // Array format: cheques[0][cheque_image][] or Multer style
                            if (count($chequeImages) > 5) {
                                $validator->errors()->add("cheques.{$index}.cheque_image", 'Maximum 5 images allowed per cheque.');
                            }
                            foreach ($chequeImages as $imgIndex => $image) {
                                if (!$image->isValid()) {
                                    $validator->errors()->add("cheques.{$index}.cheque_image.{$imgIndex}", 'Invalid image file.');
                                }
                                if ($image->getSize() > 2048 * 1024) { // 2MB
                                    $validator->errors()->add("cheques.{$index}.cheque_image.{$imgIndex}", 'Image size must be less than 2MB.');
                                }
                            }
                        } else {
                            // Single file
                            if (!$chequeImages->isValid()) {
                                $validator->errors()->add("cheques.{$index}.cheque_image", 'Invalid image file.');
                            }
                            if ($chequeImages->getSize() > 2048 * 1024) { // 2MB
                                $validator->errors()->add("cheques.{$index}.cheque_image", 'Image size must be less than 2MB.');
                            }
                        }
                    }
                }
            }
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
     * Determine receipt type based on multiple cheques data.
     */
    private function determineReceiptTypeFromData(array $data): string
    {
        $hasCash = isset($data['cash_amount']) && $data['cash_amount'] > 0;
        $hasCheques = isset($data['cheques']) && is_array($data['cheques']) && count($data['cheques']) > 0;

        if ($hasCash && $hasCheques) {
            return 'cash_and_cheque';
        } elseif ($hasCheques) {
            return 'cheque_only';
        } else {
            return 'cash_only';
        }
    }

    /**
     * Insert receipt into Oracle database
     */
    private function insertToOracle($receipt, $customer, $validated)
    {
        // Use validated data or fallback to receipt/customer data
        $oracleCustomerId = $validated['customer_id'] ?? $this->getOracleCustomerId($customer);
        $ouId = $validated['ou_id'] ?? $customer->ou_id ?? 1;
        $whId = auth()->user()->warehouse_id ?? $customer->warehouse_id ?? null;
        
        // Calculate total amount from validated data or receipt
        $totalAmount = $validated['receipt_amount'] ?? $receipt->cash_amount ?? 0;
        
        // Add cheque amounts if not provided in validated data
        if (!isset($validated['receipt_amount']) && $receipt->cheques && $receipt->cheques->count() > 0) {
            $totalAmount += $receipt->cheques->sum('cheque_amount');
        }
        
        // Create Oracle receipt using helper method
        $oracleReceiptNumber = OracleReceipt::createFromCustomerReceipt($receipt, $ouId, $oracleCustomerId, $whId);
        
        Log::info('Receipt inserted into Oracle successfully', [
            'laravel_receipt_id' => $receipt->id,
            'oracle_receipt_number' => $oracleReceiptNumber,
            'customer_id' => $oracleCustomerId,
            'amount' => $totalAmount
        ]);
        
        // Return a simple object with receipt number for compatibility
        return (object) [
            'receipt_number' => $oracleReceiptNumber,
            'ou_id' => $ouId,
            'customer_id' => $oracleCustomerId,
            'amount' => $totalAmount
        ];
    }
    
    /**
     * Get Oracle customer ID from Laravel customer
     */
    private function getOracleCustomerId($customer)
    {
        // If customer has oracle_customer_id field, use it
        if (isset($customer->oracle_customer_id)) {
            return $customer->oracle_customer_id;
        }
        
        // Otherwise, use the customer_id (assuming it matches Oracle)
        return $customer->customer_id;
    }
    
    /**
     * Map Laravel receipt type to Oracle receipt method ID
     */
    private function getOracleReceiptMethod($receipt)
    {
        // Check if it has cheques
        if ($receipt->cheques && $receipt->cheques->count() > 0) {
            if ($receipt->cash_amount > 0) {
                return 5; // Mixed payment
            } else {
                return 2; // Cheque only
            }
        }
        
        // Check if it has cash
        if ($receipt->cash_amount > 0) {
            return 1; // Cash
        }
        
        // Default to cash
        return 1;
    }
}