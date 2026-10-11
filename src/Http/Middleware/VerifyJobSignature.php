<?php

namespace HeyJorgeDev\QstashLaravel\Http\Middleware;

use Closure;
use HeyJorgeDev\QstashLaravel\QStashQueue;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Queue\QueueManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyJobSignature
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected QueueManager $manager,
        protected Config $config,
    ) {
        //
    }

    /**
     * Handle the incoming request.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $connection = $request->route('connection');

        abort_unless($this->config->get("queue.connections.{$connection}.driver") === 'qstash', 404);

        /** @var QStashQueue $queue */
        $queue = $this->manager->connection($connection);

        $verified = $queue->getQStash()->verify(
            (string) $request->header('Upstash-Signature'),
            $request->getContent(),
            $queue->destination($request->route('queue')),
        );

        if (! $verified) {
            throw new AccessDeniedHttpException('Invalid QStash signature.');
        }

        return $next($request);
    }
}
