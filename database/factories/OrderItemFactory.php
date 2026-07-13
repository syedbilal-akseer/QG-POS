<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = $this->faker->randomFloat(2, 10, 500); // Example price per item

        return [
            'order_id' => Order::factory(),
            'inventory_item_id' => Item::inRandomOrder()->value('inventory_item_id'),
            'warehouse_id' => $this->faker->randomNumber(),
            'quantity' => $this->faker->numberBetween(1, 100),
            'discount' => $this->faker->randomFloat(2, 0, 100),
            'sub_total' => fn (array $attributes) => ($price * $attributes['quantity']) - $attributes['discount'], // Calculate sub_total
        ];
    }
}
