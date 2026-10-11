<?php

use HeyJorgeDev\QstashLaravel\Tests\Fixtures\TestJob;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

const DESTINATION = 'https://example.com/qstash/qstash/default';

beforeEach(function () {
    TestJob::$handled = [];

    Http::fake([
        'qstash.upstash.io/*' => Http::response(['messageId' => 'msg_456']),
    ]);
});

function payload(TestJob $job, array $attributes = []): string
{
    $payload = (fn () => $this->createPayload($job, 'default'))->call(app('queue')->connection('qstash'));

    return json_encode(array_merge(json_decode($payload, true), $attributes));
}

function deliver(string $body, array $headers = [], string $uri = '/qstash/qstash/default')
{
    return test()->call('POST', $uri, server: collect([
        'Upstash-Signature' => signature($body, DESTINATION),
        'Upstash-Message-Id' => 'msg_123',
        'Upstash-Retried' => '0',
        ...$headers,
    ])->mapWithKeys(fn ($value, $key) => ['HTTP_'.strtoupper(str_replace('-', '_', $key)) => $value])->all(), content: $body);
}

it('runs delivered jobs', function () {
    deliver(payload(new TestJob))->assertNoContent();

    expect(TestJob::$handled)->toBe([1]);

    Http::assertNothingSent();
});

it('rejects requests without a valid signature', function (string $signature) {
    deliver(payload(new TestJob), ['Upstash-Signature' => $signature])->assertForbidden();

    expect(TestJob::$handled)->toBeEmpty();
})->with([
    'missing' => '',
    'malformed' => 'not-a-jwt',
    'wrong key' => fn () => signature(payload(new TestJob), DESTINATION, 'wrong-key'),
    'wrong url' => fn () => signature(payload(new TestJob), 'https://example.com/qstash/qstash/emails'),
    'wrong body' => fn () => signature('{}', DESTINATION),
    'wrong issuer' => fn () => signature(payload(new TestJob), DESTINATION, claims: ['iss' => 'Someone']),
    'expired' => fn () => signature(payload(new TestJob), DESTINATION, claims: ['exp' => Carbon::now()->subSecond()->getTimestamp()]),
    'not yet valid' => fn () => signature(payload(new TestJob), DESTINATION, claims: ['nbf' => Carbon::now()->addMinute()->getTimestamp()]),
]);

it('accepts requests signed with the next signing key', function () {
    $body = payload(new TestJob);

    deliver($body, ['Upstash-Signature' => signature($body, DESTINATION, 'next-signing-key')])->assertNoContent();

    expect(TestJob::$handled)->toBe([1]);
});

it('ignores connections that are not using qstash', function () {
    config(['queue.connections.sync' => ['driver' => 'sync']]);

    deliver(payload(new TestJob), uri: '/qstash/sync/default')->assertNotFound();
    deliver(payload(new TestJob), uri: '/qstash/missing/default')->assertNotFound();

    expect(TestJob::$handled)->toBeEmpty();
});

it('hands jobs back to qstash while the application is down', function () {
    Carbon::setTestNow('2026-01-01 00:00:00');

    config(['app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array']);

    $this->app->maintenanceMode()->activate([]);

    $this->get('/')->assertServiceUnavailable();

    deliver($body = payload(new TestJob))->assertNoContent();

    expect(TestJob::$handled)->toBeEmpty();

    Http::assertSent(fn (Request $request) => $request->body() === $body
        && $request->hasHeader('Upstash-Not-Before', (string) Carbon::now()->addMinute()->getTimestamp()));
});

it('releases failed jobs back onto qstash', function () {
    Carbon::setTestNow('2026-01-01 00:00:00');

    deliver(payload(new TestJob(fail: true)))->assertNoContent();

    expect(TestJob::$handled)->toBe([1]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://qstash.upstash.io/v2/publish/'.DESTINATION
        && $request->hasHeader('Upstash-Not-Before', (string) Carbon::now()->addSeconds(30)->getTimestamp())
        && $request['attempts'] === 1);
});

it('counts previous attempts and qstash retries', function () {
    deliver(payload(new TestJob(fail: true), ['attempts' => 1]), ['Upstash-Retried' => '0'])->assertNoContent();

    expect(TestJob::$handled)->toBe([2]);

    Http::assertSent(fn (Request $request) => $request['attempts'] === 2);
});

it('fails jobs that exceeded their attempts', function () {
    Event::fake([JobFailed::class]);

    deliver(payload(new TestJob(fail: true), ['attempts' => 1]), ['Upstash-Retried' => '1'])->assertNoContent();

    expect(TestJob::$handled)->toBe([3]);

    Event::assertDispatched(JobFailed::class);
    Http::assertNothingSent();
});
