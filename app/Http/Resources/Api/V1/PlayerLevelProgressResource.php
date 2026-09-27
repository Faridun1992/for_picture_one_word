<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Game\LevelProgressSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerLevelProgressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(LevelProgressSnapshot::class)->make($this->resource, $this->level);
    }
}
