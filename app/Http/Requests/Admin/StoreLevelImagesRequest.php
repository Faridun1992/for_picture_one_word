<?php

namespace App\Http\Requests\Admin;

use App\LevelStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Intervention\Image\ImageManager;
use Throwable;

class StoreLevelImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['Admin', 'Super Admin'])
            && $this->route('level')?->status === LevelStatus::Draft;
    }

    public function rules(): array
    {
        $rules = [
            'images' => ['required', 'array:1,2,3,4', 'size:4'],
        ];

        foreach ([1, 2, 3, 4] as $position) {
            $rules["images.{$position}"] = [
                'required',
                'image',
                'mimes:jpeg,png,webp',
                'extensions:jpg,jpeg,png,webp',
                'max:'.config('game.level_images.max_file_kilobytes'),
                Rule::dimensions()
                    ->minWidth(config('game.level_images.min_width'))
                    ->minHeight(config('game.level_images.min_height'))
                    ->maxWidth(config('game.level_images.max_width'))
                    ->maxHeight(config('game.level_images.max_height')),
            ];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $manager = ImageManager::gd();

            foreach ([1, 2, 3, 4] as $position) {
                if ($validator->errors()->has("images.{$position}")) {
                    continue;
                }

                $file = $this->file("images.{$position}");

                if (! $file instanceof UploadedFile) {
                    continue;
                }

                try {
                    $manager->read($file->getRealPath());
                } catch (Throwable) {
                    $validator->errors()->add("images.{$position}", 'Не удалось прочитать изображение.');
                }
            }
        }];
    }
}
