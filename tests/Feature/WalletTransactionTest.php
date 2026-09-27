<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\WalletTransaction;
use App\WalletTransactionReason;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class WalletTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_records_reason_amount_balance_and_creation_time(): void
    {
        $player = Player::factory()->create();
        $transaction = $player->walletTransactions()->create([
            'amount' => 10,
            'balance_after' => 10,
            'reason' => WalletTransactionReason::LevelReward,
            'reference_type' => 'level',
            'reference_id' => 100,
        ]);

        $this->assertSame(WalletTransactionReason::LevelReward, $transaction->reason);
        $this->assertSame(10, $transaction->amount);
        $this->assertSame(10, $transaction->balance_after);
        $this->assertNotNull($transaction->created_at);
        $this->assertSame($player->id, $transaction->player->id);
    }

    public function test_transaction_cannot_be_updated(): void
    {
        $transaction = WalletTransaction::factory()->create();
        $transaction->amount = 99;

        $this->expectException(LogicException::class);

        $transaction->save();
    }

    public function test_transaction_cannot_be_deleted(): void
    {
        $transaction = WalletTransaction::factory()->create();

        $this->expectException(LogicException::class);

        $transaction->delete();
    }

    public function test_player_with_wallet_transactions_cannot_be_deleted(): void
    {
        $transaction = WalletTransaction::factory()->create();

        $this->expectException(QueryException::class);

        $transaction->player->delete();
    }

    public function test_same_player_cannot_repeat_the_same_reason_and_reference(): void
    {
        $player = Player::factory()->create();
        $player->walletTransactions()->create([
            'amount' => 10,
            'balance_after' => 10,
            'reason' => WalletTransactionReason::LevelReward,
            'reference_type' => 'level',
            'reference_id' => 100,
        ]);

        $this->expectException(QueryException::class);

        $player->walletTransactions()->create([
            'amount' => 10,
            'balance_after' => 20,
            'reason' => WalletTransactionReason::LevelReward,
            'reference_type' => 'level',
            'reference_id' => 100,
        ]);
    }

    public function test_idempotency_key_is_unique_per_player(): void
    {
        $player = Player::factory()->create();
        $player->walletTransactions()->create([
            'amount' => 10,
            'balance_after' => 10,
            'reason' => WalletTransactionReason::LevelReward,
            'reference_type' => 'level',
            'reference_id' => 100,
            'idempotency_key' => 'complete-level-100',
        ]);

        $this->expectException(QueryException::class);

        $player->walletTransactions()->create([
            'amount' => -2,
            'balance_after' => 8,
            'reason' => WalletTransactionReason::HintCost,
            'reference_type' => 'level',
            'reference_id' => 100,
            'idempotency_key' => 'complete-level-100',
        ]);
    }
}
