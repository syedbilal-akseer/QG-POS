<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorBillApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_bill_id',
        'step',
        'action',
        'remarks',
        'user_id',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function vendorBill() { return $this->belongsTo(VendorBill::class); }
    public function user()       { return $this->belongsTo(User::class); }
}
