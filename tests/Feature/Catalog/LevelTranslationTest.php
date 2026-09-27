<?php

namespace Tests\Feature\Catalog;

use App\Models\LevelTranslation;
use App\Services\Game\AnswerNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_answer_normalization_handles_tajik_russian_and_english(): void
    {
        $normalizer = app(AnswerNormalizer::class);

        $this->assertSame('кӯҳ', $normalizer->normalize(' КӮҲ '));
        $this->assertSame('кӯҳ', $normalizer->normalize("\u{00A0}КӮҲ\u{00A0}"));
        $this->assertSame('кошка', $normalizer->normalize('КОШКА'));
        $this->assertSame('cat', $normalizer->normalize('CAT'));
    }

    public function test_answer_normalization_composes_equivalent_unicode_sequences(): void
    {
        $normalizer = app(AnswerNormalizer::class);

        $this->assertSame('й', $normalizer->normalize("И\u{0306}"));
    }

    public function test_level_translation_stores_the_normalized_answer_and_tiles(): void
    {
        $translation = LevelTranslation::factory()->create([
            'locale' => 'tj',
            'answer_display' => 'ГӮРБА',
            'letter_tiles' => ['Г', 'Ӯ', 'Р', 'Б', 'А'],
        ]);

        $this->assertSame('гӯрба', $translation->getRawOriginal('answer_normalized'));
        $this->assertSame(['Г', 'Ӯ', 'Р', 'Б', 'А'], $translation->letter_tiles);
        $this->assertSame($translation->id, $translation->level->translations()->first()->id);
    }

    public function test_level_can_have_only_one_translation_per_locale(): void
    {
        $translation = LevelTranslation::factory()->create(['locale' => 'tj']);

        $this->expectException(QueryException::class);

        LevelTranslation::factory()->for($translation->level)->create([
            'locale' => 'tj',
        ]);
    }
}
