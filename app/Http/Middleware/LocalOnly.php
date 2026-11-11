<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to the loopback interface or an authenticated caller.
 * Used for developer surfaces (API docs) that must not be publicly reachable.
 */
class LocalOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        if (in_array($ip, ['127.0.0.1', '::1'], true) || $request->user() !== null) {
            return $next($request);
        }

        // 404 instead of 403: do not advertise that the surface exists.
        abort(404);
    }
}
