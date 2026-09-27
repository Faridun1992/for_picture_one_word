<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGuestSessionRequest;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GuestSessionController extends Controller
{
    public function __invoke(StoreGuestSessionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        [$player, $token] = DB::transaction(function () use ($validated): array {
            $player = Player::query()->create([
                'locale' => $validated['locale'] ?? 'ru',
            ]);

            $player->wallet()->create(['balance' => 0]);
            $token = $player->createToken($validated['device_name'] ?? 'mobile')->plainTextToken;

            return [$player, $token];
        });

        return response()->json([
            'data' => [
                'token' => $token,
                'player' => [
                    'id' => $player->id,
                    'locale' => $player->locale,
                ],
            ],
        ], 201);
    }
}
