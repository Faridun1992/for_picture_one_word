<?php

namespace App\Models;

use Database\Factories\IdempotencyRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyRequest extends Model
{
    /** @use HasFactory<IdempotencyRequestFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['key', 'operation', 'request_hash'];

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
