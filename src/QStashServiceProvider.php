<?php

namespace HeyJorgeDev\QstashLaravel;

use HeyJorgeDev\QstashLaravel\Http\Controllers\JobController;
use HeyJorgeDev\QstashLaravel\Http\Middleware\VerifyJobSignature;
use HeyJorgeDev\QstashLaravel\Http\Middleware\VerifySignature;
use HeyJorgeDev\QstashLaravel\Jobs\QStashJob;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\QueueManager;
use Illuminate\Routing\Router;
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

        $this->app->singleton(QStash::class, fn ($app) => QStash::fromConfig(
            $app->make(HttpFactory::class), $app['config']['qstash']
        ));
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->callAfterResolving('queue', function (QueueManager $manager) {
            $manager->addConnector('qstash', fn () => new QStashConnector(
                $this->app->make(HttpFactory::class), $this->app['config']['qstash']
            ));
        });

        $this->callAfterResolving('router', function (Router $router) {
            $router->aliasMiddleware('qstash', VerifySignature::class);
        });

        $this->registerRoutes();
        $this->registerFailedJobLogger();

        PreventRequestsDuringMaintenance::except(trim(config('qstash.path'), '/').'/*');

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
            ->middleware(VerifyJobSignature::class)
            ->name('qstash.job');
    }

    /**
     * Record failed QStash jobs with the failed job provider.
     *
     * The queue:work command usually records failed jobs, but QStash jobs are run
     * by the delivery route instead, so we record them ourselves. Jobs from any
     * other connection are left alone so they are never recorded twice.
     */
    protected function registerFailedJobLogger(): void
    {
        $this->app['events']->listen(JobFailed::class, function (JobFailed $event) {
            if (! $event->job instanceof QStashJob) {
                return;
            }

            $this->app['queue.failer']->log(
                $event->connectionName,
                $event->job->getQueue(),
                $event->job->getRawBody(),
                $event->exception,
            );
        });
    }
}
