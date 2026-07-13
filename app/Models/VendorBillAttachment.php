<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorBillAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_bill_id',
        'file_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    public function vendorBill() { return $this->belongsTo(VendorBill::class); }
    public function uploader()   { return $this->belongsTo(User::class, 'uploaded_by'); }
}
