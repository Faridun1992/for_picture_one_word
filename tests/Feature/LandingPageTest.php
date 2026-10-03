<?php

namespace Tests\Feature;

use App\LevelStatus;
use App\Models\Category;
use App\Models\Level;
use App\Models\LevelImage;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_is_public_and_uses_the_default_locale(): void
    {
        config(['app.locale' => 'ru']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<html lang="ru">', false)
            ->assertSee(trans('landing.hero.title'))
            ->assertSee(trans('landing.hero.title_accent'))
            ->assertSee(trans('landing.hints.rewards_note'));
    }

    public function test_unsupported_default_locale_falls_back_to_russian(): void
    {
        config(['app.locale' => 'de']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<html lang="ru">', false);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function localeProvider(): array
    {
        return [
            'russian' => ['ru', 'Отгадай слово'],
            'tajik' => ['tj', 'Калимаро шинохт'],
            'english' => ['en', 'Guess the word'],
        ];
    }

    #[DataProvider('localeProvider')]
    public function test_landing_page_renders_every_supported_locale(string $locale, string $heroTitle): void
    {
        $response = $this->get(route('home.locale', $locale))
            ->assertOk()
            ->assertSee('<html lang="'.$locale.'">', false)
            ->assertSee($heroTitle);

        $response->assertSee(route('home.locale', 'ru'), false);
        $response->assertSee(route('home.locale', 'tj'), false);
        $response->assertSee(route('home.locale', 'en'), false);
    }

    public function test_default_locale_page_is_canonical_at_the_root_url(): void
    {
        config(['app.locale' => 'ru']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false);
    }

    public function test_non_default_locale_page_points_to_its_own_canonical_url(): void
    {
        config(['app.locale' => 'ru']);

        $this->get(route('home.locale', 'en'))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('home.locale', 'en').'">', false);
    }

    public function test_landing_page_title_does_not_repeat_the_brand_name(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<title>'.trans('landing.meta.title').'</title>', false)
            ->assertDontSee(trans('landing.meta.title').' — '.trans('landing.brand.name'), false);
    }

    public function test_landing_page_uses_localized_categories_and_published_puzzle_count(): void
    {
        $this->seed(CategorySeeder::class);
        $this->publishLevelWithImages('animals', 2);

        $this->get(route('home.locale', 'ru'))
            ->assertOk()
            ->assertSee('Животные')
            ->assertSee('2 '.trans('landing.categories.puzzles'));

        $this->get(route('home.locale', 'en'))
            ->assertOk()
            ->assertSee('Animals')
            ->assertDontSee('Животные');
    }

    public function test_landing_page_skips_categories_without_published_levels(): void
    {
        $this->seed(CategorySeeder::class);

        $this->get(route('home.locale', 'ru'))
            ->assertOk()
            ->assertSee(trans('landing.categories.empty'));
    }

    public function test_store_buttons_are_disabled_until_distribution_urls_are_configured(): void
    {
        config([
            'game.distribution.app_store_url' => null,
            'game.distribution.google_play_url' => null,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(trans('landing.hero.coming_soon'))
            ->assertSee('lp-store is-disabled', false);
    }

    public function test_store_buttons_link_to_configured_distribution_urls(): void
    {
        config([
            'game.distribution.app_store_url' => 'https://apps.apple.com/app/id1',
            'game.distribution.google_play_url' => 'https://play.google.com/store/apps/details?id=com.example',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('https://apps.apple.com/app/id1', false)
            ->assertSee('https://play.google.com/store/apps/details?id=com.example', false)
            ->assertDontSee('lp-store is-disabled', false);
    }

    public function test_unsupported_locale_segment_is_not_a_landing_page(): void
    {
        $this->get('/de')->assertNotFound();
    }

    public function test_admin_link_is_only_rendered_for_admin_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $this->get(route('home'))->assertOk()->assertDontSee(route('admin.dashboard'), false);

        $admin = User::query()->create([
            'name' => 'Landing admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ]);
        $admin->assignRole('Admin');

        $this->actingAs($admin)->get(route('home'))->assertOk()->assertSee(route('admin.dashboard'), false);
    }

    private function publishLevelWithImages(string $categorySlug, int $levels): void
    {
        $category = Category::query()->where('slug', $categorySlug)->firstOrFail();

        foreach (range(1, $levels) as $sequence) {
            $level = Level::query()->create([
                'category_id' => $category->id,
                'sequence' => $sequence,
                'difficulty' => 1,
                'status' => LevelStatus::Published,
                'published_at' => now(),
            ]);

            foreach (range(1, 4) as $position) {
                LevelImage::query()->create([
                    'level_id' => $level->id,
                    'position' => $position,
                    'storage_disk' => 'local',
                    'storage_key' => "levels/{$level->id}-{$position}.jpg",
                    'mime_type' => 'image/jpeg',
                    'width' => 640,
                    'height' => 640,
                ]);
            }
        }
    }
}
