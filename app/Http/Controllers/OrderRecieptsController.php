<?php

namespace App\Http\Controllers;
// Fixed class declaration conflict

use App\Models\CustomerReceipt;
use App\Models\Bank;
use App\Models\OracleReceipt;
use Illuminate\Http\Request;

class OrderRecieptsController extends Controller
{
    public function index(Request $request)
    {
        $pageTitle = 'Customer Receipts';

        $query = CustomerReceipt::with(['customer', 'createdBy', 'cheques'])
            ->orderBy('created_at', 'desc');

        // Apply filters if provided
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

        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }

        if ($request->filled('receipt_type')) {
            $query->where('receipt_type', $request->receipt_type);
        }

        if ($request->filled('year')) {
            $query->where('receipt_year', $request->year);
        }

        $receipts = $query->paginate(20);
        $banks = Bank::active()->get();

        return view('frontend.reciepts', compact('pageTitle', 'receipts', 'banks'));
    }

    /**
     * Show receipt details for modal view
     */
    public function show($id)
    {
        $receipt = CustomerReceipt::with(['customer', 'createdBy'])->findOrFail($id);
        return view('admin.receipts.show', compact('receipt'));
    }

    /**
     * Show the form for editing the specified receipt.
     */
    public function edit($id)
    {
        $receipt = CustomerReceipt::with(['customer'])->findOrFail($id);
        $customers = \App\Models\Customer::select('customer_id', 'customer_name', 'overall_credit_limit', 'ou_id', 'ou_name')
            ->orderBy('customer_name')
            ->get();
            
        // Initialize variables with defaults
        $operatingUnits = collect();
        
        // Debug: Check what's in the banks table
        $totalBanks = Bank::count();
        $activeBanks = Bank::active()->count();
        \Log::info("Banks debug - Total: {$totalBanks}, Active: {$activeBanks}");
        
        // Get banks from local SQL table (remove active scope to get all banks)
        $oracleBanks = Bank::select('bank_account_id', 'bank_name', 'bank_account_name', 'bank_account_num', 'org_id', 'status')
            ->orderBy('bank_name')
            ->get();
            
        \Log::info("Banks query result count: " . $oracleBanks->count());
        if ($oracleBanks->count() > 0) {
            \Log::info("First bank sample: " . json_encode($oracleBanks->first()->toArray()));
        }
            
        // Add ou_id attribute for consistency
        $oracleBanks->each(function($bank) {
            $bank->ou_id = $bank->org_id;
        });
        
        $banks = Bank::active()->get(); // Keep for backward compatibility

        return view('admin.receipts.edit', compact('receipt', 'customers', 'banks', 'operatingUnits', 'oracleBanks'));
    }

    /**
     * Update the specified receipt.
     */
    public function update(\Illuminate\Http\Request $request, $id)
    {
        $receipt = CustomerReceipt::findOrFail($id);
        $validator = $this->validateReceipt($request, $id);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        // Debug: Log what data is being submitted
        \Log::info('Receipt update data:', $validated);
        \Log::info('OU_ID in request:', ['ou_id' => $request->input('ou_id')]);
        \Log::info('Bank Account ID in request:', ['bank_account_id' => $request->input('bank_account_id')]);

        // Handle cheque image upload
        if ($request->hasFile('cheque_image')) {
            // Delete old image if exists
            if ($receipt->cheque_image) {
                
                $oldPath = str_replace(asset('storage/'), '', $receipt->cheque_image);
                \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
            }
            $validated['cheque_image'] = asset('storage/' . $request->file('cheque_image')->store('cheque-images', 'public'));
        }

        // Set Oracle-specific fields with defaults if needed
        if (isset($validated['creation_date']) && !$validated['creation_date']) {
            $validated['creation_date'] = now();
        }

        $receipt->update($validated);

        // Handle multiple cheques update/creation if present
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
        
        // Debug: Log what was actually saved
        \Log::info('Receipt after update:', $receipt->toArray());

        return redirect()->route('reciepts')->with('success', 'Receipt updated successfully!');
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
            \Illuminate\Support\Facades\Storage::disk('public')->delete($imagePath);
        }

        $receipt->delete();

        return redirect()->route('reciepts')->with('success', 'Receipt deleted successfully!');
    }

    /**
     * Validate receipt data.
     */
    private function validateReceipt(\Illuminate\Http\Request $request, $receiptId = null)
    {
        $rules = [
            'customer_id' => 'required|string',
            'outstanding' => 'nullable|numeric|min:0',
            'cash_amount' => 'nullable|numeric|min:0',
            'currency' => 'required|in:PKR,USD',
            'cash_maturity_date' => 'nullable|date|after_or_equal:today',
            'cheque_no' => 'nullable|string|max:255',
            'cheque_amount' => 'nullable|numeric|min:0',
            'maturity_date' => 'nullable|date',
            'cheque_comments' => 'nullable|string|max:1000',
            'is_third_party_cheque' => 'nullable|boolean',
            'remittance_bank_id' => 'nullable|exists:banks,id',
            'remittance_bank_name' => 'nullable|string|max:255',
            'customer_bank_id' => 'nullable|exists:banks,id',
            'customer_bank_name' => 'nullable|string|max:255',
            'cheque_image' => 'nullable|array|max:5',
            'cheque_image.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'required|string|max:1000',
            // Oracle-specific validation rules
            'ou_id' => 'nullable|string',
            'receipt_amount' => 'nullable|numeric|min:0.01',
            'receipt_date' => 'nullable|date',
            'status' => 'nullable|string',
            'comments' => 'nullable|string|max:2000',
            'creation_date' => 'nullable|date',
            'bank_account_id' => 'required|string',
            // Cheque validation rules
            'cheques.*.instrument_id' => 'nullable|string',
            'cheques.*.cheque_date' => 'nullable|date',
            'cheques.*.maturity_date' => 'nullable|date',
            'cheques.*.cheque_no' => 'nullable|string',
            'cheques.*.cheque_amount' => 'nullable|numeric|min:0.01',
        ];

        $messages = [
            'bank_account_id.required' => 'Please select an instrument/bank account.',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $messages);

        // Custom validation
        $validator->after(function ($validator) use ($request) {
            $hasCash = $request->filled('cash_amount') && $request->cash_amount > 0;
            $hasCheque = $request->filled('cheque_no');
            $hasChequeAmount = $request->filled('cheque_amount') && $request->cheque_amount > 0;

            if (!$hasCash && !$hasCheque) {
                $validator->errors()->add('payment', 'Either cash amount or cheque number must be provided.');
            }

            if ($hasCheque && !$hasChequeAmount) {
                $validator->errors()->add('cheque_amount', 'Cheque amount is required when cheque number is provided.');
            }
        });

        return $validator;
    }

    /**
     * Enter receipt to Oracle
     */
    public function enterToOracle(Request $request, $id)
    {
        try {
            $receipt = CustomerReceipt::findOrFail($id);
            
            // Validate required fields for Oracle including cheque details
            $validationRules = [
                'customer_id' => 'required|string',
                'ou_id' => 'required|string', 
                'bank_account_id' => 'required|string',
                'receipt_amount' => 'required|numeric|min:0.01',
                'receipt_date' => 'required|date',
            ];
            
            // Add cheque validation if cheques exist
            if ($request->has('cheques') && is_array($request->cheques)) {
                foreach ($request->cheques as $index => $cheque) {
                    // Only validate if cheque data exists
                    if (!empty($cheque['cheque_no']) || !empty($cheque['cheque_amount'])) {
                        $validationRules["cheques.{$index}.instrument_id"] = 'required|string';
                        $validationRules["cheques.{$index}.cheque_date"] = 'required|date';
                        $validationRules["cheques.{$index}.maturity_date"] = 'required|date';
                        $validationRules["cheques.{$index}.cheque_no"] = 'required|string';
                        $validationRules["cheques.{$index}.cheque_amount"] = 'required|numeric|min:0.01';
                    }
                }
            }
            
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $validationRules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: ' . $validator->errors()->first()
                ], 400);
            }

            // Update receipt with latest form data before Oracle submission
            $validated = $validator->validated();
            $receipt->update([
                'customer_id' => $validated['customer_id'],
                'ou_id' => $validated['ou_id'],
                'bank_account_id' => $validated['bank_account_id'],
                'receipt_amount' => $validated['receipt_amount'],
                'receipt_date' => $validated['receipt_date'],
                'currency' => $request->currency ?? 'PKR',
                'comments' => $request->comments,
                'description' => $request->description,
                'cash_amount' => $request->cash_amount,
                'cheque_no' => $request->cheque_no,
                'cheque_amount' => $request->cheque_amount,
                'receipt_type' => $this->determineReceiptType($request->all()),
            ]);

            // Refresh receipt to get updated data
            $receipt->refresh();

            // Submit to Oracle using the OracleReceipt model
            $oracleReceiptNumber = \App\Models\OracleReceipt::createFromCustomerReceipt(
                $receipt,
                $validated['ou_id'],
                $validated['customer_id'],
                null // wh_id
            );

            // Update local receipt status
            $receipt->update([
                'oracle_receipt_number' => $oracleReceiptNumber,
                'oracle_entered_at' => now(),
                'oracle_entered_by' => auth()->id(),
                'oracle_status' => 'entered',
            ]);

            \Log::info('Receipt successfully submitted to Oracle', [
                'receipt_id' => $receipt->id,
                'oracle_receipt_number' => $oracleReceiptNumber,
                'ou_id' => $validated['ou_id'],
                'customer_id' => $validated['customer_id'],
                'bank_account_id' => $validated['bank_account_id'],
                'amount' => $validated['receipt_amount']
            ]);

            return response()->json([
                'success' => true,
                'message' => "Receipt successfully entered to Oracle! Oracle Receipt Number: {$oracleReceiptNumber}",
                'oracle_receipt_number' => $oracleReceiptNumber
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to enter receipt to Oracle', [
                'receipt_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to enter receipt to Oracle: ' . $e->getMessage()
            ], 500);
        }
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