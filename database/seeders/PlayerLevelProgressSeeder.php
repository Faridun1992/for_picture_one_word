<?php

namespace Database\Seeders;

use App\Models\Level;
use App\Models\Player;
use App\Models\PlayerLevelProgress;
use App\PlayerLevelProgressStatus;
use Illuminate\Database\Seeder;

class PlayerLevelProgressSeeder extends Seeder
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
        $level = Level::query()->orderBy('sequence')->orderBy('id')->first();

        if ($player === null || $level === null) {
            return;
        }

        PlayerLevelProgress::query()->firstOrCreate(
            [
                'player_id' => $player->id,
                'level_id' => $level->id,
            ],
            [
                'status' => PlayerLevelProgressStatus::InProgress,
                'attempt_count' => 0,
                'hints_used' => 0,
                'started_at' => now(),
            ],
        );
    }
}
