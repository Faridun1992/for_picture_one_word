<?php

namespace Database\Factories;

use App\Models\Level;
use App\Models\LevelImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LevelImage>
 */
class LevelImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'level_id' => Level::factory(),
            'position' => fake()->numberBetween(1, 4),
            'storage_disk' => 'local',
            'storage_key' => 'levels/'.Str::uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 640,
            'height' => 640,
            'variants' => null,
        ];
    }
}
