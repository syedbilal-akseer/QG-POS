<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceListUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_filename',
        'total_rows',
        'updated_rows',
        'new_rows',
        'error_rows',
        'error_details',
        'status',
        'notes',
        'uploaded_at',
        'uploaded_by',
    ];

    protected $casts = [
        'error_details' => 'array',
        'uploaded_at' => 'datetime',
        'total_rows' => 'integer',
        'updated_rows' => 'integer',
        'new_rows' => 'integer',
        'error_rows' => 'integer',
    ];

    /**
     * Get the user who uploaded the price list.
     */
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'processing' => 'warning',
            'completed' => 'success',
            'failed' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get the formatted upload summary.
     */
    public function getSummaryAttribute()
    {
        return "Updated: {$this->updated_rows}, New: {$this->new_rows}, Errors: {$this->error_rows}";
    }
}