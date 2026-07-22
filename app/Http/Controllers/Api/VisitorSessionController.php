<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrackerClientContextResolver;
use App\Services\VisitorSessionResolver;
use App\Support\EcomTrackerLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorSessionController extends Controller
{
    public function __construct(
        private VisitorSessionResolver $resolver,
        private TrackerClientContextResolver $clientContextResolver,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $startedAt = microtime(true);

        $validated = $request->validate([
            'visitor_id' => ['required', 'string', 'max:64'],
        ]);

        EcomTrackerLogger::frontend()->info('api.resolve_visit.request', 'Website asked for visitor session', [
            'visitor_id' => $validated['visitor_id'],
            'ip' => $request->ip(),
        ]);

        $clientContext = $this->clientContextResolver->resolve($request);

        $result = $this->resolver->resolve($validated['visitor_id'], [
            'ip' => is_array($clientContext) ? ($clientContext['client_ip'] ?? $request->ip()) : $request->ip(),
            'user_agent' => is_array($clientContext) ? ($clientContext['user_agent'] ?? $request->userAgent()) : $request->userAgent(),
            'country' => is_array($clientContext) ? ($clientContext['ip_country'] ?? null) : null,
            'bot_resolved' => $clientContext,
        ]);

        EcomTrackerLogger::frontend()->info('api.resolve_visit.success', 'Visitor session is ready', [
            'visitor_id' => $result['visitor_id'],
            'session_id' => $result['session_id'],
            'is_new_session' => $result['is_new_session'],
            'is_new_unique_visitor' => $result['is_new_unique_visitor'],
            'has_bot_context' => is_array($clientContext),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return response()->json($result);
    }
}
