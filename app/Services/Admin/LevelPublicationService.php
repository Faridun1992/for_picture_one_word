<?php

namespace App\Services\Admin;

use App\LevelStatus;
use App\Models\Level;
use App\Models\LevelTranslation;
use App\Services\Game\AnswerNormalizer;
use App\Services\Game\LevelImageSetValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class LevelPublicationService
{
    public function __construct(
        private readonly AnswerNormalizer $answerNormalizer,
        private readonly LevelImageSetValidator $imageSetValidator,
    ) {}

    public function publish(Level $level): void
    {
        DB::transaction(function () use ($level): void {
            Level::query()
                ->where('status', LevelStatus::Published)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $lockedLevel = Level::query()->whereKey($level->id)->lockForUpdate()->firstOrFail();

            if ($lockedLevel->status === LevelStatus::Published) {
                return;
            }

            if ($lockedLevel->status !== LevelStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Архивный или отключённый уровень нельзя опубликовать.',
                ]);
            }

            $errors = $this->publicationErrors($lockedLevel);

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $lockedLevel->forceFill([
                'status' => LevelStatus::Published,
                'published_at' => now(),
            ])->save();
        });
    }

    public function archive(Level $level): void
    {
        DB::transaction(function () use ($level): void {
            Level::query()
                ->where('status', LevelStatus::Published)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $lockedLevel = Level::query()->whereKey($level->id)->lockForUpdate()->firstOrFail();

            if ($lockedLevel->status !== LevelStatus::Archived) {
                $lockedLevel->forceFill(['status' => LevelStatus::Archived])->save();
            }
        });
    }

    /** @return array<string, string> */
    private function publicationErrors(Level $level): array
    {
        $errors = [];
        $level->load(['category.translations', 'translations', 'images']);

        if (! $level->category->is_active) {
            $errors['category_id'] = 'Категория должна быть активной.';
        }

        if ($level->difficulty < 1 || $level->difficulty > 5) {
            $errors['difficulty'] = 'Сложность должна быть от 1 до 5.';
        }

        $locales = config('game.supported_locales', []);

        foreach ($locales as $locale) {
            if (! $level->category->translations->contains('locale', $locale)) {
                $errors["category.{$locale}"] = "Для категории отсутствует перевод {$locale}.";
            }

            $translation = $level->translations->firstWhere('locale', $locale);

            if (! $translation instanceof LevelTranslation) {
                $errors["translations.{$locale}"] = "Добавьте перевод уровня на {$locale}.";

                continue;
            }

            $answerLength = grapheme_strlen($translation->answer_display);

            if ($answerLength === false || $answerLength < 1 || $answerLength > config('game.max_answer_graphemes')) {
                $errors["translations.{$locale}.answer_display"] = 'Ответ должен содержать от 1 до '.config('game.max_answer_graphemes').' Unicode-графем.';

                continue;
            }

            if (! $this->hasValidAnswerTiles($translation)) {
                $errors["translations.{$locale}.letter_tiles"] = "Плитки {$locale} должны содержать ровно 12 букв и все графемы ответа с нужной кратностью.";
            }
        }

        $positions = $level->images->pluck('position')->all();

        if (! $this->imageSetValidator->hasExactlyFourPositions($positions)) {
            $errors['images'] = 'Для публикации нужны четыре изображения в позициях 1–4.';
        } else {
            foreach ($level->images as $image) {
                if ($image->width < 1 || $image->height < 1 || ! Storage::disk($image->storage_disk)->exists($image->storage_key)) {
                    $errors['images'] = 'Оригиналы всех четырёх изображений должны быть доступны в хранилище.';

                    break;
                }
            }
        }

        return $errors;
    }

    private function hasValidAnswerTiles(LevelTranslation $translation): bool
    {
        if (blank($translation->answer_display) || ! is_array($translation->letter_tiles) || count($translation->letter_tiles) !== 12) {
            return false;
        }

        try {
            $answerGraphemes = $this->answerNormalizer->splitGraphemes($translation->answer_normalized);
            $tileCounts = [];

            foreach ($translation->letter_tiles as $tile) {
                if (! is_string($tile) || count($this->answerNormalizer->splitGraphemes($tile)) !== 1) {
                    return false;
                }

                $normalizedTile = $this->answerNormalizer->normalize($tile);
                $tileCounts[$normalizedTile] = ($tileCounts[$normalizedTile] ?? 0) + 1;
            }

            foreach ($answerGraphemes as $grapheme) {
                $normalizedGrapheme = $this->answerNormalizer->normalize($grapheme);

                if (($tileCounts[$normalizedGrapheme] ?? 0) < 1) {
                    return false;
                }

                $tileCounts[$normalizedGrapheme]--;
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
