<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::post('/webhooks/invoices', fn () => 'ok')->middleware('qstash');
});

function webhook(string $uri, string $signature, string $body = '{"id":42}')
{
    return test()->call('POST', $uri, server: ['HTTP_UPSTASH_SIGNATURE' => $signature], content: $body);
}

it('allows requests signed by qstash', function () {
    webhook('https://example.com/webhooks/invoices', signature('{"id":42}', 'https://example.com/webhooks/invoices'))
        ->assertOk()
        ->assertSee('ok');
});

it('verifies the url exactly as it was published', function () {
    $url = 'https://example.com/webhooks/invoices?b=2&a=1';

    webhook($url, signature('{"id":42}', $url))->assertOk();
});

it('rejects requests not signed by qstash', function (string $signature) {
    webhook('https://example.com/webhooks/invoices', $signature)->assertForbidden();
})->with([
    'missing' => '',
    'wrong key' => fn () => signature('{"id":42}', 'https://example.com/webhooks/invoices', 'wrong-key'),
    'wrong url' => fn () => signature('{"id":42}', 'https://example.com/webhooks/other'),
    'wrong body' => fn () => signature('{"id":1}', 'https://example.com/webhooks/invoices'),
]);
