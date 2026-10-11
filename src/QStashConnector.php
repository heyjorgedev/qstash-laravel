<?php

namespace HeyJorgeDev\QstashLaravel;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Queue\Connectors\ConnectorInterface;

class QStashConnector implements ConnectorInterface
{
    /**
     * Create a new connector instance.
     */
    public function __construct(protected HttpFactory $http)
    {
        //
    }

    /**
     * Establish a queue connection.
     *
     * @return QStashQueue
     */
    public function connect(array $config)
    {
        return new QStashQueue(
            $this->http,
            $config['token'] ?? '',
            new Receiver(array_filter([
                $config['current_signing_key'] ?? null,
                $config['next_signing_key'] ?? null,
            ])),
            $config['queue'] ?? 'default',
            $config['endpoint'] ?? 'https://qstash.upstash.io',
            $config['destination'] ?? null,
            $config['after_commit'] ?? null,
        );
    }
}
