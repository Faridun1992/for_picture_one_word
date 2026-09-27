<?php

namespace App\Models;

use App\PlayerTheme;
use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Player extends Authenticatable
{
    /** @use HasFactory<PlayerFactory> */
    use HasApiTokens, HasFactory;

    /** @var list<string> */
    protected $fillable = ['locale', 'sound_enabled', 'haptics_enabled', 'theme'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<PlayerLevelProgress, $this> */
    public function levelProgress(): HasMany
    {
        return $this->hasMany(PlayerLevelProgress::class);
    }

    /** @return HasOne<PlayerWallet, $this> */
    public function wallet(): HasOne
    {
        return $this->hasOne(PlayerWallet::class);
    }

    /** @return HasMany<WalletTransaction, $this> */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /** @return HasMany<IdempotencyRequest, $this> */
    public function idempotencyRequests(): HasMany
    {
        return $this->hasMany(IdempotencyRequest::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sound_enabled' => 'boolean',
            'haptics_enabled' => 'boolean',
            'theme' => PlayerTheme::class,
        ];
    }
}
