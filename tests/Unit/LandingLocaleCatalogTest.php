<?php

namespace Tests\Unit;

use Tests\TestCase;

class LandingLocaleCatalogTest extends TestCase
{
    public function test_every_supported_locale_has_a_landing_catalog(): void
    {
        foreach (config('game.supported_locales') as $locale) {
            $this->assertFileExists(lang_path("{$locale}/landing.php"));
        }
    }

    public function test_locale_catalogs_contain_identical_keys(): void
    {
        $reference = $this->flatten(require lang_path('ru/landing.php'));
        $referenceKeys = array_keys($reference);

        $this->assertNotEmpty($referenceKeys);

        foreach (['tj', 'en'] as $locale) {
            $keys = array_keys($this->flatten(require lang_path("{$locale}/landing.php")));

            $this->assertSame($referenceKeys, $keys, "Landing catalog for {$locale} diverges from ru.");
        }
    }

    public function test_locale_catalogs_have_no_empty_values(): void
    {
        foreach (['ru', 'tj', 'en'] as $locale) {
            foreach ($this->flatten(require lang_path("{$locale}/landing.php")) as $key => $value) {
                $this->assertIsScalar($value);
                $this->assertNotSame('', trim((string) $value), "Empty landing string {$key} in {$locale}.");
            }
        }
    }

    public function test_mock_answer_length_matches_the_tile_budget(): void
    {
        foreach (['ru', 'tj', 'en'] as $locale) {
            $catalog = require lang_path("{$locale}/landing.php");
            $graphemes = preg_split('//u', $catalog['mock']['answer'], -1, PREG_SPLIT_NO_EMPTY) ?: [];

            $this->assertCount(12, $catalog['mock']['tiles'], "{$locale} must show exactly 12 tiles.");
            $this->assertLessThanOrEqual(
                count($catalog['mock']['tiles']),
                count($graphemes),
                "{$locale} mock answer is longer than the tile budget.",
            );
            $this->assertGreaterThanOrEqual(0, $catalog['mock']['revealed']);
            $this->assertLessThanOrEqual(count($graphemes), $catalog['mock']['revealed']);
        }
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array<string, scalar>
     */
    private function flatten(array $catalog, string $prefix = ''): array
    {
        $flat = [];

        foreach ($catalog as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat += $this->flatten($value, $path);

                continue;
            }

            $flat[$path] = $value;
        }

        return $flat;
    }
}