<?php

namespace Tests\Feature\Admin;

use App\LevelStatus;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Level;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_draft_with_unicode_translations(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->createAdmin();
        $category = $this->createCategory();

        $response = $this->actingAs($admin)->post(route('admin.levels.store'), [
            'category_id' => $category->id,
            'difficulty' => 2,
            'translations' => [
                'ru' => ['answer_display' => 'КОТ', 'letter_tiles' => "К\nО\nТ\nА"],
                'tj' => ['answer_display' => 'ГУРБА', 'letter_tiles' => "Г\nУ\nР\nБ\nА\nТ"],
                'en' => ['answer_display' => 'CAT', 'letter_tiles' => "C\nA\nT\nO"],
            ],
        ]);

        $level = Level::query()->firstOrFail();

        $response->assertRedirect(route('admin.levels.edit', $level));
        $this->assertSame(LevelStatus::Draft, $level->status);
        $this->assertSame(1, $level->sequence);
        $this->assertSame('гурба', $level->translations()->where('locale', 'tj')->value('answer_normalized'));
        $this->assertSame(['К', 'О', 'Т', 'А'], $level->translations()->where('locale', 'ru')->firstOrFail()->letter_tiles);
    }

    public function test_invalid_difficulty_does_not_create_a_level(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->createAdmin();
        $category = $this->createCategory();

        $this->actingAs($admin)->from(route('admin.levels.create'))
            ->post(route('admin.levels.store'), [
                'category_id' => $category->id,
                'difficulty' => 6,
                'translations' => [],
            ])
            ->assertSessionHasErrors('difficulty');

        $this->assertDatabaseCount('levels', 0);
    }

    public function test_user_without_admin_role_cannot_create_a_level(): void
    {
        $this->seed(RoleSeeder::class);
        $user = $this->createUser();
        $category = $this->createCategory();

        $this->actingAs($user)->post(route('admin.levels.store'), [
            'category_id' => $category->id,
            'difficulty' => 1,
            'translations' => [],
        ])->assertForbidden();

        $this->assertDatabaseCount('levels', 0);
    }

    public function test_only_draft_levels_can_be_edited(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->createAdmin();
        $level = Level::factory()->create(['status' => LevelStatus::Published, 'difficulty' => 1]);

        $this->actingAs($admin)->get(route('admin.levels.edit', $level))->assertForbidden();
        $this->actingAs($admin)->put(route('admin.levels.update', $level), [
            'category_id' => $level->category_id,
            'difficulty' => 3,
            'translations' => [],
        ])->assertForbidden();

        $this->assertSame(1, $level->refresh()->difficulty);
    }

    public function test_clearing_a_translation_removes_it_from_the_draft(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->createAdmin();
        $level = Level::factory()->create();
        $level->translations()->create([
            'locale' => 'ru',
            'answer_display' => 'КОТ',
            'letter_tiles' => ['К', 'О', 'Т'],
        ]);

        $this->actingAs($admin)->put(route('admin.levels.update', $level), [
            'category_id' => $level->category_id,
            'difficulty' => $level->difficulty,
            'translations' => [
                'ru' => ['answer_display' => '', 'letter_tiles' => ''],
                'tj' => ['answer_display' => '', 'letter_tiles' => ''],
                'en' => ['answer_display' => '', 'letter_tiles' => ''],
            ],
        ])->assertRedirect(route('admin.levels.edit', $level));

        $this->assertDatabaseCount('level_translations', 0);
    }

    private function createAdmin(): User
    {
        $user = $this->createUser();
        $user->assignRole('Admin');

        return $user;
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Level editor test',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ]);
    }

    private function createCategory(): Category
    {
        $category = Category::factory()->create();
        CategoryTranslation::factory()->create([
            'category_id' => $category->id,
            'locale' => 'ru',
            'name' => 'Животные',
        ]);

        return $category;
    }
}
