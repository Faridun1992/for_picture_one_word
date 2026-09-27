<?php

namespace App\Services\Admin;

use App\LevelStatus;
use App\Models\Level;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LevelReorderingService
{
    private const int MAX_SEQUENCE = 4_294_967_295;

    public function move(Level $level, string $direction): void
    {
        DB::transaction(function () use ($level, $direction): void {
            $lockedLevels = Level::query()
                ->where('status', LevelStatus::Published)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $orderedLevels = $lockedLevels->sort(static function (Level $left, Level $right): int {
                return [$left->sequence, $left->id] <=> [$right->sequence, $right->id];
            })->values();
            $currentIndex = $orderedLevels->search(static fn (Level $candidate): bool => $candidate->is($level));

            if ($currentIndex === false) {
                throw ValidationException::withMessages([
                    'status' => 'Перемещать можно только опубликованные уровни.',
                ]);
            }

            $neighborIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;

            if (! $orderedLevels->has($neighborIndex)) {
                return;
            }

            $currentLevel = $orderedLevels->get($currentIndex);
            $neighborLevel = $orderedLevels->get($neighborIndex);
            $orderedLevels->put($currentIndex, $neighborLevel);
            $orderedLevels->put($neighborIndex, $currentLevel);
            $temporarySequence = (int) Level::query()->max('sequence') + $orderedLevels->count() + 1;

            if ($temporarySequence + $orderedLevels->count() > self::MAX_SEQUENCE) {
                throw ValidationException::withMessages([
                    'sequence' => 'Порядок уровней нельзя изменить: превышен допустимый диапазон.',
                ]);
            }

            foreach ($orderedLevels as $index => $orderedLevel) {
                $orderedLevel->forceFill(['sequence' => $temporarySequence + $index])->save();
            }

            foreach ($orderedLevels as $index => $orderedLevel) {
                $orderedLevel->forceFill(['sequence' => $index + 1])->save();
            }
        });
    }
}
