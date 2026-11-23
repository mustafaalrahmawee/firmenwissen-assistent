<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Like "throttle" with a named limiter, but every request is counted first and checked afterwards.
 *
 * The increment in the cache is atomic, so parallel requests cannot all read the same count and
 * slip past the limit together. A request turned away by one limit still counts for all others.
 * Usage: ->middleware(ThrottleEveryRequest::class.':register')
 */
class ThrottleEveryRequest extends ThrottleRequests
{
    /**
     * Count the request for every limit, then turn it away if any limit is exceeded.
     *
     * @param  Request  $request
     * @param  array<int, object{key: string, maxAttempts: int, decaySeconds: int, responseCallback: ?callable}>  $limits
     */
    protected function handleRequest($request, Closure $next, array $limits): Response
    {
        $exceeded = null;

        foreach ($limits as $limit) {
            $hits = $this->limiter->hit($limit->key, $limit->decaySeconds);

            if ($hits > $limit->maxAttempts
                && ($exceeded === null || $this->getTimeUntilNextRetry($limit->key) > $this->getTimeUntilNextRetry($exceeded->key))) {
                $exceeded = $limit;
            }
        }

        if ($exceeded !== null) {
            throw $this->buildException($request, $exceeded->key, $exceeded->maxAttempts, $exceeded->responseCallback);
        }

        $response = $next($request);

        foreach ($limits as $limit) {
            $response = $this->addHeaders(
                $response,
                $limit->maxAttempts,
                $this->calculateRemainingAttempts($limit->key, $limit->maxAttempts),
            );
        }

        return $response;
    }
}
