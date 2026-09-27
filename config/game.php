<?php

return [
    'supported_locales' => ['ru', 'tj', 'en'],

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
        'reveal_letter' => 30,
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
