<?php

namespace Database\Factories;

use App\Models\Level;
use App\Models\Player;
use App\Models\PlayerLevelProgress;
use App\PlayerLevelProgressStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerLevelProgress>
 */
class PlayerLevelProgressFactory extends Factory
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
            'level_id' => Level::factory(),
            'status' => PlayerLevelProgressStatus::InProgress,
            'attempt_count' => 0,
            'hints_used' => 0,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }
}
