<?php

namespace HeyJorgeDev\QstashLaravel;

use Illuminate\Queue\Connectors\ConnectorInterface;

class QStashConnector implements ConnectorInterface
{
    public function connect(array $config): QStashQueue
    {
        return new QStashQueue();
    }
}
