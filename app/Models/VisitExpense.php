<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitExpense extends Model
{
    use HasFactory;


    protected $fillable = [
        'visit_id',
        'expense_type',
        'expense_details', // JSON
        'total',
        'comments',
        'attachments',
        'status',
        'line_manager_approval',
        'hod_approval',
        'rejection_reason',
    ];

    protected $casts = [
        'expense_details' => 'json',
        'line_manager_approval' => 'boolean',
        'hod_approval' => 'boolean',
    ];

    public function getExpenseDetailsAttribute($value)
    {
        return json_decode($value, true);
    }

    public function setExpenseDetailsAttribute($value)
    {
        $this->attributes['expense_details'] = json_encode($value);
    }

    public function getAttachmentsAttribute($value)
    {
        return json_decode($value, true);
    }

    public function setAttachmentsAttribute($value)
    {
        $this->attributes['attachments'] = json_encode($value);
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }
}
