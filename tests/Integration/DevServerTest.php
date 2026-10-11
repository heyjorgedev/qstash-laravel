<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Workbench\App\Jobs\RecordAttempts;

uses()->group('integration');

/*
|--------------------------------------------------------------------------
| QStash Development Server
|--------------------------------------------------------------------------
|
| These tests publish jobs to the local QStash development server, which
| delivers them back to the workbench application over real HTTP. Both
| servers are started before the tests run and stopped afterwards.
|
*/

$servers = [];

beforeAll(function () use (&$servers) {
    if (! (new ExecutableFinder)->find('npx')) {
        return;
    }

    $servers = [
        'http://127.0.0.1:18080/v2/keys' => new Process([
            'npx', '--yes', '@upstash/qstash-cli@2.40.12', 'dev', '-port', '18080',
        ]),
        'http://127.0.0.1:18001' => new Process([
            PHP_BINARY, 'vendor/bin/testbench', 'serve', '--host=127.0.0.1', '--port=18001', '--no-reload',
        ], dirname(__DIR__, 2)),
    ];

    foreach ($servers as $url => $server) {
        if (listening($url)) {
            throw new RuntimeException("Another server is already listening on [{$url}].");
        }

        $server->start();
    }

    foreach ($servers as $url => $server) {
        waitUntilReady($url, $server);
    }
});

afterAll(function () use (&$servers) {
    foreach ($servers as $server) {
        $server->stop();
    }
});

beforeEach(function () {
    if (! (new ExecutableFinder)->find('npx')) {
        $this->markTestSkipped('The QStash development server requires npx.');
    }

    $this->path = sys_get_temp_dir().'/qstash-'.uniqid().'.log';
});

afterEach(function () {
    @unlink($this->path);
});

it('delivers jobs to the application', function () {
    RecordAttempts::dispatch($this->path)->onConnection('qstash-dev');

    expect(linesOf($this->path, 1))->toBe(['attempt 1']);
});

it('delivers delayed jobs once the delay has passed', function () {
    $dispatchedAt = microtime(true);

    RecordAttempts::dispatch($this->path)->onConnection('qstash-dev')->delay(2);

    expect(linesOf($this->path, 1))->toBe(['attempt 1'])
        ->and(microtime(true) - $dispatchedAt)->toBeGreaterThan(1);
});

it('retries failing jobs before failing them', function () {
    RecordAttempts::dispatch($this->path, fail: true)->onConnection('qstash-dev');

    expect(linesOf($this->path, 3))->toBe(['attempt 1', 'attempt 2', 'failed']);
});

function listening(string $url): bool
{
    if (! is_resource($socket = @fsockopen(parse_url($url, PHP_URL_HOST), parse_url($url, PHP_URL_PORT), timeout: 0.2))) {
        return false;
    }

    fclose($socket);

    return true;
}

/**
 * Determine if the given URL responds successfully.
 *
 * The development server accepts connections slightly before it accepts its
 * own token, so we wait for an authenticated request to succeed instead.
 */
function responds(string $url): bool
{
    try {
        return (new Factory)
            ->withToken('eyJVc2VySUQiOiJkZWZhdWx0VXNlciIsIlBhc3N3b3JkIjoiZGVmYXVsdFBhc3N3b3JkIn0=')
            ->timeout(1)
            ->get($url)
            ->successful();
    } catch (ConnectionException) {
        return false;
    }
}

function waitUntilReady(string $url, Process $server, int $timeout = 60): void
{
    $deadline = microtime(true) + $timeout;

    while (! responds($url)) {
        if (! $server->isRunning() || microtime(true) > $deadline) {
            throw new RuntimeException("Server for [{$url}] did not start.\n".$server->getOutput().$server->getErrorOutput());
        }

        usleep(100_000);
    }
}

function linesOf(string $path, int $count, int $timeout = 15): array
{
    $deadline = microtime(true) + $timeout;

    do {
        $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];

        if (count($lines) >= $count) {
            break;
        }

        usleep(100_000);
    } while (microtime(true) < $deadline);

    return $lines;
}
