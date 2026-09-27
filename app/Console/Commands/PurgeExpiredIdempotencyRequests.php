<?php

namespace App\Console\Commands;

use App\Models\IdempotencyRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('game:purge-expired-idempotency-requests')]
#[Description('Delete completed idempotency results older than 30 days')]
class PurgeExpiredIdempotencyRequests extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = Carbon::now()->subDays(30);
        $deleted = 0;

        IdempotencyRequest::query()
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $cutoff)
            ->chunkById(1000, function ($records) use (&$deleted, $cutoff): void {
                $deleted += IdempotencyRequest::query()
                    ->whereKey($records->modelKeys())
                    ->whereNotNull('completed_at')
                    ->where('completed_at', '<=', $cutoff)
                    ->delete();
            });

        $this->info("Deleted {$deleted} completed idempotency result(s) older than 30 days.");

        return self::SUCCESS;
    }
}
