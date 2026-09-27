<?php

namespace Database\Seeders;

use App\Models\Player;
use Illuminate\Database\Seeder;

class PlayerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || Player::query()->exists()) {
            return;
        }

        Player::factory()->create(['locale' => 'ru']);
    }
}
