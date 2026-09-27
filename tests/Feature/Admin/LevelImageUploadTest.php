<?php

namespace Tests\Feature\Admin;

use App\LevelStatus;
use App\Models\Level;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LevelImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_exactly_four_valid_images_to_private_storage(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $level = Level::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.levels.images.store', $level), [
            'images' => $this->imageSet(),
        ]);

        $response->assertRedirect(route('admin.levels.edit', $level));
        $this->assertDatabaseCount('level_images', 4);

        foreach ($level->fresh()->images as $image) {
            $this->assertSame('local', $image->storage_disk);
            $this->assertSame('image/jpeg', $image->mime_type);
            $this->assertSame(640, $image->width);
            Storage::disk('local')->assertExists($image->storage_key);
        }
    }

    public function test_upload_replaces_all_four_images_and_removes_old_files(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $level = Level::factory()->create();
        $this->actingAs($admin)->post(route('admin.levels.images.store', $level), [
            'images' => $this->imageSet(),
        ])->assertRedirect();
        $oldKeys = $level->fresh()->images->pluck('storage_key')->all();

        $this->actingAs($admin)->post(route('admin.levels.images.store', $level), [
            'images' => $this->imageSet(),
        ])->assertRedirect();

        $this->assertDatabaseCount('level_images', 4);
        foreach ($oldKeys as $oldKey) {
            Storage::disk('local')->assertMissing($oldKey);
        }
        foreach ($level->fresh()->images as $image) {
            Storage::disk('local')->assertExists($image->storage_key);
        }
    }

    public function test_svg_upload_is_rejected_without_writing_files_or_rows(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $level = Level::factory()->create();
        $images = $this->imageSet();
        $images[1] = UploadedFile::fake()->create('unsafe.svg', 10, 'image/svg+xml');

        $this->actingAs($admin)->from(route('admin.levels.edit', $level))
            ->post(route('admin.levels.images.store', $level), ['images' => $images])
            ->assertSessionHasErrors('images.1');

        $this->assertDatabaseCount('level_images', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_image_set_requires_all_four_positions(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $level = Level::factory()->create();
        $images = $this->imageSet();
        unset($images[4]);

        $this->actingAs($admin)->from(route('admin.levels.edit', $level))
            ->post(route('admin.levels.images.store', $level), ['images' => $images])
            ->assertSessionHasErrors('images.4');

        $this->assertDatabaseCount('level_images', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_image_below_minimum_dimensions_is_rejected(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $level = Level::factory()->create();
        $images = $this->imageSet();
        $images[1] = UploadedFile::fake()->image('too-small.jpg', 100, 100);

        $this->actingAs($admin)->from(route('admin.levels.edit', $level))
            ->post(route('admin.levels.images.store', $level), ['images' => $images])
            ->assertSessionHasErrors('images.1');

        $this->assertDatabaseCount('level_images', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_users_without_admin_role_cannot_upload_images(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $user = $this->createUser();
        $level = Level::factory()->create();

        $this->actingAs($user)->post(route('admin.levels.images.store', $level), [
            'images' => $this->imageSet(),
        ])->assertForbidden();

        $this->assertDatabaseCount('level_images', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_published_levels_cannot_have_their_images_replaced(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $level = Level::factory()->create(['status' => LevelStatus::Published]);

        $this->actingAs($admin)->post(route('admin.levels.images.store', $level), [
            'images' => $this->imageSet(),
        ])->assertForbidden();

        $this->assertDatabaseCount('level_images', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    /** @return array<int, UploadedFile> */
    private function imageSet(): array
    {
        return [
            1 => UploadedFile::fake()->image('one.jpg', 640, 480),
            2 => UploadedFile::fake()->image('two.jpg', 640, 480),
            3 => UploadedFile::fake()->image('three.jpg', 640, 480),
            4 => UploadedFile::fake()->image('four.jpg', 640, 480),
        ];
    }

    private function createAdmin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = $this->createUser();
        $user->assignRole('Admin');

        return $user;
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Image upload test',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ]);
    }
}
