<?php

namespace Tests\Feature;

use App\LevelStatus;
use App\Models\Category;
use App\Models\Level;
use App\Models\LevelImage;
use App\Models\LevelTranslation;
use App\Models\Player;
use App\Models\PlayerLevelProgress;
use App\Models\PlayerWallet;
use App\PlayerLevelProgressStatus;
use App\WalletTransactionReason;
use App\WalletTransactionReferenceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelHintApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reveal_letter_charges_once_and_returns_the_letter(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 100]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        $token = $player->createToken('hint-test')->plainTextToken;

        $response = $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/hints",
            ['type' => 'reveal_letter'],
            ['Idempotency-Key' => 'hint-reveal-auto-001'],
        );

        $response->assertOk()
            ->assertJsonPath('data.charged', true)
            ->assertJsonPath('data.cost', 60)
            ->assertJsonPath('data.balance', 40)
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.revealed.1', 'К')
            ->assertJsonPath('data.letter_tiles', ['О', 'Ш', 'К', 'А']);

        $this->assertDatabaseHas('wallet_transactions', [
            'player_id' => $player->id,
            'amount' => -60,
            'balance_after' => 40,
            'reason' => WalletTransactionReason::HintCost->value,
            'reference_type' => WalletTransactionReferenceType::LevelHint->value,
        ]);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_a_new_reveal_letter_hint_opens_the_next_position_and_charges_again(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 120]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        $token = $player->createToken('hint-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'reveal_letter'], ['Idempotency-Key' => 'hint-letter-first-001'])
            ->assertOk()
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.balance', 60);
        $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'reveal_letter'], ['Idempotency-Key' => 'hint-letter-repeat-01'])
            ->assertOk()
            ->assertJsonPath('data.charged', true)
            ->assertJsonPath('data.cost', 60)
            ->assertJsonPath('data.position', 2)
            ->assertJsonPath('data.balance', 0);

        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseHas('player_level_progress', ['player_id' => $player->id, 'level_id' => $level->id, 'hints_used' => 2]);
    }

    public function test_reveal_letter_when_all_letters_are_already_revealed_is_free(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 100]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        PlayerLevelProgress::factory()->for($player)->for($level)->create([
            'hint_state' => ['revealed_positions' => [1, 2, 3, 4, 5], 'removed_tiles' => []],
        ]);
        $token = $player->createToken('hint-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'reveal_letter'], ['Idempotency-Key' => 'hint-no-letter-left'])
            ->assertOk()
            ->assertJsonPath('data.charged', false)
            ->assertJsonPath('data.cost', 0)
            ->assertJsonPath('data.balance', 100);

        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_replayed_hint_key_returns_the_saved_result_without_another_charge(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 100]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        $token = $player->createToken('hint-test')->plainTextToken;
        $headers = ['Idempotency-Key' => 'hint-offline-retry1'];

        $first = $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'reveal_letter'], $headers);
        $replay = $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'reveal_letter'], $headers);

        $first->assertOk();
        $replay->assertOk();
        $this->assertJsonStringEqualsJsonString($first->getContent(), $replay->getContent());
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('player_level_progress', ['player_id' => $player->id, 'level_id' => $level->id, 'hints_used' => 1]);
    }

    public function test_remove_wrong_letters_removes_the_configured_count_selected_by_server(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 100]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А', 'Ж', 'Ц']);
        $token = $player->createToken('hint-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'remove_wrong_letters', 'balance' => 10000, 'cost' => 0], ['Idempotency-Key' => 'hint-remove-first-001'])
            ->assertOk()
            ->assertJsonPath('data.cost', 40)
            ->assertJsonPath('data.removed_tiles', ['Ж', 'Ц'])
            ->assertJsonPath('data.letter_tiles', ['К', 'О', 'Ш', 'К', 'А'])
            ->assertJsonPath('data.balance', 60);

        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_removing_wrong_letters_when_none_remain_completes_without_charge(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 100]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А', 'Ж']);
        $token = $player->createToken('hint-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'remove_wrong_letters'], ['Idempotency-Key' => 'hint-remove-last-001'])
            ->assertOk()
            ->assertJsonPath('data.charged', true)
            ->assertJsonPath('data.removed_tiles', ['Ж']);
        $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'remove_wrong_letters'], ['Idempotency-Key' => 'hint-remove-empty-01'])
            ->assertOk()
            ->assertJsonPath('data.charged', false)
            ->assertJsonPath('data.cost', 0)
            ->assertJsonPath('data.balance', 60);

        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_reveal_answer_charges_and_completes_the_riddle(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 100]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        $token = $player->createToken('hint-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'reveal_answer'], ['Idempotency-Key' => 'hint-answer-reveal-01'])
            ->assertOk()
            ->assertJsonPath('data.answer', 'КОШКА')
            ->assertJsonPath('data.cost', 100)
            ->assertJsonPath('data.balance', 50)
            ->assertJsonPath('data.progress.status', PlayerLevelProgressStatus::Completed->value)
            ->assertJsonPath('data.reward.coins', 50)
            ->assertJsonPath('data.reward.balance', 50);

        $this->assertDatabaseHas('player_level_progress', ['player_id' => $player->id, 'level_id' => $level->id, 'status' => PlayerLevelProgressStatus::Completed->value]);
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_insufficient_coins_do_not_change_wallet_progress_or_ledger(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 59]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        $token = $player->createToken('hint-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'reveal_letter'], ['Idempotency-Key' => 'hint-too-poor-0001'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'insufficient_coins')
            ->assertJsonPath('meta.required', 60)
            ->assertJsonPath('meta.balance', 59);

        $this->assertSame(59, $player->wallet()->firstOrFail()->refresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseMissing('player_level_progress', ['player_id' => $player->id]);
    }

    public function test_hint_rejects_client_supplied_board_and_unrecognized_type(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 100]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А', 'Ж']);
        $token = $player->createToken('hint-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'unknown'], ['Idempotency-Key' => 'hint-unknown-type-1'])->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'remove_wrong_letters', 'free_tiles' => ['К']], ['Idempotency-Key' => 'hint-client-board-01'])->assertUnprocessable()->assertJsonValidationErrors('free_tiles');
        $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'reveal_letter', 'position' => 3], ['Idempotency-Key' => 'hint-client-position-1'])->assertUnprocessable()->assertJsonValidationErrors('position');

        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_hint_on_a_completed_level_is_rejected(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 100]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        PlayerLevelProgress::factory()->for($player)->for($level)->create([
            'status' => PlayerLevelProgressStatus::Completed,
            'completed_at' => now(),
        ]);
        $token = $player->createToken('hint-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'reveal_letter'], ['Idempotency-Key' => 'hint-after-win-0001'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'level_already_completed');

        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_hint_on_an_unplayable_level_is_not_found(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 100]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        $level->forceFill(['status' => LevelStatus::Draft])->save();
        $token = $player->createToken('hint-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/levels/{$level->id}/hints", ['type' => 'reveal_letter'], ['Idempotency-Key' => 'hint-draft-level-01'])
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    /** @param list<string> $letterTiles */
    private function createPlayableLevel(string $locale, string $answer, array $letterTiles): Level
    {
        $category = Category::factory()->create();
        $category->translations()->create(['locale' => $locale, 'name' => 'Животные']);
        $level = Level::factory()->for($category)->create(['status' => LevelStatus::Published]);
        LevelTranslation::factory()->for($level)->create([
            'locale' => $locale,
            'answer_display' => $answer,
            'letter_tiles' => $letterTiles,
        ]);

        foreach ([1, 2, 3, 4] as $position) {
            LevelImage::factory()->for($level)->create(['position' => $position]);
        }

        return $level;
    }
}
