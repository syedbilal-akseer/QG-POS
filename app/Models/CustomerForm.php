<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'form_type',
        'name',
        'designation',
        'company_name',
        'address',
        'city',
        'mobile',
        'phone',
        'email',
        'skype',
        'is_shoe_material_dealer',
        'is_shoe_manufacturer',
        'is_merchandise',
        'is_cottage',
        'is_ladies',
        'is_gents',
        'capacity',
        'inquiry',
        'sample_given',
        'sample_required',
        'submitted_by',
        'user_id',
        'customer_code',
        'customer_name_linked',
    ];

    protected $casts = [
        'is_shoe_material_dealer' => 'boolean',
        'is_shoe_manufacturer'    => 'boolean',
        'is_merchandise'          => 'boolean',
        'is_cottage'              => 'boolean',
        'is_ladies'               => 'boolean',
        'is_gents'                => 'boolean',
    ];

    /**
     * The salesperson / app user who created this form entry.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The event this form belongs to (optional — older forms have no event).
     */
    public function event()
    {
        return $this->belongsTo(CustomerFormEvent::class, 'event_id');
    }

    /**
     * Scope: filter by form_type (HBM or sales)
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('form_type', $type);
    }

    /**
     * Scope: filter by the authenticated salesperson
     */
    public function scopeForSalesperson($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
