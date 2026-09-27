<?php

namespace App\Services\Game;

use App\LevelStatus;
use App\Models\Level;
use App\Models\LevelTranslation;
use App\Support\Api\ApiException;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Single definition of a level a player is allowed to play: published, inside an
 * active category, localized and complete. Read and gameplay endpoints share it
 * so a level can never be solved that could not be loaded.
 */
class PlayableLevelResolver
{
    public function __construct(
        private readonly LevelImageSetValidator $imageSetValidator,
    ) {}

    /**
     * Load the relations the API needs and return the translation for the locale.
     *
     * @throws ApiException when the level cannot be played in that locale
     */
    public function translation(Level $level, string $locale): LevelTranslation
    {
        $level->loadMissing([
            'translations' => static fn (Relation $query) => $query->where('locale', $locale),
            'category.translations' => static fn (Relation $query) => $query->where('locale', $locale),
            'images' => static fn (Relation $query) => $query->whereBetween('position', [1, 4])->orderBy('position'),
        ]);

        $translation = $level->translations->first();

        $isPlayable = $level->status === LevelStatus::Published
            && $level->category->is_active
            && $translation instanceof LevelTranslation
            && $level->category->translations->isNotEmpty()
            && $this->imageSetValidator->hasExactlyFourPositions($level->images->pluck('position')->all());

        if (! $isPlayable) {
            throw ApiException::notFound();
        }

        return $translation;
    }
}
