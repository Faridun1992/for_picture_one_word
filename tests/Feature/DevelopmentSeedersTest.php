<?php

namespace Tests\Feature;

use Database\Seeders\CategorySeeder;
use Database\Seeders\LevelSeeder;
use Database\Seeders\PlayerLevelProgressSeeder;
use Database\Seeders\PlayerSeeder;
use Database\Seeders\PlayerWalletSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevelopmentSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_and_guest_seeders_are_repeatable_in_testing(): void
    {
        $seeders = [
            CategorySeeder::class,
            LevelSeeder::class,
            PlayerSeeder::class,
            PlayerWalletSeeder::class,
            PlayerLevelProgressSeeder::class,
        ];

        $this->seed($seeders);
        $this->seed($seeders);

        $this->assertDatabaseCount('categories', 3);
        $this->assertDatabaseCount('category_translations', 9);
        $this->assertDatabaseCount('levels', 3);
        $this->assertDatabaseCount('level_translations', 3);
        $this->assertDatabaseCount('players', 1);
        $this->assertDatabaseCount('player_wallets', 1);
        $this->assertDatabaseCount('player_level_progress', 1);
        $this->assertDatabaseHas('players', ['locale' => 'ru']);
        $this->assertDatabaseHas('level_translations', ['answer_display' => 'ГУРБА']);
    }
}
