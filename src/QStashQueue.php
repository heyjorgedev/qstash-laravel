<?php

namespace HeyJorgeDev\QstashLaravel;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;

class QStashQueue extends Queue implements QueueContract
{
    public function __construct(protected readonly string $defaultQueueName = 'default') {}

    public function size($queue = null)
    {
        // TODO: Implement size() method.
    }

    public function push($job, $data = '', $queue = null)
    {
        $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?: $this->defaultQueueName, $data),
            $queue,
            null,
            function ($payload, $queue) {
                // TODO: Implement
            }
        );
    }

    public function pushRaw($payload, $queue = null, array $options = []) {}

    public function later($delay, $job, $data = '', $queue = null)
    {
        $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?: $this->defaultQueueName, $data),
            $queue,
            $delay,
            function ($payload, $queue) {
                // TODO: Implement
            }
        );
    }

    public function pop($queue = null)
    {
        // TODO: Implement pop() method.
    }
}
