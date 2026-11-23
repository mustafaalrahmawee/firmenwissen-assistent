<?php

namespace App\Http\Middleware;

use App\Http\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenant
{
    public function __construct(private CurrentTenant $currentTenant) {}

    /**
     * Set the workspace of the request from the logged-in user only.
     * Query parameters, body and headers of the request are never read.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->currentTenant->set($request->user()->tenant);

        return $next($request);
    }
}
