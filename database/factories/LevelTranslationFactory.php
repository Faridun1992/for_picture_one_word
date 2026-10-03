<?php

namespace Database\Factories;

use App\Models\Level;
use App\Models\LevelTranslation;
use App\Services\Game\AnswerNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LevelTranslation>
 */
class LevelTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $answer = 'CAT';

        return [
            'level_id' => Level::factory(),
            'locale' => 'en',
            'answer_display' => $answer,
            'letter_tiles' => [
                ...app(AnswerNormalizer::class)->splitGraphemes($answer),
                'D', 'O', 'G', 'R', 'S', 'E', 'N', 'I', 'H',
            ],
        ];
    }
}
