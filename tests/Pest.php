<?php

use HeyJorgeDev\QstashLaravel\Tests\TestCase;
use Illuminate\Support\Carbon;

uses(TestCase::class)->in(__DIR__);

function base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function signature(string $body, string $url, string $key = 'current-signing-key', array $claims = []): string
{
    $header = base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

    $payload = base64url(json_encode(array_merge([
        'iss' => 'Upstash',
        'sub' => $url,
        'exp' => Carbon::now()->addMinutes(5)->getTimestamp(),
        'nbf' => Carbon::now()->getTimestamp(),
        'iat' => Carbon::now()->getTimestamp(),
        'jti' => 'jwt_123',
        'body' => base64url(hash('sha256', $body, true)),
    ], $claims)));

    return "{$header}.{$payload}.".base64url(hash_hmac('sha256', "{$header}.{$payload}", $key, true));
}
