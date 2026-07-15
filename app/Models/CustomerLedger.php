<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerLedger extends Model
{
    use HasFactory;
    protected $fillable = [

        'customer_code',

        'ledger_import_id',

        'transaction_date',

        'document_type',

        'document_no',

        'description',

        'debit',

        'credit',

        'balance',

    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class,'customer_code');
    }

    public function import()
    {
        return $this->belongsTo(LedgerImport::class, 'ledger_import_id');
    }
}
