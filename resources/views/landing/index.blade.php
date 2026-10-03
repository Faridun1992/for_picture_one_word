@php
    /** @var array<string, string> $localeLabels */
    $answerGraphemes = preg_split('//u', __('landing.mock.answer'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $revealedGraphemes = (int) __('landing.mock.revealed');
    $tiles = __('landing.mock.tiles');
    $hintPrices = [
        'reveal_letter' => $hints['reveal_letter'] ?? 0,
        'remove_wrong_letters' => $hints['remove_wrong_letters'] ?? 0,
        'reveal_answer' => $hints['reveal_answer'] ?? 0,
    ];
    $hintIcons = ['reveal_letter' => 'A', 'remove_wrong_letters' => '×', 'reveal_answer' => '?'];
    $hintItems = __('landing.hints.items');
    $rewardLabels = __('landing.hints.rewards');
    $rewardValues = [
        'balance' => $startingBalance,
        'correct' => $rewards['correct_answer'] ?? 0,
        'completion' => $rewards['level_completion'] ?? 0,
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>{{ __('landing.meta.title') }} — {{ __('landing.brand.name') }}</title>
    <meta name="description" content="{{ __('landing.meta.description') }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ __('landing.meta.title') }} — {{ __('landing.brand.name') }}">
    <meta property="og:description" content="{{ __('landing.meta.description') }}">
    <meta property="og:locale" content="{{ $locale }}">
    <meta name="theme-color" content="#f7f5ef">

    @foreach ($localeLabels as $code => $label)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ route('home.locale', $code) }}">
    @endforeach

    @vite(['resources/sass/landing.scss'])
</head>

<body class="lp">
<a class="lp-skip" href="#lp-main">{{ __('landing.nav.skip') }}</a>

<header class="lp-header" id="lp-top">
    <div class="lp-shell lp-header__bar">
        <a class="lp-logo" href="{{ route('home') }}">
            <span class="lp-logo__mark" aria-hidden="true">
                <span></span><span></span><span></span><span></span>
            </span>
            <span class="lp-logo__text">
                <strong>{{ __('landing.brand.name') }}</strong>
                <small>{{ __('landing.brand.tagline') }}</small>
            </span>
        </a>

        <nav class="lp-nav" aria-label="{{ __('landing.nav.menu') }}">
            <a href="#lp-how">{{ __('landing.nav.how') }}</a>
            <a href="#lp-features">{{ __('landing.nav.features') }}</a>
            <a href="#lp-categories">{{ __('landing.nav.categories') }}</a>
            <a href="#lp-hints">{{ __('landing.nav.hints') }}</a>
            <a href="#lp-faq">{{ __('landing.nav.faq') }}</a>
        </nav>

        <div class="lp-header__side">
            <div class="lp-switch" role="group" aria-label="{{ __('landing.footer.language') }}">
                @foreach ($localeLabels as $code => $label)
                    <a class="lp-switch__item @if ($code === $locale) is-active @endif"
                       href="{{ route('home.locale', $code) }}"
                       hreflang="{{ $code }}"
                       lang="{{ $code }}"
                       @if ($code === $locale) aria-current="true" @endif
                       title="{{ $label }}">{{ strtoupper($code) }}</a>
                @endforeach
            </div>

            <a class="lp-btn lp-btn--solid lp-header__play" href="#lp-download">{{ __('landing.nav.play') }}</a>
        </div>

        <details class="lp-burger">
            <summary aria-label="{{ __('landing.nav.menu') }}">
                <span></span><span></span><span></span>
            </summary>
            <div class="lp-burger__panel">
                <a href="#lp-how">{{ __('landing.nav.how') }}</a>
                <a href="#lp-features">{{ __('landing.nav.features') }}</a>
                <a href="#lp-categories">{{ __('landing.nav.categories') }}</a>
                <a href="#lp-hints">{{ __('landing.nav.hints') }}</a>
                <a href="#lp-faq">{{ __('landing.nav.faq') }}</a>
                <div class="lp-switch lp-switch--block" role="group" aria-label="{{ __('landing.footer.language') }}">
                    @foreach ($localeLabels as $code => $label)
                        <a class="lp-switch__item @if ($code === $locale) is-active @endif"
                           href="{{ route('home.locale', $code) }}"
                           hreflang="{{ $code }}"
                           @if ($code === $locale) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        </details>
    </div>
</header>

<main id="lp-main">
    <section class="lp-hero">
        <span class="lp-hero__glow lp-hero__glow--one" aria-hidden="true"></span>
        <span class="lp-hero__glow lp-hero__glow--two" aria-hidden="true"></span>

        <div class="lp-shell lp-hero__grid">
            <div class="lp-hero__copy">
                <p class="lp-eyebrow"><span>{{ __('landing.hero.eyebrow') }}</span></p>

                <h1 class="lp-hero__title">
                    {{ __('landing.hero.title') }}
                    <em>{{ __('landing.hero.title_accent') }}</em>
                </h1>

                <p class="lp-hero__lead">{{ __('landing.hero.lead') }}</p>

                <x-landing.store-buttons />

                <ul class="lp-badges">
                    <li>{{ __('landing.hero.badges.guest') }}</li>
                    <li>{{ __('landing.hero.badges.offline') }}</li>
                    <li>{{ __('landing.hero.badges.languages') }}</li>
                </ul>
            </div>

            <div class="lp-hero__preview">
                <div class="lp-device">
                    <span class="lp-device__notch" aria-hidden="true"></span>
                    <div class="lp-device__screen">
                        <div class="lp-game">
                            <div class="lp-game__top">
                                <div class="lp-game__level">
                                    <span>{{ __('landing.mock.category') }}</span>
                                    <strong>{{ __('landing.mock.level') }}</strong>
                                </div>
                                <div class="lp-game__wallet">
                                    <span class="lp-coin" aria-hidden="true">₡</span>
                                    <strong>{{ number_format($startingBalance) }}</strong>
                                </div>
                            </div>

                            <div class="lp-game__pictures">
                                @for ($position = 1; $position <= 4; $position++)
                                    <span class="lp-shot lp-shot--{{ $position }}"></span>
                                @endfor
                            </div>

                            <div class="lp-game__answer">
                                @foreach ($answerGraphemes as $index => $grapheme)
                                    <span class="lp-slot @if ($index < $revealedGraphemes) is-open @endif">
                                        {{ $index < $revealedGraphemes ? $grapheme : '' }}
                                    </span>
                                @endforeach
                            </div>

                            <div class="lp-game__tiles">
                                @foreach ($tiles as $tile)
                                    <span class="lp-tile">{{ $tile }}</span>
                                @endforeach
                            </div>

                            <div class="lp-game__hints">
                                @foreach ($hintPrices as $key => $price)
                                    <button class="lp-hint" type="button" disabled>
                                        <span class="lp-hint__icon">{{ $hintIcons[$key] }}</span>
                                        <span class="lp-hint__price">{{ $price }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <span class="lp-device__reward" aria-hidden="true">+{{ $rewardValues['completion'] }} {{ __('landing.mock.coins') }}</span>
                </div>

                <span class="lp-blob lp-blob--one" aria-hidden="true"></span>
                <span class="lp-blob lp-blob--two" aria-hidden="true"></span>
            </div>
        </div>
    </section>

    <section class="lp-stats">
        <div class="lp-shell lp-stats__grid">
            <div class="lp-stat">
                <strong>{{ number_format($publishedLevelsCount) }}</strong>
                <span>{{ __('landing.stats.puzzles') }}</span>
            </div>
            <div class="lp-stat">
                <strong>{{ number_format($categories->count()) }}</strong>
                <span>{{ __('landing.stats.categories') }}</span>
            </div>
            <div class="lp-stat">
                <strong>{{ number_format(count($localeLabels)) }}</strong>
                <span>{{ __('landing.stats.languages') }}</span>
            </div>
            <div class="lp-stat">
                <strong>{{ count($hintItems) }}</strong>
                <span>{{ __('landing.stats.hints') }}</span>
            </div>
        </div>
    </section>

    <section class="lp-section" id="lp-how">
        <div class="lp-shell">
            <header class="lp-head">
                <p class="lp-eyebrow"><span>{{ __('landing.nav.how') }}</span></p>
                <h2>{{ __('landing.how.title') }}</h2>
                <p>{{ __('landing.how.lead') }}</p>
            </header>

            <ol class="lp-steps">
                @foreach (__('landing.how.steps') as $index => $step)
                    <li class="lp-step">
                        <span class="lp-step__number">{{ $index + 1 }}</span>
                        <h3>{{ $step['title'] }}</h3>
                        <p>{{ $step['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="lp-section lp-section--tint" id="lp-features">
        <div class="lp-shell">
            <header class="lp-head">
                <p class="lp-eyebrow"><span>{{ __('landing.nav.features') }}</span></p>
                <h2>{{ __('landing.features.title') }}</h2>
                <p>{{ __('landing.features.lead') }}</p>
            </header>

            <div class="lp-cards">
                @foreach (__('landing.features.items') as $item)
                    <article class="lp-card">
                        <span class="lp-card__mark" aria-hidden="true"></span>
                        <h3>{{ $item['title'] }}</h3>
                        <p>{{ $item['text'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="lp-section" id="lp-categories">
        <div class="lp-shell">
            <header class="lp-head">
                <p class="lp-eyebrow"><span>{{ __('landing.nav.categories') }}</span></p>
                <h2>{{ __('landing.categories.title') }}</h2>
                <p>{{ __('landing.categories.lead') }}</p>
            </header>

            @if ($categories->isEmpty())
                <p class="lp-empty">{{ __('landing.categories.empty') }}</p>
            @else
                <ul class="lp-categories">
                    @foreach ($categories as $category)
                        <li class="lp-category">
                            <span class="lp-category__thumb" aria-hidden="true"></span>
                            <strong>{{ $category->translations->first()?->name ?? $category->slug }}</strong>
                            <small>{{ $category->published_levels_count }} {{ __('landing.categories.puzzles') }}</small>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <section class="lp-section lp-section--dark" id="lp-hints">
        <div class="lp-shell">
            <header class="lp-head lp-head--light">
                <p class="lp-eyebrow"><span>{{ __('landing.nav.hints') }}</span></p>
                <h2>{{ __('landing.hints.title') }}</h2>
                <p>{{ __('landing.hints.lead') }}</p>
            </header>

            <div class="lp-hints-layout">
                <ul class="lp-hint-list">
                    @foreach ($hintItems as $item)
                        <li class="lp-hint-card">
                            <span class="lp-hint-card__icon" aria-hidden="true">{{ $hintIcons[$item['key']] }}</span>
                            <div>
                                <h3>{{ $item['title'] }}</h3>
                                <p>{{ $item['text'] }}</p>
                            </div>
                            <span class="lp-hint-card__price">
                                {{ $hintPrices[$item['key']] ?? 0 }}
                                <small>{{ __('landing.mock.coins') }}</small>
                            </span>
                        </li>
                    @endforeach
                </ul>

                <div class="lp-rewards">
                    <h3>{{ __('landing.hints.rewards_title') }}</h3>
                    <ul>
                        @foreach ($rewardValues as $key => $value)
                            <li>
                                <span>{{ $rewardLabels[$key] }}</span>
                                <strong>+{{ number_format($value) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                    <p class="lp-rewards__note">{{ __('landing.hints.rewards_note') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="lp-section" id="lp-faq">
        <div class="lp-shell lp-shell--narrow">
            <header class="lp-head">
                <p class="lp-eyebrow"><span>{{ __('landing.nav.faq') }}</span></p>
                <h2>{{ __('landing.faq.title') }}</h2>
            </header>

            <div class="lp-faq">
                @foreach (__('landing.faq.items') as $item)
                    <details class="lp-faq__item">
                        <summary>{{ $item['question'] }}</summary>
                        <p>{{ $item['answer'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="lp-cta" id="lp-download">
        <div class="lp-shell lp-cta__inner">
            <h2>{{ __('landing.cta.title') }}</h2>
            <p>{{ __('landing.cta.lead') }}</p>
            <x-landing.store-buttons variant="cta" />
        </div>
    </section>
</main>

<footer class="lp-footer">
    <div class="lp-shell lp-footer__grid">
        <div class="lp-footer__brand">
            <a class="lp-logo" href="#lp-top">
                <span class="lp-logo__mark" aria-hidden="true">
                    <span></span><span></span><span></span><span></span>
                </span>
                <span class="lp-logo__text">
                    <strong>{{ __('landing.brand.name') }}</strong>
                    <small>{{ __('landing.brand.tagline') }}</small>
                </span>
            </a>
            <p>{{ __('landing.footer.tagline') }}</p>
        </div>

        <nav class="lp-footer__links" aria-label="{{ __('landing.nav.menu') }}">
            <a href="#lp-how">{{ __('landing.nav.how') }}</a>
            <a href="#lp-features">{{ __('landing.nav.features') }}</a>
            <a href="#lp-categories">{{ __('landing.nav.categories') }}</a>
            <a href="#lp-hints">{{ __('landing.nav.hints') }}</a>
            <a href="#lp-faq">{{ __('landing.nav.faq') }}</a>
            @auth
                @if (Auth::user()->hasRole(['Admin', 'Super Admin']))
                    <a href="{{ route('admin.dashboard') }}">{{ __('landing.footer.admin') }}</a>
                @endif
            @endauth
            @if (! empty($distribution['support_email']))
                <a href="mailto:{{ $distribution['support_email'] }}">{{ __('landing.footer.support') }}</a>
            @endif
        </nav>

        <div class="lp-footer__locale">
            <span>{{ __('landing.footer.language') }}</span>
            <div class="lp-switch" role="group" aria-label="{{ __('landing.footer.language') }}">
                @foreach ($localeLabels as $code => $label)
                    <a class="lp-switch__item @if ($code === $locale) is-active @endif"
                       href="{{ route('home.locale', $code) }}"
                       hreflang="{{ $code }}"
                       @if ($code === $locale) aria-current="true" @endif>{{ strtoupper($code) }}</a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="lp-shell lp-footer__bottom">
        <small>{{ __('landing.footer.rights') }}</small>
        <small>{{ date('Y') }} · {{ __('landing.brand.name') }}</small>
    </div>
</footer>
</body>
</html>