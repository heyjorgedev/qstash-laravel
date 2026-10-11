<?php

namespace HeyJorgeDev\QstashLaravel\Jobs;

use HeyJorgeDev\QstashLaravel\QStashQueue;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;

class QStashJob extends Job implements JobContract
{
    /**
     * The raw job payload delivered by QStash.
     *
     * @var string
     */
    protected $job;

    /**
     * Create a new job instance.
     *
     * @param  string  $job
     * @param  string  $connectionName
     * @param  string  $queue
     */
    public function __construct(
        Container $container,
        protected QStashQueue $qstash,
        $job,
        protected string $messageId,
        protected int $retried,
        $connectionName,
        $queue,
    ) {
        $this->job = $job;
        $this->queue = $queue;
        $this->container = $container;
        $this->connectionName = $connectionName;
    }

    /**
     * Release the job back into the queue after (n) seconds.
     *
     * @param  int  $delay
     * @return void
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        $this->qstash->release($this->queue, $this, $delay);
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * Attempts made before the job was released are stored on the payload, while
     * QStash tells us how many times it has retried this particular delivery.
     *
     * @return int
     */
    public function attempts()
    {
        return ($this->payload()['attempts'] ?? 0) + $this->retried + 1;
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->messageId;
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->job;
    }
}
