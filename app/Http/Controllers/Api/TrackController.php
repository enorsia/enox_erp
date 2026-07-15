<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrackIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class TrackController extends Controller
{
    public function __construct(
        private TrackIngestService $ingestService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $this->logInfo('Incoming track request', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => $request->input('session.session_id'),
            'event_count' => count($request->input('events', [])),
            'action_types' => collect($request->input('events', []))->pluck('action_type')->filter()->values()->all(),
        ]);

        try {
            $validated = $request->validate([
                'session' => ['nullable', 'array'],
                'session.session_id' => ['nullable', 'string', 'max:64'],
                'session.user_id' => ['nullable', 'integer'],
                'session.user_name' => ['nullable', 'string', 'max:255'],
                'session.user_email' => ['nullable', 'string', 'max:255'],
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

            $this->logInfo('Track request processed', [
                'session_id' => $validated['session']['session_id'] ?? ($validated['events'][0]['session_id'] ?? null),
                'accepted_count' => count($acceptedIds),
                'accepted_ids' => $acceptedIds,
            ]);

            return response()->json([
                'accepted_ids' => $acceptedIds,
            ]);
        } catch (ValidationException $e) {
            $this->logError('Track validation failed', [
                'session_id' => $request->input('session.session_id'),
                'errors' => $e->errors(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            $this->logError('Track ingest failed', [
                'session_id' => $request->input('session.session_id'),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logInfo(string $message, array $context = []): void
    {
        if (! config('tracker.logging_enabled')) {
            return;
        }

        Log::info('[EnoxTracker] ' . $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logError(string $message, array $context = []): void
    {
        if (! config('tracker.logging_enabled')) {
            return;
        }

        Log::error('[EnoxTracker] ' . $message, $context);
    }
}
