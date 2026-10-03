<?php

return [
    'supported_locales' => ['ru', 'tj', 'en'],
    'max_answer_graphemes' => 12,

    // Store links rendered on the public landing page. Empty values render a
    // disabled "coming soon" button instead of a broken link.
    'distribution' => [
        'app_store_url' => env('APP_STORE_URL', ''),
        'google_play_url' => env('GOOGLE_PLAY_URL', ''),
        'support_email' => env('SUPPORT_EMAIL', ''),
    ],

    // Server-owned MVP economy. Client requests never supply prices or rewards.
    'wallet' => [
        'currency' => 'coins',
        'starting_balance' => 300,
    ],

    'rewards' => [
        'correct_answer' => 10,
        'level_completion' => 50,
        'daily_challenge' => 100,
        'streak' => [5 => 20, 10 => 50],
        'milestones' => [50 => 150, 100 => 300, 500 => 300, 1000 => 500],
        'rewarded_ad' => 50,
    ],

    'hints' => [
        'reveal_letter' => 60,
        'remove_wrong_letters' => 40,
        'remove_wrong_letters_count' => 2,
        'reveal_answer' => 100,
    ],

    'ads' => [
        'rewarded_daily_limit' => 5,
        'interstitial_completed_puzzles_min' => 4,
        'interstitial_completed_puzzles_max' => 6,
        'interstitial_cooldown_seconds' => 90,
    ],

    'premium' => [
        'disable_interstitial' => true,
        'unlimited_free_hints' => false,
    ],

    'level_images' => [
        'disk' => 'local',
        'directory' => 'levels',
        'max_file_kilobytes' => 5120,
        'min_width' => 320,
        'min_height' => 320,
        'max_width' => 4096,
        'max_height' => 4096,
        'variants' => [
            'thumbnail' => [
                'max_width' => 320,
                'max_height' => 320,
                'quality' => 80,
            ],
            'display' => [
                'max_width' => 1280,
                'max_height' => 1280,
                'quality' => 85,
            ],
        ],
    ],
];
