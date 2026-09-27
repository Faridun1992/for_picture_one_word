<?php

namespace Tests\Feature;

use App\LevelStatus;
use App\Models\Category;
use App\Models\Level;
use App\Models\LevelImage;
use App\Models\LevelTranslation;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_return_localized_names_and_only_published_available_level_counts(): void
    {
        $category = $this->createTranslatedCategory('animals', 'Ҳайвонот');
        $this->createPlayableLevel($category, 1);
        Level::factory()->for($category)->create(['sequence' => 2, 'status' => LevelStatus::Draft]);
        $response = $this->withToken($this->createPlayerToken('tj'))
            ->getJson('/api/v1/categories?locale=tj');

        $response->assertOk()
            ->assertJsonPath('data.0.slug', 'animals')
            ->assertJsonPath('data.0.name', 'Ҳайвонот')
            ->assertJsonPath('data.0.published_levels_count', 1);
    }

    public function test_category_endpoint_requires_a_player_token(): void
    {
        $this->getJson('/api/v1/categories')->assertUnauthorized();
    }

    public function test_level_catalog_hides_drafts_and_incomplete_levels_without_returning_answers(): void
    {
        $category = $this->createTranslatedCategory('animals', 'Ҳайвонот');
        $publishedLevel = $this->createPlayableLevel($category, 1);
        Level::factory()->for($category)->create(['sequence' => 2, 'status' => LevelStatus::Draft]);
        Level::factory()->for($category)->create(['sequence' => 3, 'status' => LevelStatus::Published]);
        $token = $this->createPlayerToken('tj');

        $response = $this->withToken($token)->getJson('/api/v1/levels?locale=tj');

        $response->assertOk()->assertJsonCount(1, 'data');
        $level = $response->json('data.0');
        $this->assertSame($publishedLevel->id, $level['id']);
        $this->assertSame(5, $level['answer_length']);
        $this->assertSame('Ҳайвонот', $level['category']['name']);
        $this->assertSame('tj', $level['locale']);
        $this->assertSame([1, 2, 3, 4], array_column($level['images'], 'position'));
        $this->assertArrayNotHasKey('answer_display', $level);
        $this->assertArrayNotHasKey('answer_normalized', $level);
        $this->assertArrayHasKey('content_version', $level);
    }

    public function test_level_details_return_four_ordered_images_and_hide_unpublished_levels(): void
    {
        $category = $this->createTranslatedCategory('animals', 'Ҳайвонот');
        $level = $this->createPlayableLevel($category, 1);
        $token = $this->createPlayerToken('tj');

        $this->withToken($token)->getJson("/api/v1/levels/{$level->id}?locale=tj")
            ->assertOk()
            ->assertJsonPath('data.id', $level->id)
            ->assertJsonPath('data.images.0.position', 1)
            ->assertJsonPath('data.images.3.position', 4)
            ->assertJsonMissingPath('data.answer_display')
            ->assertJsonMissingPath('data.answer_normalized');

        $draftLevel = Level::factory()->for($category)->create(['status' => LevelStatus::Draft]);
        $this->withToken($token)->getJson("/api/v1/levels/{$draftLevel->id}?locale=tj")
            ->assertNotFound();
    }

    public function test_private_local_images_receive_a_signed_temporary_url(): void
    {
        $this->freezeTime();
        $category = $this->createTranslatedCategory('animals', 'Ҳайвонот');
        $level = $this->createPlayableLevel($category, 1);
        $level->images()->update(['storage_disk' => 'local']);
        $token = $this->createPlayerToken('tj');

        $url = $this->withToken($token)->getJson("/api/v1/levels/{$level->id}?locale=tj")
            ->assertOk()
            ->json('data.images.0.url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertArrayHasKey('signature', $query);
        $this->assertSame(now()->addHours(24)->timestamp, (int) $query['expires']);
    }

    public function test_level_catalog_supports_cursor_pagination_and_category_filtering(): void
    {
        $category = $this->createTranslatedCategory('animals', 'Ҳайвонот');
        $firstLevel = $this->createPlayableLevel($category, 1);
        $secondLevel = $this->createPlayableLevel($category, 2);
        $token = $this->createPlayerToken('tj');

        $firstPage = $this->withToken($token)->getJson('/api/v1/levels?locale=tj&limit=1');
        $firstPage->assertOk()
            ->assertJsonPath('data.0.id', $firstLevel->id);
        $cursor = $firstPage->json('meta.next_cursor');

        $this->withToken($token)
            ->getJson('/api/v1/levels?locale=tj&limit=1&category_id='.$category->id.'&cursor='.urlencode($cursor))
            ->assertOk()
            ->assertJsonPath('data.0.id', $secondLevel->id);
    }

    public function test_catalog_rejects_unknown_locale_bad_limit_and_unknown_category(): void
    {
        $token = $this->createPlayerToken();

        $this->withToken($token)->getJson('/api/v1/categories?locale=fa')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');

        $this->withToken($token)->getJson('/api/v1/levels?limit=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');

        $this->withToken($token)->getJson('/api/v1/levels?category_id=999999')
            ->assertNotFound();
    }

    private function createTranslatedCategory(string $slug, string $name): Category
    {
        $category = Category::factory()->create(['slug' => $slug]);
        $category->translations()->create(['locale' => 'tj', 'name' => $name]);

        return $category;
    }

    private function createPlayableLevel(Category $category, int $sequence): Level
    {
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
            LevelImage::factory()->for($level)->create([
                'position' => $position,
                'storage_disk' => 'public',
                'storage_key' => "levels/{$level->id}/{$position}.jpg",
            ]);
        }

        return $level;
    }

    private function createPlayerToken(string $locale = 'tj'): string
    {
        return Player::factory()->create(['locale' => $locale])
            ->createToken('catalog-test')
            ->plainTextToken;
    }
}
