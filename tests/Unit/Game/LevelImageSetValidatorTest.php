<?php

namespace Tests\Unit\Game;

use App\Services\Game\LevelImageSetValidator;
use PHPUnit\Framework\TestCase;

class LevelImageSetValidatorTest extends TestCase
{
    public function test_accepts_exactly_the_four_required_positions_in_any_order(): void
    {
        $validator = new LevelImageSetValidator;

        $this->assertTrue($validator->hasExactlyFourPositions([4, 2, 1, 3]));
    }

    public function test_rejects_missing_repeated_and_extra_positions(): void
    {
        $validator = new LevelImageSetValidator;

        $this->assertFalse($validator->hasExactlyFourPositions([1, 2, 4]));
        $this->assertFalse($validator->hasExactlyFourPositions([1, 2, 2, 4]));
        $this->assertFalse($validator->hasExactlyFourPositions([1, 2, 3, 4, 5]));
        $this->assertFalse($validator->hasExactlyFourPositions(['1invalid', 2, 3, 4]));
    }
}
