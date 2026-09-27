<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    */

    'path' => env('HORIZON_PATH', 'admin/horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    */

    'waits' => [
        'redis:payments' => 30,  // алерт если платёж ждёт больше 30 сек
        'redis:scout' => 120,
        'redis:default' => 60,
        'redis:statistics' => 300,
        'redis:images' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    */

    'silenced' => [],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB) — воркер перезапускается при превышении
    |--------------------------------------------------------------------------
    */

    'memory_limit' => 512,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Три очереди с убывающим приоритетом:
    |   1. payments  — платёжные операции, 2 воркера, nice=0
    |   2. scout     — обновление индексов Manticore, 1 воркер, nice=5
    |   3. default   — всё остальное + low, авто-баланс, nice=10
    |   4. statistics — обновление статистики ClickHouse, 1 воркер, nice=15
    |   5. images    — обработка изображений (Imagick, тяжёлая RAM/CPU),
    |ровно 1 воркер всегда, nice=19 (самый низкий приоритет),
    |maxJobs=1 — перезапуск процесса после каждого задания,
    |чтобы освобождать память Imagick/GD.

    */

    'defaults' => [
        'supervisor-payments' => [
            'connection' => 'redis',
            'queue' => ['payments'],
            'balance' => 'simple',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 2,
            'minProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 90,
            'nice' => 0,
        ],

        'supervisor-scout' => [
            'connection' => 'redis',
            'queue' => ['scout'],
            'balance' => 'simple',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'minProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 5,
        ],

        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default', 'low'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 2,
            'minProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 10,
        ],

        'supervisor-statistics' => [
            'connection' => 'redis',
            'queue' => ['statistics'],
            'balance' => 'simple',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'minProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 300,
            'nice' => 15,
        ],

        'supervisor-images' => [
            'connection' => 'redis',
            'queue' => ['images'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'minProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 1,
            'memory' => 1024,
            'timeout' => 75,
            'nice' => 19,
        ],

    ],

    'environments' => [
        'production' => [
            'supervisor-payments' => [
                'minProcesses' => 2,
                'maxProcesses' => 2,
            ],
            'supervisor-scout' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-default' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
            'supervisor-statistics' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-images' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
        ],

        'staging' => [
            'supervisor-payments' => [
                'minProcesses' => 2,
                'maxProcesses' => 2,
            ],
            'supervisor-scout' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-default' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
            'supervisor-statistics' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-images' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
        ],

        'local' => [
            'supervisor-payments' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-scout' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-default' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-statistics' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-images' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
        ],
    ],

];
