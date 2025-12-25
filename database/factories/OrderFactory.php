<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Order;
use App\Models\User;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'transaction_id' => $this->faker->uuid,
            'user_id' => User::factory(),
            'subtotal' => $this->faker->randomFloat(2, 10, 100),
            'tax' => $this->faker->randomFloat(2, 1, 10),
            'total' => function (array $attributes) {
                return $attributes['subtotal'] + $attributes['tax'];
            },
            'status' => 'completed',
            'payment_method' => 'card',
            'billing_name' => $this->faker->name,
            'billing_email' => $this->faker->safeEmail,
        ];
    }
}
