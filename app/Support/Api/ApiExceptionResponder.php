<?php

namespace App\Support\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ApiExceptionResponder
{
    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        if ($exception instanceof ValidationException) {
            return $this->response(
                message: 'The given data was invalid.',
                code: 'validation_failed',
                status: 422,
                errors: $exception->errors(),
            );
        }

        if ($exception instanceof AuthenticationException) {
            return $this->response('Unauthenticated.', 'unauthenticated', 401);
        }

        if ($exception instanceof AuthorizationException) {
            return $this->response('Forbidden.', 'forbidden', 403);
        }

        if ($exception instanceof ModelNotFoundException) {
            return $this->response('Resource not found.', 'not_found', 404);
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            if ($status >= 500) {
                return $this->response('Server error.', 'internal_server_error', $status);
            }

            [$code, $message] = match ($status) {
                403 => ['forbidden', 'Forbidden.'],
                404 => ['not_found', 'Resource not found.'],
                409 => ['conflict', 'Conflict.'],
                422 => ['validation_failed', 'The given data was invalid.'],
                429 => ['too_many_requests', 'Too many requests.'],
                default => ['http_error', $exception->getMessage() ?: 'Request failed.'],
            };

            return $this->response($message, $code, $status);
        }

        return $this->response('Server error.', 'internal_server_error', 500);
    }

    /** @param array<string, mixed>|null $errors */
    private function response(
        string $message,
        string $code,
        int $status,
        ?array $errors = null,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'errors' => (object) ($errors ?? []),
        ], $status);
    }
}
