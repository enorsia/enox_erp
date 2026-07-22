<?php

namespace App\Http\Middleware;

use App\Support\EcomTrackerLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class VerifyTrackerApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $hash = config('tracker.api_key_hash');

        if (empty($hash)) {
            EcomTrackerLogger::frontend()->error('api.auth', 'Tracker API key is not set on server');

            abort(503, 'Tracker API is not configured.');
        }

        $token = $request->bearerToken();

        if (! $token || ! Hash::check($token, $hash)) {
            EcomTrackerLogger::frontend()->warning('api.auth', 'Wrong API key from website', [
                'ip' => $request->ip(),
                'has_token' => (bool) $token,
                'route' => $request->path(),
            ]);

            abort(401, 'Invalid API key.');
        }

        EcomTrackerLogger::frontend()->debug('api.auth', 'API key is OK', [
            'ip' => $request->ip(),
            'route' => $request->path(),
        ]);

        return $next($request);
    }
}
