<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LevelImage;
use App\Services\Game\AnswerNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class LevelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translation = $this->translations->first();
        $categoryTranslation = $this->category->translations->first();
        $timestamps = [
            $this->updated_at?->format('U.u'),
            $translation?->updated_at?->format('U.u'),
            $categoryTranslation?->updated_at?->format('U.u'),
            ...$this->images->map(static fn (LevelImage $image): ?string => $image->updated_at?->format('U.u'))->all(),
        ];

        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'difficulty' => $this->difficulty,
            'category' => [
                'id' => $this->category->id,
                'slug' => $this->category->slug,
                'name' => $categoryTranslation?->name,
            ],
            'answer_length' => count(app(AnswerNormalizer::class)->splitGraphemes($translation->answer_display)),
            'locale' => $translation->locale,
            'letter_tiles' => $translation->letter_tiles,
            'images' => $this->images->map(function (LevelImage $image): array {
                $displayVariant = data_get($image->variants, 'display', []);
                $thumbnailVariant = data_get($image->variants, 'thumbnail', []);
                $displayKey = $displayVariant['storage_key'] ?? $image->storage_key;

                return [
                    'position' => $image->position,
                    'url' => $this->imageUrl($image->storage_disk, $displayKey),
                    'thumbnail_url' => isset($thumbnailVariant['storage_key'])
                        ? $this->imageUrl($image->storage_disk, $thumbnailVariant['storage_key'])
                        : null,
                    'width' => $displayVariant['width'] ?? $image->width,
                    'height' => $displayVariant['height'] ?? $image->height,
                ];
            })->values()->all(),
            'content_version' => substr(hash('sha256', implode('|', $timestamps)), 0, 24),
        ];
    }

    private function imageUrl(string $diskName, string $storageKey): string
    {
        $disk = Storage::disk($diskName);

        if ($disk->providesTemporaryUrls()) {
            return $disk->temporaryUrl($storageKey, now()->addHours(24));
        }

        return $disk->url($storageKey);
    }
}
