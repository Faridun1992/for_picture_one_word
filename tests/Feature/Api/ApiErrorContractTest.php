<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApiErrorContractTest extends TestCase
{
    public function test_unknown_api_route_returns_the_common_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/missing');

        $response->assertNotFound();
        $this->assertSame(
            '{"message":"Resource not found.","code":"not_found","errors":{}}',
            $response->getContent(),
        );
    }

    public function test_validation_errors_include_field_messages(): void
    {
        Route::post('/api/v1/test-validation', static function (): never {
            throw ValidationException::withMessages([
                'answer' => ['The answer field is required.'],
            ]);
        });

        $this->postJson('/api/v1/test-validation')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('errors.answer.0', 'The answer field is required.');
    }
}
