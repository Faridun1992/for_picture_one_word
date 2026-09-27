<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PlayerProgressRequest;
use App\Http\Resources\Api\V1\PlayerLevelProgressResource;
use App\Models\Player;
use App\PlayerLevelProgressStatus;
use App\Services\Game\CurrentLevelResolver;
use Illuminate\Http\JsonResponse;

class PlayerProgressController extends Controller
{
    public function __invoke(PlayerProgressRequest $request, CurrentLevelResolver $currentLevelResolver): JsonResponse
    {
        $player = $request->user();

        abort_unless($player instanceof Player, 403);

        $progress = $player->levelProgress()
            ->with('level:id,sequence')
            ->orderBy('level_id')
            ->cursorPaginate($request->pageSize(), ['*'], 'cursor');

        return response()->json([
            'data' => [
                'current_level_id' => $currentLevelResolver->forPlayer($player),
                'balance' => (int) ($player->wallet()->value('balance') ?? 0),
                'progress' => PlayerLevelProgressResource::collection($progress->items())->resolve($request),
                'statistics' => [
                    'total_levels' => $player->levelProgress()->count(),
                    'completed_levels' => $player->levelProgress()
                        ->where('status', PlayerLevelProgressStatus::Completed->value)
                        ->count(),
                    'total_attempts' => (int) $player->levelProgress()->sum('attempt_count'),
                ],
            ],
            'meta' => [
                'next_cursor' => $progress->nextCursor()?->encode(),
                'previous_cursor' => $progress->previousCursor()?->encode(),
            ],
        ]);
    }
}
