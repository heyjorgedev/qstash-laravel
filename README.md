# QStash Queue Driver for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/heyjorgedev/qstash-laravel.svg?style=flat-square)](https://packagist.org/packages/heyjorgedev/qstash-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/heyjorgedev/qstash-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/heyjorgedev/qstash-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/heyjorgedev/qstash-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/heyjorgedev/qstash-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/heyjorgedev/qstash-laravel.svg?style=flat-square)](https://packagist.org/packages/heyjorgedev/qstash-laravel)

A Laravel queue driver for [Upstash QStash](https://upstash.com/docs/qstash). Dispatch jobs as you normally would and let QStash deliver them back to your application over HTTP, with no `queue:work` process to run.

## How It Works

QStash is a push-based HTTP queue. Instead of a worker polling for jobs, QStash calls your application:

1. When a job is dispatched, the driver publishes the serialized job payload to QStash, with a destination URL pointing back at your application.
2. QStash makes a signed `POST` request to that URL.
3. The package verifies the `Upstash-Signature` JWT (HS256, signed with your current or next signing key), checking the issuer, expiry, destination URL and SHA-256 hash of the body.
4. The job is run through Laravel's queue worker, so job events, `$tries`, `$backoff`, `$maxExceptions`, failed jobs and the `failed()` method all work as normal.

Because jobs are pushed to you, your application must be reachable over HTTP by QStash.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

Install the package via Composer:

```bash
composer require heyjorgedev/qstash-laravel
```

The service provider is registered automatically. Optionally, publish the configuration file:

```bash
php artisan vendor:publish --tag="qstash-config"
```

This publishes `config/qstash.php`:

```php
return [
    /*
    | The URI path QStash will deliver jobs to. Jobs are delivered to
    | "{path}/{connection}/{queue}" on your application.
    */
    'path' => env('QSTASH_PATH', 'qstash'),
];
```

## Configuration

Add a `qstash` connection to your `config/queue.php` file:

```php
'qstash' => [
    'driver' => 'qstash',
    'token' => env('QSTASH_TOKEN'),
    'current_signing_key' => env('QSTASH_CURRENT_SIGNING_KEY'),
    'next_signing_key' => env('QSTASH_NEXT_SIGNING_KEY'),
    'queue' => env('QSTASH_QUEUE', 'default'),
    'endpoint' => env('QSTASH_URL', 'https://qstash.upstash.io'),
    'destination' => env('QSTASH_DESTINATION_URL'),
    'retries' => env('QSTASH_RETRIES'),
    'retry_delay' => env('QSTASH_RETRY_DELAY'),
    'after_commit' => false,
],
```

Then set your credentials, found in the [Upstash console](https://console.upstash.com/qstash), in your `.env` file:

```ini
QUEUE_CONNECTION=qstash

QSTASH_TOKEN=
QSTASH_CURRENT_SIGNING_KEY=
QSTASH_NEXT_SIGNING_KEY=
```

The `endpoint` option is the QStash API base URL. Change it if your QStash instance lives in another region (for example, `https://qstash-us-east-1.upstash.io`) or when using the local development server.

The `destination` option is the public base URL QStash should call. It defaults to your `app.url`. Jobs are delivered to `{destination}/{path}/{connection}/{queue}`, for example:

```
https://example.com/qstash/qstash/default
```

The `retries` and `retry_delay` options are sent to QStash as the `Upstash-Retries` and `Upstash-Retry-Delay` headers, and control how QStash redelivers a job when your application fails to respond successfully. The delay is in milliseconds and may be a QStash expression such as `pow(2, retried) * 1000`. When omitted, your QStash plan's defaults apply.

## Usage

Dispatch jobs exactly as you would with any other queue driver:

```php
ProcessPodcast::dispatch($podcast);

ProcessPodcast::dispatch($podcast)->onQueue('emails');

ProcessPodcast::dispatch($podcast)->delay(now()->addMinutes(10));
```

Delayed jobs use QStash's `Upstash-Not-Before` header. The maximum delay depends on your QStash plan.

## Retries & Failures

Laravel's retry semantics apply. When a job is released, either manually or via `$backoff` after an exception, the driver publishes it to QStash again with the appropriate delay.

The endpoint responds with a `2xx` status once Laravel has handled the job, whether it succeeded, was released or failed, so QStash does not duplicate Laravel's retries. QStash's own retries only kick in if a request fails without Laravel handling it, such as when the PHP process crashes or times out. Those deliveries count towards the job's attempts, and their timing can be tuned with the `retries` and `retry_delay` options.

## Things to Know

- **Execution time.** Jobs run inside an HTTP request, so they are bound by your web server and PHP `max_execution_time` limits, as well as QStash's request timeout for your plan. The job `$timeout` property is not enforced, since there is no `pcntl` alarm in an HTTP context. Long-running jobs belong on a different queue connection.
- **Queue size.** `Queue::size()` and related methods always return `0`, as QStash does not expose these counts for published messages.
- **Middleware.** The route is registered without the `web` middleware group, so CSRF protection does not apply. Do not put it behind authentication: the signature is the authentication.
- **Invalid signatures.** Requests with a missing or invalid signature receive a `403` response.

## Local Development

QStash cannot reach `localhost`, so you have two options:

1. **Use a tunnel.** Expose your application with a tool such as [ngrok](https://ngrok.com), [Expose](https://expose.dev) or [cloudflared](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/), then set `QSTASH_DESTINATION_URL` to the tunnel URL.
2. **Run QStash locally.** Start Upstash's local QStash server:

    ```bash
    npx @upstash/qstash-cli dev
    ```

    Then set `QSTASH_URL=http://127.0.0.1:8080` and use the token and signing keys it prints.

## Testing

```bash
composer test
```

The integration tests deliver real jobs through the local QStash development server to a workbench application. They start both servers themselves on ports `18080` and `18001`, and require Node.js (`npx`):

```bash
composer test-integration
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jorge Lapa](https://github.com/heyjorgedev)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
