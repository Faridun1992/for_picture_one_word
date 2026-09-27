<?php

namespace App\Providers;

use App\Models\Player;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('game-api', function (Request $request): array {
            $player = $request->user();
            $playerKey = $player instanceof Player ? 'player:'.$player->getAuthIdentifier() : 'guest:'.$request->ip();

            return [
                Limit::perMinute(120)->by($playerKey),
                Limit::perMinute(600)->by('ip:'.$request->ip()),
            ];
        });

        Model::automaticallyEagerLoadRelationships();

        Paginator::useBootstrapFive();
    }
}
