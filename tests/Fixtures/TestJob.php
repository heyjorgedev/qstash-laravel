<?php

namespace HeyJorgeDev\QstashLaravel\Tests\Fixtures;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class TestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public static array $handled = [];

    public $tries = 3;

    public $backoff = 30;

    public $timeout;

    public function __construct(public bool $fail = false)
    {
        //
    }

    public function handle(): void
    {
        static::$handled[] = $this->attempts();

        if ($this->fail) {
            throw new Exception('Job failed.');
        }
    }
}
