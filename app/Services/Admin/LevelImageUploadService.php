<?php

namespace App\Services\Admin;

use App\Jobs\Admin\GenerateLevelImageVariants;
use App\LevelStatus;
use App\Models\Level;
use App\Models\LevelImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

class LevelImageUploadService
{
    /** @param array<int, UploadedFile> $files */
    public function replace(Level $level, array $files): void
    {
        $diskName = config('game.level_images.disk');
        $directory = trim(config('game.level_images.directory').'/'.$level->id, '/');
        $disk = Storage::disk($diskName);
        $newKeys = [];
        $metadata = [];

        try {
            foreach ($files as $position => $file) {
                $mimeType = $file->getMimeType();
                $extension = match ($mimeType) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => throw new RuntimeException('Unsupported uploaded image type.'),
                };

                $image = ImageManager::gd()->read($file->getRealPath());
                $storageKey = $file->storeAs(
                    $directory,
                    Str::uuid().'.'.$extension,
                    ['disk' => $diskName, 'visibility' => 'private'],
                );

                if (! is_string($storageKey) || $storageKey === '') {
                    throw new RuntimeException('Unable to store uploaded level image.');
                }

                $newKeys[] = $storageKey;
                $metadata[$position] = [
                    'storage_disk' => $diskName,
                    'storage_key' => $storageKey,
                    'mime_type' => $mimeType,
                    'width' => $image->width(),
                    'height' => $image->height(),
                    'variants' => null,
                ];
            }

            $transactionResult = DB::transaction(function () use ($level, $metadata): array {
                $lockedLevel = Level::query()->whereKey($level->id)->lockForUpdate()->firstOrFail();

                if ($lockedLevel->status !== LevelStatus::Draft) {
                    throw ValidationException::withMessages([
                        'status' => 'Изображения можно заменять только у черновика.',
                    ]);
                }

                $oldImages = [];
                $imagesToGenerate = [];

                foreach ($metadata as $position => $imageData) {
                    $oldImage = $lockedLevel->images()->where('position', $position)->first();

                    if ($oldImage instanceof LevelImage) {
                        $oldImages[] = [
                            'storage_disk' => $oldImage->storage_disk,
                            'storage_key' => $oldImage->storage_key,
                            'variants' => $oldImage->variants ?? [],
                        ];
                    }

                    $storedImage = $lockedLevel->images()->updateOrCreate(
                        ['position' => $position],
                        $imageData,
                    );
                    $imagesToGenerate[] = [
                        'id' => $storedImage->id,
                        'storage_key' => $storedImage->storage_key,
                    ];
                }

                return ['old_images' => $oldImages, 'images_to_generate' => $imagesToGenerate];
            });
        } catch (Throwable $exception) {
            try {
                $disk->delete($newKeys);
            } catch (Throwable $cleanupException) {
                Log::error('Unable to clean up a failed level image upload', [
                    'level_id' => $level->id,
                    'exception' => $cleanupException->getMessage(),
                ]);
            }

            throw $exception;
        }

        foreach ($transactionResult['images_to_generate'] as $imageToGenerate) {
            try {
                GenerateLevelImageVariants::dispatch($imageToGenerate['id'], $imageToGenerate['storage_key']);
            } catch (Throwable $exception) {
                Log::warning('Unable to queue level image variants', [
                    'level_image_id' => $imageToGenerate['id'],
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($transactionResult['old_images'] as $oldImage) {
            $oldKeys = [$oldImage['storage_key']];

            foreach ($oldImage['variants'] as $variant) {
                if (isset($variant['storage_key']) && is_string($variant['storage_key'])) {
                    $oldKeys[] = $variant['storage_key'];
                }
            }

            try {
                $deleted = Storage::disk($oldImage['storage_disk'])->delete($oldKeys);
            } catch (Throwable) {
                $deleted = false;
            }

            if (! $deleted) {
                Log::warning('Unable to remove replaced level image files', [
                    'storage_disk' => $oldImage['storage_disk'],
                    'storage_keys' => $oldKeys,
                ]);
            }
        }
    }
}
