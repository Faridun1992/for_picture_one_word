<?php

namespace App\Http\Requests\Api\V1;

use App\HintType;
use Illuminate\Validation\Rule;

class StoreLevelHintRequest extends PlayerLocaleRequest
{
    use ValidatesIdempotencyKey;

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            ...$this->localeRules(),
            'type' => ['required', 'string', Rule::in(HintType::values())],
            'position' => ['nullable', 'integer', 'min:1', 'max:100', 'prohibited_unless:type,'.HintType::RevealLetter->value],
            'free_tiles' => ['prohibited'],
        ];
    }

    public function hintType(): HintType
    {
        return HintType::from((string) $this->validated()['type']);
    }

    public function position(): ?int
    {
        $position = $this->validated()['position'] ?? null;

        return $position === null ? null : (int) $position;
    }
}
