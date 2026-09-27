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
            'images' => $this->images->map(fn (LevelImage $image): array => [
                'position' => $image->position,
                'url' => $this->imageUrl($image),
                'width' => $image->width,
                'height' => $image->height,
            ])->values()->all(),
            'content_version' => substr(hash('sha256', implode('|', $timestamps)), 0, 24),
        ];
    }

    private function imageUrl(LevelImage $image): string
    {
        $disk = Storage::disk($image->storage_disk);

        if ($disk->providesTemporaryUrls()) {
            return $disk->temporaryUrl($image->storage_key, now()->addHours(24));
        }

        return $disk->url($image->storage_key);
    }
}
