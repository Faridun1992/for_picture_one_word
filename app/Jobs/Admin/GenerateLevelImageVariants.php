<?php

namespace App\Jobs\Admin;

use App\Services\Admin\LevelImageVariantGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateLevelImageVariants implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $levelImageId,
        public string $expectedStorageKey,
    ) {
        $this->onQueue('images');
    }

    public function backoff(): int
    {
        return 10;
    }

    public function handle(LevelImageVariantGenerator $generator): void
    {
        $generator->generate($this->levelImageId, $this->expectedStorageKey);
    }
}
