<?php

namespace App\Services\Admin;

use App\Models\LevelImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\EncodedImage;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

class LevelImageVariantGenerator
{
    public function generate(int $levelImageId, string $expectedStorageKey): void
    {
        $levelImage = LevelImage::query()->find($levelImageId);

        if (! $levelImage instanceof LevelImage || $levelImage->storage_key !== $expectedStorageKey) {
            return;
        }

        $disk = Storage::disk($levelImage->storage_disk);
        $original = $disk->get($expectedStorageKey);

        if (! is_string($original)) {
            if (LevelImage::query()->whereKey($levelImageId)->value('storage_key') !== $expectedStorageKey) {
                return;
            }

            throw new RuntimeException('Unable to read the original level image.');
        }

        $source = ImageManager::gd()->read($original);
        $extension = match ($levelImage->mime_type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Unsupported original image type.'),
        };
        $variantSettings = config('game.level_images.variants', []);
        $directory = 'levels/'.$levelImage->level_id.'/variants/'.$levelImage->id.'/'.Str::uuid();
        $newKeys = [];
        $variants = [];

        try {
            foreach ($variantSettings as $name => $settings) {
                $variantImage = clone $source;
                $variantImage->scaleDown(
                    width: $settings['max_width'],
                    height: $settings['max_height'],
                );

                $encoded = $this->encode($variantImage, $levelImage->mime_type, $settings['quality'] ?? 85);
                $storageKey = $directory.'/'.$name.'.'.$extension;
                $stored = $disk->put($storageKey, $encoded->toString(), ['visibility' => 'private']);

                if (! $stored) {
                    throw new RuntimeException('Unable to store a level image variant.');
                }

                $newKeys[] = $storageKey;
                $variants[$name] = [
                    'storage_key' => $storageKey,
                    'mime_type' => $levelImage->mime_type,
                    'width' => $variantImage->width(),
                    'height' => $variantImage->height(),
                ];
            }

            $previousVariants = DB::transaction(function () use ($levelImageId, $expectedStorageKey, $variants): ?array {
                $currentImage = LevelImage::query()->whereKey($levelImageId)->lockForUpdate()->first();

                if (! $currentImage instanceof LevelImage || $currentImage->storage_key !== $expectedStorageKey) {
                    return null;
                }

                $previousVariants = $currentImage->variants ?? [];
                $currentImage->update(['variants' => $variants]);

                return $previousVariants;
            });

            if ($previousVariants === null) {
                $disk->delete($newKeys);

                return;
            }

            $this->deletePreviousVariants($levelImage->storage_disk, $previousVariants, $newKeys);
        } catch (Throwable $exception) {
            try {
                $disk->delete($newKeys);
            } catch (Throwable $cleanupException) {
                Log::error('Unable to clean up failed level image variants', [
                    'level_image_id' => $levelImageId,
                    'exception' => $cleanupException->getMessage(),
                ]);
            }

            throw $exception;
        }
    }

    private function encode(mixed $image, string $mimeType, int $quality): EncodedImage
    {
        return match ($mimeType) {
            'image/jpeg' => $image->toJpeg(quality: $quality, strip: true),
            'image/png' => $image->toPng(),
            'image/webp' => $image->toWebp(quality: $quality, strip: true),
            default => throw new RuntimeException('Unsupported level image format.'),
        };
    }

    /** @param array<string, array{storage_key?: string}> $previousVariants
     * @param  list<string>  $newKeys
     */
    private function deletePreviousVariants(string $diskName, array $previousVariants, array $newKeys): void
    {
        foreach ($previousVariants as $variant) {
            $storageKey = $variant['storage_key'] ?? null;

            if (! is_string($storageKey) || in_array($storageKey, $newKeys, true)) {
                continue;
            }

            try {
                $deleted = Storage::disk($diskName)->delete($storageKey);
            } catch (Throwable) {
                $deleted = false;
            }

            if (! $deleted) {
                Log::warning('Unable to remove replaced level image variant', [
                    'storage_disk' => $diskName,
                    'storage_key' => $storageKey,
                ]);
            }
        }
    }
}
