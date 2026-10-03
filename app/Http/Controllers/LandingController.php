<?php

namespace App\Http\Controllers;

use App\LevelStatus;
use App\Models\Category;
use App\Models\Level;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class LandingController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const LOCALE_LABELS = [
        'ru' => 'Русский',
        'tj' => 'Тоҷикӣ',
        'en' => 'English',
    ];

    public function index(?string $locale = null): View
    {
        $defaultLocale = $this->resolveLocale(null);
        $locale = $this->resolveLocale($locale);

        app()->setLocale($locale);

        return view('landing.index', [
            'locale' => $locale,
            'canonicalUrl' => $locale === $defaultLocale
                ? route('home')
                : route('home.locale', $locale),
            'localeLabels' => self::LOCALE_LABELS,
            'categories' => $this->categories($locale),
            'publishedLevelsCount' => $this->publishedLevelsCount(),
            'hints' => config('game.hints'),
            'rewards' => config('game.rewards'),
            'startingBalance' => config('game.wallet.starting_balance'),
            'distribution' => config('game.distribution'),
        ]);
    }

    private function resolveLocale(?string $locale): string
    {
        /** @var list<string> $supported */
        $supported = config('game.supported_locales');

        if (is_string($locale) && in_array($locale, $supported, true)) {
            return $locale;
        }

        $preferred = config('app.locale');

        return is_string($preferred) && in_array($preferred, $supported, true) ? $preferred : 'ru';
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories(string $locale): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->whereHas('translations', static fn (Builder $query) => $query->where('locale', $locale))
            ->whereHas(
                'levels',
                static fn (Builder $query) => $query->where('status', LevelStatus::Published->value),
                '>',
                0,
            )
            ->with(['translations' => static fn (Relation $query) => $query->where('locale', $locale)])
            ->withCount([
                'levels as published_levels_count' => static fn (Builder $query) => $query
                    ->where('status', LevelStatus::Published->value),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function publishedLevelsCount(): int
    {
        return Level::query()
            ->where('status', LevelStatus::Published->value)
            ->whereHas('images', static fn (Builder $query) => $query->whereBetween('position', [1, 4]), '=', 4)
            ->count();
    }
}
