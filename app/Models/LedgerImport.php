<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per bulk ledger PDF upload — tracks how many customers were
 * found in the file, how many were newly imported vs already-imported
 * duplicates (same customer + period), and how many failed to split.
 */
class LedgerImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_filename',
        'source_file_hash',
        'period_from',
        'period_to',
        'customers_found',
        'imported_count',
        'duplicate_count',
        'failed_count',
        'status',
        'error',
        'uploaded_by',
        'uploaded_at',
        'notes',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'uploaded_at' => 'datetime',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function customerLedgers()
    {
        return $this->hasMany(CustomerLedger::class);
    }
}
