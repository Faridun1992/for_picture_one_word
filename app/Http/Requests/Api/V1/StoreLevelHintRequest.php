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
            'position' => ['prohibited'],
            'free_tiles' => ['prohibited'],
        ];
    }

    public function hintType(): HintType
    {
        return HintType::from((string) $this->validated()['type']);
    }
}
