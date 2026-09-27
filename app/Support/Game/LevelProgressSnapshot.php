<?php

namespace App\Support\Game;

use App\Models\Level;
use App\Models\PlayerLevelProgress;

/**
 * Single shape of a level progress entry, shared by the read endpoints and by
 * the gameplay responses so both always agree.
 */
class LevelProgressSnapshot
{
    /** @return array<string, mixed> */
    public function make(PlayerLevelProgress $progress, Level $level): array
    {
        return [
            'level_id' => (int) $progress->level_id,
            'sequence' => (int) $level->sequence,
            'status' => $progress->status->value,
            'attempt_count' => (int) $progress->attempt_count,
            'hints_used' => (int) $progress->hints_used,
            'started_at' => $progress->started_at?->toISOString(),
            'completed_at' => $progress->completed_at?->toISOString(),
        ];
    }
}
