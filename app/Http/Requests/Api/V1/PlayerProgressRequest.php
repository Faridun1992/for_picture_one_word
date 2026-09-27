<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PlayerProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'cursor' => ['sometimes', 'string', 'max:2048'],
            'limit' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    public function pageSize(): int
    {
        return (int) ($this->validated()['limit'] ?? 50);
    }
}
