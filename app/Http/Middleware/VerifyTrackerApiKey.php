<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyTrackerApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $hash = config('tracker.api_key_hash');

        if (empty($hash)) {
            if (config('tracker.logging_enabled')) {
                Log::error('[EnoxTracker] API key hash is not configured');
            }

            abort(503, 'Tracker API is not configured.');
        }

        $token = $request->bearerToken();

        if (! $token || ! Hash::check($token, $hash)) {
            if (config('tracker.logging_enabled')) {
                Log::warning('[EnoxTracker] Invalid API key', [
                    'ip' => $request->ip(),
                    'has_token' => (bool) $token,
                ]);
            }

            abort(401, 'Invalid API key.');
        }

        if (config('tracker.logging_enabled')) {
            Log::debug('[EnoxTracker] API key middleware passed', [
                'ip' => $request->ip(),
                'has_token' => (bool) $token,
            ]);
        }

        return $next($request);
    }
}
