<?php

namespace Tests\Feature\Admin;

use App\LevelStatus;
use App\Models\Level;
use App\Models\Player;
use App\Models\PlayerLevelProgress;
use App\Models\User;
use App\PlayerLevelProgressStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class LevelStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_sees_aggregated_level_statistics_without_player_data(): void
    {
        $admin = $this->createAdmin();
        $level = Level::factory()->create(['status' => LevelStatus::Published]);
        $archivedLevel = Level::factory()->create(['status' => LevelStatus::Archived]);
        $firstPlayer = Player::factory()->create();
        $secondPlayer = Player::factory()->create();
        PlayerLevelProgress::factory()->create([
            'player_id' => $firstPlayer->id,
            'level_id' => $level->id,
            'status' => PlayerLevelProgressStatus::Completed,
            'attempt_count' => 3,
            'hints_used' => 1,
            'completed_at' => now(),
        ]);
        PlayerLevelProgress::factory()->create([
            'player_id' => $secondPlayer->id,
            'level_id' => $level->id,
            'status' => PlayerLevelProgressStatus::InProgress,
            'attempt_count' => 4,
            'hints_used' => 2,
        ]);

        $this->actingAs($admin)->get(route('admin.statistics.levels'))
            ->assertOk()
            ->assertSee('Статистика прохождений')
            ->assertSee('50%')
            ->assertDontSee('player_id')
            ->assertViewHas('totals', static fn (object $totals): bool => (int) $totals->started_count === 2
                && (int) $totals->completed_count === 1
                && (int) $totals->attempts_count === 7
                && (int) $totals->hints_count === 3)
            ->assertViewHas('levels', static function (LengthAwarePaginator $levels) use ($level, $archivedLevel): bool {
                $rows = $levels->getCollection()->keyBy('id');

                return isset($rows[$level->id], $rows[$archivedLevel->id])
                    && (int) $rows[$level->id]->started_count === 2
                    && (int) $rows[$level->id]->completed_count === 1
                    && (int) $rows[$level->id]->attempts_count === 7
                    && (int) $rows[$level->id]->hints_count === 3
                    && (int) $rows[$archivedLevel->id]->started_count === 0;
            });
    }

    public function test_non_admin_cannot_view_level_statistics(): void
    {
        $user = User::query()->create([
            'name' => 'Regular user',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ]);

        $this->actingAs($user)->get(route('admin.statistics.levels'))->assertForbidden();
    }

    private function createAdmin(): User
    {
        $user = User::query()->create([
            'name' => 'Statistics admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ]);
        $user->assignRole('Admin');

        return $user;
    }
}
