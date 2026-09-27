<?php

namespace App\Http\Requests\Api\V1;

class StoreLevelAttemptRequest extends PlayerLocaleRequest
{
    use ValidatesIdempotencyKey;

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            ...$this->localeRules(),
            'answer' => ['required', 'string', 'max:100'],
        ];
    }

    public function answer(): string
    {
        return (string) $this->validated()['answer'];
    }
}
