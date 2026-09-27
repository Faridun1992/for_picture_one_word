<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class GuestSessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_session_creates_player_wallet_and_player_token(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', [
            'locale' => 'tj',
            'device_name' => 'Test phone',
            'coins' => 999999,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.player.locale', 'tj');

        $playerId = $response->json('data.player.id');
        $token = $response->json('data.token');
        $tokenId = explode('|', $token, 2)[0];
        $this->assertIsString($token);
        $this->assertSame(Player::class, PersonalAccessToken::query()->findOrFail($tokenId)->tokenable_type);
        $this->assertDatabaseHas('players', ['id' => $playerId, 'locale' => 'tj', 'user_id' => null]);
        $this->assertDatabaseHas('player_wallets', [
            'player_id' => $playerId,
            'balance' => 300,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'player_id' => $playerId,
            'amount' => 300,
            'reason' => 'welcome_reward',
            'reference_type' => 'player_welcome',
            'reference_id' => $playerId,
        ]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_guest_session_defaults_to_russian_when_locale_is_omitted(): void
    {
        $response = $this->postJson('/api/v1/auth/guest');

        $response->assertCreated()
            ->assertJsonPath('data.player.locale', 'ru');
    }

    public function test_guest_token_authenticates_as_its_player_on_protected_game_routes(): void
    {
        $session = $this->postJson('/api/v1/auth/guest')->assertCreated();
        $playerId = $session->json('data.player.id');
        $otherPlayer = Player::factory()->create();

        $this->withToken($session->json('data.token'))
            ->getJson('/api/v1/me?player_id='.$otherPlayer->id)
            ->assertOk()
            ->assertJsonPath('data.id', $playerId)
            ->assertJsonMissingPath('data.user_id');
    }

    public function test_protected_game_route_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_guest_session_rejects_unsupported_locale_without_creating_records(): void
    {
        $this->postJson('/api/v1/auth/guest', ['locale' => 'tg'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');

        $this->assertDatabaseCount('players', 0);
        $this->assertDatabaseCount('player_wallets', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_guest_session_rejects_device_names_longer_than_one_hundred_characters(): void
    {
        $this->postJson('/api/v1/auth/guest', ['device_name' => str_repeat('x', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_name');

        $this->assertDatabaseCount('players', 0);
    }

    public function test_guest_session_is_limited_to_ten_requests_per_minute_and_ip(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/auth/guest')->assertCreated();
        }

        $this->postJson('/api/v1/auth/guest')->assertTooManyRequests();

        $this->assertDatabaseCount('players', 10);
    }

    public function test_session_revoke_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/auth/session')->assertUnauthorized();
    }

    public function test_session_revoke_deletes_only_the_current_player_token(): void
    {
        $player = Player::factory()->create();
        $currentToken = $player->createToken('current');
        $otherToken = $player->createToken('other');

        $this->withToken($currentToken->plainTextToken)
            ->deleteJson('/api/v1/auth/session')
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $currentToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
    }

    public function test_session_revoke_rejects_user_token(): void
    {
        $user = User::query()->create([
            'name' => 'Web account',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ]);
        $token = $user->createToken('web-account');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/v1/auth/session')
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }
}
