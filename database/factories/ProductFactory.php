<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Product;
use App\Models\ProducerProfile;
use App\Models\Category;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'producer_profile_id' => ProducerProfile::factory(),
            'category_id' => Category::factory(),
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->slug,
            'price' => $this->faker->randomFloat(2, 5, 50),
            'image_path' => $this->faker->imageUrl(),
            'description' => $this->faker->sentence,
            'file_path' => 'sounds/test.mp3',
            'audio_preview_path' => 'sounds/preview.mp3',
            'bpm' => 120,
            'key' => 'C Major',
            'tags' => ['lofi', 'ambient'],
            // Add other required fields if any based on migration
        ];
    }
}
