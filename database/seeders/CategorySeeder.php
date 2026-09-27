<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'animals' => ['ru' => 'Животные', 'tj' => 'Ҳайвонот', 'en' => 'Animals'],
            'food' => ['ru' => 'Еда', 'tj' => 'Ғизо', 'en' => 'Food'],
            'nature' => ['ru' => 'Природа', 'tj' => 'Табиат', 'en' => 'Nature'],
        ];

        $sortOrder = 0;

        foreach ($categories as $slug => $translations) {
            $category = Category::query()->updateOrCreate(
                ['slug' => $slug],
                ['sort_order' => $sortOrder, 'is_active' => true],
            );
            $sortOrder += 10;

            foreach ($translations as $locale => $name) {
                $category->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['name' => $name],
                );
            }
        }
    }
}
