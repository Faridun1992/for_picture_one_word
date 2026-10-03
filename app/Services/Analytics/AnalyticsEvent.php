<?php

namespace App\Services\Analytics;

enum AnalyticsEvent: string
{
    case LevelStarted = 'level_started';
    case LevelCompleted = 'level_completed';
    case HintUsed = 'hint_used';
}
