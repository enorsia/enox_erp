<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrackerClientContextResolver;
use App\Models\TrackingCtrSummary;
use App\Services\TrackIngestService;
use App\Support\EcomTrackerLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class TrackController extends Controller
{
    public function __construct(
        private TrackIngestService $ingestService,
        private TrackerClientContextResolver $clientContextResolver,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $startedAt = microtime(true);

        $events = $request->input('events', []);
        $legacyGridTypes = ['grid_impression', 'grid_click'];
        $discardedLegacyIds = [];

        $events = array_values(array_filter($events, function ($event) use ($legacyGridTypes, &$discardedLegacyIds) {
            $actionType = (string) ($event['action_type'] ?? '');

            if (! in_array($actionType, $legacyGridTypes, true)) {
                return true;
            }

            if (! empty($event['id'])) {
                $discardedLegacyIds[] = (string) $event['id'];
            }

            return false;
        }));

        if ($discardedLegacyIds !== []) {
            $request->merge(['events' => $events]);
            EcomTrackerLogger::frontend()->info('api.track.legacy_grid_stripped', 'Dropped legacy grid events from /track batch', [
                'discarded_count' => count($discardedLegacyIds),
                'discarded_event_ids' => $discardedLegacyIds,
            ]);
        }

        if ($events === []) {
            return response()->json([
                'accepted_ids' => $discardedLegacyIds,
            ]);
        }

        EcomTrackerLogger::frontend()->info('api.track.request', 'Website sent user actions', [
            'ip' => $request->ip(),
            'session_id' => $request->input('session.session_id'),
            'visitor_id' => $request->input('session.visitor_id'),
            'event_count' => count($events),
            'action_types' => collect($events)->pluck('action_type')->filter()->values()->all(),
        ]);

        try {
            $validated = $request->validate([
                'session' => ['required', 'array'],
                'session.session_id' => ['nullable', 'string', 'max:64'],
                'session.visitor_id' => ['required', 'string', 'max:64'],
                'session.user_id' => ['nullable', 'integer'],
                'session.user_name' => ['nullable', 'string', 'max:255'],
                'session.user_email' => ['nullable', 'string', 'max:255'],
                'session.user_phone' => ['nullable', 'string', 'max:50'],
                'session.is_logged_in' => ['nullable', 'boolean'],
                'session.utm_source' => ['nullable', 'string', 'max:100'],
                'session.utm_medium' => ['nullable', 'string', 'max:100'],
                'session.utm_campaign' => ['nullable', 'string', 'max:100'],
                'session.landing_page' => ['nullable', 'string', 'max:2048'],
                'session.last_paid_touch' => ['nullable', 'array'],
                'session.last_paid_touch.utm_source' => ['nullable', 'string', 'max:100'],
                'session.last_paid_touch.utm_medium' => ['nullable', 'string', 'max:100'],
                'session.last_paid_touch.utm_campaign' => ['nullable', 'string', 'max:100'],
                'session.last_paid_touch.landing_page' => ['nullable', 'string', 'max:2048'],
                'session.last_paid_touch.captured_at' => ['nullable', 'string', 'max:64'],
                'events' => ['required', 'array', 'min:1', 'max:' . max(1, (int) config('tracker.ingest_max_events', 50))],
                'events.*.id' => ['required', 'uuid'],
                'events.*.session_id' => ['required', 'string', 'max:64'],
                'events.*.action_type' => ['required', 'string', 'in:' . implode(',', config('tracker.allowed_action_types'))],
                'events.*.category_name' => ['nullable', 'string', 'max:255'],
                'events.*.category_code' => ['nullable', 'string', 'max:100'],
                'events.*.category_id' => ['nullable', 'string', 'max:50'],
                'events.*.department_name' => ['nullable', 'string', 'max:255'],
                'events.*.department_id' => ['nullable', 'string', 'max:50'],
                'events.*.product_name' => ['nullable', 'string', 'max:255'],
                'events.*.product_code' => ['nullable', 'string', 'max:100'],
                'events.*.sku' => ['nullable', 'string', 'max:100'],
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

            $clientContext = $this->clientContextResolver->resolve($request);

            if ($clientContext !== null) {
                EcomTrackerLogger::frontend()->info('ingest.client_context', 'Client context for batch', [
                    'session_id' => $validated['session']['session_id'] ?? ($validated['events'][0]['session_id'] ?? null),
                    'is_bot' => $clientContext['is_bot'] ?? null,
                    'ip_country' => $clientContext['ip_country'] ?? null,
                ]);
            }

            $acceptedIds = $this->ingestService->ingest($request, $validated);

            $sentIds = collect($validated['events'])->pluck('id')->map(fn ($id) => (string) $id);
            $acceptedSet = collect($acceptedIds)->map(fn ($id) => (string) $id);
            $rejectedIds = $sentIds->diff($acceptedSet)->values()->all();

            if ($rejectedIds !== []) {
                EcomTrackerLogger::frontend()->error('api.track.partial_accept', 'Some events were not stored', [
                    'session_id' => $validated['session']['session_id'] ?? ($validated['events'][0]['session_id'] ?? null),
                    'visitor_id' => $validated['session']['visitor_id'] ?? null,
                    'rejected_event_ids' => $rejectedIds,
                    'accepted_ids' => $acceptedIds,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);
            }

            EcomTrackerLogger::frontend()->info('api.track.success', 'User actions saved OK', [
                'session_id' => $validated['session']['session_id'] ?? ($validated['events'][0]['session_id'] ?? null),
                'visitor_id' => $validated['session']['visitor_id'] ?? null,
                'accepted_count' => count($acceptedIds),
                'sent_count' => $sentIds->count(),
                'rejected_count' => count($rejectedIds),
                'accepted_ids' => $acceptedIds,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'accepted_ids' => array_values(array_unique(array_merge($acceptedIds, $discardedLegacyIds))),
            ]);
        } catch (ValidationException $e) {
            EcomTrackerLogger::frontend()->error('api.track.validation_failed', 'Website sent invalid track payload', [
                'session_id' => $request->input('session.session_id'),
                'visitor_id' => $request->input('session.visitor_id'),
                'event_count' => count($events),
                'event_ids' => collect($events)->pluck('id')->filter()->values()->all(),
                'action_types' => collect($events)->pluck('action_type')->filter()->values()->all(),
                'errors' => $e->errors(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            throw $e;
        } catch (Throwable $e) {
            EcomTrackerLogger::frontend()->error('api.track.failed', 'Could not save user actions', [
                'session_id' => $request->input('session.session_id'),
                'visitor_id' => $request->input('session.visitor_id'),
                'event_count' => count($events),
                'event_ids' => collect($events)->pluck('id')->filter()->values()->all(),
                'action_types' => collect($events)->pluck('action_type')->filter()->values()->all(),
                'message' => $e->getMessage(),
                'exception' => $e::class,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            throw $e;
        }
    }

    public function getTrackingCtrSummaries(Request $request)
    {
        try {
            $summaries = TrackingCtrSummary::select(
                'product_code',
                'ctr',
                'ctr_average',
            )->get()->keyBy('product_code');

            return response()->json([
                'status' => true,
                'message' => 'Tracking CTR summaries updated successfully',
                'data' => $summaries
            ], 200);
        } catch (Throwable $th) {
            Log::error('Tracking CTR summaries update failed', ['error' => $th->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
