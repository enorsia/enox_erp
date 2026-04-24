<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EnoxTrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * EnoxTrackerController — API endpoint for the EnoxTracker SDK.
 *
 * Receives batched events from the frontend, categorises them by type,
 * and routes each group to the appropriate ClickHouse table via EnoxTrackerService.
 *
 * POST /api/enox-tracker/ingest
 * GET  /api/enox-tracker/health
 */
class EnoxTrackerController extends Controller
{
    protected EnoxTrackerService $tracker;

    /**
     * Event types that go to specialised tables instead of the generic events table.
     */
    private const ELEMENT_CLICK_EVENTS    = ['element_click', 'rage_click'];
    private const SECTION_EVENTS         = ['section_visibility'];
    private const ACCORDION_EVENTS       = ['accordion_interaction'];
    private const TRANSITION_EVENTS      = ['page_transition'];
    private const IMAGE_ATTENTION_EVENTS = ['image_attention', 'image_swiped'];
    private const HOVER_EVENTS           = ['hover_event'];
    private const FORM_EVENTS            = ['form_field_interaction'];
    private const WEB_VITALS_EVENTS      = ['web_vitals'];
    private const ERROR_EVENTS           = ['js_error'];
    private const EXIT_INTENT_EVENTS     = ['exit_intent'];
    private const VIDEO_EVENTS           = ['video_event'];
    private const PRODUCT_INTERACTION_EVENTS = ['product_interaction', 'variant_selected', 'quick_view', 'notify_me'];
    private const DESCRIPTION_READ_EVENTS   = ['description_read', 'clipboard_action'];
    private const SEARCH_EVENTS             = ['search', 'search_click', 'search_refine'];
    private const ATTENTION_SPAN_EVENTS     = ['attention_span'];
    private const SCROLL_EVENTS             = ['scroll_depth', 'scroll_depth_final'];

    public function __construct(EnoxTrackerService $tracker)
    {
        $this->tracker = $tracker;
    }

