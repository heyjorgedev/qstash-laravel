<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QStash Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where QStash will deliver your queued jobs. Jobs
    | are delivered to "{path}/{connection}/{queue}" on your application,
    | and every request is verified using your QStash signing keys.
    |
    */

    'path' => env('QSTASH_PATH', 'qstash'),

];
