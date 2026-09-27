<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CatalogQueryRequest;
use App\Http\Resources\Api\V1\LevelResource;
use App\Models\Level;
use App\Services\Game\PlayableLevelResolver;
use Illuminate\Http\JsonResponse;

class LevelShowController extends Controller
{
    public function __invoke(
        CatalogQueryRequest $request,
        Level $level,
        PlayableLevelResolver $playableLevelResolver,
    ): JsonResponse {
        $playableLevelResolver->translation($level, $request->selectedLocale());

        return response()->json([
            'data' => (new LevelResource($level))->resolve($request),
        ]);
    }
}
