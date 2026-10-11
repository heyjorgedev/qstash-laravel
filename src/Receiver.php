<?php

namespace HeyJorgeDev\QstashLaravel;

use Illuminate\Support\Carbon;

class Receiver
{
    /**
     * Create a new receiver instance.
     *
     * @param  array<int, string>  $keys
     */
    public function __construct(protected array $keys)
    {
        //
    }

    /**
     * Determine if the given signature is valid for the request body and URL.
     */
    public function verify(string $signature, string $body, string $url): bool
    {
        $segments = explode('.', $signature);

        if (count($segments) !== 3) {
            return false;
        }

        [$header, $payload, $hash] = $segments;

        if (! $this->signedWithAnyKey("{$header}.{$payload}", $hash)) {
            return false;
        }

        $claims = json_decode($this->decode($payload), true);

        if (! is_array($claims) || ! is_string($claims['body'] ?? null)) {
            return false;
        }

        $now = Carbon::now()->getTimestamp();

        return ($claims['iss'] ?? null) === 'Upstash'
            && ($claims['sub'] ?? null) === $url
            && ($claims['exp'] ?? 0) > $now
            && ($claims['nbf'] ?? PHP_INT_MAX) <= $now
            && hash_equals(rtrim($claims['body'], '='), $this->encode(hash('sha256', $body, true)));
    }

    /**
     * Determine if the given value was signed with any of the signing keys.
     */
    protected function signedWithAnyKey(string $value, string $hash): bool
    {
        foreach ($this->keys as $key) {
            if (hash_equals($this->encode(hash_hmac('sha256', $value, $key, true)), rtrim($hash, '='))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Encode the given value using URL safe Base64.
     */
    protected function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Decode the given URL safe Base64 value.
     */
    protected function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}
