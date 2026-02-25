<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'receipt_number',
        'receipt_year',
        'overall_credit_limit',
        'outstanding',
        'cash_amount',
        'currency',
        'cash_maturity_date',
        'cheque_no',
        'cheque_amount',
        'maturity_date',
        'cheque_comments',
        'is_third_party_cheque',
        'remittance_bank_id',
        'remittance_bank_name',
        'customer_bank_id',
        'customer_bank_name',
        'cheque_image',
        'description',
        'receipt_type',
        'created_by',
        'oracle_status',
        'oracle_receipt_number',
        'oracle_entered_at',
        'oracle_entered_by',
        // Oracle-specific fields
        'ou_id',
        'receipt_amount',
        'receipt_date',
        'receipt_method',
        'status',
        'comments',
        'creation_date',
        'bank_account_id',
    ];

    protected $casts = [
        'overall_credit_limit' => 'decimal:2',
        'outstanding' => 'decimal:2',
        'cash_amount' => 'decimal:2',
        'cheque_amount' => 'decimal:2',
        'maturity_date' => 'date',
        'cash_maturity_date' => 'date',
        'is_third_party_cheque' => 'boolean',
        'receipt_year' => 'integer',
        'oracle_entered_at' => 'datetime',
        // Oracle-specific casts
        'receipt_amount' => 'decimal:2',
        'receipt_date' => 'date',
        'creation_date' => 'datetime',
    ];

    /**
     * Get the customer that owns the receipt.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * Get the user who created the receipt.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who entered the receipt to Oracle.
     */
    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'oracle_entered_by');
    }

    /**
     * Get the remittance bank details from Oracle.
     */
    public function remittanceBank()
    {
        return $this->belongsTo(OracleBankMaster::class, 'remittance_bank_id', 'bank_id');
    }

    /**
     * Get the customer bank details from Oracle.
     */
    public function customerBank()
    {
        return $this->belongsTo(OracleBankMaster::class, 'customer_bank_id', 'bank_id');
    }

    /**
     * Get all cheques associated with this receipt.
     */
    public function cheques()
    {
        return $this->hasMany(ReceiptCheque::class);
    }

    /**
     * Calculate outstanding amount based on credit limit and current outstanding.
     */
    public function calculateOutstanding()
    {
        // This can be customized based on business logic
        // For now, it's stored directly, but could be calculated from orders/payments
        return $this->outstanding;
    }

    /**
     * Get the total receipt amount (cash + all cheques).
     */
    public function getTotalAmountAttribute()
    {
        $cashAmount = $this->cash_amount ?? 0;
        $totalChequeAmount = $this->cheques->sum('cheque_amount');
        return $cashAmount + $totalChequeAmount;
    }

    /**
     * Get total cheque amount from all cheques.
     */
    public function getTotalChequeAmountAttribute()
    {
        return $this->cheques->sum('cheque_amount');
    }

    /**
     * Get count of cheques.
     */
    public function getChequesCountAttribute()
    {
        return $this->cheques->count();
    }

    /**
     * Check if receipt has cheque.
     */
    public function hasCheque()
    {
        return $this->cheques->count() > 0;
    }

    /**
     * Check if receipt has cash.
     */
    public function hasCash()
    {
        return $this->cash_amount > 0;
    }

    /**
     * Generate unique receipt number for the current year.
     */
    public static function generateReceiptNumber($year = null)
    {
        $year = $year ?: date('Y');
        
        // Get the last receipt number for the year
        $lastReceipt = self::where('receipt_year', $year)
            ->orderBy('receipt_number', 'desc')
            ->first();

        if (!$lastReceipt) {
            $sequence = 1;
        } else {
            // Extract sequence number from receipt number (format: YEAR-NNNN)
            $parts = explode('-', $lastReceipt->receipt_number);
            $sequence = isset($parts[1]) ? intval($parts[1]) + 1 : 1;
        }

        return sprintf('%d-%04d', $year, $sequence);
    }

    /**
     * Get formatted currency amount.
     */
    public function getFormattedAmountAttribute()
    {
        $symbol = $this->currency === 'USD' ? '$' : 'PKR ';
        return $symbol . number_format($this->total_amount, 2);
    }

    /**
     * Get formatted cash amount.
     */
    public function getFormattedCashAmountAttribute()
    {
        if (!$this->cash_amount) return null;
        $symbol = $this->currency === 'USD' ? '$' : 'PKR ';
        return $symbol . number_format($this->cash_amount, 2);
    }

    /**
     * Get formatted cheque amount.
     */
    public function getFormattedChequeAmountAttribute()
    {
        if (!$this->cheque_amount) return null;
        $symbol = $this->currency === 'USD' ? '$' : 'PKR ';
        return $symbol . number_format($this->cheque_amount, 2);
    }

    /**
     * Scope to filter by year.
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('receipt_year', $year);
    }

    /**
     * Scope to filter by currency.
     */
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }
}