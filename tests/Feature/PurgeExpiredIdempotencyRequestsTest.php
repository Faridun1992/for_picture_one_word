<?php

namespace Tests\Feature;

use App\Models\IdempotencyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeExpiredIdempotencyRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_removes_only_completed_results_at_least_thirty_days_old(): void
    {
        $this->travelTo(now()->startOfSecond());
        $completedBeforeCutoff = IdempotencyRequest::factory()->create(['completed_at' => now()->subDays(31)]);
        $completedAtCutoff = IdempotencyRequest::factory()->create(['completed_at' => now()->subDays(30)]);
        $recentlyCompleted = IdempotencyRequest::factory()->create(['completed_at' => now()->subDays(29)]);
        $incomplete = IdempotencyRequest::factory()->create([
            'created_at' => now()->subDays(90),
            'completed_at' => null,
            'response_status' => null,
            'response_body' => null,
        ]);

        $this->artisan('game:purge-expired-idempotency-requests')
            ->expectsOutput('Deleted 2 completed idempotency result(s) older than 30 days.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('idempotency_requests', ['id' => $completedBeforeCutoff->id]);
        $this->assertDatabaseMissing('idempotency_requests', ['id' => $completedAtCutoff->id]);
        $this->assertDatabaseHas('idempotency_requests', ['id' => $recentlyCompleted->id]);
        $this->assertDatabaseHas('idempotency_requests', ['id' => $incomplete->id]);
    }
}
