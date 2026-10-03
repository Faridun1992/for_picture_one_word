<?php

namespace App\Services\Analytics;

use App\HintType;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class LogAnalyticsProvider implements AnalyticsProvider
{
    public function record(
        AnalyticsEvent $event,
        int $levelId,
        string $locale,
        ?HintType $hintType = null,
    ): void {
        if (! in_array($locale, config('game.supported_locales', []), true)) {
            throw new InvalidArgumentException('Unsupported analytics locale.');
        }

        if (($event === AnalyticsEvent::HintUsed) !== ($hintType !== null)) {
            throw new InvalidArgumentException('Hint type must only be provided for hint events.');
        }

        Log::channel('analytics')->info($event->value, array_filter([
            'level_id' => $levelId,
            'locale' => $locale,
            'hint_type' => $hintType?->value,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
