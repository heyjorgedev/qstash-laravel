<?php

namespace HeyJorgeDev\QstashLaravel;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use HeyJorgeDev\QstashLaravel\Commands\QstashLaravelCommand;

class QstashLaravelServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('qstash-laravel')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_qstash-laravel_table')
            ->hasCommand(QstashLaravelCommand::class);
    }
}
