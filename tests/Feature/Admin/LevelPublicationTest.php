<?php

namespace Tests\Feature\Admin;

use App\LevelStatus;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Level;
use App\Models\PlayerLevelProgress;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LevelPublicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    public function test_admin_can_publish_a_complete_multilingual_level(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createCompleteDraft();

        $this->actingAs($admin)
            ->post(route('admin.levels.publish', $level))
            ->assertRedirect(route('admin.levels.index'))
            ->assertSessionHas('status', 'Уровень опубликован.');

        $this->assertSame(LevelStatus::Published, $level->refresh()->status);
        $this->assertNotNull($level->published_at);
        $publishedAt = $level->published_at;

        $this->actingAs($admin)->post(route('admin.levels.publish', $level))->assertRedirect(route('admin.levels.index'));

        $this->assertTrue($publishedAt->equalTo($level->refresh()->published_at));
    }

    public function test_incomplete_level_cannot_be_published(): void
    {
        $admin = $this->createAdmin();
        $level = Level::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.levels.edit', $level))
            ->post(route('admin.levels.publish', $level))
            ->assertRedirect(route('admin.levels.edit', $level))
            ->assertSessionHasErrors(['category.ru', 'translations.ru', 'images']);

        $this->assertSame(LevelStatus::Draft, $level->refresh()->status);
        $this->assertNull($level->published_at);
    }

    public function test_publication_rejects_letter_tiles_that_do_not_cover_the_answer(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createCompleteDraft();
        $level->translations()->where('locale', 'ru')->update([
            'letter_tiles' => json_encode(['К', 'О', 'А'], JSON_THROW_ON_ERROR),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.levels.edit', $level))
            ->post(route('admin.levels.publish', $level))
            ->assertRedirect(route('admin.levels.edit', $level))
            ->assertSessionHasErrors('translations.ru.letter_tiles');

        $this->assertSame(LevelStatus::Draft, $level->refresh()->status);
    }

    public function test_publication_rejects_eleven_tiles_even_when_the_answer_is_covered(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createCompleteDraft();
        $level->translations()->where('locale', 'ru')->update([
            'letter_tiles' => json_encode(['К', 'О', 'Т', 'А', 'В', 'Г', 'Д', 'Е', 'Ж', 'З', 'И'], JSON_THROW_ON_ERROR),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.levels.edit', $level))
            ->post(route('admin.levels.publish', $level))
            ->assertRedirect(route('admin.levels.edit', $level))
            ->assertSessionHasErrors('translations.ru.letter_tiles');

        $this->assertSame(LevelStatus::Draft, $level->refresh()->status);
    }

    public function test_publication_rejects_an_answer_longer_than_twelve_tajik_graphemes(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createCompleteDraft();
        $longAnswer = str_repeat('Ӯ', 13);
        $level->translations()->where('locale', 'tj')->update([
            'answer_display' => $longAnswer,
            'answer_normalized' => mb_strtolower($longAnswer),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.levels.edit', $level))
            ->post(route('admin.levels.publish', $level))
            ->assertRedirect(route('admin.levels.edit', $level))
            ->assertSessionHasErrors('translations.tj.answer_display');

        $this->assertSame(LevelStatus::Draft, $level->refresh()->status);
    }

    public function test_publication_requires_duplicate_answer_letters_to_have_duplicate_tiles(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createCompleteDraft();
        $level->translations()->where('locale', 'ru')->update([
            'answer_display' => 'КОК',
            'answer_normalized' => 'кок',
            'letter_tiles' => json_encode(['К', 'О', 'Т', 'А', 'В', 'Г', 'Д', 'Е', 'Ж', 'З', 'И', 'Й'], JSON_THROW_ON_ERROR),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.levels.edit', $level))
            ->post(route('admin.levels.publish', $level))
            ->assertRedirect(route('admin.levels.edit', $level))
            ->assertSessionHasErrors('translations.ru.letter_tiles');

        $this->assertSame(LevelStatus::Draft, $level->refresh()->status);
    }

    public function test_inactive_category_and_missing_image_original_block_publication(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createCompleteDraft();
        $level->category->update(['is_active' => false]);
        Storage::disk('local')->delete($level->images()->where('position', 2)->value('storage_key'));

        $this->actingAs($admin)
            ->from(route('admin.levels.edit', $level))
            ->post(route('admin.levels.publish', $level))
            ->assertRedirect(route('admin.levels.edit', $level))
            ->assertSessionHasErrors(['category_id', 'images']);

        $this->assertSame(LevelStatus::Draft, $level->refresh()->status);
    }

    public function test_published_level_can_be_archived_without_deleting_player_history(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createCompleteDraft();
        $level->forceFill(['status' => LevelStatus::Published, 'published_at' => now()->subDay()])->save();
        $publishedAt = $level->published_at;
        $progress = PlayerLevelProgress::factory()->create(['level_id' => $level->id]);

        $this->actingAs($admin)
            ->post(route('admin.levels.archive', $level))
            ->assertRedirect(route('admin.levels.index'));

        $this->assertSame(LevelStatus::Archived, $level->refresh()->status);
        $this->assertTrue($publishedAt->equalTo($level->published_at));
        $this->assertDatabaseHas('player_level_progress', ['id' => $progress->id, 'level_id' => $level->id]);
        $this->assertDatabaseHas('level_images', ['level_id' => $level->id]);
        $this->assertDatabaseHas('level_translations', ['level_id' => $level->id, 'locale' => 'ru']);
    }

    public function test_archive_is_idempotent_and_non_admin_cannot_publish_or_archive(): void
    {
        $level = $this->createCompleteDraft();
        $user = User::query()->create([
            'name' => 'Regular user',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ]);

        $this->actingAs($user)->post(route('admin.levels.publish', $level))->assertForbidden();
        $this->actingAs($user)->post(route('admin.levels.archive', $level))->assertForbidden();

        $admin = $this->createAdmin();
        $this->actingAs($admin)->post(route('admin.levels.archive', $level))->assertRedirect(route('admin.levels.index'));
        $archivedAt = $level->refresh()->updated_at;

        $this->travel(5)->seconds();
        $this->actingAs($admin)->post(route('admin.levels.archive', $level))->assertRedirect(route('admin.levels.index'));

        $this->assertTrue($archivedAt->equalTo($level->refresh()->updated_at));
        $this->assertSame(LevelStatus::Archived, $level->status);
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

    private function createCompleteDraft(): Level
    {
        $category = Category::factory()->create(['is_active' => true]);
        foreach (config('game.supported_locales') as $locale) {
            CategoryTranslation::factory()->create(['category_id' => $category->id, 'locale' => $locale]);
        }

        $level = Level::factory()->for($category)->create();
        $translations = [
            'ru' => ['КОТ', ['К', 'О', 'Т', 'А', 'В', 'Г', 'Д', 'Е', 'Ж', 'З', 'И', 'Й']],
            'tj' => ['ГУРБА', ['Г', 'У', 'Р', 'Б', 'А', 'Т', 'М', 'С', 'Л', 'Н', 'Д', 'О']],
            'en' => ['CAT', ['C', 'A', 'T', 'O', 'R', 'S', 'E', 'N', 'I', 'G', 'H', 'L']],
        ];

        foreach ($translations as $locale => [$answer, $tiles]) {
            $level->translations()->create([
                'locale' => $locale,
                'answer_display' => $answer,
                'letter_tiles' => $tiles,
            ]);
        }

        foreach ([1, 2, 3, 4] as $position) {
            $key = "levels/{$level->id}/image-{$position}.jpg";
            Storage::disk('local')->put($key, 'image-content');
            $level->images()->create([
                'position' => $position,
                'storage_disk' => 'local',
                'storage_key' => $key,
                'mime_type' => 'image/jpeg',
                'width' => 640,
                'height' => 640,
            ]);
        }

        return $level;
    }
}
