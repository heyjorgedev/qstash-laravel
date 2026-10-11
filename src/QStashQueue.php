<?php

namespace HeyJorgeDev\QstashLaravel;

use HeyJorgeDev\QstashLaravel\Jobs\QStashJob;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Queue;
use LogicException;

class QStashQueue extends Queue implements QueueContract
{
    /**
     * Create a new QStash queue instance.
     */
    public function __construct(
        protected HttpFactory $http,
        protected string $token,
        protected Receiver $receiver,
        protected string $default = 'default',
        protected string $endpoint = 'https://qstash.upstash.io',
        protected ?string $destination = null,
        ?bool $dispatchAfterCommit = false,
    ) {
        $this->dispatchAfterCommit = $dispatchAfterCommit;
    }

    /**
     * Get the size of the queue.
     *
     * QStash does not expose the number of pending messages for a destination.
     *
     * @param  string|null  $queue
     * @return int
     */
    public function size($queue = null)
    {
        return 0;
    }

    /**
     * Get the number of pending jobs.
     *
     * @param  string|null  $queue
     * @return int
     */
    public function pendingSize($queue = null)
    {
        return 0;
    }

    /**
     * Get the number of delayed jobs.
     *
     * @param  string|null  $queue
     * @return int
     */
    public function delayedSize($queue = null)
    {
        return 0;
    }

    /**
     * Get the number of reserved jobs.
     *
     * @param  string|null  $queue
     * @return int
     */
    public function reservedSize($queue = null)
    {
        return 0;
    }

    /**
     * Get the creation timestamp of the oldest pending job, excluding delayed jobs.
     *
     * @param  string|null  $queue
     * @return int|null
     */
    public function creationTimeOfOldestPendingJob($queue = null)
    {
        return null;
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string|object  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            function ($payload, $queue) {
                return $this->pushRaw($payload, $queue);
            }
        );
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string|null  $queue
     * @return string
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->publish($payload, $this->getQueue($queue));
    }

    /**
     * Push a new job onto the queue after (n) seconds.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string|object  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            $delay,
            function ($payload, $queue, $delay) {
                return $this->publish($payload, $this->getQueue($queue), $delay);
            }
        );
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string|null  $queue
     * @return Job|null
     *
     * @throws LogicException
     */
    public function pop($queue = null)
    {
        throw new LogicException('QStash delivers jobs to your application over HTTP, so jobs may not be popped.');
    }

    /**
     * Release a job back onto the queue after (n) seconds.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return string
     */
    public function release(string $queue, QStashJob $job, $delay)
    {
        $payload = json_encode(
            ['attempts' => $job->attempts()] + $job->payload(), JSON_UNESCAPED_UNICODE
        );

        return $this->publish($payload, $queue, $delay);
    }

    /**
     * Publish a payload to QStash.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return string
     *
     * @throws RequestException
     */
    protected function publish(string $payload, string $queue, $delay = 0)
    {
        return $this->http
            ->withToken($this->token)
            ->when($delay, fn (PendingRequest $request) => $request->withHeaders([
                'Upstash-Not-Before' => $this->availableAt($delay),
            ]))
            ->withBody($payload, 'application/json')
            ->post(rtrim($this->endpoint, '/').'/v2/publish/'.$this->destination($queue))
            ->throw()
            ->json('messageId');
    }

    /**
     * Get the URL QStash should deliver jobs on the given queue to.
     */
    public function destination(string $queue): string
    {
        $path = $this->container['url']->route('qstash.job', [
            'connection' => $this->getConnectionName(),
            'queue' => $queue,
        ], false);

        return rtrim($this->destination ?? $this->container['config']['app.url'], '/').$path;
    }

    /**
     * Get the queue or return the default.
     *
     * @param  string|null  $queue
     * @return string
     */
    public function getQueue($queue)
    {
        return $queue ?: $this->default;
    }

    /**
     * Get the receiver used to verify incoming requests.
     */
    public function receiver(): Receiver
    {
        return $this->receiver;
    }
}
