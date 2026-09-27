<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CatalogQueryRequest;
use App\Http\Resources\Api\V1\LevelResource;
use App\LevelStatus;
use App\Models\Category;
use App\Models\Level;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;

class LevelIndexController extends Controller
{
    public function __invoke(CatalogQueryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $locale = $request->selectedLocale();

        if (isset($validated['category_id']) && ! Category::query()->whereKey($validated['category_id'])->exists()) {
            abort(404);
        }

        $levels = Level::query()
            ->where('status', LevelStatus::Published->value)
            ->whereHas('translations', static fn (Builder $query) => $query->where('locale', $locale))
            ->whereHas('category', static function (Builder $query) use ($locale): void {
                $query->where('is_active', true)
                    ->whereHas('translations', static fn (Builder $translations) => $translations->where('locale', $locale));
            })
            ->whereHas('images', static fn (Builder $query) => $query->whereBetween('position', [1, 4]), '=', 4)
            ->when(isset($validated['category_id']), static fn (Builder $query) => $query->where('category_id', $validated['category_id']))
            ->when(isset($validated['after_id']), static fn (Builder $query) => $query->where('id', '>', $validated['after_id']))
            ->with([
                'translations' => static fn (Relation $query) => $query->where('locale', $locale),
                'category.translations' => static fn (Relation $query) => $query->where('locale', $locale),
                'images' => static fn (Relation $query) => $query->whereBetween('position', [1, 4])->orderBy('position'),
            ])
            ->orderBy('sequence')
            ->orderBy('id')
            ->cursorPaginate($request->pageSize(), ['*'], 'cursor');

        return response()->json([
            'data' => LevelResource::collection($levels->items())->resolve($request),
            'meta' => [
                'next_cursor' => $levels->nextCursor()?->encode(),
                'previous_cursor' => $levels->previousCursor()?->encode(),
            ],
        ]);
    }
}
