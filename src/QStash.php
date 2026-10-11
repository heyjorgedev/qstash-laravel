<?php

namespace HeyJorgeDev\QstashLaravel;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Traits\ForwardsCalls;

/**
 * @mixin PendingMessage
 */
class QStash
{
    use ForwardsCalls;

    /**
     * Create a new QStash client instance.
     */
    public function __construct(
        protected HttpFactory $http,
        protected string $token,
        protected Receiver $receiver,
        protected string $endpoint = 'https://qstash.upstash.io',
    ) {
        //
    }

    /**
     * Create a new QStash client instance from the given configuration.
     */
    public static function fromConfig(HttpFactory $http, array $config): self
    {
        return new self(
            $http,
            $config['token'] ?? '',
            new Receiver(array_filter([
                $config['current_signing_key'] ?? null,
                $config['next_signing_key'] ?? null,
            ])),
            $config['endpoint'] ?? 'https://qstash.upstash.io',
        );
    }

    /**
     * Create a new pending message.
     */
    public function message(): PendingMessage
    {
        return new PendingMessage($this);
    }

    /**
     * Publish a raw message to the given URL.
     *
     * @param  array<string, mixed>  $headers
     * @return string
     *
     * @throws RequestException
     */
    public function publishRaw(string $url, string $body, string $contentType, array $headers = [])
    {
        return $this->http
            ->withToken($this->token)
            ->withHeaders($headers)
            ->withBody($body, $contentType)
            ->post(rtrim($this->endpoint, '/').'/v2/publish/'.$url)
            ->throw()
            ->json('messageId');
    }

    /**
     * Determine if the given signature is valid for the request body and URL.
     */
    public function verify(string $signature, string $body, string $url): bool
    {
        return $this->receiver->verify($signature, $body, $url);
    }

    /**
     * Dynamically pass methods to a new pending message.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->message(), $method, $parameters);
    }
}
