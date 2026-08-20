<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalApiAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {


        $allowedIps = config('enoxsuite.internal_api.allowed_ips', []);
        if (! in_array($request->ip(), $allowedIps)) {
            abort(403, 'Unauthorized IP : ' . $request->ip());
        }

        $apiKey = $request->header('X-INTERNAL-KEY');
        $validKeys = config('enoxsuite.internal_api.keys', []);

        if (! $apiKey || ! in_array($apiKey, $validKeys)) {
            abort(401, 'Invalid API Key: ' . $apiKey);
        }

        return $next($request);
    }
}
