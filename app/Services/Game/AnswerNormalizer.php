<?php

namespace App\Services\Game;

use RuntimeException;

class AnswerNormalizer
{
    public function normalize(string $answer): string
    {
        if (! mb_check_encoding($answer, 'UTF-8')) {
            throw new RuntimeException('Answer must be valid UTF-8.');
        }

        $normalized = \Normalizer::normalize($answer, \Normalizer::FORM_C);

        if ($normalized === false) {
            throw new RuntimeException('Answer could not be Unicode-normalized.');
        }

        $folded = mb_convert_case($normalized, MB_CASE_FOLD, 'UTF-8');
        $foldedNormalized = \Normalizer::normalize($folded, \Normalizer::FORM_C);

        if ($foldedNormalized === false) {
            throw new RuntimeException('Answer could not be Unicode-normalized.');
        }

        $trimmed = preg_replace('/^[\\p{Z}\\t\\n\\r\\f\\v]+|[\\p{Z}\\t\\n\\r\\f\\v]+$/u', '', $foldedNormalized);

        if ($trimmed === null) {
            throw new RuntimeException('Answer could not be Unicode-normalized.');
        }

        return $trimmed;
    }

    /** @return list<string> */
    public function splitGraphemes(string $value): array
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            throw new RuntimeException('Text must be valid UTF-8.');
        }

        if (preg_match_all('/\\X/u', $value, $matches) === false) {
            throw new RuntimeException('Text could not be split into graphemes.');
        }

        return $matches[0];
    }
}
