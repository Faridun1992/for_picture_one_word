<?php

use App\Http\Controllers\Admin\LevelController;
use App\Http\Controllers\Admin\LevelStatisticsController;
use App\Http\Controllers\HomeController;

Auth::routes(['register' => false]);

Route::get('/home', [HomeController::class, 'index'])
    ->middleware(['auth', 'role:Admin|Super Admin'])
    ->name('home');

Route::get('/admin/statistics/levels', LevelStatisticsController::class)
    ->middleware(['auth', 'role:Admin|Super Admin'])
    ->name('admin.statistics.levels');

Route::middleware(['auth', 'role:Admin|Super Admin'])
    ->prefix('admin/levels')
    ->name('admin.levels.')
    ->controller(LevelController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{level}/edit', 'edit')->name('edit');
        Route::put('/{level}', 'update')->name('update');
        Route::post('/{level}/images', 'storeImages')->name('images.store');
        Route::post('/{level}/publish', 'publish')->name('publish');
        Route::post('/{level}/archive', 'archive')->name('archive');
        Route::post('/{level}/move', 'move')->name('move');
    });
