<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Services\Game\WalletBalanceManager;
use App\WalletTransactionReason;
use App\WalletTransactionReferenceType;
use Illuminate\Database\Seeder;

class PlayerWalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $player = Player::query()->first();

        if ($player !== null && ! $player->wallet()->exists()) {
            app(WalletBalanceManager::class)->credit(
                player: $player,
                amount: (int) config('game.wallet.starting_balance'),
                reason: WalletTransactionReason::WelcomeReward,
                referenceType: WalletTransactionReferenceType::PlayerWelcome,
                referenceId: (int) $player->id,
            );
        }
    }
}
