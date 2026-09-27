<?php

namespace Database\Factories;

use App\Models\IdempotencyRequest;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdempotencyRequest>
 */
class IdempotencyRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'key' => fake()->uuid(),
            'operation' => 'complete_level',
            'request_hash' => hash('sha256', '{}'),
            'response_status' => 200,
            'response_body' => ['data' => ['accepted' => true]],
            'completed_at' => now(),
        ];
    }
}
