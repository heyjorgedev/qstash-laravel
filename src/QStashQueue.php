<?php

namespace HeyJorgeDev\QstashLaravel;

use HeyJorgeDev\QStash\Client;
use HeyJorgeDev\QStash\ValueObjects\Message;
use HeyJorgeDev\QStash\ValueObjects\MessageToPublish;
use HeyJorgeDev\QStash\ValueObjects\Url;
use HeyJorgeDev\QstashLaravel\Exceptions\FeatureNotSupportedException;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;

class QStashQueue extends Queue implements QueueContract
{
    public function __construct(
        protected readonly Client $client,
        protected readonly string $defaultQueueName = 'default',
    ) {}

    public function size($queue = null): int
    {
        // TODO: Implement
        return 0;
    }

    public function push($job, $data = '', $queue = null): void
    {
        $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?: $this->defaultQueueName, $data),
            $queue ?: $this->defaultQueueName,
            null,
            function ($payload, $queue) {
                $this->client->messages()->enqueue(
                    $queue,
                    $this->buildQStashMessage($payload)
                );
            }
        );
    }

    public function pushRaw($payload, $queue = null, array $options = []) {}

    public function later($delay, $job, $data = '', $queue = null): void
    {
        $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?: $this->defaultQueueName, $data),
            $queue ?: $this->defaultQueueName,
            $delay,
            function ($payload, $queue, $delay) {
                $this->client->messages()->enqueue(
                    $queue,
                    $this->buildQStashMessage($payload)->withDelay(seconds: $this->secondsUntil($delay))
                );
            }
        );
    }

    public function pop($queue = null)
    {
        throw new FeatureNotSupportedException(
            'The QStash Queue doesn\'t support the pop method since it is a push driver to an http endpoint'
        );
    }

    protected function buildQStashMessage(array|string $payload): MessageToPublish
    {
        // TODO: Implement callback route
        return Message::to(new Url(''))
            ->withPost()
            ->withBody($payload);
    }
}
