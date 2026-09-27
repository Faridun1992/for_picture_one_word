<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdatePlayerSettingsRequest;
use App\Http\Resources\Api\V1\PlayerSettingsResource;
use Illuminate\Http\JsonResponse;

class PlayerSettingsController extends Controller
{
    public function __invoke(UpdatePlayerSettingsRequest $request): JsonResponse
    {
        $player = $request->user();
        $player->fill($request->validated())->save();

        return response()->json([
            'data' => (new PlayerSettingsResource($player->refresh()))->resolve($request),
        ]);
    }
}
