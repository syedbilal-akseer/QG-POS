<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_filename',
        'customer_code',
        'customer_name',
        'customer_phone',
        'invoice_number',
        'invoice_date',
        'total_amount',
        'pdf_path',
        'extracted_pages',
        'page_range',
        'processing_status',
        'uploaded_by',
        'uploaded_at',
        'notes'
    ];

    protected $casts = [
        'extracted_pages' => 'array',
        'invoice_date' => 'date',
        'uploaded_at' => 'datetime',
        'total_amount' => 'decimal:2'
    ];

    /**
     * Get the user who uploaded this invoice
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope for filtering by customer code
     */
    public function scopeByCustomer($query, $customerCode)
    {
        return $query->where('customer_code', $customerCode);
    }

    /**
     * Scope for filtering by processing status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('processing_status', $status);
    }
}