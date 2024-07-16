<?php

namespace HeyJorgeDev\QstashLaravel;

use HeyJorgeDev\QStash\QStash;
use Illuminate\Queue\Connectors\ConnectorInterface;

class QStashConnector implements ConnectorInterface
{
    public function connect(array $config): QStashQueue
    {
        return new QStashQueue(
            // TODO: Implement read token
            QStash::client(''),
        );
    }
}
