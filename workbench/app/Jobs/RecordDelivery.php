<?php

namespace Workbench\App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class RecordDelivery implements ShouldQueue
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
    public function __construct(
        public string $path,
        public bool $fail = false,
        public bool $crash = false,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->record([
            'event' => 'attempt',
            'attempt' => $this->attempts(),
            'id' => $this->job->getJobId(),
            'queue' => $this->job->getQueue(),
        ]);

        if ($this->fail) {
            throw new Exception('Job failed.');
        }

        // Simulate the PHP process dying before Laravel could handle the job,
        // which leaves QStash to deliver the very same message once again.
        if ($this->crash && $this->attempts() === 1) {
            http_response_code(500);

            exit;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->record(['event' => 'failed']);
    }

    /**
     * Append the given entry to the job's log file.
     */
    protected function record(array $entry): void
    {
        file_put_contents($this->path, json_encode($entry).PHP_EOL, FILE_APPEND);
    }
}
