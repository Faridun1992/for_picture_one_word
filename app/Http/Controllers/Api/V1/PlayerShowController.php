<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlayerSettingsResource;
use App\Models\Player;
use App\Services\Game\CurrentLevelResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerShowController extends Controller
{
    public function __invoke(Request $request, CurrentLevelResolver $currentLevelResolver): JsonResponse
    {
        $player = $request->user();

        abort_unless($player instanceof Player, 403);

        $currentLevelId = $currentLevelResolver->forPlayer($player);

        return response()->json([
            'data' => [
                'id' => $player->id,
                'locale' => $player->locale,
                'balance' => (int) ($player->wallet()->value('balance') ?? 0),
                'current_level_id' => $currentLevelId,
                'current_level_status' => $currentLevelResolver->statusForPlayer($player, $currentLevelId),
                'settings' => (new PlayerSettingsResource($player))->resolve(),
            ],
        ]);
    }
}
