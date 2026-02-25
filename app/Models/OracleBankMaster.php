<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OracleBankMaster extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'oracle';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'apps.qg_bank_master';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'bank_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'bank_id',
        'bank_code',
        'bank_name',
        'bank_short_name',
        'branch_code',
        'branch_name',
        'account_number',
        'account_title',
        'account_type',
        'currency',
        'iban',
        'swift_code',
        'routing_number',
        'address1',
        'address2',
        'city',
        'state',
        'country',
        'postal_code',
        'phone_number',
        'fax_number',
        'email',
        'contact_person',
        'contact_person_phone',
        'contact_person_email',
        'bank_gl_account',
        'status',
        'ou_id',
        'ou_name',
        'created_by',
        'updated_by',
        'creation_date',
        'last_update_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'creation_date' => 'datetime',
        'last_update_date' => 'datetime',
    ];

    /**
     * Scope to get active banks only.
     */
    public function scopeActive($query)
    {
        return $query;
    }

    /**
     * Scope to filter by organization unit.
     */
    public function scopeByOrgUnit($query, $ouId)
    {
        return $query->where('ou_id', $ouId);
    }

    /**
     * Scope to search banks by name or code.
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('bank_name', 'LIKE', "%{$term}%")
              ->orWhere('bank_code', 'LIKE', "%{$term}%")
              ->orWhere('bank_short_name', 'LIKE', "%{$term}%")
              ->orWhere('account_number', 'LIKE', "%{$term}%");
        });
    }

    /**
     * Get formatted bank display name.
     */
    public function getDisplayNameAttribute()
    {
        return $this->bank_short_name 
            ? "{$this->bank_short_name} - {$this->account_number}"
            : "{$this->bank_name} - {$this->account_number}";
    }

    /**
     * Get full bank details.
     */
    public function getFullDetailsAttribute()
    {
        return [
            'bank_info' => [
                'name' => $this->bank_name,
                'code' => $this->bank_code,
                'short_name' => $this->bank_short_name,
            ],
            'branch_info' => [
                'code' => $this->branch_code,
                'name' => $this->branch_name,
            ],
            'account_info' => [
                'number' => $this->account_number,
                'title' => $this->account_title,
                'type' => $this->account_type,
                'currency' => $this->currency,
                'iban' => $this->iban,
            ],
            'contact_info' => [
                'phone' => $this->phone_number,
                'email' => $this->email,
                'address' => trim($this->address1 . ' ' . $this->address2),
                'city' => $this->city,
            ],
        ];
    }
}