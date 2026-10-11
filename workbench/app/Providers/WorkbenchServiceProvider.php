<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // These are the public, fixed credentials of the local QStash development
        // server (npx @upstash/qstash-cli dev), which the integration tests use
        // to deliver jobs to the workbench application served on port 18001.
        config([
            'queue.failed.driver' => 'null',
            'queue.connections.qstash-dev' => [
                'driver' => 'qstash',
                'token' => 'eyJVc2VySUQiOiJkZWZhdWx0VXNlciIsIlBhc3N3b3JkIjoiZGVmYXVsdFBhc3N3b3JkIn0=',
                'current_signing_key' => 'sig_7kYjw48mhY7kAjqNGcy6cr29RJ6r',
                'next_signing_key' => 'sig_5ZB6DVzB1wjE8S6rZ7eenA8Pdnhs',
                'endpoint' => 'http://127.0.0.1:18080',
                'destination' => 'http://127.0.0.1:18001',
                'retry_delay' => 500,
            ],
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Route::view('/', 'welcome');
    }
}
