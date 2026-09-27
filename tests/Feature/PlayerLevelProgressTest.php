<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\Player;
use App\Models\PlayerLevelProgress;
use App\PlayerLevelProgressStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerLevelProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_progress_starts_with_expected_state_and_relations(): void
    {
        $player = Player::factory()->create();
        $level = Level::factory()->create();
        $progress = new PlayerLevelProgress(['started_at' => now()]);
        $progress->player()->associate($player);
        $progress->level()->associate($level);
        $progress->save();
        $progress->refresh();

        $this->assertSame(PlayerLevelProgressStatus::InProgress, $progress->status);
        $this->assertSame(0, $progress->attempt_count);
        $this->assertSame(0, $progress->hints_used);
        $this->assertNotNull($progress->started_at);
        $this->assertNull($progress->completed_at);
        $this->assertSame($progress->id, $progress->player->levelProgress()->firstOrFail()->id);
        $this->assertSame($progress->id, $progress->level->playerProgress()->firstOrFail()->id);
    }

    public function test_player_can_have_only_one_progress_record_per_level(): void
    {
        $player = Player::factory()->create();
        $level = Level::factory()->create();
        PlayerLevelProgress::factory()->for($player)->for($level)->create();

        $this->expectException(QueryException::class);

        PlayerLevelProgress::factory()->for($player)->for($level)->create();
    }

    public function test_deleting_player_removes_its_progress(): void
    {
        $progress = PlayerLevelProgress::factory()->create();

        $progress->player->delete();

        $this->assertDatabaseMissing('player_level_progress', ['id' => $progress->id]);
    }

    public function test_level_with_player_progress_cannot_be_deleted(): void
    {
        $progress = PlayerLevelProgress::factory()->create();

        $this->expectException(QueryException::class);

        $progress->level->delete();
    }
}
