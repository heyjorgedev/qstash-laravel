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
            $this->headers($config),
            $config['after_commit'] ?? null,
        );
    }

    /**
     * Get the QStash headers to publish every message with.
     *
     * @return array<string, mixed>
     */
    protected function headers(array $config): array
    {
        return array_filter([
            'Upstash-Retries' => $config['retries'] ?? null,
            'Upstash-Retry-Delay' => $config['retry_delay'] ?? null,
            'Upstash-Timeout' => $this->duration($config['timeout'] ?? null),
            ...$this->flowControlHeaders($config['flow_control'] ?? []),
        ], filled(...));
    }

    /**
     * Get the QStash flow control headers.
     *
     * @return array<string, string>
     */
    protected function flowControlHeaders(array $config): array
    {
        $value = collect([
            'parallelism' => $config['parallelism'] ?? null,
            'rate' => $config['rate'] ?? null,
            'period' => $this->duration($config['period'] ?? null),
        ])->filter(filled(...))->map(fn ($value, $option) => "{$option}={$value}")->implode(', ');

        if (blank($config['key'] ?? null) || $value === '') {
            return [];
        }

        return [
            'Upstash-Flow-Control-Key' => $config['key'],
            'Upstash-Flow-Control-Value' => $value,
        ];
    }

    /**
     * Format the given duration for QStash, treating numbers as seconds.
     */
    protected function duration(int|string|null $duration): ?string
    {
        return is_numeric($duration) ? "{$duration}s" : $duration;
    }
}
