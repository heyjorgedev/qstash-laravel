<?php

namespace HeyJorgeDev\QstashLaravel;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Support\Traits\Conditionable;

class PendingMessage
{
    use Conditionable, InteractsWithTime;

    /**
     * The headers to publish the message with.
     *
     * @var array<string, mixed>
     */
    protected array $headers = [];

    /**
     * The raw body of the message.
     */
    protected ?string $body = null;

    /**
     * The content type of the message.
     */
    protected string $contentType = 'application/json';

    /**
     * Create a new pending message instance.
     */
    public function __construct(protected QStash $qstash)
    {
        //
    }

    /**
     * Add the given headers to the request QStash makes to the destination.
     *
     * @param  array<string, mixed>  $headers
     * @return $this
     */
    public function withHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $this->headers["Upstash-Forward-{$name}"] = $value;
        }

        return $this;
    }

    /**
     * Use the given raw body for the message.
     *
     * @return $this
     */
    public function withBody(string $content, string $contentType = 'application/json'): static
    {
        $this->body = $content;
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * Set the HTTP method QStash uses to call the destination.
     *
     * @return $this
     */
    public function method(string $method): static
    {
        return $this->withUpstashHeader('Method', strtoupper($method));
    }

    /**
     * Delay the delivery of the message.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return $this
     */
    public function delay($delay): static
    {
        return $this->withUpstashHeader('Not-Before', $this->availableAt($delay));
    }

    /**
     * Set how many times QStash retries a failed delivery, and optionally how long it waits in between.
     *
     * @return $this
     */
    public function retries(int $times, int|string|null $delay = null): static
    {
        return $this->withUpstashHeader('Retries', $times)
            ->when(! is_null($delay), fn () => $this->retryDelay($delay));
    }

    /**
     * Set how long QStash waits between retries.
     *
     * The delay is in milliseconds and may be an expression such as "pow(2, retried) * 1000".
     *
     * @return $this
     */
    public function retryDelay(int|string $delay): static
    {
        return $this->withUpstashHeader('Retry-Delay', $delay);
    }

    /**
     * Set how long QStash waits for the destination to respond, in seconds or as a duration such as "5m".
     *
     * @return $this
     */
    public function timeout(int|string $timeout): static
    {
        return $this->withUpstashHeader('Timeout', $this->duration($timeout));
    }

    /**
     * Have QStash call the given URL with the response of the destination.
     *
     * @return $this
     */
    public function callback(string $url): static
    {
        return $this->withUpstashHeader('Callback', $url);
    }

    /**
     * Have QStash call the given URL once every delivery attempt has failed.
     *
     * @return $this
     */
    public function failureCallback(string $url): static
    {
        return $this->withUpstashHeader('Failure-Callback', $url);
    }

    /**
     * Ignore any other message published with the given ID in the deduplication window.
     *
     * @return $this
     */
    public function deduplicate(string $id): static
    {
        return $this->withUpstashHeader('Deduplication-Id', $id);
    }

    /**
     * Ignore any other message published with the same destination and body in the deduplication window.
     *
     * @return $this
     */
    public function deduplicateByContent(): static
    {
        return $this->withUpstashHeader('Content-Based-Deduplication', 'true');
    }

    /**
     * Limit how many messages sharing the given key are delivered at once, or per period.
     *
     * @return $this
     */
    public function flowControl(string $key, ?int $parallelism = null, ?int $rate = null, int|string|null $period = null): static
    {
        $value = collect([
            'parallelism' => $parallelism,
            'rate' => $rate,
            'period' => is_null($period) ? null : $this->duration($period),
        ])->filter(filled(...))->map(fn ($value, $option) => "{$option}={$value}")->implode(', ');

        if ($value === '') {
            return $this;
        }

        return $this->withUpstashHeader('Flow-Control-Key', $key)
            ->withUpstashHeader('Flow-Control-Value', $value);
    }

    /**
     * Apply the given queue connection style options to the message.
     *
     * @param  array{retries?: int|null, retry_delay?: int|string|null, timeout?: int|string|null, flow_control?: array<string, mixed>}  $options
     * @return $this
     */
    public function withOptions(array $options): static
    {
        $flowControl = $options['flow_control'] ?? [];

        return $this
            ->when(filled($options['retries'] ?? null), fn () => $this->retries((int) $options['retries']))
            ->when(filled($options['retry_delay'] ?? null), fn () => $this->retryDelay($options['retry_delay']))
            ->when(filled($options['timeout'] ?? null), fn () => $this->timeout($options['timeout']))
            ->when(filled($flowControl['key'] ?? null), fn () => $this->flowControl(
                $flowControl['key'],
                filled($flowControl['parallelism'] ?? null) ? (int) $flowControl['parallelism'] : null,
                filled($flowControl['rate'] ?? null) ? (int) $flowControl['rate'] : null,
                $flowControl['period'] ?? null,
            ));
    }

    /**
     * Publish the message to the given URL.
     *
     * @param  array<array-key, mixed>  $data
     * @return string
     *
     * @throws RequestException
     */
    public function publish(string $url, array $data = [])
    {
        return $this->qstash->publishRaw(
            $url,
            $this->body ?? ($data === [] ? '' : json_encode($data)),
            $this->contentType,
            $this->headers,
        );
    }

    /**
     * Set the given QStash header.
     *
     * @return $this
     */
    protected function withUpstashHeader(string $name, mixed $value): static
    {
        $this->headers['Upstash-'.$name] = $value;

        return $this;
    }

    /**
     * Format the given duration for QStash, treating numbers as seconds.
     */
    protected function duration(int|string $duration): string
    {
        return is_numeric($duration) ? "{$duration}s" : $duration;
    }
}
