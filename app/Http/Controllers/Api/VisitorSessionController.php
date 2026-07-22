<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrackerClientContextResolver;
use App\Services\VisitorSessionResolver;
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
        $validated = $request->validate([
            'visitor_id' => ['required', 'string', 'max:64'],
        ]);

        $clientContext = $this->clientContextResolver->resolve($request);

        $result = $this->resolver->resolve($validated['visitor_id'], [
            'ip' => is_array($clientContext) ? ($clientContext['client_ip'] ?? $request->ip()) : $request->ip(),
            'user_agent' => is_array($clientContext) ? ($clientContext['user_agent'] ?? $request->userAgent()) : $request->userAgent(),
            'country' => is_array($clientContext) ? ($clientContext['ip_country'] ?? null) : null,
            'bot_resolved' => $clientContext,
        ]);

        return response()->json($result);
    }
}
