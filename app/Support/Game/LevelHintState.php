<?php

namespace App\Support\Game;

/**
 * Server side state of the hints already applied by a player to one level.
 *
 * The client never owns this state. It is the anchor that keeps a repeated
 * hint from being charged twice and that lets a client restore a lost board.
 */
final class LevelHintState
{
    /**
     * @param  list<int>  $revealedPositions
     * @param  list<string>  $removedTiles
     */
    private function __construct(
        private readonly array $revealedPositions,
        private readonly array $removedTiles,
    ) {}

    /** @param array<string, mixed>|null $state */
    public static function fromArray(?array $state): self
    {
        $positions = [];

        foreach (self::items($state['revealed_positions'] ?? null) as $position) {
            $isUsable = is_int($position) || (is_string($position) && ctype_digit($position));

            if ($isUsable && (int) $position >= 1) {
                $positions[] = (int) $position;
            }
        }

        $positions = array_values(array_unique($positions));
        sort($positions);

        $tiles = [];

        foreach (self::items($state['removed_tiles'] ?? null) as $tile) {
            if (is_string($tile)) {
                $tiles[] = $tile;
            }
        }

        return new self($positions, $tiles);
    }

    /** @return list<int> */
    public function revealedPositions(): array
    {
        return $this->revealedPositions;
    }

    public function isPositionRevealed(int $position): bool
    {
        return in_array($position, $this->revealedPositions, true);
    }

    public function withRevealedPosition(int $position): self
    {
        if ($this->isPositionRevealed($position)) {
            return $this;
        }

        $positions = [...$this->revealedPositions, $position];
        sort($positions);

        return new self($positions, $this->removedTiles);
    }

    /** @return list<string> */
    public function removedTiles(): array
    {
        return $this->removedTiles;
    }

    /** @param list<string> $tiles */
    public function withRemovedTiles(array $tiles): self
    {
        return new self($this->revealedPositions, [...$this->removedTiles, ...$tiles]);
    }

    /**
     * Tiles the player still owns, in the order of the level tile set: the tiles
     * consumed by revealed letters and the tiles removed by the wrong letter hint
     * are not part of it any more.
     *
     * @param  list<string>  $letterTiles
     * @param  list<string>  $answerGraphemes
     * @return list<string>
     */
    public function remainingTiles(array $letterTiles, array $answerGraphemes): array
    {
        $consumed = $this->removedTiles;

        foreach ($this->revealedPositions as $position) {
            $grapheme = $answerGraphemes[$position - 1] ?? null;

            if (is_string($grapheme)) {
                $consumed[] = $grapheme;
            }
        }

        return TileSet::fromTiles($consumed)->takeFrom($letterTiles);
    }

    /** @return array{revealed_positions: list<int>, removed_tiles: list<string>} */
    public function toArray(): array
    {
        return [
            'revealed_positions' => $this->revealedPositions,
            'removed_tiles' => $this->removedTiles,
        ];
    }

    /** @return list<mixed> */
    private static function items(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
