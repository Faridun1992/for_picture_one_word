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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerProgressApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_returns_only_the_players_state_balance_and_statistics(): void
    {
        $player = Player::factory()->create(['locale' => 'tj']);
        PlayerWallet::factory()->for($player)->create(['balance' => 25]);
        $currentLevel = $this->createPlayableLevel(1);
        $completedLevel = $this->createPlayableLevel(2);
        PlayerLevelProgress::factory()->for($player)->for($currentLevel)->create([
            'attempt_count' => 2,
            'hints_used' => 1,
        ]);
        PlayerLevelProgress::factory()->for($player)->for($completedLevel)->create([
            'status' => PlayerLevelProgressStatus::Completed,
            'attempt_count' => 3,
            'completed_at' => now(),
        ]);

        $anotherPlayer = Player::factory()->create();
        PlayerLevelProgress::factory()->for($anotherPlayer)->create();
        $token = $player->createToken('progress-test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/progress');

        $response->assertOk()
            ->assertJsonPath('data.current_level_id', $currentLevel->id)
            ->assertJsonPath('data.balance', 25)
            ->assertJsonPath('data.progress.0.level_id', $currentLevel->id)
            ->assertJsonPath('data.progress.0.status', 'in_progress')
            ->assertJsonPath('data.statistics.total_levels', 2)
            ->assertJsonPath('data.statistics.completed_levels', 1)
            ->assertJsonPath('data.statistics.total_attempts', 5);
    }

    public function test_progress_selects_the_first_available_level_for_a_new_player(): void
    {
        $player = Player::factory()->create(['locale' => 'tj']);
        $this->createPlayableLevel(2);
        $firstLevel = $this->createPlayableLevel(1);
        $token = $player->createToken('progress-test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/progress')
            ->assertOk()
            ->assertJsonPath('data.current_level_id', $firstLevel->id);
    }

    public function test_progress_uses_cursor_pagination_scoped_to_the_authenticated_player(): void
    {
        $player = Player::factory()->create();
        $firstLevel = Level::factory()->create(['sequence' => 1]);
        $secondLevel = Level::factory()->create(['sequence' => 2]);
        $thirdLevel = Level::factory()->create(['sequence' => 3]);
        PlayerLevelProgress::factory()->for($player)->for($firstLevel)->create();
        PlayerLevelProgress::factory()->for($player)->for($secondLevel)->create();
        PlayerLevelProgress::factory()->for($player)->for($thirdLevel)->create();
        PlayerLevelProgress::factory()->create();
        $token = $player->createToken('progress-test')->plainTextToken;

        $firstPage = $this->withToken($token)->getJson('/api/v1/progress?limit=2');
        $firstPage->assertOk()->assertJsonCount(2, 'data.progress');
        $cursor = $firstPage->json('meta.next_cursor');

        $this->withToken($token)->getJson('/api/v1/progress?limit=2&cursor='.urlencode($cursor))
            ->assertOk()
            ->assertJsonCount(1, 'data.progress')
            ->assertJsonPath('data.progress.0.level_id', $thirdLevel->id);
    }

    public function test_progress_requires_authentication_and_valid_pagination(): void
    {
        $this->getJson('/api/v1/progress')->assertUnauthorized();

        $player = Player::factory()->create();
        $token = $player->createToken('progress-test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/progress?limit=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');
    }

    private function createPlayableLevel(int $sequence): Level
    {
        $category = Category::factory()->create();
        $category->translations()->create(['locale' => 'tj', 'name' => 'Ҳайвонот']);
        $level = Level::factory()->for($category)->create([
            'sequence' => $sequence,
            'status' => LevelStatus::Published,
        ]);
        LevelTranslation::factory()->for($level)->create([
            'locale' => 'tj',
            'answer_display' => 'ГУРБА',
            'letter_tiles' => ['Г', 'У', 'Р', 'Б', 'А'],
        ]);

        for ($position = 1; $position <= 4; $position++) {
            LevelImage::factory()->for($level)->create(['position' => $position]);
        }

        return $level;
    }
}
