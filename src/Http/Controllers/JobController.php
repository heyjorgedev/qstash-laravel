<?php

namespace HeyJorgeDev\QstashLaravel\Http\Controllers;

use HeyJorgeDev\QstashLaravel\Jobs\QStashJob;
use HeyJorgeDev\QstashLaravel\QStashQueue;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\MaintenanceMode;
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
        protected MaintenanceMode $maintenance,
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

        // Queue workers pause while the application is down for maintenance, so we
        // will hand the job back to QStash to be delivered again in a minute. It
        // is published unchanged, which means this does not count as an attempt.
        if ($this->maintenance->active()) {
            $qstash->pushRaw($request->getContent(), $queue, ['delay' => 60]);

            return new Response(status: 204);
        }

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
