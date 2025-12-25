<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Category;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word,
            'slug' => $this->faker->slug,
            'icon' => 'Music',
            'color_class' => 'text-blue-500',
            'bg_class' => 'bg-blue-500/10',
        ];
    }
}
