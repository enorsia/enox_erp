<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrackingCtrCategoryIngestService;
use App\Support\EcomTrackerLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class TrackCtrCategoryController extends Controller
{
    public function __construct(
        private TrackingCtrCategoryIngestService $ingestService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $events = $request->input('events', []);

        EcomTrackerLogger::frontend()->info('api.ctr_category.request', 'Category CTR batch received', [
            'session_id' => $request->input('session.session_id'),
            'visitor_id' => $request->input('session.visitor_id'),
            'event_count' => count($events),
            'event_types' => collect($events)->pluck('event_type')->filter()->values()->all(),
        ]);

        try {
            $validated = $request->validate([
                'session' => ['required', 'array'],
                'session.session_id' => ['required', 'string', 'max:64'],
                'session.visitor_id' => ['required', 'string', 'max:64'],
                'category' => ['required', 'array'],
                'category.department_id' => ['nullable'],
                'category.department_slug' => ['nullable', 'string', 'max:255'],
                'category.category_id' => ['required'],
                'category.category_slug' => ['required', 'string', 'max:255'],
                'category.type' => ['required', 'string', 'max:32'],
                'events' => ['required', 'array', 'min:1', 'max:' . max(1, (int) config('tracker.ingest_max_events', 50))],
                'events.*.id' => ['required', 'uuid'],
                'events.*.event_type' => ['required', 'string', 'in:impression,grid-product-click'],
                'events.*.product_code' => ['required', 'string', 'max:100'],
                'events.*.created_at' => ['nullable', 'string', 'max:64'],
            ]);

            $acceptedIds = $this->ingestService->ingest($request, $validated);

            EcomTrackerLogger::frontend()->info('api.ctr_category.success', 'Category CTR batch stored', [
                'session_id' => $validated['session']['session_id'],
                'accepted_count' => count($acceptedIds),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'accepted_ids' => $acceptedIds,
            ]);
        } catch (ValidationException $exception) {
            EcomTrackerLogger::frontend()->error('api.ctr_category.validation_failed', 'Invalid category CTR payload', [
                'errors' => $exception->errors(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            EcomTrackerLogger::frontend()->error('api.ctr_category.failed', 'Category CTR ingest failed', [
                'message' => $exception->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            throw $exception;
        }
    }
}
