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
     * Create receipt(s) from Laravel CustomerReceipt data.
     *
     * Inserts ONE row into apps.qg_pos_receipts PER cheque, plus an extra row
     * for the cash portion when present. Earlier versions inserted a single
     * row no matter how many cheques the receipt carried — the total amount
     * was summed but only the first cheque's number / bank account survived,
     * which made cheque #2 invisible in Oracle.
     *
     * Returns the FIRST inserted Oracle receipt_number for caller compatibility
     * (callers store this on customer_receipts.oracle_receipt_number).
     */
    public static function createFromCustomerReceipt($customerReceipt, $ouId, $customerId, $whId = null, $explicitReceiptMethodId = null)
    {
        $cashAmount = (float) ($customerReceipt->cash_amount ?? 0);
        $hasNewCheques = $customerReceipt->cheques && $customerReceipt->cheques->count() > 0;
        $hasLegacyCheque = !$hasNewCheques
            && !empty($customerReceipt->cheque_no)
            && (float) ($customerReceipt->cheque_amount ?? 0) > 0;

        // Build a list of Oracle rows to insert. Each entry is a self-contained
        // associative array ready for the qg_pos_receipts INSERT below.
        $rowsToInsert = [];

        $sharedReceiptDate = $customerReceipt->receipt_date ?? $customerReceipt->created_at;
        $sharedCreationDate = $customerReceipt->creation_date ?? now();
        $sharedComments    = $customerReceipt->description ?? $customerReceipt->comments ?? '';
        $paymentRef        = $customerReceipt->receipt_number;

        // ── 1) Cash portion ──────────────────────────────────────────────────
        if ($cashAmount > 0) {
            $cashReceiptMethodId = $explicitReceiptMethodId
                ?: self::getReceiptMethodIdFromBank(
                    $customerReceipt->bank_account_id,
                    'cash_only',
                    $ouId
                );

            $rowsToInsert[] = [
                'ou_id'             => $ouId,
                'receipt_number'    => self::getNextReceiptNumber($ouId),
                'customer_id'       => $customerId,
                'receipt_amount'    => $cashAmount,
                'receipt_date'      => $sharedReceiptDate,
                'status'            => null,
                'comments'          => $sharedComments,
                'receipt_method_id' => $cashReceiptMethodId,
                'creation_date'     => $sharedCreationDate,
                'wh_id'             => $whId,
                'type'              => 'MOBILE',
                'payment_ref'       => $paymentRef,
                'bank_account_id'   => $customerReceipt->bank_account_id,
                '_source'           => 'cash',
            ];
        }

        // ── 2) Each cheque becomes its own Oracle receipt row ────────────────
        if ($hasNewCheques) {
            foreach ($customerReceipt->cheques as $cheque) {
                $bankAccountId = $cheque->instrument_id ?: $customerReceipt->bank_account_id;
                $receiptMethodId = self::getReceiptMethodIdFromBank(
                    $bankAccountId,
                    'cheque_only',
                    $ouId
                );

                $rowsToInsert[] = [
                    'ou_id'             => $ouId,
                    'receipt_number'    => self::resolveChequeReceiptNumber(
                        $cheque->cheque_no,
                        $bankAccountId,
                        $ouId
                    ),
                    'customer_id'       => $customerId,
                    'receipt_amount'    => (float) $cheque->cheque_amount,
                    'receipt_date'      => $sharedReceiptDate,
                    'status'            => null,
                    'comments'          => $sharedComments,
                    'receipt_method_id' => $receiptMethodId,
                    'creation_date'     => $sharedCreationDate,
                    'wh_id'             => $whId,
                    'type'              => 'MOBILE',
                    'payment_ref'       => $paymentRef,
                    'bank_account_id'   => $bankAccountId,
                    '_source'           => 'cheque:' . ($cheque->cheque_no ?? '?'),
                ];
            }
        } elseif ($hasLegacyCheque) {
            // Backward-compat: very old receipts stored a single cheque on the
            // receipt row itself rather than via the receipt_cheques relation.
            $bankAccountId = $customerReceipt->bank_account_id;
            $receiptMethodId = self::getReceiptMethodIdFromBank(
                $bankAccountId,
                'cheque_only',
                $ouId
            );

            $rowsToInsert[] = [
                'ou_id'             => $ouId,
                'receipt_number'    => self::resolveChequeReceiptNumber(
                    $customerReceipt->cheque_no,
                    $bankAccountId,
                    $ouId
                ),
                'customer_id'       => $customerId,
                'receipt_amount'    => (float) $customerReceipt->cheque_amount,
                'receipt_date'      => $sharedReceiptDate,
                'status'            => null,
                'comments'          => $sharedComments,
                'receipt_method_id' => $receiptMethodId,
                'creation_date'     => $sharedCreationDate,
                'wh_id'             => $whId,
                'type'              => 'MOBILE',
                'payment_ref'       => $paymentRef,
                'bank_account_id'   => $bankAccountId,
                '_source'           => 'legacy_cheque',
            ];
        }

        // Edge case: receipt has neither cash nor cheques (zero-value receipt).
        // Insert one bookkeeping row so we don't lose the entry entirely.
        if (empty($rowsToInsert)) {
            $rowsToInsert[] = [
                'ou_id'             => $ouId,
                'receipt_number'    => self::getNextReceiptNumber($ouId),
                'customer_id'       => $customerId,
                'receipt_amount'    => 0,
                'receipt_date'      => $sharedReceiptDate,
                'status'            => null,
                'comments'          => $sharedComments,
                'receipt_method_id' => $explicitReceiptMethodId ?: self::mapReceiptMethod('cash_only'),
                'creation_date'     => $sharedCreationDate,
                'wh_id'             => $whId,
                'type'              => 'MOBILE',
                'payment_ref'       => $paymentRef,
                'bank_account_id'   => $customerReceipt->bank_account_id,
                '_source'           => 'empty',
            ];
        }

        \Log::info('=== Oracle Receipt Insert Plan ===', [
            'laravel_receipt_id' => $customerReceipt->id,
            'receipt_type'       => $customerReceipt->receipt_type,
            'row_count'          => count($rowsToInsert),
            'rows'               => array_map(fn ($r) => [
                'source'            => $r['_source'],
                'receipt_number'    => $r['receipt_number'],
                'receipt_amount'    => $r['receipt_amount'],
                'receipt_method_id' => $r['receipt_method_id'],
                'bank_account_id'   => $r['bank_account_id'],
            ], $rowsToInsert),
        ]);

        $firstInsertedReceiptNumber = null;
        foreach ($rowsToInsert as $row) {
            $source = $row['_source'];
            unset($row['_source']); // not a real Oracle column

            try {
                self::insert($row);

                \Log::info('Oracle receipt insert successful', [
                    'source'            => $source,
                    'receipt_number'    => $row['receipt_number'],
                    'receipt_amount'    => $row['receipt_amount'],
                    'receipt_method_id' => $row['receipt_method_id'],
                ]);

                if ($firstInsertedReceiptNumber === null) {
                    $firstInsertedReceiptNumber = $row['receipt_number'];
                }
            } catch (\Exception $e) {
                \Log::error('Oracle receipt insert failed', [
                    'source' => $source,
                    'data'   => $row,
                    'error'  => $e->getMessage(),
                    'trace'  => $e->getTraceAsString(),
                ]);
                throw new \Exception('Failed to insert receipt to Oracle: ' . $e->getMessage());
            }
        }

        return $firstInsertedReceiptNumber;
    }

    /**
     * Pick the receipt_number to use for a cheque row. Mirrors the original
     * single-row logic: prefer the numeric portion of cheque_no, then fall
     * back to bank_account_id, then an auto-generated sequence number.
     */
    private static function resolveChequeReceiptNumber($chequeNo, $bankAccountId, $ouId)
    {
        if ($chequeNo) {
            $numeric = preg_replace('/[^0-9]/', '', (string) $chequeNo);
            if ($numeric !== '') {
                return $numeric;
            }
        }
        if ($bankAccountId) {
            return $bankAccountId;
        }
        return self::getNextReceiptNumber($ouId);
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