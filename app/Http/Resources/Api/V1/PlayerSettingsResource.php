<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerSettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'locale' => $this->locale,
            'sound_enabled' => $this->sound_enabled,
            'haptics_enabled' => $this->haptics_enabled,
            'theme' => $this->theme->value,
        ];
    }
}
