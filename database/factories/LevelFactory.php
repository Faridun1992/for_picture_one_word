<?php

namespace Database\Factories;

use App\LevelStatus;
use App\Models\Category;
use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Level>
 */
class LevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'sequence' => fake()->numberBetween(1, 10000),
            'difficulty' => fake()->numberBetween(1, 5),
            'status' => LevelStatus::Draft,
            'published_at' => null,
        ];
    }
}
