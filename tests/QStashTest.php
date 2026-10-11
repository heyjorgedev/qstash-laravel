<?php

use HeyJorgeDev\QstashLaravel\Facades\QStash;
use HeyJorgeDev\QstashLaravel\Tests\Fixtures\TestJob;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

describe('when qstash accepts the message', function () {
    beforeEach(function () {
        Http::fake(['*' => Http::response(['messageId' => 'msg_123'])]);
    });

    it('publishes data to any url', function () {
        expect(QStash::publish('https://billing.test/hooks', ['id' => 42]))->toBe('msg_123');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://qstash.upstash.io/v2/publish/https://billing.test/hooks'
            && $request->hasHeader('Authorization', 'Bearer default-token')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->body() === '{"id":42}');
    });

    it('publishes raw bodies', function () {
        QStash::withBody('<invoice id="42" />', 'application/xml')->publish('https://billing.test/hooks');

        Http::assertSent(fn (Request $request) => $request->hasHeader('Content-Type', 'application/xml')
            && $request->body() === '<invoice id="42" />');
    });

    it('forwards headers to the destination', function () {
        QStash::withHeaders(['X-Signature' => 'abc'])->publish('https://billing.test/hooks');

        Http::assertSent(fn (Request $request) => $request->hasHeader('Upstash-Forward-X-Signature', 'abc')
            && ! $request->hasHeader('X-Signature'));
    });

    it('publishes messages with delivery options', function () {
        Carbon::setTestNow('2026-01-01 00:00:00');

        QStash::method('put')
            ->delay(60)
            ->retries(5, 'pow(2, retried) * 1000')
            ->timeout('5m')
            ->callback('https://example.com/delivered')
            ->failureCallback('https://example.com/failed')
            ->deduplicate('invoice-42')
            ->flowControl('hooks', parallelism: 10, period: 60)
            ->publish('https://billing.test/hooks');

        Http::assertSent(fn (Request $request) => $request->hasHeader('Upstash-Method', 'PUT')
            && $request->hasHeader('Upstash-Not-Before', (string) Carbon::now()->addMinute()->getTimestamp())
            && $request->hasHeader('Upstash-Retries', '5')
            && $request->hasHeader('Upstash-Retry-Delay', 'pow(2, retried) * 1000')
            && $request->hasHeader('Upstash-Timeout', '5m')
            && $request->hasHeader('Upstash-Callback', 'https://example.com/delivered')
            && $request->hasHeader('Upstash-Failure-Callback', 'https://example.com/failed')
            && $request->hasHeader('Upstash-Deduplication-Id', 'invoice-42')
            && $request->hasHeader('Upstash-Flow-Control-Key', 'hooks')
            && $request->hasHeader('Upstash-Flow-Control-Value', 'parallelism=10, period=60s'));
    });

    it('deduplicates messages by their content', function () {
        QStash::deduplicateByContent()->publish('https://billing.test/hooks', ['id' => 42]);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Upstash-Content-Based-Deduplication', 'true'));
    });

    it('uses the qstash credentials for connections without their own', function () {
        config(['queue.connections.minimal' => ['driver' => 'qstash', 'token' => null]]);

        Queue::connection('minimal')->push(new TestJob);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://qstash.upstash.io/v2/publish/https://example.com/qstash/minimal/default'
            && $request->hasHeader('Authorization', 'Bearer default-token'));
    });
});

it('throws when qstash rejects the message', function () {
    Http::fake(['*' => Http::response(['error' => 'invalid token'], 401)]);

    QStash::publish('https://billing.test/hooks');
})->throws(RequestException::class);

it('can be faked after the client was resolved', function () {
    QStash::getFacadeRoot();

    Http::fake(['*' => Http::response(['messageId' => 'msg_fake'])]);

    expect(QStash::publish('https://billing.test/hooks'))->toBe('msg_fake');
});
