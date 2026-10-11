<?php

namespace HeyJorgeDev\QstashLaravel;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Support\Arr;

class QStashConnector implements ConnectorInterface
{
    /**
     * Create a new connector instance.
     *
     * @param  array<string, mixed>  $defaults
     */
    public function __construct(
        protected HttpFactory $http,
        protected array $defaults = [],
    ) {
        //
    }

    /**
     * Establish a queue connection.
     *
     * Credentials missing from the connection fall back to the QStash configuration.
     *
     * @return QStashQueue
     */
    public function connect(array $config)
    {
        $config = array_merge($this->defaults, array_filter($config, filled(...)));

        return new QStashQueue(
            QStash::fromConfig($this->http, $config),
            $config['queue'] ?? 'default',
            $config['destination'] ?? null,
            Arr::only($config, ['retries', 'retry_delay', 'timeout', 'flow_control']),
            $config['after_commit'] ?? null,
        );
    }
}
