<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QStash Credentials
    |--------------------------------------------------------------------------
    |
    | These credentials are used by the QStash facade to publish messages and
    | to verify the signature of requests made by QStash. Queue connections
    | use them too, unless the connection specifies credentials of its own.
    |
    */

    'token' => env('QSTASH_TOKEN'),

    'current_signing_key' => env('QSTASH_CURRENT_SIGNING_KEY'),

    'next_signing_key' => env('QSTASH_NEXT_SIGNING_KEY'),

    'endpoint' => env('QSTASH_URL', 'https://qstash.upstash.io'),

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
