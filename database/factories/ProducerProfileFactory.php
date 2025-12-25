<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ProducerProfile;
use App\Models\User;

class ProducerProfileFactory extends Factory
{
    protected $model = ProducerProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => $this->faker->name,
            'bio' => $this->faker->paragraph,
            'status' => 'approved',
            'sales_count' => 0,
        ];
    }
}
