<?php

namespace Workbench\App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class RecordAttempts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 2;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $path, public bool $fail = false)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->record("attempt {$this->attempts()}");

        if ($this->fail) {
            throw new Exception('Job failed.');
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->record('failed');
    }

    /**
     * Append the given line to the job's log file.
     */
    protected function record(string $line): void
    {
        file_put_contents($this->path, $line.PHP_EOL, FILE_APPEND);
    }
}
