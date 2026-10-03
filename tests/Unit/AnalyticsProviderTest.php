<?php

namespace Tests\Unit;

use App\HintType;
use App\Services\Analytics\AnalyticsEvent;
use App\Services\Analytics\AnalyticsProvider;
use App\Services\Analytics\LogAnalyticsProvider;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class AnalyticsProviderTest extends TestCase
{
    public function test_provider_records_only_the_catalogued_level_started_properties(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')
            ->once()
            ->with('level_started', ['level_id' => 17, 'locale' => 'tj']);
        Log::shouldReceive('channel')->once()->with('analytics')->andReturn($logger);

        $this->app->make(AnalyticsProvider::class)->record(AnalyticsEvent::LevelStarted, 17, 'tj');

        $this->assertInstanceOf(LogAnalyticsProvider::class, $this->app->make(AnalyticsProvider::class));
    }

    public function test_provider_records_hint_type_only_for_hint_events(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')
            ->once()
            ->with('hint_used', [
                'level_id' => 18,
                'locale' => 'ru',
                'hint_type' => HintType::RevealLetter->value,
            ]);
        Log::shouldReceive('channel')->once()->with('analytics')->andReturn($logger);

        $this->app->make(AnalyticsProvider::class)->record(
            AnalyticsEvent::HintUsed,
            18,
            'ru',
            HintType::RevealLetter,
        );
    }

    public function test_provider_rejects_unsupported_locales(): void
    {
        $provider = $this->app->make(AnalyticsProvider::class);

        $this->expectException(InvalidArgumentException::class);
        $provider->record(AnalyticsEvent::LevelStarted, 19, 'xx');
    }

    public function test_provider_requires_hint_type_for_hint_event(): void
    {
        $provider = $this->app->make(AnalyticsProvider::class);

        $this->expectException(InvalidArgumentException::class);
        $provider->record(AnalyticsEvent::HintUsed, 20, 'en');
    }

    public function test_provider_rejects_hint_type_for_non_hint_event(): void
    {
        $provider = $this->app->make(AnalyticsProvider::class);

        $this->expectException(InvalidArgumentException::class);
        $provider->record(AnalyticsEvent::LevelStarted, 21, 'en', HintType::RevealAnswer);
    }
}
