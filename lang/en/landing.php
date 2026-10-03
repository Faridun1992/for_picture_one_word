<?php

return [
    'meta' => [
        'title' => 'Four Pictures One Word — Four pictures, one word puzzle game',
        'description' => 'Four Pictures One Word is a mobile picture puzzle: guess the word from four images, spend Coins on hints and play without registration in English, Russian and Tajik.',
    ],

    'brand' => [
        'name' => 'Four Pictures',
        'tagline' => 'one word',
    ],

    'nav' => [
        'how' => 'How to play',
        'features' => 'Features',
        'categories' => 'Categories',
        'hints' => 'Hints',
        'faq' => 'FAQ',
        'play' => 'Play',
        'menu' => 'Menu',
        'skip' => 'Skip to content',
    ],

    'hero' => [
        'eyebrow' => '4 pictures · 1 word',
        'title' => 'Guess the word',
        'title_accent' => 'from four pictures',
        'lead' => 'Collect the letters, earn Coins and unlock new categories. Play without registration, with hints and even offline.',
        'primary' => 'Download on the App Store',
        'secondary' => 'Get it on Google Play',
        'coming_soon' => 'Coming soon to stores',
        'badges' => [
            'guest' => 'No registration',
            'offline' => 'Works offline',
            'languages' => 'English, Russian, Tajik',
        ],
    ],

    'mock' => [
        'level' => 'Level 1',
        'category' => 'Animals',
        'answer' => 'CAT',
        'revealed' => 2,
        'tiles' => ['C', 'A', 'T', 'E', 'N', 'S', 'R', 'O', 'L', 'D', 'K', 'M'],
        'hint_reveal' => 'Reveal a letter',
        'hint_remove' => 'Remove wrong tiles',
        'hint_answer' => 'Show the answer',
        'coins' => 'Coins',
    ],

    'stats' => [
        'puzzles' => 'published puzzles',
        'categories' => 'categories in the game',
        'languages' => 'interface languages',
        'hints' => 'hints with a clear price',
    ],

    'how' => [
        'title' => 'How to play',
        'lead' => 'Three steps from four pictures to a finished word.',
        'steps' => [
            [
                'title' => 'Look at the pictures',
                'text' => 'The four images next to each other show which word you have to build.',
            ],
            [
                'title' => 'Collect the letters',
                'text' => 'The board holds exactly 12 tiles: the letters you need and a few wrong ones. Tap a letter and it lands in an answer slot.',
            ],
            [
                'title' => 'Earn Coins',
                'text' => 'A correct answer pays a reward, and a finished puzzle pays even more.',
            ],
        ],
    ],

    'features' => [
        'title' => 'Features',
        'lead' => 'Everything that helps you finish a puzzle instead of giving up.',
        'items' => [
            [
                'title' => 'Hints paid for with Coins',
                'text' => 'Reveal one letter, remove two wrong tiles or show the full answer — the price is printed on the button.',
            ],
            [
                'title' => 'Honest balance',
                'text' => 'Coins are granted by the server. Resending the same answer never pays twice.',
            ],
            [
                'title' => 'Categories and levels',
                'text' => 'Animals, food, nature and new collections. Levels are unlocked strictly in order.',
            ],
            [
                'title' => 'Offline play',
                'text' => 'Profile, progress and the unfinished puzzle are stored on your phone.',
            ],
            [
                'title' => 'Three interface languages',
                'text' => 'English, Russian and Tajik can be switched in settings without reinstalling.',
            ],
            [
                'title' => 'No registration',
                'text' => 'An account is not needed: a guest session is created on the first launch.',
            ],
        ],
    ],

    'categories' => [
        'title' => 'Categories',
        'lead' => 'Collections are updated by the game editors, so the list keeps growing.',
        'puzzles' => 'puzzles',
        'empty' => 'Categories will appear soon.',
    ],

    'hints' => [
        'title' => 'Hints and Coins',
        'lead' => 'Hint prices and rewards are defined by the server, so they stay the same everywhere.',
        'items' => [
            [
                'key' => 'reveal_letter',
                'title' => 'Reveal a letter',
                'text' => 'The server opens the first closed letter from the left. You cannot pick the position.',
            ],
            [
                'key' => 'remove_wrong_letters',
                'title' => 'Remove wrong tiles',
                'text' => 'Deletes two incorrect tiles and leaves more correct letters on the board.',
            ],
            [
                'key' => 'reveal_answer',
                'title' => 'Show the answer',
                'text' => 'Opens the word and finishes the puzzle with a reward.',
            ],
        ],
        'rewards_title' => 'Where Coins come from',
        'rewards_note' => 'Coins are granted by the server: resending the same answer never pays twice.',
        'rewards' => [
            'balance' => 'Starting balance',
            'correct' => 'Correct answer',
            'completion' => 'Completed puzzle',
        ],
    ],

    'faq' => [
        'title' => 'Questions and answers',
        'items' => [
            [
                'question' => 'Do I need an account?',
                'answer' => 'No. The game starts with a guest session: no login, password or email required.',
            ],
            [
                'question' => 'How do I earn Coins?',
                'answer' => 'A new player receives a starting balance, a correct answer pays a small reward and a completed puzzle pays more.',
            ],
            [
                'question' => 'What if I do not have enough Coins?',
                'answer' => 'The hint is not charged and the letters stay untouched — the app shows how many Coins are needed and how many you have.',
            ],
            [
                'question' => 'Does the game work offline?',
                'answer' => 'Yes. Completed levels and the last puzzle are stored on the phone, and the answer is sent once the network is back.',
            ],
            [
                'question' => 'Which languages are available?',
                'answer' => 'The interface is fully translated into English, Russian and Tajik.',
            ],
        ],
    ],

    'cta' => [
        'title' => 'Download the game and start the first puzzle',
        'lead' => 'Free to install, no registration required.',
    ],

    'footer' => [
        'tagline' => 'The mobile game “Four Pictures One Word”.',
        'language' => 'Language',
        'admin' => 'Admin panel',
        'rights' => 'All rights reserved.',
        'support' => 'Support',
    ],
];
