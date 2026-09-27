<?php

namespace App\Support\Api;

use RuntimeException;

class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $meta  Extra machine readable context the client needs, for example the price of an operation.
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $meta = [],
    ) {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self('not_found', 'Resource not found.', 404);
    }

    public static function conflict(string $errorCode, string $message): self
    {
        return new self($errorCode, $message, 409);
    }

    /** @param array<string, mixed> $meta */
    public static function unprocessable(string $errorCode, string $message, array $meta = []): self
    {
        return new self($errorCode, $message, 422, $meta);
    }
}
