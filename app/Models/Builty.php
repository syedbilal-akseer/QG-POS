<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Builty extends Model
{
    use HasFactory;

    protected $table = 'builties';

    /**
     * Builty numbers are auto-generated in the form BLT-YYYY-N where N is the
     * next per-year sequence. Mirrors the order-number pattern in
     * App\Models\Order::booted(): we lockForUpdate before reading so two
     * concurrent uploads in the same second can't collide on the same suffix.
     */
    protected static function booted()
    {
        static::creating(function ($builty) {
            if (!empty($builty->builty_number)) {
                return; // explicit value already provided — keep it
            }

            $year   = now()->format('Y');
            $prefix = "BLT-{$year}-";

            // lockForUpdate prevents two concurrent inserts from reading the
            // same "last" row and producing the same next number.
            $lastNumber = DB::table('builties')
                ->lockForUpdate()
                ->where('builty_number', 'LIKE', $prefix . '%')
                ->orderByDesc('id')
                ->value('builty_number');

            $nextSeq = 1;
            if ($lastNumber && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastNumber, $m)) {
                $nextSeq = ((int) $m[1]) + 1;
            }

            // Safety net for unique-index collisions left by manual DB edits
            // or earlier double-inserts.
            while (DB::table('builties')->where('builty_number', $prefix . $nextSeq)->exists()) {
                $nextSeq++;
            }

            $builty->builty_number = $prefix . $nextSeq;
        });
    }

    protected $fillable = [
        'builty_number',
        'order_id',
        'invoice_id',
        'customer_code',
        'file_path',
        'original_filename',
        'original_ext',
        'uploaded_by',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_code', 'customer_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
