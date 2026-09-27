<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translation = $this->translations->first();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $translation?->name,
            'published_levels_count' => (int) $this->published_levels_count,
        ];
    }
}
