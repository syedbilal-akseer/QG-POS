<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnedCheque extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_receipt_id',
        'receipt_cheque_id',
        'reason',
        'image_path',
        'submitted_by_id',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['image_url'];

    /**
     * Get the full URL of the returned check image.
     */
    public function getImageUrlAttribute()
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    /**
     * Get the customer receipt associated with the returned check.
     */
    public function customerReceipt()
    {
        return $this->belongsTo(CustomerReceipt::class);
    }

    /**
     * Get the specific cheque associated with the return.
     */
    public function cheque()
    {
        return $this->belongsTo(ReceiptCheque::class, 'receipt_cheque_id');
    }

    /**
     * Get the user who submitted the return record.
     */
    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }
}
