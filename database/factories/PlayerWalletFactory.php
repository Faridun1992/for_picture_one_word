<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerWallet>
 */
class PlayerWalletFactory extends Factory
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
            'balance' => 0,
            'updated_at' => now(),
        ];
    }
}
