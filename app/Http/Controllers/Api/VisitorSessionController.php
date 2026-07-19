<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VisitorSessionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorSessionController extends Controller
{
    public function __construct(
        private VisitorSessionResolver $resolver,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visitor_id' => ['required', 'string', 'max:64'],
        ]);

        $result = $this->resolver->resolve($validated['visitor_id'], [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($result);
    }
}
