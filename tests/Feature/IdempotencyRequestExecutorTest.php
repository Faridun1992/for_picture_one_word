<?php

namespace Tests\Feature;

use App\Models\IdempotencyRequest;
use App\Models\Player;
use App\Models\PlayerWallet;
use App\Services\Game\IdempotencyRequestExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class IdempotencyRequestExecutorTest extends TestCase
{
    use RefreshDatabase;

    public function test_retries_with_same_key_and_payload_replay_the_saved_result(): void
    {
        $player = Player::factory()->create();
        $wallet = PlayerWallet::factory()->for($player)->create(['balance' => 0]);
        $calls = 0;
        $executor = new IdempotencyRequestExecutor;

        $first = $executor->execute($player, 'offline-answer-001', 'complete_level', ['answer' => 'ГУРБА'], function () use ($wallet, &$calls): array {
            $calls++;
            $wallet->increment('balance', 10);

            return ['status' => 200, 'body' => ['data' => ['correct' => true, 'balance' => 10]]];
        });
        $replay = $executor->execute($player, 'offline-answer-001', 'complete_level', ['answer' => 'ГУРБА'], function () use (&$calls): array {
            $calls++;

            return ['status' => 500, 'body' => []];
        });

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame($first->getContent(), $replay->getContent());
        $this->assertSame(1, $calls);
        $this->assertSame(10, $wallet->fresh()->balance);
        $this->assertDatabaseCount('idempotency_requests', 1);
        $this->assertNotNull(IdempotencyRequest::query()->firstOrFail()->completed_at);
    }

    public function test_reusing_key_for_different_payload_or_operation_returns_409_without_execution(): void
    {
        $player = Player::factory()->create();
        $executor = new IdempotencyRequestExecutor;
        $calls = 0;
        $executor->execute($player, 'offline-answer-002', 'complete_level', ['answer' => 'CAT'], fn (): array => [
            'status' => 200,
            'body' => ['data' => ['correct' => true]],
        ]);

        $changedPayload = $executor->execute($player, 'offline-answer-002', 'complete_level', ['answer' => 'DOG'], function () use (&$calls): array {
            $calls++;

            return ['status' => 200, 'body' => []];
        });
        $changedOperation = $executor->execute($player, 'offline-answer-002', 'use_hint', ['answer' => 'CAT'], function () use (&$calls): array {
            $calls++;

            return ['status' => 200, 'body' => []];
        });

        $this->assertSame(409, $changedPayload->getStatusCode());
        $this->assertSame('idempotency_key_reused', $changedPayload->getData(true)['code']);
        $this->assertSame(409, $changedOperation->getStatusCode());
        $this->assertSame('idempotency_key_reused', $changedOperation->getData(true)['code']);
        $this->assertSame(0, $calls);
    }

    public function test_incomplete_key_returns_in_progress_and_does_not_run_operation(): void
    {
        $player = Player::factory()->create();
        IdempotencyRequest::factory()->for($player)->create([
            'key' => 'offline-answer-003',
            'request_hash' => hash('sha256', '{"answer":"CAT"}'),
            'completed_at' => null,
            'response_status' => null,
            'response_body' => null,
        ]);
        $calls = 0;

        $response = (new IdempotencyRequestExecutor)->execute($player, 'offline-answer-003', 'complete_level', ['answer' => 'CAT'], function () use (&$calls): array {
            $calls++;

            return ['status' => 200, 'body' => []];
        });

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('idempotency_request_in_progress', $response->getData(true)['code']);
        $this->assertSame(0, $calls);
    }

    public function test_failed_operation_rolls_back_its_effect_and_idempotency_record(): void
    {
        $player = Player::factory()->create();
        $wallet = PlayerWallet::factory()->for($player)->create(['balance' => 0]);

        try {
            (new IdempotencyRequestExecutor)->execute($player, 'offline-answer-004', 'complete_level', ['answer' => 'CAT'], function () use ($wallet): array {
                $wallet->increment('balance', 10);
                throw new RuntimeException('Temporary failure');
            });
            $this->fail('The operation exception should be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Temporary failure', $exception->getMessage());
        }

        $this->assertSame(0, $wallet->fresh()->balance);
        $this->assertDatabaseCount('idempotency_requests', 0);
    }
}
