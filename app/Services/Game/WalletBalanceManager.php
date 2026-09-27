<?php

namespace App\Services\Game;

use App\Models\Player;
use App\Models\PlayerWallet;
use App\Support\Api\ApiException;
use App\WalletTransactionReason;
use App\WalletTransactionReferenceType;
use Illuminate\Support\Facades\DB;

/**
 * Single place where coins are credited or debited. The balance is a cache of
 * the ledger sum, so every change writes the wallet row and an append only
 * ledger row inside one transaction while the wallet row stays locked.
 */
class WalletBalanceManager
{
    public function credit(
        Player $player,
        int $amount,
        WalletTransactionReason $reason,
        WalletTransactionReferenceType $referenceType,
        int $referenceId,
        ?string $idempotencyKey = null,
    ): int {
        return $this->apply($player, abs($amount), $reason, $referenceType, $referenceId, $idempotencyKey);
    }

    public function debit(
        Player $player,
        int $amount,
        WalletTransactionReason $reason,
        WalletTransactionReferenceType $referenceType,
        int $referenceId,
        ?string $idempotencyKey = null,
    ): int {
        return $this->apply($player, -abs($amount), $reason, $referenceType, $referenceId, $idempotencyKey);
    }

    public function balance(Player $player): int
    {
        return (int) ($player->wallet()->value('balance') ?? 0);
    }

    private function apply(
        Player $player,
        int $amount,
        WalletTransactionReason $reason,
        WalletTransactionReferenceType $referenceType,
        int $referenceId,
        ?string $idempotencyKey,
    ): int {
        if ($amount === 0) {
            return $this->balance($player);
        }

        return DB::transaction(function () use ($player, $amount, $reason, $referenceType, $referenceId, $idempotencyKey): int {
            $wallet = PlayerWallet::query()
                ->where('player_id', $player->id)
                ->lockForUpdate()
                ->first() ?? $player->wallet()->create(['balance' => 0]);

            $balanceAfter = $wallet->balance + $amount;

            if ($balanceAfter < 0) {
                throw ApiException::unprocessable(
                    'insufficient_coins',
                    'The player does not have enough coins for this operation.',
                    [
                        'required' => abs($amount),
                        'balance' => $wallet->balance,
                    ],
                );
            }

            $wallet->forceFill(['balance' => $balanceAfter])->save();

            $player->walletTransactions()->create([
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
            ]);

            return $balanceAfter;
        });
    }
}
