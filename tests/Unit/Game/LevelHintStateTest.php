<?php

namespace Tests\Unit\Game;

use App\Support\Game\LevelHintState;
use App\Support\Game\TileSet;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LevelHintStateTest extends TestCase
{
    #[Test]
    public function it_starts_empty_for_missing_or_unusable_state(): void
    {
        $this->assertSame([], LevelHintState::fromArray(null)->revealedPositions());
        $this->assertSame([], LevelHintState::fromArray(null)->removedTiles());
        $this->assertSame([], LevelHintState::fromArray(['revealed_positions' => 'nonsense'])->revealedPositions());
        $this->assertSame([], LevelHintState::fromArray(['removed_tiles' => [1, 2]])->removedTiles());
    }

    #[Test]
    public function it_normalizes_positions_from_json_decoded_integers_and_strings(): void
    {
        $state = LevelHintState::fromArray([
            'revealed_positions' => ['3', 1, 3, 'x', -2],
            'removed_tiles' => ['Ж'],
        ]);

        $this->assertSame([1, 3], $state->revealedPositions());
        $this->assertSame(['Ж'], $state->removedTiles());
    }

    #[Test]
    public function revealing_the_same_position_twice_keeps_a_single_entry(): void
    {
        $state = LevelHintState::fromArray(null)
            ->withRevealedPosition(2)
            ->withRevealedPosition(1)
            ->withRevealedPosition(2);

        $this->assertSame([1, 2], $state->revealedPositions());
        $this->assertTrue($state->isPositionRevealed(1));
        $this->assertFalse($state->isPositionRevealed(3));
    }

    #[Test]
    public function remaining_tiles_drop_removed_tiles_and_the_letters_of_revealed_positions(): void
    {
        $state = LevelHintState::fromArray([
            'revealed_positions' => [1, 4],
            'removed_tiles' => ['Ц'],
        ]);

        $this->assertSame(
            ['О', 'Ш', 'А'],
            $state->remainingTiles(['К', 'О', 'Ш', 'К', 'А', 'Ц'], ['К', 'О', 'Ш', 'К', 'А']),
        );
    }

    #[Test]
    public function tile_set_counts_duplicates_and_subtracts_as_a_multiset(): void
    {
        $this->assertSame(['О'], TileSet::fromTiles(['К', 'К'])->takeFrom(['К', 'К', 'О']));
        $this->assertSame(['Ш', 'К'], TileSet::fromTiles(['К', 'О', 'А'])->takeFrom(['К', 'О', 'Ш', 'К', 'А']));
    }
}
