<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogQueryRequest extends FormRequest
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
            'category_id' => ['sometimes', 'integer', 'min:1'],
            'after_id' => ['sometimes', 'integer', 'min:1'],
            'cursor' => ['sometimes', 'string', 'max:2048'],
            'limit' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }

    public function selectedLocale(): string
    {
        $locale = $this->validated()['locale'] ?? null;
        $player = $this->user();

        return $locale ?? ($player instanceof Player ? $player->locale : 'ru');
    }

    public function pageSize(): int
    {
        return (int) ($this->validated()['limit'] ?? 20);
    }
}
