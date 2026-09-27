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

class LevelAttemptApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_answer_completes_the_level_and_grants_the_reward_once(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 0]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        $nextLevel = $this->createPlayableLevel('ru', 'СОБАКА', ['С', 'О', 'Б', 'А', 'К', 'А'], sequence: 2);
        $token = $player->createToken('attempt-test')->plainTextToken;

        $response = $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'кошка'],
            ['Idempotency-Key' => 'attempt-correct-0001'],
        );

        $response->assertOk()
            ->assertJsonPath('data.level_id', $level->id)
            ->assertJsonPath('data.correct', true)
            ->assertJsonPath('data.already_completed', false)
            ->assertJsonPath('data.reward.coins', 60)
            ->assertJsonPath('data.reward.correct_answer', 10)
            ->assertJsonPath('data.reward.level_completion', 50)
            ->assertJsonPath('data.reward.balance', 60)
            ->assertJsonPath('data.next_level_id', $nextLevel->id)
            ->assertJsonPath('data.progress.status', PlayerLevelProgressStatus::Completed->value)
            ->assertJsonPath('data.progress.attempt_count', 1);

        $this->assertDatabaseHas('player_level_progress', [
            'player_id' => $player->id,
            'level_id' => $level->id,
            'status' => PlayerLevelProgressStatus::Completed->value,
            'attempt_count' => 1,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'player_id' => $player->id,
            'amount' => 10,
            'balance_after' => 10,
            'reason' => WalletTransactionReason::LevelReward->value,
            'reference_type' => WalletTransactionReferenceType::CorrectAnswer->value,
            'reference_id' => $level->id,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'player_id' => $player->id,
            'amount' => 50,
            'balance_after' => 60,
            'reference_type' => WalletTransactionReferenceType::LevelCompletion->value,
            'reference_id' => $level->id,
        ]);
    }

    public function test_incorrect_answer_keeps_progress_open_and_wallet_untouched(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 12]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        $token = $player->createToken('attempt-test')->plainTextToken;

        $response = $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'СОБАКА'],
            ['Idempotency-Key' => 'attempt-wrong-000001'],
        );

        $response->assertOk()
            ->assertJsonPath('data.correct', false)
            ->assertJsonPath('data.progress.status', PlayerLevelProgressStatus::InProgress->value)
            ->assertJsonPath('data.progress.attempt_count', 1)
            ->assertJsonMissingPath('data.reward');

        $this->assertDatabaseHas('player_level_progress', [
            'player_id' => $player->id,
            'level_id' => $level->id,
            'status' => PlayerLevelProgressStatus::InProgress->value,
            'attempt_count' => 1,
            'completed_at' => null,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', ['player_id' => $player->id]);
        $this->assertSame(12, $player->wallet()->firstOrFail()->refresh()->balance);
    }

    public function test_completed_level_answers_the_same_result_without_a_second_reward(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 0]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        $token = $player->createToken('attempt-test')->plainTextToken;

        $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'КОШКА'],
            ['Idempotency-Key' => 'attempt-first-00001'],
        )->assertOk();

        $repeat = $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'КОШКА'],
            ['Idempotency-Key' => 'attempt-second-0001'],
        );

        $repeat->assertOk()
            ->assertJsonPath('data.correct', true)
            ->assertJsonPath('data.already_completed', true)
            ->assertJsonPath('data.reward.coins', 0)
            ->assertJsonPath('data.reward.balance', 60)
            ->assertJsonPath('data.progress.attempt_count', 1);

        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_completed_level_with_wrong_answer_returns_conflict(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 0]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        PlayerLevelProgress::factory()->for($player)->for($level)->create([
            'status' => PlayerLevelProgressStatus::Completed,
            'attempt_count' => 4,
            'completed_at' => now(),
        ]);
        $token = $player->createToken('attempt-test')->plainTextToken;

        $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'СОБАКА'],
            ['Idempotency-Key' => 'attempt-conflict-01'],
        )
            ->assertStatus(409)
            ->assertJsonPath('code', 'level_already_completed');

        $this->assertDatabaseMissing('wallet_transactions', ['player_id' => $player->id]);
    }

    public function test_replayed_idempotency_key_returns_the_saved_response(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 0]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        $token = $player->createToken('attempt-test')->plainTextToken;

        $first = $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'КОШКА'],
            ['Idempotency-Key' => 'attempt-offline-0001'],
        );
        $replay = $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'КОШКА'],
            ['Idempotency-Key' => 'attempt-offline-0001'],
        );

        $first->assertOk();
        $replay->assertOk();
        $this->assertJsonStringEqualsJsonString($first->getContent(), $replay->getContent());
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseHas('player_level_progress', [
            'player_id' => $player->id,
            'level_id' => $level->id,
            'attempt_count' => 1,
        ]);
    }

    public function test_same_key_with_another_answer_is_rejected_without_execution(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 0]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        $token = $player->createToken('attempt-test')->plainTextToken;

        $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'СОБАКА'],
            ['Idempotency-Key' => 'attempt-shared-0001'],
        )->assertOk();

        $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'КОШКА'],
            ['Idempotency-Key' => 'attempt-shared-0001'],
        )
            ->assertStatus(409)
            ->assertJsonPath('code', 'idempotency_key_reused');

        $this->assertDatabaseHas('player_level_progress', [
            'player_id' => $player->id,
            'level_id' => $level->id,
            'status' => PlayerLevelProgressStatus::InProgress->value,
            'attempt_count' => 1,
        ]);
    }

    public function test_unicode_answers_are_matched_after_case_folding_for_tajik_letters(): void
    {
        $player = Player::factory()->create(['locale' => 'tj']);
        PlayerWallet::factory()->for($player)->create(['balance' => 0]);
        $level = $this->createPlayableLevel('tj', 'ҲАВО', ['Ҳ', 'А', 'В', 'О']);
        $token = $player->createToken('attempt-test')->plainTextToken;

        $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'ҳаво'],
            ['Idempotency-Key' => 'attempt-unicode-tj1'],
        )
            ->assertOk()
            ->assertJsonPath('data.correct', true);

        $this->assertDatabaseHas('player_level_progress', [
            'player_id' => $player->id,
            'level_id' => $level->id,
            'status' => PlayerLevelProgressStatus::Completed->value,
        ]);
    }

    public function test_unicode_answers_are_matched_after_nfc_normalization(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 0]);
        $level = $this->createPlayableLevel('ru', 'ЧАЙ', ['Ч', 'А', 'Й']);
        $token = $player->createToken('attempt-test')->plainTextToken;

        $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'ЧАИ'],
            ['Idempotency-Key' => 'attempt-unicode-ru2'],
        )
            ->assertOk()
            ->assertJsonPath('data.correct', false)
            ->assertJsonPath('data.progress.attempt_count', 1);

        $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => "ЧА\u{0418}\u{0306}"],
            ['Idempotency-Key' => 'attempt-unicode-ru1'],
        )
            ->assertOk()
            ->assertJsonPath('data.correct', true);

        $this->assertDatabaseHas('player_level_progress', [
            'player_id' => $player->id,
            'level_id' => $level->id,
            'status' => PlayerLevelProgressStatus::Completed->value,
            'attempt_count' => 2,
        ]);
    }

    public function test_attempt_never_touches_another_players_state(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        $anotherPlayer = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 0]);
        PlayerWallet::factory()->for($anotherPlayer)->create(['balance' => 7]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        $token = $player->createToken('attempt-test')->plainTextToken;

        $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'КОШКА'],
            ['Idempotency-Key' => 'attempt-isolation-01'],
        )->assertOk();

        $this->assertDatabaseMissing('player_level_progress', ['player_id' => $anotherPlayer->id]);
        $this->assertDatabaseMissing('wallet_transactions', ['player_id' => $anotherPlayer->id]);
        $this->assertSame(7, $anotherPlayer->wallet()->firstOrFail()->refresh()->balance);
    }

    public function test_attempt_requires_authentication_idempotency_key_and_a_valid_answer(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 0]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        $token = $player->createToken('attempt-test')->plainTextToken;

        $this->postJson("/api/v1/levels/{$level->id}/attempts", ['answer' => 'КОШКА'])
            ->assertUnauthorized();

        $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'КОШКА'],
            ['Idempotency-Key' => 'short'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');

        $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => ''],
            ['Idempotency-Key' => 'attempt-missing-answer'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('answer');

        $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'КОШКА', 'locale' => 'de'],
            ['Idempotency-Key' => 'attempt-locale-0001'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');

        $this->assertDatabaseCount('player_level_progress', 0);
    }

    public function test_client_cannot_set_balance_or_reward_values(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 9]);
        $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А']);
        $token = $player->createToken('attempt-test')->plainTextToken;

        $this->withToken($token)->postJson(
            "/api/v1/levels/{$level->id}/attempts",
            ['answer' => 'КОШКА', 'balance' => 500, 'reward' => 10000, 'completed' => false],
            ['Idempotency-Key' => 'attempt-client-values-01'],
        )
            ->assertOk()
            ->assertJsonPath('data.reward.coins', 60)
            ->assertJsonPath('data.reward.balance', 69);

        $this->assertSame(69, $player->wallet()->firstOrFail()->refresh()->balance);
    }

    public function test_each_solved_count_milestone_is_paid_once(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 0]);
        $token = $player->createToken('attempt-test')->plainTextToken;

        for ($sequence = 1; $sequence <= 50; $sequence++) {
            $level = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А'], $sequence);
            $this->withToken($token)->postJson(
                "/api/v1/levels/{$level->id}/attempts",
                ['answer' => 'КОШКА'],
                ['Idempotency-Key' => 'milestone-level-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT)],
            )->assertOk();
        }

        $this->assertDatabaseHas('wallet_transactions', [
            'player_id' => $player->id,
            'reason' => WalletTransactionReason::MilestoneReward->value,
            'reference_type' => WalletTransactionReferenceType::LevelMilestone->value,
            'reference_id' => 50,
            'amount' => 150,
        ]);
        $this->assertSame(50 * 60 + 150, $player->wallet()->firstOrFail()->refresh()->balance);
    }

    public function test_level_without_translation_or_without_four_images_is_not_playable(): void
    {
        $player = Player::factory()->create(['locale' => 'ru']);
        PlayerWallet::factory()->for($player)->create(['balance' => 0]);
        $token = $player->createToken('attempt-test')->plainTextToken;

        $withoutTranslation = Level::factory()->for(
            Category::factory()->create(['is_active' => true])
        )->create(['sequence' => 1, 'status' => LevelStatus::Published]);

        $withoutImages = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А'], sequence: 2);
        $withoutImages->images()->where('position', 4)->delete();

        $draft = $this->createPlayableLevel('ru', 'КОШКА', ['К', 'О', 'Ш', 'К', 'А'], sequence: 3);
        $draft->forceFill(['status' => LevelStatus::Draft])->save();

        foreach ([$withoutTranslation, $withoutImages, $draft] as $level) {
            $this->withToken($token)->postJson(
                "/api/v1/levels/{$level->id}/attempts",
                ['answer' => 'КОШКА'],
                ['Idempotency-Key' => 'attempt-unplayable-'.$level->id],
            )
                ->assertNotFound()
                ->assertJsonPath('code', 'not_found');
        }

        $this->assertDatabaseCount('player_level_progress', 0);
        $this->assertDatabaseMissing('wallet_transactions', ['player_id' => $player->id]);
    }

    /**
     * @param  list<string>  $letterTiles
     */
    private function createPlayableLevel(
        string $locale,
        string $answer,
        array $letterTiles,
        int $sequence = 1,
    ): Level {
        $category = Category::factory()->create();
        $category->translations()->create(['locale' => $locale, 'name' => 'Животные']);
        $level = Level::factory()->for($category)->create([
            'sequence' => $sequence,
            'status' => LevelStatus::Published,
        ]);
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
