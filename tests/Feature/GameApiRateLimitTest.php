<?php

namespace Tests\Feature;

use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class GameApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_api_rate_limit_is_scoped_to_player_and_returns_429_after_limit(): void
    {
        $player = Player::factory()->create();
        $otherPlayer = Player::factory()->create();
        $this->withToken($player->createToken('mobile')->plainTextToken);

        for ($attempt = 0; $attempt < 120; $attempt++) {
            $this->getJson('/api/v1/me')->assertOk();
        }

        $this->getJson('/api/v1/me')
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests');

        Auth::forgetGuards();

        $this->withToken($otherPlayer->createToken('mobile')->plainTextToken)
            ->getJson('/api/v1/me')
            ->assertOk();
    }
}
