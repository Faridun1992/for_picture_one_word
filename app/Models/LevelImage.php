<?php

namespace App\Models;

use Database\Factories\LevelImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelImage extends Model
{
    /** @use HasFactory<LevelImageFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'level_id',
        'position',
        'storage_disk',
        'storage_key',
        'mime_type',
        'width',
        'height',
        'variants',
    ];

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'variants' => 'array',
        ];
    }
}
