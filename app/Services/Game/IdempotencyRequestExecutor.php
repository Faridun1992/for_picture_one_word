<?php

namespace App\Services\Game;

use App\Models\IdempotencyRequest;
use App\Models\Player;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IdempotencyRequestExecutor
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(): array{status: int, body: array<string, mixed>}  $operationCallback
     */
    public function execute(
        Player $player,
        string $key,
        string $operation,
        array $payload,
        Closure $operationCallback,
    ): JsonResponse {
        $requestHash = $this->requestHash($payload);

        try {
            return DB::transaction(function () use ($player, $key, $operation, $requestHash, $operationCallback): JsonResponse {
                $existing = $player->idempotencyRequests()
                    ->where('key', $key)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof IdempotencyRequest) {
                    return $this->replayOrReject($existing, $operation, $requestHash);
                }

                $record = $player->idempotencyRequests()->create([
                    'key' => $key,
                    'operation' => $operation,
                    'request_hash' => $requestHash,
                ]);

                $result = $operationCallback();

                if (! isset($result['status'], $result['body']) || $result['status'] < 100 || $result['status'] > 599) {
                    throw new InvalidArgumentException('The idempotent operation must return a valid HTTP status and response body.');
                }

                $record->forceFill([
                    'response_status' => $result['status'],
                    'response_body' => $result['body'],
                    'completed_at' => now(),
                ])->save();

                return response()->json($result['body'], $result['status']);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $player->idempotencyRequests()->where('key', $key)->first();

            if (! $existing instanceof IdempotencyRequest) {
                throw $exception;
            }

            return $this->replayOrReject($existing, $operation, $requestHash);
        }
    }

    private function replayOrReject(IdempotencyRequest $record, string $operation, string $requestHash): JsonResponse
    {
        if ($record->operation !== $operation || $record->request_hash !== $requestHash) {
            return response()->json([
                'message' => 'This idempotency key was already used for a different request.',
                'code' => 'idempotency_key_reused',
                'errors' => [],
            ], 409);
        }

        if ($record->completed_at === null || $record->response_status === null || $record->response_body === null) {
            return response()->json([
                'message' => 'The operation is still in progress.',
                'code' => 'idempotency_request_in_progress',
                'errors' => [],
            ], 409);
        }

        return response()->json($record->response_body, $record->response_status);
    }

    /** @param  array<string, mixed>  $payload */
    private function requestHash(array $payload): string
    {
        $canonicalPayload = $this->sortKeysRecursively($payload);
        $canonicalJson = json_encode(
            $canonicalPayload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );

        return hash('sha256', $canonicalJson);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function sortKeysRecursively(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeysRecursively($item);
            }
        }

        return $value;
    }
}
