<?php

namespace App\Http\Requests\Api\V1;

class CatalogQueryRequest extends PlayerLocaleRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            ...$this->localeRules(),
            'category_id' => ['sometimes', 'integer', 'min:1'],
            'after_id' => ['sometimes', 'integer', 'min:1'],
            'cursor' => ['sometimes', 'string', 'max:2048'],
            'limit' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }

    public function pageSize(): int
    {
        return (int) ($this->validated()['limit'] ?? 20);
    }
}
