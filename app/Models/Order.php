<?php

namespace App\Models;

use App\Enums\OrderStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id',
        'salesperson_id',
        'order_status',
    ];

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
}
