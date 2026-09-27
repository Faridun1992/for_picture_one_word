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
];
