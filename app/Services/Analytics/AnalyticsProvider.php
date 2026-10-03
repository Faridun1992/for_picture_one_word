<?php

namespace App\Services\Analytics;

use App\HintType;

interface AnalyticsProvider
{
    public function record(
        AnalyticsEvent $event,
        int $levelId,
        string $locale,
        ?HintType $hintType = null,
    ): void;
}
