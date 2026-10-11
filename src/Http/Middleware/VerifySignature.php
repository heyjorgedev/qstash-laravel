<?php

namespace HeyJorgeDev\QstashLaravel\Http\Middleware;

use Closure;
use HeyJorgeDev\QstashLaravel\QStash;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifySignature
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(protected QStash $qstash)
    {
        //
    }

    /**
     * Ensure the incoming request was signed by QStash.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, Closure $next): Response
    {
        // QStash signs the URL exactly as it was published, so we will use the raw
        // request URI instead of the full URL, which normalizes the query string.
        $verified = $this->qstash->verify(
            (string) $request->header('Upstash-Signature'),
            $request->getContent(),
            $request->getSchemeAndHttpHost().$request->getRequestUri(),
        );

        if (! $verified) {
            throw new AccessDeniedHttpException('Invalid QStash signature.');
        }

        return $next($request);
    }
}
