<?php

namespace Tests\Feature\Admin;

use App\Jobs\Admin\GenerateLevelImageVariants;
use App\LevelStatus;
use App\Models\Level;
use App\Models\LevelImage;
use App\Models\User;
use App\Services\Admin\LevelImageVariantGenerator;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LevelImageVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_generates_private_display_and_thumbnail_variants(): void
    {
        Storage::fake('local');
        $level = Level::factory()->create();
        $levelImage = $this->createImage($level, 1800, 1200);

        (new GenerateLevelImageVariants($levelImage->id, $levelImage->storage_key))
            ->handle(app(LevelImageVariantGenerator::class));

        $variants = $levelImage->refresh()->variants;
        $this->assertSame(2, count($variants));
        $this->assertSame('image/jpeg', $variants['display']['mime_type']);
        $this->assertLessThanOrEqual(1280, $variants['display']['width']);
        $this->assertLessThanOrEqual(1280, $variants['display']['height']);
        $this->assertLessThanOrEqual(320, $variants['thumbnail']['width']);
        $this->assertLessThanOrEqual(320, $variants['thumbnail']['height']);
        Storage::disk('local')->assertExists($levelImage->storage_key);
        Storage::disk('local')->assertExists($variants['display']['storage_key']);
        Storage::disk('local')->assertExists($variants['thumbnail']['storage_key']);
    }

    public function test_job_is_safe_to_retry_and_replaces_the_previous_variant_set(): void
    {
        Storage::fake('local');
        $levelImage = $this->createImage(Level::factory()->create());
        $job = new GenerateLevelImageVariants($levelImage->id, $levelImage->storage_key);

        $job->handle(app(LevelImageVariantGenerator::class));
        $firstVariantKeys = collect($levelImage->refresh()->variants)->pluck('storage_key')->all();
        $job->handle(app(LevelImageVariantGenerator::class));
        $secondVariantKeys = collect($levelImage->refresh()->variants)->pluck('storage_key')->all();

        foreach ($firstVariantKeys as $key) {
            Storage::disk('local')->assertMissing($key);
        }
        foreach ($secondVariantKeys as $key) {
            Storage::disk('local')->assertExists($key);
        }
        $this->assertCount(2, $secondVariantKeys);
    }

    public function test_job_does_not_write_variants_for_a_replaced_source_image(): void
    {
        Storage::fake('local');
        $levelImage = $this->createImage(Level::factory()->create());
        $oldStorageKey = $levelImage->storage_key;
        $levelImage->update(['storage_key' => 'levels/replaced.jpg', 'variants' => null]);

        (new GenerateLevelImageVariants($levelImage->id, $oldStorageKey))
            ->handle(app(LevelImageVariantGenerator::class));

        $this->assertNull($levelImage->refresh()->variants);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_upload_enqueues_one_image_job_per_position_on_images_queue(): void
    {
        Storage::fake('local');
        Queue::fake([GenerateLevelImageVariants::class]);
        $this->seed(RoleSeeder::class);
        $admin = User::query()->create([
            'name' => 'Image queue test',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ]);
        $admin->assignRole('Admin');
        $level = Level::factory()->create(['status' => LevelStatus::Draft]);
        $images = [
            1 => UploadedFile::fake()->image('one.jpg', 640, 480),
            2 => UploadedFile::fake()->image('two.jpg', 640, 480),
            3 => UploadedFile::fake()->image('three.jpg', 640, 480),
            4 => UploadedFile::fake()->image('four.jpg', 640, 480),
        ];

        $this->actingAs($admin)->post(route('admin.levels.images.store', $level), ['images' => $images])
            ->assertRedirect();

        Queue::assertPushedTimes(GenerateLevelImageVariants::class, 4);
        Queue::assertPushedOn('images', GenerateLevelImageVariants::class);
    }

    private function createImage(Level $level, int $width = 640, int $height = 480): LevelImage
    {
        $file = UploadedFile::fake()->image('source.jpg', $width, $height);
        $storageKey = $file->storeAs(
            'levels/'.$level->id,
            'source.jpg',
            ['disk' => 'local', 'visibility' => 'private'],
        );

        return $level->images()->create([
            'position' => 1,
            'storage_disk' => 'local',
            'storage_key' => $storageKey,
            'mime_type' => $file->getMimeType(),
            'width' => $width,
            'height' => $height,
        ]);
    }
}
