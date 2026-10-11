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
