@props([
    'variant' => 'hero',
])

@php
    $icons = [
        'apple' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" focusable="false"><path d="M16.4 12.7c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.1-2.8.9-3.5.9-.7 0-1.8-.9-3-.8-1.5 0-2.9.9-3.7 2.3-1.6 2.7-.4 6.7 1.1 8.9.8 1.1 1.6 2.3 2.8 2.2 1.1 0 1.5-.7 2.9-.7 1.3 0 1.7.7 2.9.7 1.2 0 2-1.1 2.7-2.2.9-1.3 1.2-2.5 1.2-2.6-.1 0-2.9-1.1-3-4.4Zm-2.9-8.4c.6-.8 1-1.8.9-2.9-.9 0-2 .6-2.6 1.4-.6.7-1.1 1.8-1 2.8 1 .1 2-.5 2.7-1.3Z"/></svg>',
        'play' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" focusable="false"><path d="M3.6 2.3c-.3.3-.5.8-.5 1.4v16.6c0 .6.2 1.1.5 1.4l.1.1 9.3-9.3v-.2L3.7 2.3h-.1Zm11 12.1-2.3-2.3v-.2l2.3-2.3.1.1 2.8 1.6c.8.4.8 1.2 0 1.6l-2.8 1.5h-.1Zm-1-3.9L6.2 2.2l9.5 5.5-2.1 2.3v.5Zm-7 7.8V5.8l7.4 7.4-7.4 8.1Z"/></svg>',
    ];

    $stores = [
        [
            'url' => config('game.distribution.app_store_url'),
            'label' => __('landing.hero.primary'),
            'note' => __('landing.hero.coming_soon'),
            'icon' => $icons['apple'],
        ],
        [
            'url' => config('game.distribution.google_play_url'),
            'label' => __('landing.hero.secondary'),
            'note' => __('landing.hero.coming_soon'),
            'icon' => $icons['play'],
        ],
    ];
@endphp

<div class="lp-stores lp-stores--{{ $variant }}">
    @foreach ($stores as $store)
        <a @class(['lp-store', 'is-disabled' => blank($store['url'])])
           @if (filled($store['url']))
               href="{{ $store['url'] }}" rel="noopener"
           @else
               aria-disabled="true"
           @endif>
            <span class="lp-store__icon" aria-hidden="true">{!! $store['icon'] !!}</span>
            <span class="lp-store__text">
                {{ $store['label'] }}
                @if (blank($store['url']))
                    <small>{{ $store['note'] }}</small>
                @endif
            </span>
        </a>
    @endforeach
</div>