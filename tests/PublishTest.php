<?php

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

    it('publishes jobs to qstash', function () {
        $id = Queue::push(new TestJob);

        expect($id)->toBe('msg_123');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://qstash.upstash.io/v2/publish/https://example.com/qstash/qstash/default'
                && $request->hasHeader('Authorization', 'Bearer qstash-token')
                && $request->hasHeader('Content-Type', 'application/json')
                && ! $request->hasHeader('Upstash-Not-Before')
                && $request['displayName'] === TestJob::class;
        });
    });

    it('publishes jobs to the given queue', function () {
        Queue::pushOn('emails', new TestJob);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/qstash/qstash/emails'));
    });

    it('publishes delayed jobs', function () {
        Carbon::setTestNow('2026-01-01 00:00:00');

        Queue::later(60, new TestJob);

        Http::assertSent(fn (Request $request) => $request->hasHeader(
            'Upstash-Not-Before', (string) Carbon::now()->addSeconds(60)->getTimestamp()
        ));
    });

    it('uses the configured endpoint and destination', function () {
        config([
            'queue.connections.qstash.endpoint' => 'https://qstash-us-east-1.upstash.io/',
            'queue.connections.qstash.destination' => 'https://tunnel.test/',
        ]);

        Queue::push(new TestJob);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://qstash-us-east-1.upstash.io/v2/publish/https://tunnel.test/qstash/qstash/default');
    });
});

it('throws when qstash rejects the message', function () {
    Http::fake(['*' => Http::response(['error' => 'invalid token'], 401)]);

    Queue::push(new TestJob);
})->throws(RequestException::class);
