<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLevelHintRequest;
use App\Models\IdempotencyRequest;
use App\Models\Level;
use App\Models\Player;
use App\Services\Game\IdempotencyRequestExecutor;
use App\Services\Game\LevelHintService;
use Illuminate\Http\JsonResponse;

class LevelHintController extends Controller
{
    public function __invoke(
        StoreLevelHintRequest $request,
        Level $level,
        LevelHintService $levelHintService,
        IdempotencyRequestExecutor $idempotencyRequestExecutor,
    ): JsonResponse {
        $player = $request->user();

        abort_unless($player instanceof Player, 403);

        $locale = $request->selectedLocale();
        $type = $request->hintType();
        $key = $request->idempotencyKey();

        return $idempotencyRequestExecutor->execute(
            player: $player,
            key: $key,
            operation: 'level_hint',
            payload: [
                'level_id' => (int) $level->id,
                'locale' => $locale,
                'type' => $type->value,
            ],
            operationCallback: fn (IdempotencyRequest $operation): array => $levelHintService->apply(
                player: $player,
                level: $level,
                locale: $locale,
                type: $type,
                operationId: (int) $operation->id,
            ),
        );
    }
}
