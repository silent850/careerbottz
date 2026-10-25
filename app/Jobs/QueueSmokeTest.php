<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class QueueSmokeTest implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cache::put('careerbottz:queue_smoke_test', 'ok', 300);
    }
}
