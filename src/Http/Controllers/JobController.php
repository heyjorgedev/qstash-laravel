<?php

namespace HeyJorgeDev\QstashLaravel\Http\Controllers;

use HeyJorgeDev\QstashLaravel\Jobs\QStashJob;
use HeyJorgeDev\QstashLaravel\QStashQueue;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\WorkerOptions;
use Throwable;

class JobController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected Container $container,
        protected QueueManager $manager,
        protected ExceptionHandler $exceptions,
    ) {
        //
    }

    /**
     * Run the job QStash delivered.
     *
     * Once the worker has processed the job it has either been deleted, released
     * back onto QStash, or marked as failed, so we always acknowledge delivery
     * to prevent QStash from retrying a job Laravel has already handled.
     */
    public function __invoke(Request $request, string $connection, string $queue): Response
    {
        /** @var QStashQueue $qstash */
        $qstash = $this->manager->connection($connection);

        $job = new QStashJob(
            $this->container,
            $qstash,
            $request->getContent(),
            (string) $request->header('Upstash-Message-Id'),
            (int) $request->header('Upstash-Retried', '0'),
            $connection,
            $queue,
        );

        try {
            $this->container->make('queue.worker')->process($connection, $job, new WorkerOptions);
        } catch (Throwable $e) {
            $this->exceptions->report($e);
        }

        return new Response(status: 204);
    }
}
