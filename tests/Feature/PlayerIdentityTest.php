<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PlayerIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_defaults_to_russian_locale(): void
    {
        $player = Player::query()->create();

        $this->assertSame('ru', $player->refresh()->locale);
    }

    public function test_guest_player_can_be_linked_to_an_account_without_changing_identity(): void
    {
        $player = Player::factory()->create(['locale' => 'tj']);
        $user = $this->createUser();

        $player->user()->associate($user)->save();

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'user_id' => $user->id,
            'locale' => 'tj',
        ]);
        $this->assertSame($player->id, $user->player()->value('id'));
    }

    public function test_deleting_an_account_keeps_its_player_as_a_guest(): void
    {
        $user = $this->createUser();
        $player = Player::factory()->for($user)->create();

        $user->delete();

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'user_id' => null,
        ]);
    }

    public function test_an_account_cannot_be_linked_to_multiple_players(): void
    {
        $user = $this->createUser();
        Player::factory()->for($user)->create();

        $this->expectException(QueryException::class);

        Player::factory()->for($user)->create();
    }

    public function test_player_token_can_access_player_routes(): void
    {
        $player = Player::factory()->create();
        $token = $player->createToken('test')->plainTextToken;
        $this->registerPlayerProbeRoute();

        $this->withToken($token)->getJson('/api/v1/test/player-only')
            ->assertOk()
            ->assertJsonPath('player_id', $player->id);
    }

    public function test_user_token_cannot_access_player_routes(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;
        $this->registerPlayerProbeRoute();

        $this->withToken($token)->getJson('/api/v1/test/player-only')
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Test account',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ]);
    }

    private function registerPlayerProbeRoute(): void
    {
        Route::middleware(['auth:sanctum', 'player'])
            ->get('/api/v1/test/player-only', fn () => response()->json([
                'player_id' => request()->user()->id,
            ]));
    }
}
