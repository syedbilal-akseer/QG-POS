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
        'oracle_at',
        'pushed_by',
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
            // Wrap the entire operation in a transaction
            DB::transaction(function () use ($order) {
                // Get current year and month in YYYYMM format
                $monthPrefix = now()->format('Ym'); // e.g., 202601 for January 2026

                // Fetch the latest order number for this month with a lock
                $lastOrder = Order::lockForUpdate()
                    ->where('order_number', 'LIKE', $monthPrefix . '%')
                    ->latest('order_number')
                    ->first();
                // Calculate the sequence number for this month
                if ($lastOrder) {
                    // Increment from the last order number
                    $orderNumber = $lastOrder->order_number + 1;
                } else {
                    // First order of the month - start with 01
                    $orderNumber = $monthPrefix . '01';
                }

                // Assign the generated order number
                $order->order_number = $orderNumber;

            });
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

    public function pushedBy()
    {
        return $this->belongsTo(User::class, 'pushed_by');
    }
}
