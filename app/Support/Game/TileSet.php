<?php

namespace App\Support\Game;

/**
 * Multiset of tiles used to preserve duplicate letters during server-side
 * hint calculations.
 */
final class TileSet
{
    /** @param array<string, int> $counts */
    private function __construct(private readonly array $counts) {}

    /** @param list<string> $tiles */
    public static function fromTiles(array $tiles): self
    {
        $counts = [];

        foreach ($tiles as $tile) {
            $counts[$tile] = ($counts[$tile] ?? 0) + 1;
        }

        return new self($counts);
    }

    /**
     * Tiles of the given list that stay once the tiles of this set are taken away.
     *
     * @param  list<string>  $tiles
     * @return list<string>
     */
    public function takeFrom(array $tiles): array
    {
        $remaining = $this->counts;
        $kept = [];

        foreach ($tiles as $tile) {
            if (($remaining[$tile] ?? 0) > 0) {
                $remaining[$tile]--;

                continue;
            }

            $kept[] = $tile;
        }

        return $kept;
    }
}
