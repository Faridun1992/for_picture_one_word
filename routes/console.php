<?php

use App\Console\Commands\PurgeExpiredIdempotencyRequests;
use Illuminate\Support\Facades\Schedule;


Schedule::command(PurgeExpiredIdempotencyRequests::class)->dailyAt('03:30')->withoutOverlapping();
