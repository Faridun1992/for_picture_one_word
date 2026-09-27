<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\WalletTransaction;
use App\WalletTransactionReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
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
            'amount' => 10,
            'balance_after' => 10,
            'reason' => WalletTransactionReason::LevelReward,
            'reference_type' => 'level',
            'reference_id' => fake()->unique()->numberBetween(1, 1000000),
            'idempotency_key' => null,
            'created_at' => now(),
        ];
    }
}
