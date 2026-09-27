<?php

namespace App\Services\Game;

use App\LevelStatus;
use App\Models\Level;
use App\Models\Player;
use App\Models\PlayerLevelProgress;
use App\PlayerLevelProgressStatus;
use Illuminate\Database\Eloquent\Builder;

class CurrentLevelResolver
{
    public function forPlayer(Player $player): ?int
    {
        $locale = $player->locale;
        $inProgressLevelId = PlayerLevelProgress::query()
            ->join('levels as current_levels', 'current_levels.id', '=', 'player_level_progress.level_id')
            ->where('player_level_progress.player_id', $player->id)
            ->where('player_level_progress.status', PlayerLevelProgressStatus::InProgress->value)
            ->where('current_levels.status', LevelStatus::Published->value)
            ->whereHas('level.translations', static fn (Builder $query) => $query->where('locale', $locale))
            ->whereHas('level.category', static function (Builder $query) use ($locale): void {
                $query->where('is_active', true)
                    ->whereHas('translations', static fn (Builder $translations) => $translations->where('locale', $locale));
            })
            ->whereHas('level.images', static fn (Builder $query) => $query->whereBetween('position', [1, 4]), '=', 4)
            ->orderBy('current_levels.sequence')
            ->orderBy('current_levels.id')
            ->value('player_level_progress.level_id');

        if ($inProgressLevelId !== null) {
            return (int) $inProgressLevelId;
        }

        $nextLevelId = Level::query()
            ->where('status', LevelStatus::Published->value)
            ->whereHas('translations', static fn (Builder $query) => $query->where('locale', $locale))
            ->whereHas('category', static function (Builder $query) use ($locale): void {
                $query->where('is_active', true)
                    ->whereHas('translations', static fn (Builder $translations) => $translations->where('locale', $locale));
            })
            ->whereHas('images', static fn (Builder $query) => $query->whereBetween('position', [1, 4]), '=', 4)
            ->whereDoesntHave('playerProgress', static function (Builder $query) use ($player): void {
                $query->where('player_id', $player->id)
                    ->where('status', PlayerLevelProgressStatus::Completed->value);
            })
            ->orderBy('sequence')
            ->orderBy('id')
            ->value('levels.id');

        return $nextLevelId === null ? null : (int) $nextLevelId;
    }
}
