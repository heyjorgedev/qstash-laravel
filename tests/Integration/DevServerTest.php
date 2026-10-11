<?php

use HeyJorgeDev\QstashLaravel\QStash;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Workbench\App\Jobs\RecordDelivery;

use function Orchestra\Testbench\default_skeleton_path;

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
            PHP_BINARY, '-S', '127.0.0.1:18001', '-t', default_skeleton_path('public'), default_skeleton_path('server.php'),
        ], env: [
            'PHP_CLI_SERVER_WORKERS' => 4,
            'TESTBENCH_WORKING_PATH' => dirname(__DIR__, 2),
        ]),
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
        // Neither the built-in PHP server nor npx stop the processes they started
        // when they are stopped themselves, so we will stop those processes too.
        Process::fromShellCommandline('pkill -P '.$server->getPid())->run();

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
    $id = Queue::connection('qstash-dev')->push(new RecordDelivery($this->path));

    expect(deliveriesOf($this->path, 1)[0])->toMatchArray([
        'event' => 'attempt', 'attempt' => 1, 'id' => $id, 'queue' => 'default',
    ]);
});

it('delivers jobs on the given queue', function () {
    Queue::connection('qstash-dev')->pushOn('emails', new RecordDelivery($this->path));

    expect(deliveriesOf($this->path, 1)[0]['queue'])->toBe('emails');
});

it('delivers delayed jobs once the delay has passed', function () {
    $dispatchedAt = microtime(true);

    Queue::connection('qstash-dev')->later(2, new RecordDelivery($this->path));

    expect(deliveriesOf($this->path, 1)[0]['attempt'])->toBe(1)
        ->and(microtime(true) - $dispatchedAt)->toBeGreaterThan(1);
});

it('retries failing jobs before failing them', function () {
    Queue::connection('qstash-dev')->push(new RecordDelivery($this->path, fail: true));

    $deliveries = deliveriesOf($this->path, 3);

    expect(array_column($deliveries, 'event'))->toBe(['attempt', 'attempt', 'failed'])
        ->and(array_column($deliveries, 'attempt'))->toBe([1, 2]);
});

it('counts qstash redeliveries as attempts', function () {
    $id = Queue::connection('qstash-dev')->push(new RecordDelivery($this->path, crash: true));

    $deliveries = deliveriesOf($this->path, 2);

    expect(array_column($deliveries, 'attempt'))->toBe([1, 2])
        ->and(array_column($deliveries, 'id'))->toBe([$id, $id]);
});

it('delivers jobs again once they exceed their timeout', function () {
    Queue::connection('qstash-dev')->push(new RecordDelivery($this->path, sleep: 3, timeout: 1));

    expect(array_column(deliveriesOf($this->path, 2), 'attempt'))->toBe([1, 2]);
});

it('limits the parallelism of deliveries using flow control', function () {
    Queue::connection('qstash-dev-serial')->push(new RecordDelivery($this->path, sleep: 1));
    Queue::connection('qstash-dev-serial')->push(new RecordDelivery($this->path, sleep: 1));

    [$first, $second] = deliveriesOf($this->path, 2);

    expect($second['at'] - $first['at'])->toBeGreaterThanOrEqual(1);
});

it('delivers jobs in parallel without flow control', function () {
    Queue::connection('qstash-dev')->push(new RecordDelivery($this->path, sleep: 1));
    Queue::connection('qstash-dev')->push(new RecordDelivery($this->path, sleep: 1));

    [$first, $second] = deliveriesOf($this->path, 2);

    expect($second['at'] - $first['at'])->toBeLessThan(1);
});

it('dispatches chained jobs from delivered jobs', function () {
    Bus::chain([
        new RecordDelivery($this->path),
        new RecordDelivery($this->path),
    ])->onConnection('qstash-dev')->dispatch();

    $deliveries = deliveriesOf($this->path, 2);

    expect(array_column($deliveries, 'attempt'))->toBe([1, 1])
        ->and($deliveries[0]['id'])->not->toBe($deliveries[1]['id']);
});

it('publishes messages to any url', function () {
    qstash()->withHeaders(['X-Webhook' => 'invoice.paid'])->publish(recordingUrl($this->path), ['id' => 42]);

    expect(deliveriesOf($this->path, 1))->toBe([
        ['body' => '{"id":42}', 'webhook' => 'invoice.paid'],
    ]);
});

it('calls back with the response of the destination', function () {
    qstash()->callback(recordingUrl($callback = $this->path.'-callback'))->publish(recordingUrl($this->path), ['id' => 42]);

    expect(deliveriesOf($callback, 1))->toHaveCount(1);

    @unlink($callback);
});

it('rejects requests that are not signed by qstash', function () {
    expect(Http::post(recordingUrl($this->path), ['id' => 42])->status())->toBe(403);
});

function qstash(): QStash
{
    return QStash::fromConfig(app(Factory::class), config('queue.connections.qstash-dev'));
}

function recordingUrl(string $path): string
{
    return 'http://127.0.0.1:18001/webhooks/record?log='.basename($path);
}

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

function deliveriesOf(string $path, int $count, int $timeout = 15): array
{
    $deadline = microtime(true) + $timeout;

    do {
        $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];

        if (count($lines) >= $count) {
            break;
        }

        usleep(100_000);
    } while (microtime(true) < $deadline);

    return array_map(fn ($line) => json_decode($line, true), $lines);
}
