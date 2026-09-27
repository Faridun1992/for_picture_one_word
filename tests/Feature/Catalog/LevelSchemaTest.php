<?php

namespace Tests\Feature\Catalog;

use App\LevelStatus;
use App\Models\Category;
use App\Models\Level;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_defaults_to_draft_and_belongs_to_its_category(): void
    {
        $level = Level::factory()->create();

        $this->assertSame(LevelStatus::Draft, $level->status);
        $this->assertNull($level->published_at);
        $this->assertInstanceOf(Category::class, $level->category);
    }

    public function test_published_level_stores_valid_difficulty_and_publication_time(): void
    {
        $publishedAt = now()->startOfSecond();
        $level = Level::factory()->create([
            'difficulty' => 5,
            'status' => LevelStatus::Published,
            'published_at' => $publishedAt,
        ]);

        $this->assertSame(5, $level->difficulty);
        $this->assertSame(LevelStatus::Published, $level->status);
        $this->assertTrue($level->published_at->equalTo($publishedAt));
    }

    public function test_category_cannot_be_deleted_while_levels_reference_it(): void
    {
        $level = Level::factory()->create();

        $this->expectException(QueryException::class);

        $level->category->delete();
    }
}
