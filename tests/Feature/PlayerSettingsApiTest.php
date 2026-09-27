<?php

namespace Tests\Feature;

use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_player_defaults_and_only_current_player_data(): void
    {
        $player = Player::factory()->create([
            'sound_enabled' => true,
            'haptics_enabled' => true,
            'theme' => 'system',
        ]);
        $otherPlayer = Player::factory()->create();

        $this->withToken($player->createToken('mobile')->plainTextToken)
            ->getJson('/api/v1/me?player_id='.$otherPlayer->id)
            ->assertOk()
            ->assertJsonPath('data.id', $player->id)
            ->assertJsonPath('data.settings.sound_enabled', true)
            ->assertJsonPath('data.settings.haptics_enabled', true)
            ->assertJsonPath('data.settings.theme', 'system')
            ->assertJsonMissingPath('data.user_id');
    }

    public function test_player_can_update_and_read_back_localized_settings(): void
    {
        $player = Player::factory()->create();
        $player->wallet()->create(['balance' => 10]);
        $token = $player->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/v1/me/settings', [
                'locale' => 'tj',
                'sound_enabled' => false,
                'haptics_enabled' => false,
                'theme' => 'dark',
                'coins' => 999999,
            ])
            ->assertOk()
            ->assertJsonPath('data.locale', 'tj')
            ->assertJsonPath('data.sound_enabled', false)
            ->assertJsonPath('data.haptics_enabled', false)
            ->assertJsonPath('data.theme', 'dark');

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'locale' => 'tj',
            'sound_enabled' => false,
            'haptics_enabled' => false,
            'theme' => 'dark',
        ]);
        $this->assertDatabaseHas('player_wallets', ['player_id' => $player->id, 'balance' => 10]);
    }

    public function test_settings_reject_unsupported_values_without_changing_player(): void
    {
        $player = Player::factory()->create(['locale' => 'ru', 'theme' => 'system']);
        $token = $player->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/v1/me/settings', [
                'locale' => 'tg',
                'sound_enabled' => 'sometimes',
                'theme' => 'auto',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locale', 'sound_enabled', 'theme']);

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'locale' => 'ru',
            'sound_enabled' => true,
            'theme' => 'system',
        ]);
    }

    public function test_settings_require_authentication(): void
    {
        $this->patchJson('/api/v1/me/settings', ['theme' => 'dark'])->assertUnauthorized();
    }
}
