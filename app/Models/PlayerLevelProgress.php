<?php

namespace App\Models;

use App\PlayerLevelProgressStatus;
use Database\Factories\PlayerLevelProgressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerLevelProgress extends Model
{
    /** @use HasFactory<PlayerLevelProgressFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'level_id',
        'status',
        'attempt_count',
        'hints_used',
        'hint_state',
        'started_at',
        'completed_at',
    ];

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PlayerLevelProgressStatus::class,
            'attempt_count' => 'integer',
            'hints_used' => 'integer',
            'hint_state' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
