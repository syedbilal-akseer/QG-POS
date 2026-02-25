<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'org_id',
        'receipt_classes',
        'receipt_method_id',
        'receipt_class_id',
        'bank_account_name',
        'bank_account_num',
        'bank_account_id',
        'iban_number',
        'bank_name',
        'bank_branch_name',
        'account_type',
        'currency',
        'country',
        'status',
        'created_by',
        'synced_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'creation_date' => 'datetime',
        'ou_id' => 'integer',
    ];

    /**
     * Scope to get active banks only.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter by currency.
     */
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Scope to filter by organization unit.
     */
    public function scopeByOrgUnit($query, $ouId)
    {
        return $query->where('ou_id', $ouId);
    }

    /**
     * Scope to search banks by name, code, or account number.
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('bank_name', 'LIKE', "%{$term}%")
              ->orWhere('bank_account_num', 'LIKE', "%{$term}%")
              ->orWhere('bank_branch_name', 'LIKE', "%{$term}%")
              ->orWhere('bank_account_name', 'LIKE', "%{$term}%");
        });
    }

    /**
     * Get formatted bank display name.
     */
    public function getDisplayNameAttribute()
    {
        return $this->bank_account_name 
            ? "{$this->bank_name} - {$this->bank_account_name}"
            : "{$this->bank_name} - {$this->bank_account_num}";
    }

    /**
     * Get full bank address.
     */
    public function getFullAddressAttribute()
    {
        $address = $this->address1;
        if ($this->address2) {
            $address .= ', ' . $this->address2;
        }
        $address .= ', ' . $this->city;
        if ($this->state) {
            $address .= ', ' . $this->state;
        }
        $address .= ', ' . $this->country;
        if ($this->postal_code) {
            $address .= ' ' . $this->postal_code;
        }
        return $address;
    }

    /**
     * Get bank details for API response.
     */
    public function getBankDetailsAttribute()
    {
        return [
            'bank_info' => [
                'name' => $this->bank_name,
                'branch_name' => $this->bank_branch_name,
            ],
            'account_info' => [
                'number' => $this->bank_account_num,
                'name' => $this->bank_account_name,
                'type' => $this->account_type,
                'currency' => $this->currency,
                'iban' => $this->iban_number,
            ],
            'receipt_info' => [
                'method_id' => $this->receipt_method_id,
                'class_id' => $this->receipt_class_id,
                'classes' => $this->receipt_classes,
            ],
        ];
    }

    /**
     * Receipts that use this bank as remittance bank.
     */
    public function remittanceReceipts()
    {
        return $this->hasMany(CustomerReceipt::class, 'remittance_bank_id');
    }

    /**
     * Receipts that use this bank as customer bank.
     */
    public function customerReceipts()
    {
        return $this->hasMany(CustomerReceipt::class, 'customer_bank_id');
    }
}