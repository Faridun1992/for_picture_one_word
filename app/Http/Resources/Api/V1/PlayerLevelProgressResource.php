<?php

namespace App\Http\Resources\Api\V1;

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
        return [
            'level_id' => $this->level_id,
            'sequence' => $this->level->sequence,
            'status' => $this->status->value,
            'attempt_count' => $this->attempt_count,
            'hints_used' => $this->hints_used,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
