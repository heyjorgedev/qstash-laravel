<?php

namespace HeyJorgeDev\QstashLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \HeyJorgeDev\QstashLaravel\PendingMessage message()
 * @method static string publishRaw(string $url, string $body, string $contentType, array $headers = [])
 * @method static bool verify(string $signature, string $body, string $url)
 * @method static \HeyJorgeDev\QstashLaravel\PendingMessage withHeaders(array $headers)
 * @method static \HeyJorgeDev\QstashLaravel\PendingMessage withBody(string $content, string $contentType = 'application/json')
 * @method static \HeyJorgeDev\QstashLaravel\PendingMessage method(string $method)
 * @method static \HeyJorgeDev\QstashLaravel\PendingMessage delay(\DateTimeInterface|\DateInterval|int $delay)
 * @method static \HeyJorgeDev\QstashLaravel\PendingMessage retries(int $times, int|string|null $delay = null)
 * @method static \HeyJorgeDev\QstashLaravel\PendingMessage retryDelay(int|string $delay)
 * @method static \HeyJorgeDev\QstashLaravel\PendingMessage timeout(int|string $timeout)
 * @method static \HeyJorgeDev\QstashLaravel\PendingMessage callback(string $url)
 * @method static \HeyJorgeDev\QstashLaravel\PendingMessage failureCallback(string $url)
 * @method static \HeyJorgeDev\QstashLaravel\PendingMessage deduplicate(string $id)
 * @method static \HeyJorgeDev\QstashLaravel\PendingMessage deduplicateByContent()
 * @method static \HeyJorgeDev\QstashLaravel\PendingMessage flowControl(string $key, int|null $parallelism = null, int|null $rate = null, int|string|null $period = null)
 * @method static string publish(string $url, array $data = [])
 *
 * @see \HeyJorgeDev\QstashLaravel\QStash
 */
class QStash extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \HeyJorgeDev\QstashLaravel\QStash::class;
    }
}
