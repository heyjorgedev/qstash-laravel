<?php

namespace HeyJorgeDev\QstashLaravel\Commands;

use Illuminate\Console\Command;

class QstashLaravelCommand extends Command
{
    public $signature = 'qstash-laravel';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
