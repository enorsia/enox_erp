<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrackIngestService;
use App\Support\EcomTrackerLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class TrackController extends Controller
{
    public function __construct(
        private TrackIngestService $ingestService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $startedAt = microtime(true);

        EcomTrackerLogger::frontend()->info('api.track.request', 'Website sent user actions', [
            'ip' => $request->ip(),
            'session_id' => $request->input('session.session_id'),
            'visitor_id' => $request->input('session.visitor_id'),
            'event_count' => count($request->input('events', [])),
            'action_types' => collect($request->input('events', []))->pluck('action_type')->filter()->values()->all(),
        ]);

        try {
            $validated = $request->validate([
                'session' => ['nullable', 'array'],
                'session.session_id' => ['nullable', 'string', 'max:64'],
                'session.visitor_id' => ['nullable', 'string', 'max:64'],
                'session.user_id' => ['nullable', 'integer'],
                'session.user_name' => ['nullable', 'string', 'max:255'],
                'session.user_email' => ['nullable', 'string', 'max:255'],
                'session.user_phone' => ['nullable', 'string', 'max:50'],
                'session.is_logged_in' => ['nullable', 'boolean'],
                'session.utm_source' => ['nullable', 'string', 'max:100'],
                'session.utm_medium' => ['nullable', 'string', 'max:100'],
                'session.utm_campaign' => ['nullable', 'string', 'max:100'],
                'session.landing_page' => ['nullable', 'string', 'max:2048'],
                'events' => ['required', 'array', 'min:1', 'max:50'],
                'events.*.id' => ['required', 'uuid'],
                'events.*.session_id' => ['required', 'string', 'max:64'],
                'events.*.action_type' => ['required', 'string', 'in:' . implode(',', config('tracker.allowed_action_types'))],
                'events.*.category_name' => ['nullable', 'string', 'max:255'],
                'events.*.category_code' => ['nullable', 'string', 'max:100'],
                'events.*.product_name' => ['nullable', 'string', 'max:255'],
                'events.*.product_code' => ['nullable', 'string', 'max:100'],
                'events.*.product_color_id' => ['nullable', 'string', 'max:50'],
                'events.*.product_color_code' => ['nullable', 'string', 'max:255'],
                'events.*.general_color_name' => ['nullable', 'string', 'max:255'],
                'events.*.product_price' => ['nullable', 'numeric'],
                'events.*.page_url' => ['nullable', 'string', 'max:2048'],
                'events.*.referer' => ['nullable', 'string', 'max:2048'],
                'events.*.add_to_cart' => ['nullable', 'array'],
                'events.*.begin_checkout' => ['nullable', 'array'],
                'events.*.proceed_to_checkout' => ['nullable', 'array'],
                'events.*.payment_success' => ['nullable', 'array'],
                'events.*.start_time' => ['nullable'],
                'events.*.end_time' => ['nullable'],
                'events.*.created_at' => ['nullable'],
            ]);

            $acceptedIds = $this->ingestService->ingest($request, $validated);

            EcomTrackerLogger::frontend()->info('api.track.success', 'User actions saved OK', [
                'session_id' => $validated['session']['session_id'] ?? ($validated['events'][0]['session_id'] ?? null),
                'visitor_id' => $validated['session']['visitor_id'] ?? null,
                'accepted_count' => count($acceptedIds),
                'accepted_ids' => $acceptedIds,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'accepted_ids' => $acceptedIds,
            ]);
        } catch (ValidationException $e) {
            EcomTrackerLogger::frontend()->warning('api.track.validation_failed', 'Website sent wrong data', [
                'session_id' => $request->input('session.session_id'),
                'errors' => $e->errors(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            throw $e;
        } catch (Throwable $e) {
            EcomTrackerLogger::frontend()->error('api.track.failed', 'Could not save user actions', [
                'session_id' => $request->input('session.session_id'),
                'message' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            throw $e;
        }
    }
}
