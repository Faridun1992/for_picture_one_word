<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CatalogQueryRequest;
use App\Http\Resources\Api\V1\LevelResource;
use App\LevelStatus;
use App\Models\Level;
use App\Services\Game\LevelImageSetValidator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;

class LevelShowController extends Controller
{
    public function __invoke(
        CatalogQueryRequest $request,
        Level $level,
        LevelImageSetValidator $imageSetValidator,
    ): JsonResponse {
        $locale = $request->selectedLocale();
        $level->load([
            'translations' => static fn (Relation $query) => $query->where('locale', $locale),
            'category.translations' => static fn (Relation $query) => $query->where('locale', $locale),
            'images' => static fn (Relation $query) => $query->whereBetween('position', [1, 4])->orderBy('position'),
        ]);

        abort_unless(
            $level->status === LevelStatus::Published
                && $level->category->is_active
                && $level->translations->isNotEmpty()
                && $level->category->translations->isNotEmpty()
                && $imageSetValidator->hasExactlyFourPositions($level->images->pluck('position')->all()),
            404,
        );

        return response()->json([
            'data' => (new LevelResource($level))->resolve($request),
        ]);
    }
}
