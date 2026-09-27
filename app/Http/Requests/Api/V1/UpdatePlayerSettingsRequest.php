<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlayerSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'required', 'string', Rule::in(config('game.supported_locales'))],
            'sound_enabled' => ['sometimes', 'required', 'boolean'],
            'haptics_enabled' => ['sometimes', 'required', 'boolean'],
            'theme' => ['sometimes', 'required', 'string', Rule::in(['system', 'light', 'dark'])],
        ];
    }
}
