<?php

namespace App\Models;

use App\LevelStatus;
use Database\Factories\LevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Level extends Model
{
    /** @use HasFactory<LevelFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'category_id',
        'sequence',
        'difficulty',
        'status',
        'published_at',
    ];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<LevelTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(LevelTranslation::class);
    }

    /** @return HasMany<LevelImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(LevelImage::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'difficulty' => 'integer',
            'status' => LevelStatus::class,
            'published_at' => 'immutable_datetime',
        ];
    }
}
