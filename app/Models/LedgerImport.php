<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerImport extends Model
{
    use HasFactory;
     protected $fillable = [
        'file_name',
        'file_path',
        'status',
        'total_customers',
        'total_transactions',
        'processed_transactions',
        'error_log',
        'created_by'
    ];

    public function ledgers()
    {
        return $this->hasMany(CustomerLedger::class);
    }
}
