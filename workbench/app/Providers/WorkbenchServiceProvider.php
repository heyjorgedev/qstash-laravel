<?php

namespace Workbench\App\Providers;

use Illuminate\Http\Request;
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
        $connection = [
            'driver' => 'qstash',
            'token' => 'eyJVc2VySUQiOiJkZWZhdWx0VXNlciIsIlBhc3N3b3JkIjoiZGVmYXVsdFBhc3N3b3JkIn0=',
            'current_signing_key' => 'sig_7kYjw48mhY7kAjqNGcy6cr29RJ6r',
            'next_signing_key' => 'sig_5ZB6DVzB1wjE8S6rZ7eenA8Pdnhs',
            'endpoint' => 'http://127.0.0.1:18080',
            'destination' => 'http://127.0.0.1:18001',
            'retry_delay' => 500,
        ];

        config([
            'qstash.token' => $connection['token'],
            'qstash.current_signing_key' => $connection['current_signing_key'],
            'qstash.next_signing_key' => $connection['next_signing_key'],
            'qstash.endpoint' => $connection['endpoint'],
            'queue.failed.driver' => 'null',
            'queue.connections.qstash-dev' => $connection,
            'queue.connections.qstash-dev-serial' => [
                ...$connection,
                'flow_control' => ['key' => 'workbench', 'parallelism' => 1],
            ],
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Route::view('/', 'welcome');

        Route::post('/webhooks/record', function (Request $request) {
            file_put_contents(sys_get_temp_dir().'/'.basename($request->query('log')), json_encode([
                'body' => $request->getContent(),
                'webhook' => $request->header('X-Webhook'),
            ]).PHP_EOL, FILE_APPEND);
        })->middleware('qstash');
    }
}
