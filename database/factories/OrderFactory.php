<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::inRandomOrder()->value('customer_id'),
            'user_id' => User::inRandomOrder()->value('id'),
            'order_number' => $this->faker->unique()->numerify('########'),
            'notes' => $this->faker->sentence(),
            'sub_total' => 0, // Initial value
            'total_amount' => 0, // Initial value
        ];
    }

    /**
     * Indicate that the order should have order items and calculate totals.
     *
     * @return Factory
     */
    public function withItems($count = 5)
    {
        return $this->has(
            OrderItem::factory()->count($count),
            'orderItems'
        )->afterCreating(function (Order $order) {
            // Calculate sub_total by summing the sub_total of each order item
            $subTotal = $order->orderItems->sum('sub_total');

            $order->sub_total = $subTotal;
            $order->total_amount = $subTotal;

            // Save the updated totals
            $order->save();
        });
    }
}
