<?php

namespace HeyJorgeDev\QstashLaravel\Tests;

use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use WithWorkbench;

    protected function defineEnvironment($app)
    {
        $app['config']->set('app.url', 'https://example.com');
        $app['config']->set('qstash.token', 'default-token');
        $app['config']->set('qstash.current_signing_key', 'current-signing-key');
        $app['config']->set('qstash.next_signing_key', 'next-signing-key');
        $app['config']->set('qstash.endpoint', 'https://qstash.upstash.io');
        $app['config']->set('queue.default', 'qstash');
        $app['config']->set('queue.failed.driver', 'null');
        $app['config']->set('queue.connections.qstash', [
            'driver' => 'qstash',
            'token' => 'qstash-token',
            'current_signing_key' => 'current-signing-key',
            'next_signing_key' => 'next-signing-key',
            'queue' => 'default',
        ]);
    }
}
