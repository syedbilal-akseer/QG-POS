<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptCheque extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_receipt_id',
        'bank_name',
        'instrument_id',
        'instrument_name',
        'instrument_account_name',
        'instrument_account_num',
        'org_id',
        'cheque_no',
        'cheque_amount',
        'cheque_date',
        'reference',
        'comments',
        'cheque_images',
        'is_third_party_cheque',
        'maturity_date',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'cheque_amount' => 'decimal:2',
        'cheque_date' => 'date',
        'maturity_date' => 'date',
        'is_third_party_cheque' => 'boolean',
        'cheque_images' => 'array',
    ];

    /**
     * Get the customer receipt that owns this cheque.
     */
    public function customerReceipt()
    {
        return $this->belongsTo(CustomerReceipt::class);
    }

    /**
     * Get formatted cheque amount with currency.
     */
    public function getFormattedAmountAttribute()
    {
        $currency = $this->customerReceipt->currency ?? 'PKR';
        $symbol = $currency === 'USD' ? '$' : 'PKR ';
        return $symbol . number_format($this->cheque_amount, 2);
    }

    /**
     * Get cheque status badge color.
     */
    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'pending' => 'bg-warning',
            'cleared' => 'bg-success',
            'bounced' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            default => 'bg-info',
        };
    }

    /**
     * Get cheque status display text.
     */
    public function getStatusDisplayAttribute()
    {
        return match($this->status) {
            'pending' => 'Pending',
            'cleared' => 'Cleared',
            'bounced' => 'Bounced',
            'cancelled' => 'Cancelled',
            default => 'Unknown',
        };
    }

    /**
     * Check if cheque is overdue.
     */
    public function getIsOverdueAttribute()
    {
        if (!$this->maturity_date || $this->status !== 'pending') {
            return false;
        }
        return $this->maturity_date->isPast();
    }

    /**
     * Get days until/since maturity.
     */
    public function getDaysToMaturityAttribute()
    {
        if (!$this->maturity_date) {
            return null;
        }
        return now()->diffInDays($this->maturity_date, false);
    }

    /**
     * Scope to get pending cheques.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get cleared cheques.
     */
    public function scopeCleared($query)
    {
        return $query->where('status', 'cleared');
    }

    /**
     * Scope to get overdue cheques.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
                     ->where('maturity_date', '<', now()->toDateString());
    }

    /**
     * Scope to filter by bank name.
     */
    public function scopeByBank($query, $bankName)
    {
        return $query->where('bank_name', 'LIKE', "%{$bankName}%");
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('cheque_date', [$startDate, $endDate]);
    }

    /**
     * Get cheque details for API response.
     */
    public function getChequeDetailsAttribute()
    {
        return [
            'id' => $this->id,
            'bank_name' => $this->bank_name,
            'cheque_no' => $this->cheque_no,
            'cheque_amount' => $this->cheque_amount,
            'formatted_amount' => $this->formatted_amount,
            'cheque_date' => $this->cheque_date?->format('Y-m-d'),
            'maturity_date' => $this->maturity_date?->format('Y-m-d'),
            'reference' => $this->reference,
            'comments' => $this->comments,
            'cheque_images' => $this->cheque_images ?? [],
            'is_third_party_cheque' => $this->is_third_party_cheque,
            'status' => $this->status,
            'status_display' => $this->status_display,
            'status_badge' => $this->status_badge,
            'is_overdue' => $this->is_overdue,
            'days_to_maturity' => $this->days_to_maturity,
        ];
    }
}