    /**
     * Receive a batch of analytics events from the frontend SDK.
     *
     * Payload shape:
     * {
     *   "batch": [ { event, anonymous_id, session_id, user_id, timestamp, context, properties } ],
     *   "session": { ... }
     * }
     */
    public function ingest(Request $request): JsonResponse
    {
        try {
            // ── Validate ──────────────────────────────────
            $validated = $request->validate([
                'batch'                          => 'required|array|min:1|max:100',
                'batch.*.event'                  => 'required|string|max:100',
                'batch.*.anonymous_id'           => 'nullable|string|max:100',
                'batch.*.session_id'             => 'nullable|string|max:100',
                'batch.*.user_id'                => 'nullable|string|max:100',
                'batch.*.project_id'             => 'nullable|string|max:100',
                'batch.*.timestamp'              => 'nullable',
                'batch.*.active_time_ms'         => 'nullable|integer|min:0',
                'batch.*.total_time_on_page_ms'  => 'nullable|integer|min:0',
                'batch.*.is_new_user'            => 'nullable|integer',
                'batch.*.experiment_id'          => 'nullable|string|max:100',
                'batch.*.experiment_variant'     => 'nullable|string|max:100',
                'batch.*.context'                => 'nullable|array',
                'batch.*.properties'             => 'nullable|array',
                'session'                        => 'nullable|array',
            ]);

            $batch     = $validated['batch'];
            $session   = $validated['session'] ?? null;
            $ipAddress = $request->ip();

            // Debug log: record incoming payload summary so frontend payloads can be inspected during development.
            // We log IP, total events and a small sample (first 5 events) to avoid excessively large log entries.
            try {
                Log::channel('enoxtracker')->info('[EnoxTracker] Ingest received', [
                    'ip' => $ipAddress,
                    'total_events' => is_array($batch) ? count($batch) : 0,
                    'sample_events' => is_array($batch) ? array_slice($batch, 0, 5) : [],
                    'session' => $session,
                ]);
            } catch (\Throwable $ex) {
                // Ensure logging never breaks ingestion flow
                Log::channel('enoxtracker')->warning('[EnoxTracker] Failed to write ingest debug log', ['error' => $ex->getMessage()]);
            }

            // ── Categorise events by destination table ────
            $generalEvents       = [];
            $elementInteractions = [];
            $sectionVisibility   = [];
            $accordionTracking   = [];
            $pageTransitions     = [];
            $imageAttention      = [];
            $hoverEvents         = [];
            $formInteractions    = [];
            $webVitals           = [];
            $errorEvents         = [];
            $exitIntentEvents    = [];
            $videoEvents         = [];
            $productInteractions = [];
            $descriptionReads    = [];
            $searchEvents        = [];
            $attentionSpans      = [];
            $scrollEvents        = [];

            foreach ($batch as $event) {
                $eventName = $event['event'] ?? '';

                if (in_array($eventName, self::ELEMENT_CLICK_EVENTS, true)) {
                    $elementInteractions[] = $event;
                } elseif (in_array($eventName, self::SECTION_EVENTS, true)) {
                    $sectionVisibility[] = $event;
                } elseif (in_array($eventName, self::ACCORDION_EVENTS, true)) {
                    $accordionTracking[] = $event;
                } elseif (in_array($eventName, self::TRANSITION_EVENTS, true)) {
                    $pageTransitions[] = $event;
                } elseif (in_array($eventName, self::IMAGE_ATTENTION_EVENTS, true)) {
                    $imageAttention[] = $event;
                } elseif (in_array($eventName, self::HOVER_EVENTS, true)) {
                    $hoverEvents[] = $event;
                } elseif (in_array($eventName, self::FORM_EVENTS, true)) {
                    $formInteractions[] = $event;
                } elseif (in_array($eventName, self::WEB_VITALS_EVENTS, true)) {
                    $webVitals[] = $event;
                } elseif (in_array($eventName, self::ERROR_EVENTS, true)) {
                    $errorEvents[] = $event;
                } elseif (in_array($eventName, self::EXIT_INTENT_EVENTS, true)) {
                    $exitIntentEvents[] = $event;
                } elseif (in_array($eventName, self::VIDEO_EVENTS, true)) {
                    $videoEvents[] = $event;
                } elseif (in_array($eventName, self::PRODUCT_INTERACTION_EVENTS, true)) {
                    $productInteractions[] = $event;
                    $generalEvents[] = $event; // also record in generic events
                } elseif (in_array($eventName, self::DESCRIPTION_READ_EVENTS, true)) {
                    $descriptionReads[] = $event;
                } elseif (in_array($eventName, self::SEARCH_EVENTS, true)) {
                    $searchEvents[] = $event;
                    $generalEvents[] = $event; // also record in generic events
                } elseif (in_array($eventName, self::ATTENTION_SPAN_EVENTS, true)) {
                    $attentionSpans[] = $event;
                } elseif (in_array($eventName, self::SCROLL_EVENTS, true)) {
                    $scrollEvents[] = $event;
                } else {
                    $generalEvents[] = $event;
                }
            }

            // ── Insert each category ─────────────────────
            $results = [];

            if (!empty($generalEvents)) {
                $ok = $this->tracker->insertEvents($generalEvents, $ipAddress);
                $results['events'] = ['count' => count($generalEvents), 'success' => $ok];
                if (!$ok) Log::channel('enoxtracker')->warning('[EnoxTracker] General events insert failed', ['count' => count($generalEvents)]);
            }

            if (!empty($elementInteractions)) {
                $ok = $this->tracker->insertElementInteractions($elementInteractions, $ipAddress);
                $results['element_interactions'] = ['count' => count($elementInteractions), 'success' => $ok];
            }

            if (!empty($sectionVisibility)) {
                $ok = $this->tracker->insertSectionVisibility($sectionVisibility, $ipAddress);
                $results['section_visibility'] = ['count' => count($sectionVisibility), 'success' => $ok];
            }

            if (!empty($accordionTracking)) {
                $ok = $this->tracker->insertAccordionTracking($accordionTracking, $ipAddress);
                $results['accordion_tracking'] = ['count' => count($accordionTracking), 'success' => $ok];
            }

            if (!empty($pageTransitions)) {
                $ok = $this->tracker->insertPageTransitions($pageTransitions, $ipAddress);
                $results['page_transitions'] = ['count' => count($pageTransitions), 'success' => $ok];
            }

            if (!empty($imageAttention)) {
                $ok = $this->tracker->insertImageAttention($imageAttention, $ipAddress);
                $results['image_attention'] = ['count' => count($imageAttention), 'success' => $ok];
            }

            if (!empty($hoverEvents)) {
                $ok = $this->tracker->insertHoverEvents($hoverEvents, $ipAddress);
                $results['hover_events'] = ['count' => count($hoverEvents), 'success' => $ok];
            }

            if (!empty($formInteractions)) {
                $ok = $this->tracker->insertFormInteractions($formInteractions, $ipAddress);
                $results['form_interactions'] = ['count' => count($formInteractions), 'success' => $ok];
            }

            if (!empty($webVitals)) {
                $ok = $this->tracker->insertPerformanceMetrics($webVitals, $ipAddress);
                $results['performance_metrics'] = ['count' => count($webVitals), 'success' => $ok];
            }

            if (!empty($errorEvents)) {
                $ok = $this->tracker->insertErrorEvents($errorEvents, $ipAddress);
                $results['error_events'] = ['count' => count($errorEvents), 'success' => $ok];
            }

            if (!empty($exitIntentEvents)) {
                $ok = $this->tracker->insertExitIntentEvents($exitIntentEvents, $ipAddress);
                $results['exit_intent_events'] = ['count' => count($exitIntentEvents), 'success' => $ok];
            }

            if (!empty($videoEvents)) {
                $ok = $this->tracker->insertVideoEvents($videoEvents, $ipAddress);
                $results['video_events'] = ['count' => count($videoEvents), 'success' => $ok];
            }

            if (!empty($productInteractions)) {
                $ok = $this->tracker->insertProductInteractions($productInteractions, $ipAddress);
                $results['product_interactions'] = ['count' => count($productInteractions), 'success' => $ok];
            }

            if (!empty($descriptionReads)) {
                $ok = $this->tracker->insertDescriptionReads($descriptionReads, $ipAddress);
                $results['description_reads'] = ['count' => count($descriptionReads), 'success' => $ok];
            }

            if (!empty($searchEvents)) {
                $ok = $this->tracker->insertSearchEvents($searchEvents, $ipAddress);
                $results['search_events'] = ['count' => count($searchEvents), 'success' => $ok];
            }

            if (!empty($attentionSpans)) {
                $ok = $this->tracker->insertAttentionSpans($attentionSpans, $ipAddress);
                $results['attention_spans'] = ['count' => count($attentionSpans), 'success' => $ok];
            }

            if (!empty($scrollEvents)) {
                $ok = $this->tracker->insertScrollEvents($scrollEvents, $ipAddress);
                $results['scroll_events'] = ['count' => count($scrollEvents), 'success' => $ok];
            }

            // ── Upsert session ───────────────────────────
            if ($session && !empty($session['session_id'])) {
                $this->tracker->upsertSession($session, $ipAddress);
            }

            // ── Response ─────────────────────────────────
            $anyFailure = collect($results)->contains(fn ($r) => !$r['success']);

            if ($anyFailure) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partial failure — some inserts failed',
                    'results' => $results,
                ], 207); // Multi-Status
            }

            return response()->json([
                'success' => true,
                'message' => 'Events received',
                'total'   => count($batch),
                'results' => $results,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::channel('enoxtracker')->info('[EnoxTracker] Validation failed on ingest', [
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::channel('enoxtracker')->error('[EnoxTracker] Ingest exception', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Health check endpoint for the analytics system.
     */
    public function health(): JsonResponse
    {
        try {
            $result = $this->tracker->healthCheck();

            return response()->json([
                'status'     => $result ? 'ok' : 'error',
                'clickhouse' => $result ? 'connected' : 'disconnected',
            ]);

        } catch (\Exception $e) {
            Log::channel('enoxtracker')->error('[EnoxTracker] Health check exception', [
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'status'     => 'error',
                'clickhouse' => 'disconnected',
                'error'      => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Debug endpoint — return recent events from ClickHouse so you can verify from browser.
     * GET /api/enox-tracker/debug?session_id=xxx  (or without param for last 20 events)
     *
     * REMOVE or restrict this endpoint before production.
     */
    public function debug(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $sessionId = $request->query('session_id', '');
            $limit     = min((int) $request->query('limit', 20), 100);

            if ($sessionId) {
                $where = "WHERE session_id = '" . addslashes($sessionId) . "'";
            } else {
                $where = '';
            }

            $result = $this->tracker->rawQuery(
                "SELECT event_name, session_id, anonymous_id,
                        active_time_ms, total_time_on_page_ms, element_visible_ms,
                        scroll_depth_pct, scroll_depth_px, is_new_user,
                        page_path, browser, device_type, utm_source, utm_medium,
                        product_id, price, event_timestamp
                 FROM enox_tracker.events
                 {$where}
                 ORDER BY event_timestamp DESC
                 LIMIT {$limit}"
            );

            $counts = $this->tracker->rawQuery(
                "SELECT
                    (SELECT count() FROM enox_tracker.events)   AS events,
                    (SELECT count() FROM enox_tracker.sessions) AS sessions,
                    (SELECT count() FROM enox_tracker.element_interactions) AS element_interactions,
                    (SELECT count() FROM enox_tracker.image_attention) AS image_attention"
            );

            return response()->json([
                'status'  => 'ok',
                'counts'  => $counts['data'][0] ?? [],
                'events'  => $result['data']    ?? [],
                'rows'    => count($result['data'] ?? []),
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
