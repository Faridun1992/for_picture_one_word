<?php

namespace App\Models;

use Database\Factories\PlayerWalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerWallet extends Model
{
    /** @use HasFactory<PlayerWalletFactory> */
    use HasFactory;

    public const CREATED_AT = null;

    protected $primaryKey = 'player_id';

    public $incrementing = false;

    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = ['balance'];

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }
}
