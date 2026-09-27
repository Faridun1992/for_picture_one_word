<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuestSessionRequest extends FormRequest
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
            'device_name' => ['sometimes', 'required', 'string', 'max:100'],
        ];
    }
}
