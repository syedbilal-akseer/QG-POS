<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'ledger_import_id',
        'original_filename',
        'source_file_hash',
        'customer_code',
        'customer_name',
        'customer_phone',
        'salesperson_raw',
        'salesperson_id',
        'salesperson_name',
        'period_from',
        'period_to',
        'total_debit',
        'total_credit',
        'pdf_path',
        'extracted_pages',
        'page_range',
        'processing_status',
        'uploaded_by',
        'uploaded_at',
        'notes',
        'whatsapp_sent_at',
        'whatsapp_message_id',
        'whatsapp_status',
        'whatsapp_error',
    ];

    protected $casts = [
        'extracted_pages' => 'array',
        'period_from' => 'date',
        'period_to' => 'date',
        'uploaded_at' => 'datetime',
        'whatsapp_sent_at' => 'datetime',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
    ];

    /**
     * The user who uploaded this ledger batch.
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The resolved salesperson user (nullable — the raw text may not match anyone).
     */
    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function scopeByCustomer($query, $customerCode)
    {
        return $query->where('customer_code', $customerCode);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('processing_status', $status);
    }
}
