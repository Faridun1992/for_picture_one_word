<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['Admin', 'Super Admin']) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $translations = $this->input('translations', []);

        if (! is_array($translations)) {
            return;
        }

        foreach (config('game.supported_locales', []) as $locale) {
            $tiles = data_get($translations, "{$locale}.letter_tiles");

            if (is_string($tiles)) {
                $tiles = preg_split('/\R/u', trim($tiles), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }

            if (is_array($tiles)) {
                $tiles = array_values(array_filter(
                    array_map(static fn (mixed $tile): mixed => is_string($tile) ? trim($tile) : $tile, $tiles),
                    static fn (mixed $tile): bool => ! is_string($tile) || $tile !== '',
                ));
                data_set($translations, "{$locale}.letter_tiles", $tiles);
            }
        }

        $this->merge(['translations' => $translations]);
    }

    public function rules(): array
    {
        $rules = [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'difficulty' => ['required', 'integer', 'between:1,5'],
            'translations' => ['nullable', 'array:'.implode(',', config('game.supported_locales', []))],
        ];

        foreach (config('game.supported_locales', []) as $locale) {
            $rules["translations.{$locale}"] = ['nullable', 'array:answer_display,letter_tiles'];
            $rules["translations.{$locale}.answer_display"] = ['nullable', 'string', 'max:100'];
            $rules["translations.{$locale}.letter_tiles"] = ['nullable', 'array', 'max:12'];
            $rules["translations.{$locale}.letter_tiles.*"] = ['required', 'string', 'max:32'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (config('game.supported_locales', []) as $locale) {
                $answer = $this->input("translations.{$locale}.answer_display");
                $tiles = $this->input("translations.{$locale}.letter_tiles", []);

                if (blank($answer) && empty($tiles)) {
                    continue;
                }

                if (blank($answer)) {
                    $validator->errors()->add("translations.{$locale}.answer_display", 'Укажите ответ для набора букв.');
                } elseif (is_string($answer)) {
                    $answerLength = grapheme_strlen(trim($answer));

                    if ($answerLength === false || $answerLength > config('game.max_answer_graphemes')) {
                        $validator->errors()->add("translations.{$locale}.answer_display", 'Ответ не должен превышать '.config('game.max_answer_graphemes').' Unicode-графем.');
                    }
                }

                if (empty($tiles)) {
                    $validator->errors()->add("translations.{$locale}.letter_tiles", 'Укажите плитки по одной на строку.');
                }

                if (is_array($tiles) && count($tiles) !== 12) {
                    $validator->errors()->add("translations.{$locale}.letter_tiles", 'Для каждого языка укажите ровно 12 плиток: буквы ответа и дополнительные буквы.');
                }

                foreach (is_array($tiles) ? $tiles : [] as $index => $tile) {
                    if (is_string($tile) && grapheme_strlen($tile) !== 1) {
                        $validator->errors()->add("translations.{$locale}.letter_tiles.{$index}", 'Каждая плитка должна содержать один Unicode-символ.');
                    }
                }
            }
        }];
    }
}
