<?php

namespace Database\Factories;

use App\Models\Component;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return ['order_id' => Order::factory(), 'component_id' => Component::factory(), 'quantity' => 1, 'unit_price' => 1800000];
    }
}
