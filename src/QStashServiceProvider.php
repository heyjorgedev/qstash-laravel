<?php

namespace HeyJorgeDev\QstashLaravel;

use HeyJorgeDev\QstashLaravel\Http\Controllers\JobController;
use HeyJorgeDev\QstashLaravel\Http\Middleware\VerifySignature;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class QStashServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/qstash.php', 'qstash');
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->callAfterResolving('queue', function (QueueManager $manager) {
            $manager->addConnector('qstash', fn () => new QStashConnector(
                $this->app->make(HttpFactory::class)
            ));
        });

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/qstash.php' => $this->app->configPath('qstash.php'),
            ], 'qstash-config');
        }
    }

    /**
     * Register the route QStash delivers jobs to.
     */
    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::post(config('qstash.path').'/{connection}/{queue}', JobController::class)
            ->middleware(VerifySignature::class)
            ->name('qstash.job');
    }
}
