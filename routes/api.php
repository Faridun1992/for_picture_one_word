<?php

use App\Http\Controllers\Api\V1\CategoryIndexController;
use App\Http\Controllers\Api\V1\GuestSessionController;
use App\Http\Controllers\Api\V1\LevelIndexController;
use App\Http\Controllers\Api\V1\LevelShowController;
use App\Http\Controllers\Api\V1\PlayerProgressController;
use App\Http\Controllers\Api\V1\PlayerSettingsController;
use App\Http\Controllers\Api\V1\PlayerShowController;
use App\Http\Controllers\Api\V1\RevokeGuestSessionController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', static fn (): JsonResponse => response()->json([
        'status' => 'ok',
    ]));

    Route::post('/auth/guest', GuestSessionController::class)
        ->middleware('throttle:10,1');

    Route::delete('/auth/session', RevokeGuestSessionController::class)
        ->middleware(['auth:sanctum', 'player']);

    Route::middleware(['auth:sanctum', 'player', 'throttle:game-api'])->group(function (): void {
        Route::get('/categories', CategoryIndexController::class);
        Route::get('/levels', LevelIndexController::class);
        Route::get('/levels/{level}', LevelShowController::class);
        Route::get('/progress', PlayerProgressController::class);
        Route::get('/me', PlayerShowController::class);
        Route::patch('/me/settings', PlayerSettingsController::class);
    });
});
