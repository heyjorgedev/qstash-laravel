<?php

namespace HeyJorgeDev\QstashLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \HeyJorgeDev\QstashLaravel\QstashLaravel
 */
class QstashLaravel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \HeyJorgeDev\QstashLaravel\QstashLaravel::class;
    }
}
