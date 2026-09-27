<?php

namespace Tests\Feature\Admin;

use App\LevelStatus;
use App\Models\Level;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelReorderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_move_published_levels_up_and_order_is_compacted(): void
    {
        $admin = $this->createAdmin();
        [$first, $second, $third] = $this->createPublishedLevels([10, 30, 20]);

        $this->actingAs($admin)
            ->post(route('admin.levels.move', $third), ['direction' => 'up'])
            ->assertRedirect(route('admin.levels.index'))
            ->assertSessionHas('status', 'Порядок уровней обновлён.');

        $this->assertSame([$third->id, $first->id, $second->id], $this->publishedOrder());
        $this->assertSame([1, 2, 3], Level::query()->whereIn('id', [$first->id, $second->id, $third->id])->orderBy('sequence')->pluck('sequence')->all());
    }

    public function test_duplicate_sequences_have_stable_id_order_and_boundary_move_is_a_no_op(): void
    {
        $admin = $this->createAdmin();
        [$first, $second, $third] = $this->createPublishedLevels([1, 1, 9]);

        $this->actingAs($admin)->post(route('admin.levels.move', $first), ['direction' => 'up'])
            ->assertRedirect(route('admin.levels.index'));

        $this->assertSame([$first->id, $second->id, $third->id], $this->publishedOrder());
        $this->assertSame([1, 1, 9], [$first->refresh()->sequence, $second->refresh()->sequence, $third->refresh()->sequence]);

        $this->actingAs($admin)->post(route('admin.levels.move', $second), ['direction' => 'up'])
            ->assertRedirect(route('admin.levels.index'));

        $this->assertSame([$second->id, $first->id, $third->id], $this->publishedOrder());
        $this->assertSame([1, 2, 3], Level::query()->whereIn('id', [$first->id, $second->id, $third->id])->orderBy('sequence')->pluck('sequence')->all());
    }

    public function test_only_admins_can_move_published_levels_and_direction_is_validated(): void
    {
        $level = $this->createPublishedLevels([1])[0];
        $user = User::query()->create([
            'name' => 'Regular user',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ]);

        $this->actingAs($user)->post(route('admin.levels.move', $level), ['direction' => 'up'])->assertForbidden();

        $this->actingAs($this->createAdmin())
            ->from(route('admin.levels.index'))
            ->post(route('admin.levels.move', $level), ['direction' => 'sideways'])
            ->assertRedirect(route('admin.levels.index'))
            ->assertSessionHasErrors('direction');

        $this->assertSame(1, $level->refresh()->sequence);
    }

    /** @param list<int> $sequences
     * @return list<Level>
     */
    private function createPublishedLevels(array $sequences): array
    {
        $levels = [];
        foreach ($sequences as $sequence) {
            $levels[] = Level::factory()->create([
                'sequence' => $sequence,
                'status' => LevelStatus::Published,
                'published_at' => now(),
            ]);
        }

        return $levels;
    }

    /** @return list<int> */
    private function publishedOrder(): array
    {
        return Level::query()
            ->where('status', LevelStatus::Published)
            ->orderBy('sequence')
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    private function createAdmin(): User
    {
        $user = User::query()->create([
            'name' => 'Level admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ]);
        $user->assignRole('Admin');

        return $user;
    }
}
