<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\PlayerWallet;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_wallet_starts_with_zero_balance(): void
    {
        $player = Player::factory()->create();
        $wallet = $player->wallet()->create();

        $this->assertSame(0, $wallet->refresh()->balance);
        $this->assertSame($player->id, $wallet->player->id);
        $this->assertNotNull($wallet->updated_at);
    }

    public function test_player_can_have_only_one_wallet(): void
    {
        $player = Player::factory()->create();
        PlayerWallet::factory()->for($player)->create();

        $this->expectException(QueryException::class);

        PlayerWallet::factory()->for($player)->create();
    }

    public function test_deleting_player_cascades_to_wallet(): void
    {
        $wallet = PlayerWallet::factory()->create();

        $wallet->player->delete();

        $this->assertDatabaseMissing('player_wallets', ['player_id' => $wallet->player_id]);
    }
}
