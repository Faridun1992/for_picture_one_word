<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLevelAttemptRequest;
use App\Models\IdempotencyRequest;
use App\Models\Level;
use App\Models\Player;
use App\Services\Game\IdempotencyRequestExecutor;
use App\Services\Game\LevelAttemptService;
use Illuminate\Http\JsonResponse;

class LevelAttemptController extends Controller
{
    public function __invoke(
        StoreLevelAttemptRequest $request,
        Level $level,
        LevelAttemptService $levelAttemptService,
        IdempotencyRequestExecutor $idempotencyRequestExecutor,
    ): JsonResponse {
        $player = $request->user();

        abort_unless($player instanceof Player, 403);

        $locale = $request->selectedLocale();
        $answer = $request->answer();
        $key = $request->idempotencyKey();

        return $idempotencyRequestExecutor->execute(
            player: $player,
            key: $key,
            operation: 'level_attempt',
            payload: [
                'level_id' => (int) $level->id,
                'locale' => $locale,
                'answer' => $answer,
            ],
            operationCallback: fn (IdempotencyRequest $operation): array => $levelAttemptService->attempt($player, $level, $locale, $answer),
        );
    }
}
