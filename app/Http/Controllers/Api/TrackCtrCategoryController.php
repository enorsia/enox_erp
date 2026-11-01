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
                'category' => ['nullable', 'array'],
                'category.department_id' => ['nullable'],
                'category.department_slug' => ['nullable', 'string', 'max:255'],
                'category.category_id' => ['required_with:category', 'nullable'],
                'category.category_slug' => ['required_with:category', 'nullable', 'string', 'max:255'],
                'category.micro_category_id' => ['nullable'],
                'category.micro_category_slug' => ['nullable', 'string', 'max:255'],
                'category.type' => ['required_with:category', 'nullable', 'string', 'max:32'],
                'events' => ['required', 'array', 'min:1', 'max:' . max(1, (int) config('tracker.ingest_max_events', 50))],
                'events.*.id' => ['required', 'uuid'],
                'events.*.event_type' => ['required', 'string', 'in:impression,grid-product-click'],
                'events.*.product_code' => ['required', 'string', 'max:100'],
                'events.*.created_at' => ['nullable', 'string', 'max:64'],
                'events.*.tracking_meta' => ['nullable', 'array'],
                'events.*.tracking_meta.department_id' => ['nullable'],
                'events.*.tracking_meta.department_slug' => ['nullable', 'string', 'max:255'],
                'events.*.tracking_meta.category_id' => ['required_with:events.*.tracking_meta'],
                'events.*.tracking_meta.category_slug' => ['required_with:events.*.tracking_meta', 'string', 'max:255'],
                'events.*.tracking_meta.micro_category_id' => ['nullable'],
                'events.*.tracking_meta.micro_category_slug' => ['nullable', 'string', 'max:255'],
                'events.*.tracking_meta.type' => ['required_with:events.*.tracking_meta', 'string', 'max:32'],
                'events.*.category' => ['nullable', 'array'],
                'events.*.category.department_id' => ['nullable'],
                'events.*.category.department_slug' => ['nullable', 'string', 'max:255'],
                'events.*.category.category_id' => ['required_with:events.*.category'],
                'events.*.category.category_slug' => ['required_with:events.*.category', 'string', 'max:255'],
                'events.*.category.micro_category_id' => ['nullable'],
                'events.*.category.micro_category_slug' => ['nullable', 'string', 'max:255'],
                'events.*.category.type' => ['required_with:events.*.category', 'string', 'max:32'],
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
