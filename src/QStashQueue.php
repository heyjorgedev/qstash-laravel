<?php

namespace HeyJorgeDev\QstashLaravel;

use Illuminate\Queue\Queue;
use RectorPrefix202407\Illuminate\Contracts\Queue\Queue as QueueContract;

class QStashQueue extends Queue implements QueueContract
{
    public function __construct() {}

    public function size($queue = null)
    {
        // TODO: Implement size() method.
    }

    public function push($job, $data = '', $queue = null)
    {
        // TODO: Implement push() method.
    }

    public function pushRaw($payload, $queue = null, array $options = [])
    {
        // TODO: Implement pushRaw() method.
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        // TODO: Implement later() method.
    }

    public function pop($queue = null)
    {
        // TODO: Implement pop() method.
    }
}
