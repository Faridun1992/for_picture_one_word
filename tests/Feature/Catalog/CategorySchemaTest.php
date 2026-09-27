<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_translation_stores_tajik_unicode(): void
    {
        $category = Category::factory()->create();

        $translation = $category->translations()->create([
            'locale' => 'tj',
            'name' => 'Ҳайвонот',
        ]);

        $this->assertSame('Ҳайвонот', $translation->name);
        $this->assertSame($category->id, $translation->category->id);
    }

    public function test_category_has_only_one_translation_per_locale(): void
    {
        $category = Category::factory()->create();
        $category->translations()->create(['locale' => 'en', 'name' => 'Animals']);

        $this->expectException(QueryException::class);

        $category->translations()->create(['locale' => 'en', 'name' => 'Creatures']);
    }

    public function test_deleting_category_cascades_its_translations(): void
    {
        $category = Category::factory()->hasTranslations(3)->create();
        $translationIds = $category->translations()->pluck('id');

        $category->delete();

        $this->assertDatabaseMissing('category_translations', ['id' => $translationIds[0]]);
    }
}
