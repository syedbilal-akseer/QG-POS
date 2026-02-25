<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OracleReceipt extends Model
{
    protected $connection = 'oracle';
    protected $table = 'apps.qg_pos_receipts';
    
    // Oracle doesn't use Laravel's created_at/updated_at by default
    public $timestamps = false;
    
    // Disable primary key for insert-only operations
    public $incrementing = false;
    protected $primaryKey = null;
    
    protected $fillable = [
        'ou_id',
        'receipt_number',
        'customer_id',
        'receipt_amount',
        'receipt_date',
        'status',
        'comments',
        'receipt_method_id',
        'cash_receipt_id',
        'sales_order_number',
        'creation_date',
        'wh_id',
        'type',
        'payment_ref',
        'bank_account_id',
    ];
    
    protected $casts = [
        'receipt_amount' => 'decimal:2',
        'receipt_date' => 'datetime',
        'creation_date' => 'datetime',
        'ou_id' => 'integer',
        'receipt_number' => 'integer',
        'customer_id' => 'integer',
        'receipt_method_id' => 'integer',
        'cash_receipt_id' => 'integer',
        'sales_order_number' => 'integer',
        'wh_id' => 'integer',
        'bank_account_id' => 'integer',
    ];
    
    /**
     * Generate next unique receipt number for the given OU
     */
    public static function getNextReceiptNumber($ouId)
    {
        // Start from the highest existing receipt number
        $lastReceipt = self::where('ou_id', $ouId)
            ->orderBy('receipt_number', 'desc')
            ->first();
            
        $nextNumber = $lastReceipt ? $lastReceipt->receipt_number + 1 : 1;
        
        // Ensure uniqueness by checking if the number already exists
        while (self::where('ou_id', $ouId)->where('receipt_number', $nextNumber)->exists()) {
            $nextNumber++;
        }
        
        return $nextNumber;
    }
    
    /**
     * Create receipt from Laravel CustomerReceipt data
     */
    public static function createFromCustomerReceipt($customerReceipt, $ouId, $customerId, $whId = null)
    {
        $receiptNumber = self::getNextReceiptNumber($ouId);
        
        // Calculate total amount
        $totalAmount = ($customerReceipt->cash_amount ?? 0);
        if ($customerReceipt->cheques && $customerReceipt->cheques->count() > 0) {
            $totalAmount += $customerReceipt->cheques->sum('cheque_amount');
        } else if ($customerReceipt->cheque_amount) {
            $totalAmount += $customerReceipt->cheque_amount;
        }
        
        // Get receipt_method_id from bank based on bank_account_id and ouId
        $receiptMethodId = self::getReceiptMethodIdFromBank($customerReceipt->bank_account_id, $customerReceipt->receipt_type, $ouId);
        
        // Set receipt number based on payment type
        $oracleReceiptNumber = $receiptNumber; // Default to auto-generated

        if ($customerReceipt->receipt_type === 'cash_only') {
            // For cash_only, use auto-generated receipt number (like 2509001)
            $oracleReceiptNumber = $receiptNumber;
        } elseif (in_array($customerReceipt->receipt_type, ['cash_and_cheque', 'cheque_only'])) {
            // For cash_and_cheque or cheque_only, use bank account number as receipt number
            if ($customerReceipt->bank_account_id) {
                $oracleReceiptNumber = $customerReceipt->bank_account_id;
            } else {
                // Fallback to auto-generated if no bank account
                $oracleReceiptNumber = $receiptNumber;
            }
        }
        
        $data = [
            'ou_id' => $ouId,
            'receipt_number' => $oracleReceiptNumber, // Use cheque number (numeric) as per requirement
            'customer_id' => $customerId,
            'receipt_amount' => $totalAmount,
            'receipt_date' => $customerReceipt->receipt_date ?? $customerReceipt->created_at,
            'status' => null, // Set to NULL as required
            'comments' => $customerReceipt->description ?? $customerReceipt->comments ?? '', // Use description (receipt comments) first
            'receipt_method_id' => $receiptMethodId,
            'creation_date' => $customerReceipt->creation_date ?? now(),
            'wh_id' => $whId,
            'type' => 'MOBILE',
            'payment_ref' => $customerReceipt->receipt_number, // Keep original receipt number as reference
            'bank_account_id' => $customerReceipt->bank_account_id,
        ];
        
        // Console log data being inserted to Oracle (for debugging)
        \Log::info('=== Oracle Receipt Insert Data ===', [
            'laravel_receipt_id' => $customerReceipt->id,
            'oracle_data' => $data,
            'bank_lookup' => [
                'bank_account_id' => $customerReceipt->bank_account_id,
                'receipt_method_id' => $receiptMethodId,
                'receipt_type' => $customerReceipt->receipt_type,
            ],
            'original_receipt_fields' => [
                'ou_id' => $customerReceipt->ou_id ?? 'Not set',
                'bank_account_id' => $customerReceipt->bank_account_id ?? 'Not set',
                'receipt_amount' => $customerReceipt->receipt_amount ?? 'Calculated: ' . $totalAmount,
                'receipt_date' => $customerReceipt->receipt_date ?? 'Using created_at',
                'status' => 'Set to NULL as required',
                'cheque_no_original' => $customerReceipt->cheque_no ?? 'No cheque number',
                'receipt_number_used' => $oracleReceiptNumber . ' (cheque_no: ' . ($customerReceipt->cheque_no ?? 'none') . ')',
                'comments' => $customerReceipt->description ?? $customerReceipt->comments ?? 'No comments',
                'creation_date' => $customerReceipt->creation_date ?? 'Using now()',
            ]
        ]);
        
        try {
            self::insert($data);
            
            \Log::info('Oracle receipt insert successful', [
                'receipt_number' => $receiptNumber,
                'oracle_receipt_number' => $oracleReceiptNumber
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Oracle receipt insert failed', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Failed to insert receipt to Oracle: ' . $e->getMessage());
        }
        
        // Return receipt number for tracking
        return $receiptNumber;
    }
    
    /**
     * Get receipt_method_id from qg_bank_master view based on bank_account_id
     */
    private static function getReceiptMethodIdFromBank($bankAccountId, $receiptType, $ouId = null)
    {
        if (!$bankAccountId) {
            \Log::warning('No bank_account_id provided, using fallback receipt_method_id');
            return self::mapReceiptMethod($receiptType);
        }
        
        try {
            // Query the qg_bank_master view to get receipt_method_id and org_id
            $query = \DB::connection('oracle')
                ->table('apps.qg_bank_master')
                ->select('receipt_method_id', 'org_id')
                ->where('bank_account_id', $bankAccountId);
            
            // Filter by Operating Unit if provided (Crucial for non-unique account IDs)
            if ($ouId) {
                $query->where('org_id', $ouId);
            }
                
            $bankData = $query->first();
            
            if ($bankData && $bankData->receipt_method_id) {
                \Log::info('Found receipt_method_id from qg_bank_master', [
                    'bank_account_id' => $bankAccountId,
                    'receipt_method_id' => $bankData->receipt_method_id,
                    'org_id' => $bankData->org_id
                ]);
                return $bankData->receipt_method_id;
            } else {
                \Log::warning('No receipt_method_id found in qg_bank_master, using fallback', [
                    'bank_account_id' => $bankAccountId,
                    'found_record' => $bankData ? 'yes' : 'no'
                ]);
                return self::mapReceiptMethod($receiptType);
            }
        } catch (\Exception $e) {
            \Log::error('Error querying qg_bank_master for receipt_method_id', [
                'bank_account_id' => $bankAccountId,
                'error' => $e->getMessage()
            ]);
            return self::mapReceiptMethod($receiptType);
        }
    }

    /**
     * Map Laravel receipt method to Oracle receipt method ID (fallback)
     */
    private static function mapReceiptMethod($receiptType)
    {
        $mapping = [
            'cash_only' => 1,
            'cheque_only' => 2,
            'cash_and_cheque' => 5, // Mixed
        ];
        
        return $mapping[$receiptType] ?? 1; // Default to cash
    }
    
    /**
     * Get receipt status text
     */
    public function getStatusTextAttribute()
    {
        $statuses = [
            'A' => 'Active',
            'I' => 'Inactive',
            'P' => 'Pending',
            'C' => 'Cancelled',
        ];
        
        return $statuses[$this->status] ?? 'Unknown';
    }
}