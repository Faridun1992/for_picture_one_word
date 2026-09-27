<?php

namespace App\Services\Game;

class LevelImageSetValidator
{
    private const array EXPECTED_POSITIONS = [1, 2, 3, 4];

    /** @param list<int|string> $positions */
    public function hasExactlyFourPositions(array $positions): bool
    {
        $normalizedPositions = [];

        foreach ($positions as $position) {
            if (is_int($position)) {
                $normalizedPositions[] = $position;

                continue;
            }

            if (! ctype_digit($position)) {
                return false;
            }

            $normalizedPositions[] = (int) $position;
        }

        sort($normalizedPositions, SORT_NUMERIC);

        return $normalizedPositions === self::EXPECTED_POSITIONS;
    }
}
