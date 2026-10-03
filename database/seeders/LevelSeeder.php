<?php

namespace Database\Seeders;

use App\LevelStatus;
use App\Models\Category;
use App\Models\Level;
use App\Models\LevelTranslation;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (Category::query()->orderBy('id')->get() as $category) {
            $level = Level::query()->firstOrCreate(
                ['category_id' => $category->id, 'sequence' => 1],
                ['difficulty' => 1, 'status' => LevelStatus::Draft],
            );

            if ($category->slug !== 'animals') {
                continue;
            }

            $sampleTranslations = [
                'ru' => ['КОШКА', ['К', 'О', 'Ш', 'К', 'А', 'Т', 'М', 'С', 'Л', 'Р', 'Н', 'Е']],
                'tj' => ['ГУРБА', ['Г', 'У', 'Р', 'Б', 'А', 'Т', 'М', 'С', 'Л', 'Н', 'Д', 'О']],
                'en' => ['CAT', ['C', 'A', 'T', 'O', 'R', 'S', 'E', 'N', 'I', 'G', 'H', 'L']],
            ];

            foreach ($sampleTranslations as $locale => [$answer, $letterTiles]) {
                $translation = LevelTranslation::query()->firstOrNew([
                    'level_id' => $level->id,
                    'locale' => $locale,
                ]);
                $translation->fill([
                    'answer_display' => $answer,
                    'letter_tiles' => $letterTiles,
                ]);
                $translation->save();
            }
        }
    }
}
