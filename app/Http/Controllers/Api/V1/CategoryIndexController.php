<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CatalogQueryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\LevelStatus;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;

class CategoryIndexController extends Controller
{
    public function __invoke(CatalogQueryRequest $request): JsonResponse
    {
        $locale = $request->selectedLocale();
        $categories = Category::query()
            ->where('is_active', true)
            ->whereHas('translations', static fn (Builder $query) => $query->where('locale', $locale))
            ->with(['translations' => static fn (Relation $query) => $query->where('locale', $locale)])
            ->withCount([
                'levels as published_levels_count' => static function (Builder $query) use ($locale): void {
                    $query->where('status', LevelStatus::Published->value)
                        ->whereHas('translations', static fn (Builder $translations) => $translations->where('locale', $locale))
                        ->whereHas('images', static fn (Builder $images) => $images->whereBetween('position', [1, 4]), '=', 4);
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->cursorPaginate($request->pageSize(), ['*'], 'cursor');

        return response()->json([
            'data' => CategoryResource::collection($categories->items())->resolve($request),
            'meta' => [
                'next_cursor' => $categories->nextCursor()?->encode(),
                'previous_cursor' => $categories->previousCursor()?->encode(),
            ],
        ]);
    }
}
