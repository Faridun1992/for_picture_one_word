<?php

namespace App\Models;

use Database\Factories\LevelTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelTranslation extends Model
{
    /** @use HasFactory<LevelTranslationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'level_id',
        'locale',
        'answer_display',
        'letter_tiles',
    ];

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['letter_tiles' => 'array'];
    }
}
