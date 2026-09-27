<?php

namespace Tests\Feature\Catalog;

use App\Models\LevelImage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_metadata_and_variants_are_stored_on_its_level(): void
    {
        $image = LevelImage::factory()->create([
            'position' => 4,
            'width' => null,
            'height' => null,
            'variants' => ['thumbnail' => ['storage_key' => 'levels/thumb.jpg']],
        ]);

        $this->assertSame(4, $image->position);
        $this->assertNull($image->width);
        $this->assertSame('levels/thumb.jpg', $image->variants['thumbnail']['storage_key']);
        $this->assertSame($image->id, $image->level->images()->first()->id);
    }

    public function test_each_level_position_can_only_be_used_once(): void
    {
        $image = LevelImage::factory()->create(['position' => 1]);

        $this->expectException(QueryException::class);

        LevelImage::factory()->create([
            'level_id' => $image->level_id,
            'position' => 1,
        ]);
    }
}
