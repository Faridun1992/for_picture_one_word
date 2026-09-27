<?php

namespace App\Models;

use App\WalletTransactionReason;
use App\WalletTransactionReferenceType;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WalletTransaction extends Model
{
    /** @use HasFactory<WalletTransactionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'amount',
        'balance_after',
        'reason',
        'reference_type',
        'reference_id',
        'idempotency_key',
    ];

    protected static function booted(): void
    {
        static::creating(static function (WalletTransaction $transaction): void {
            $transaction->created_at ??= now();
        });

        static::updating(static function (): never {
            throw new LogicException('Wallet transactions are immutable.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Wallet transactions are immutable.');
        });
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'reason' => WalletTransactionReason::class,
            'reference_type' => WalletTransactionReferenceType::class,
            'reference_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
