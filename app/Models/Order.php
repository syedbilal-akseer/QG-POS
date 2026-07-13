<?php

namespace App\Models;

use App\Enums\OrderStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id',
        'salesperson_id',
        'order_status',
        'order_number',
        'notes',
        'remarks',
        'oracle_at',
        'pushed_by',
        'transporter_id',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'oracle_at'    => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Automatically generate an order number before creating the model.
     * Format: YYYYMMNNNNN (Year + Month + Sequence Number)
     * Example: 202601 (January 2026, first order), 202602 (January 2026, second order)
     * Continues from existing sequence (e.g., 202546 -> 202601 for January 2026)
     */
    protected static function booted()
    {
        static::creating(function ($order) {
            if ($order->order_number) {
                return; // already set — don't override
            }

            $monthPrefix   = now()->format('Ym');         // e.g. '202604'
            $monthStartNum = (int) ($monthPrefix . '01'); // e.g.  20260401

            // DB::table bypasses ALL Eloquent scopes (including SoftDeletes),
            // so cancelled / soft-deleted rows are always visible.
            // Order by id DESC to get the most recently inserted row —
            // its order_number is the last number actually committed to the table.
            // lockForUpdate blocks concurrent transactions until this one commits.
            $lastOrderNumber = DB::table('orders')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('order_number');

            $next = max((int) $lastOrderNumber + 1, $monthStartNum);

            // Safety net: if a duplicate somehow slipped through (e.g. manual
            // DB edits created a collision), keep incrementing until clear.
            while (DB::table('orders')->where('order_number', (string) $next)->exists()) {
                $next++;
            }

            $order->order_number = (string) $next;
        });
    }

    /**
     * Generate a unique 8-digit order number.
     *
     * @return string
     */
    protected static function generateUniqueOrderNumber()
    {
        do {
            $orderNumber = mt_rand(10000000, 99999999);
        } while (static::where('order_number', $orderNumber)->exists()); // Check for uniqueness

        return $orderNumber;
    }

    /**
     * Accessor and Mutator for the OrderStatus attribute.
     *
     * This method automatically casts the OrderStatus attribute to and from
     * the OrderStatusEnum. When getting the OrderStatus, it returns an instance of OrderStatusEnum
     * corresponding to the stored value. When setting the OrderStatus, it ensures that
     * the value stored in the database is a valid OrderStatusEnum value.
     *
     * @return Attribute
     */
    protected function orderStatus(): Attribute
    {
        return Attribute::make(
            get: fn($value) => OrderStatusEnum::from($value),
            set: fn(OrderStatusEnum $order_status) => $order_status->value,
        );
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function salesperson()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function pushedBy()
    {
        return $this->belongsTo(User::class, 'pushed_by');
    }

    public function transporter()
    {
        return $this->belongsTo(\App\Models\Transporter::class, 'transporter_id');
    }
}
