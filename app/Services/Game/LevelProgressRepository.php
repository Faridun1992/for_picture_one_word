<?php

namespace App\Services\Game;

use App\Models\Level;
use App\Models\Player;
use App\Models\PlayerLevelProgress;
use App\PlayerLevelProgressStatus;

/**
 * Single entry point for the player level progress row a gameplay request works
 * on. The row is created on the first attempt or hint and read under a row lock
 * afterwards, so a player can never have two competing progress rows.
 */
class LevelProgressRepository
{
    public function lockOrCreate(Player $player, Level $level): PlayerLevelProgress
    {
        // The caller locks the owning Player row before this transaction enters
        // the repository, covering the no-existing-progress race on MySQL.
        $progress = PlayerLevelProgress::query()
            ->where('player_id', $player->id)
            ->where('level_id', $level->id)
            ->lockForUpdate()
            ->first();

        if ($progress instanceof PlayerLevelProgress) {
            return $progress;
        }

        return $player->levelProgress()->create([
            'level_id' => $level->id,
            'status' => PlayerLevelProgressStatus::InProgress,
            'attempt_count' => 0,
            'hints_used' => 0,
            'started_at' => now(),
        ]);
    }
}
