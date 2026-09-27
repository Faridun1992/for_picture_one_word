<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Support\Facades\Validator;

/**
 * Reads the client operation key of a gameplay request. The header is validated
 * with the request so a missing or malformed key answers 422 like any other
 * invalid input.
 */
trait ValidatesIdempotencyKey
{
    public function idempotencyKey(): string
    {
        $key = $this->header('Idempotency-Key');

        Validator::make(
            ['idempotency_key' => $key],
            ['idempotency_key' => ['required', 'string', 'ascii', 'min:8', 'max:80']],
        )->validate();

        return (string) $key;
    }
}
