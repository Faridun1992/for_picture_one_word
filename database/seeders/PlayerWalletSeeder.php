<?php

namespace Database\Seeders;

use App\Models\Player;
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

        if ($player !== null) {
            $player->wallet()->firstOrCreate([], ['balance' => 0]);
        }
    }
}
