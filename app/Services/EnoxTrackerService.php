<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * EnoxTrackerService — Domain-specific analytics service.
 *
 * Uses ClickHouseService for raw HTTP queries.
 * Provides typed insert methods for each tracking table:
 *   - events (general)
 *   - sessions
 *   - element_interactions
 *   - section_visibility
 *   - accordion_tracking
 *   - page_transitions
 */
class EnoxTrackerService
{
    protected ClickHouseService $ch;

    public function __construct(ClickHouseService $ch)
    {
        $this->ch = $ch;
    }

    // ─────────────────────────────────────────
    // EVENTS (general)
    // ─────────────────────────────────────────
    public function insertEvents(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $columns = [
                'event_name', 'user_id', 'anonymous_id', 'session_id', 'project_id',
                'product_id', 'product_name', 'sku', 'variant_color', 'variant_size',
                'category', 'price', 'quantity', 'currency', 'event_value',
                'order_id',
                'page_url', 'page_title', 'page_path', 'page_type', 'referrer',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
                'gclid', 'fbclid', 'ttclid', 'msclkid', 'affiliate_id',
                'device_type', 'browser', 'os', 'screen_resolution',
                'viewport_width', 'viewport_height', 'language', 'connection_type',
                'ip_address', 'properties',
                'active_time_ms', 'total_time_on_page_ms',
                'scroll_depth_pct', 'scroll_depth_px', 'element_visible_ms',
                // Click / element fields — populated when element_click events are dual-written
                'is_rage_click', 'is_dead_click',
                'target_element_tag', 'target_element_id', 'target_element_classes',
                'target_element_text', 'target_data_track',
                'click_x', 'click_y', 'click_x_pct', 'click_y_pct',
                'is_new_user', 'event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $props = $event['properties'] ?? [];
                $ctx   = $event['context']    ?? [];
                $dev   = $ctx['device']   ?? [];
                $page  = $ctx['page']     ?? [];
                $camp  = $ctx['campaign'] ?? [];
                $ts    = $this->parseTimestamp($event['timestamp'] ?? null);

                $values = [
                    "'" . $this->esc($event['event']        ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($props['product_id']   ?? '') . "'",
                    "'" . $this->esc($props['product_name'] ?? '') . "'",
                    "'" . $this->esc($props['sku']          ?? '') . "'",
                    "'" . $this->esc($props['variant_color'] ?? $props['color'] ?? '') . "'",
                    "'" . $this->esc($props['variant_size']  ?? $props['size']  ?? '') . "'",
                    "'" . $this->esc($props['category']     ?? '') . "'",
                    (float) ($props['price']    ?? 0),
                    (int)   ($props['quantity'] ?? 0),
                    "'" . $this->esc($props['currency'] ?? 'GBP') . "'",
                    (float) ($props['event_value'] ?? $props['revenue'] ?? $props['total'] ?? 0),
                    "'" . $this->esc($props['order_id'] ?? $event['order_id'] ?? '') . "'",
                    "'" . $this->esc($page['url']      ?? $props['url']      ?? '') . "'",
                    "'" . $this->esc($page['title']    ?? $props['title']    ?? '') . "'",
                    "'" . $this->esc($page['path']     ?? $props['path']     ?? '') . "'",
                    "'" . $this->esc($page['type']     ?? $props['page_type'] ?? '') . "'",
                    "'" . $this->esc($page['referrer'] ?? $props['referrer'] ?? '') . "'",
                    "'" . $this->esc($camp['source']   ?? '') . "'",
                    "'" . $this->esc($camp['medium']   ?? '') . "'",
                    "'" . $this->esc($camp['name']     ?? '') . "'",
                    "'" . $this->esc($camp['term']     ?? '') . "'",
                    "'" . $this->esc($camp['content']  ?? '') . "'",
                    "'" . $this->esc($camp['gclid']    ?? '') . "'",
                    "'" . $this->esc($camp['fbclid']   ?? '') . "'",
                    "'" . $this->esc($camp['ttclid']   ?? '') . "'",
                    "'" . $this->esc($camp['msclkid']  ?? '') . "'",
                    "'" . $this->esc($camp['affiliate_id'] ?? '') . "'",
                    "'" . $this->esc($dev['type']       ?? '') . "'",
                    "'" . $this->esc($dev['browser']    ?? '') . "'",
                    "'" . $this->esc($dev['os']         ?? '') . "'",
                    "'" . $this->esc($dev['screen']     ?? '') . "'",
                    (int) ($dev['viewport_w'] ?? 0),
                    (int) ($dev['viewport_h'] ?? 0),
                    "'" . $this->esc($dev['language']   ?? '') . "'",
                    "'" . $this->esc($dev['connection'] ?? '') . "'",
                    "'" . $this->esc($ipAddress) . "'",
                    "'" . $this->esc(json_encode($props)) . "'",
                    // active_time_ms: top-level (test payloads) OR properties.active_ms (time_on_page) OR properties.active_time_ms (user_idle)
                    (int) ($event['active_time_ms'] ?? $props['active_ms'] ?? $props['active_time_ms'] ?? $event['duration_ms'] ?? 0),
                    // total_time_on_page_ms: top-level (test payloads) OR properties.total_ms (time_on_page)
                    (int) ($event['total_time_on_page_ms'] ?? $props['total_ms'] ?? 0),
                    // scroll_depth_pct: 'time_on_page' stores it as scroll_depth_pct; 'scroll_depth' uses depth_percent
                    (int) ($props['scroll_depth_pct'] ?? $props['depth_percent'] ?? 0),
                    // scroll_depth_px: 'scroll_depth' event stores as depth_px
                    (int) ($props['scroll_depth_px']  ?? $props['depth_px']  ?? 0),
                    // element_visible_ms: explicit field or visible_ms (section_visibility events go to separate table)
                    (int) ($props['element_visible_ms'] ?? $props['total_visible_ms'] ?? 0),
                    // Click / element fields — from element_click dual-write
                    (int) ($props['is_rage_click']    ?? $event['is_rage_click']    ?? 0),
                    (int) ($props['is_dead_click']    ?? $event['is_dead_click']    ?? 0),
                    "'" . $this->esc($props['tag']     ?? $props['target_element_tag']     ?? '') . "'",
                    "'" . $this->esc($props['id']      ?? $props['target_element_id']      ?? '') . "'",
                    "'" . $this->esc(substr($props['classes'] ?? $props['target_element_classes'] ?? '', 0, 200)) . "'",
                    "'" . $this->esc(substr($props['text']    ?? $props['target_element_text']    ?? '', 0, 200)) . "'",
                    "'" . $this->esc($props['data_track'] ?? $props['target_data_track'] ?? '') . "'",
                    (int) ($props['click_x']     ?? 0),
                    (int) ($props['click_y']     ?? 0),
                    (int) ($props['click_x_pct'] ?? 0),
                    (int) ($props['click_y_pct'] ?? 0),
                    // is_new_user is a TOP-LEVEL field in the SDK event, NOT inside context
                    (int) ($event['is_new_user'] ?? 0),
                    "'" . $this->esc($ts) . "'",
                ];

                $rows[] = '(' . implode(',', $values) . ')';
            }

            $sql = "INSERT INTO enox_tracker.events (" . implode(',', $columns) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert events', ['message' => $e->getMessage(), 'count' => count($events)]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // SESSIONS
    // ─────────────────────────────────────────
    public function upsertSession(array $s, string $ipAddress = ''): bool
    {
        try {
            $ctx    = $s['context']  ?? [];
            $dev    = $ctx['device'] ?? [];
            $camp   = $ctx['campaign'] ?? [];
            $page   = $ctx['page']   ?? [];
            $start  = $this->parseTimestamp($s['start_time'] ?? null);
            $end    = $this->parseTimestamp($s['end_time']   ?? null);

            $cols = [
                'session_id','project_id','anonymous_id','user_id',
                'device_type','browser','os','screen_resolution','language','connection_type',
                'ip_address',
                'referrer','utm_source','utm_medium','utm_campaign',
                'landing_page','is_new_user',
                'page_count','event_count',
                'start_time','end_time','duration_seconds',
            ];

            $vals = [
                "'" . $this->esc($s['session_id']   ?? '') . "'",
                "'" . $this->esc($s['project_id']   ?? 'default') . "'",
                "'" . $this->esc($s['anonymous_id'] ?? '') . "'",
                "'" . $this->esc($s['user_id']      ?? '') . "'",
                "'" . $this->esc($dev['type']       ?? '') . "'",
                "'" . $this->esc($dev['browser']    ?? '') . "'",
                "'" . $this->esc($dev['os']         ?? '') . "'",
                "'" . $this->esc($dev['screen']     ?? '') . "'",
                "'" . $this->esc($dev['language']   ?? '') . "'",
                "'" . $this->esc($dev['connection'] ?? '') . "'",
                "'" . $this->esc($ipAddress) . "'",
                "'" . $this->esc($page['referrer']  ?? '') . "'",
                "'" . $this->esc($camp['source']    ?? '') . "'",
                "'" . $this->esc($camp['medium']    ?? '') . "'",
                "'" . $this->esc($camp['name']      ?? '') . "'",
                "'" . $this->esc($s['landing_page'] ?? '') . "'",
                (int) ($s['is_new_user']       ?? 1),
                (int) ($s['page_count']        ?? 0),
                (int) ($s['event_count']       ?? 0),
                "'" . $this->esc($start) . "'",
                "'" . $this->esc($end)   . "'",
                (int) ($s['duration_seconds']  ?? 0),
            ];

            $sql = "INSERT INTO enox_tracker.sessions (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to upsert session', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // ELEMENT INTERACTIONS
    // ─────────────────────────────────────────
    public function insertElementInteractions(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','anonymous_id','user_id',
                'element_tag','element_id','element_classes','element_text','element_type','data_track',
                'click_x','click_y','viewport_width','viewport_height','click_x_pct','click_y_pct',
                'is_rage_click','rage_click_count','is_dead_click',
                'page_path','page_url','ip_address','event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);
                $vw = (int) ($p['viewport_width'] ?? 0);
                $vh = (int) ($p['viewport_height'] ?? 0);
                $cx = (int) ($p['click_x'] ?? 0);
                $cy = (int) ($p['click_y'] ?? 0);

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($p['tag']     ?? '') . "'",
                    "'" . $this->esc($p['id']      ?? '') . "'",
                    "'" . $this->esc(substr($p['classes'] ?? '', 0, 200)) . "'",
                    "'" . $this->esc(substr($p['text']    ?? '', 0, 200)) . "'",
                    "'" . $this->esc($p['element_type'] ?? '') . "'",
                    "'" . $this->esc($p['data_track']   ?? '') . "'",
                    $cx, $cy, $vw, $vh,
                    (int) ($p['click_x_pct'] ?? ($vw ? (int) round($cx / $vw * 100) : 0)),
                    (int) ($p['click_y_pct'] ?? ($vh ? (int) round($cy / $vh * 100) : 0)),
                    (int) ($p['is_rage_click']    ?? 0),
                    (int) ($p['rage_click_count'] ?? 0),
                    (int) ($p['is_dead_click']    ?? 0),
                    "'" . $this->esc($p['page_path'] ?? $event['context']['page']['path'] ?? '') . "'",
                    "'" . $this->esc($event['context']['page']['url'] ?? '') . "'",
                    "'" . $this->esc($ipAddress) . "'",
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.element_interactions (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert element interactions', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // SECTION VISIBILITY (ms not seconds)
    // ─────────────────────────────────────────
    public function insertSectionVisibility(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','anonymous_id','user_id',
                'section_id','section_label','visible_ms','visible_pct',
                'page_path','ip_address','event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);

                // SDK sends visible_ms (v2) or visible_seconds (v1 legacy) — handle both
                $visibleMs = (int) ($p['visible_ms'] ?? (($p['visible_seconds'] ?? 0) * 1000));

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($p['section_id']    ?? '') . "'",
                    "'" . $this->esc($p['section_label'] ?? '') . "'",
                    $visibleMs,
                    (int) ($p['visible_percent'] ?? 0),
                    "'" . $this->esc($p['page_path'] ?? $event['context']['page']['path'] ?? '') . "'",
                    "'" . $this->esc($ipAddress) . "'",
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.section_visibility (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert section visibility', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // ACCORDION TRACKING (open_duration_ms)
    // ─────────────────────────────────────────
    public function insertAccordionTracking(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','anonymous_id','user_id',
                'product_id','accordion_id','accordion_name','action','open_duration_ms',
                'page_path','ip_address','event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);

                // SDK v2 sends open_duration_ms; v1 sent duration_seconds — handle both
                $durMs = (int) ($p['open_duration_ms'] ?? (($p['duration_seconds'] ?? 0) * 1000));

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($p['product_id']      ?? '') . "'",
                    "'" . $this->esc($p['accordion_id']    ?? '') . "'",
                    "'" . $this->esc($p['accordion_name']  ?? '') . "'",
                    "'" . $this->esc($p['action']          ?? '') . "'",
                    $durMs,
                    "'" . $this->esc($p['page_path'] ?? $event['context']['page']['path'] ?? '') . "'",
                    "'" . $this->esc($ipAddress) . "'",
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.accordion_tracking (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert accordion tracking', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // PAGE TRANSITIONS (time_on_from_ms)
    // ─────────────────────────────────────────
    public function insertPageTransitions(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','anonymous_id','user_id',
                'from_path','from_title','to_path','to_title',
                'time_on_from_ms','navigation_type',
                'ip_address','event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);

                // SDK v2 sends time_on_from_ms; v1 sent time_on_from_seconds — handle both
                $dwellMs = (int) ($p['time_on_from_ms'] ?? (($p['time_on_from_seconds'] ?? 0) * 1000));

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($p['from_path']        ?? '') . "'",
                    "'" . $this->esc($p['from_title']       ?? '') . "'",
                    "'" . $this->esc($p['to_path']          ?? '') . "'",
                    "'" . $this->esc($p['to_title']         ?? '') . "'",
                    $dwellMs,
                    "'" . $this->esc($p['navigation_type']  ?? 'link_click') . "'",
                    "'" . $this->esc($ipAddress) . "'",
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.page_transitions (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert page transitions', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // IMAGE ATTENTION
    // ─────────────────────────────────────────
    public function insertImageAttention(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','anonymous_id','user_id',
                'product_id','image_index','image_url','image_type',
                'visible_ms','hover_ms','hover_count',
                'was_zoomed','zoom_duration_ms','was_swiped','swipe_direction','was_clicked',
                'scroll_position_pct','page_path','event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($p['product_id']      ?? '') . "'",
                    (int) ($p['image_index'] ?? 0),
                    "'" . $this->esc(substr($p['image_url'] ?? '', 0, 500)) . "'",
                    "'" . $this->esc($p['image_type']      ?? 'gallery') . "'",
                    (int) ($p['visible_ms']       ?? 0),
                    (int) ($p['hover_ms']         ?? 0),
                    (int) ($p['hover_count']      ?? 0),
                    (int) ($p['was_zoomed']        ?? 0),
                    (int) ($p['zoom_duration_ms']  ?? 0),
                    (int) ($p['was_swiped']        ?? 0),
                    "'" . $this->esc($p['swipe_direction'] ?? '') . "'",
                    (int) ($p['was_clicked']       ?? 0),
                    (int) ($p['scroll_position_pct'] ?? 0),
                    "'" . $this->esc($p['page_path'] ?? $event['context']['page']['path'] ?? '') . "'",
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.image_attention (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert image attention', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // HOVER EVENTS
    // ─────────────────────────────────────────
    public function insertHoverEvents(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','anonymous_id','user_id',
                'element_tag','element_id','element_type','element_label',
                'hover_duration_ms','hover_start_x','hover_start_y','hover_end_x','hover_end_y',
                'product_id','page_path','event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($p['element_tag']   ?? '') . "'",
                    "'" . $this->esc($p['element_id']    ?? '') . "'",
                    "'" . $this->esc($p['element_type']  ?? '') . "'",
                    "'" . $this->esc(substr($p['element_label'] ?? '', 0, 200)) . "'",
                    (int) ($p['hover_duration_ms'] ?? 0),
                    (int) ($p['hover_start_x']     ?? 0),
                    (int) ($p['hover_start_y']     ?? 0),
                    (int) ($p['hover_end_x']       ?? 0),
                    (int) ($p['hover_end_y']       ?? 0),
                    "'" . $this->esc($p['product_id'] ?? '') . "'",
                    "'" . $this->esc($p['page_path']  ?? '') . "'",
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.hover_events (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert hover events', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // FORM INTERACTIONS
    // ─────────────────────────────────────────
    public function insertFormInteractions(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','anonymous_id','user_id',
                'form_id','form_name','field_name','field_type','action',
                'had_error','error_message','time_on_field_ms',
                'page_path','event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);

                // Handle v1 (duration_seconds) and v2 (time_on_field_ms)
                $timeMs = (int) ($p['time_on_field_ms'] ?? (($p['duration_seconds'] ?? 0) * 1000));

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($p['form_id']         ?? '') . "'",
                    "'" . $this->esc($p['form_id'] ?? $p['form_name'] ?? '') . "'",
                    "'" . $this->esc($p['field_name']       ?? '') . "'",
                    "'" . $this->esc($p['field_type']       ?? '') . "'",
                    "'" . $this->esc($p['action']           ?? '') . "'",
                    (int) ($p['had_error']    ?? 0),
                    "'" . $this->esc(substr($p['error_message'] ?? '', 0, 300)) . "'",
                    $timeMs,
                    "'" . $this->esc($p['page_path'] ?? '') . "'",
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.form_interactions (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert form interactions', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // PERFORMANCE METRICS (Core Web Vitals)
    // ─────────────────────────────────────────
    public function insertPerformanceMetrics(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','anonymous_id','user_id',
                'page_path','page_type',
                'lcp_ms','fcp_ms','fid_ms','inp_ms','cls_score','ttfb_ms','dom_load_ms','page_load_ms',
                'lcp_rating','cls_rating','inp_rating',
                'connection_type','effective_bandwidth','device_type','browser',
                'event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($p['page_path']        ?? '') . "'",
                    "'" . $this->esc($p['page_type']        ?? '') . "'",
                    (float) ($p['lcp_ms']      ?? 0),
                    (float) ($p['fcp_ms']      ?? 0),
                    (float) ($p['fid_ms']      ?? 0),
                    (float) ($p['inp_ms']      ?? 0),
                    (float) ($p['cls_score']   ?? 0),
                    (float) ($p['ttfb_ms']     ?? 0),
                    (float) ($p['dom_load_ms'] ?? 0),
                    (float) ($p['page_load_ms'] ?? 0),
                    "'" . $this->esc($p['lcp_rating'] ?? '') . "'",
                    "'" . $this->esc($p['cls_rating'] ?? '') . "'",
                    "'" . $this->esc($p['inp_rating'] ?? '') . "'",
                    "'" . $this->esc($p['connection_type']      ?? '') . "'",
                    (float) ($p['effective_bandwidth']           ?? 0),
                    "'" . $this->esc($event['context']['device']['type']    ?? '') . "'",
                    "'" . $this->esc($event['context']['device']['browser'] ?? '') . "'",
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.performance_metrics (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert performance metrics', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // ERROR EVENTS
    // ─────────────────────────────────────────
    public function insertErrorEvents(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','anonymous_id','user_id','page_path',
                'error_type','error_message','error_filename','error_line','error_column','error_stack',
                'device_type','browser','os','event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($p['page_path']        ?? '') . "'",
                    "'" . $this->esc($p['error_type']       ?? 'js_error') . "'",
                    "'" . $this->esc(substr($p['error_message']  ?? '', 0, 500)) . "'",
                    "'" . $this->esc(substr($p['error_filename'] ?? '', 0, 300)) . "'",
                    (int) ($p['error_line']   ?? 0),
                    (int) ($p['error_column'] ?? 0),
                    "'" . $this->esc(substr($p['error_stack'] ?? '', 0, 2000)) . "'",
                    "'" . $this->esc($event['context']['device']['type']    ?? '') . "'",
                    "'" . $this->esc($event['context']['device']['browser'] ?? '') . "'",
                    "'" . $this->esc($event['context']['device']['os']      ?? '') . "'",
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.error_events (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert error events', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // EXIT INTENT EVENTS
    // ─────────────────────────────────────────
    public function insertExitIntentEvents(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','anonymous_id','user_id',
                'trigger_type','time_on_page_ms','active_time_ms','scroll_depth_at_exit',
                'page_path','page_type','device_type','event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($p['trigger_type']     ?? 'mouse_leave_top') . "'",
                    (int) ($p['time_on_page_ms']      ?? $event['total_time_on_page_ms'] ?? 0),
                    (int) ($p['active_time_ms']        ?? $event['active_time_ms'] ?? 0),
                    (int) ($p['scroll_depth_at_exit']  ?? 0),
                    "'" . $this->esc($p['page_path']   ?? '') . "'",
                    "'" . $this->esc($p['page_type']   ?? '') . "'",
                    "'" . $this->esc($event['context']['device']['type'] ?? '') . "'",
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.exit_intent_events (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert exit intent events', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // VIDEO EVENTS
    // ─────────────────────────────────────────
    public function insertVideoEvents(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','anonymous_id','user_id',
                'product_id','page_path','video_id','video_url','video_type',
                'action','play_position_s','video_duration_s','watch_pct',
                'is_autoplay','is_muted','play_count','pause_count',
                'event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($p['product_id']       ?? '') . "'",
                    "'" . $this->esc($p['page_path']        ?? '') . "'",
                    "'" . $this->esc($p['video_id']         ?? '') . "'",
                    "'" . $this->esc(substr($p['video_url'] ?? '', 0, 500)) . "'",
                    "'" . $this->esc($p['video_type']       ?? 'product') . "'",
                    "'" . $this->esc($p['action']           ?? '') . "'",
                    (int)   ($p['play_position_s']  ?? 0),
                    (int)   ($p['video_duration_s'] ?? 0),
                    (int)   ($p['watch_pct']        ?? 0),
                    (int)   ($p['is_autoplay']      ?? 0),
                    (int)   ($p['is_muted']         ?? 0),
                    (int)   ($p['play_count']       ?? 0),
                    (int)   ($p['pause_count']      ?? 0),
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.video_events (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert video events', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // PRODUCT INTERACTIONS
    // Maps: product_interaction, product_viewed (deep), image_swiped deep events
    // ─────────────────────────────────────────
    public function insertProductInteractions(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','user_id','anonymous_id',
                'product_id','sku','page_path',
                'interaction_type','variant_color','variant_size','variant_id','is_in_stock',
                'image_index','image_url','image_type',
                'section_name','read_depth_pct',
                'time_since_page_entry_ms','active_time_before_ms',
                'event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($p['product_id']       ?? '') . "'",
                    "'" . $this->esc($p['sku']              ?? '') . "'",
                    "'" . $this->esc($p['page_path']        ?? $event['context']['page']['path'] ?? '') . "'",
                    "'" . $this->esc($p['interaction_type'] ?? 'page_view') . "'",
                    "'" . $this->esc($p['variant_color']    ?? '') . "'",
                    "'" . $this->esc($p['variant_size']     ?? '') . "'",
                    "'" . $this->esc($p['variant_id']       ?? '') . "'",
                    (int) ($p['is_in_stock'] ?? 1),
                    (int) ($p['image_index'] ?? 0),
                    "'" . $this->esc(substr($p['image_url']   ?? '', 0, 400)) . "'",
                    "'" . $this->esc($p['image_type']        ?? '') . "'",
                    "'" . $this->esc($p['section_name']      ?? '') . "'",
                    (int) ($p['read_depth_pct']              ?? 0),
                    (int) ($p['time_since_page_entry_ms']    ?? $event['total_time_on_page_ms'] ?? 0),
                    (int) ($p['active_time_before_ms']       ?? $event['active_time_ms'] ?? 0),
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.product_interactions (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert product interactions', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // DESCRIPTION READS
    // ─────────────────────────────────────────
    public function insertDescriptionReads(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','user_id','anonymous_id',
                'product_id','sku','page_path',
                'section_name','section_position',
                'entered_viewport','max_read_pct','visible_ms','active_read_ms','scroll_passes',
                'was_expanded','was_copied','link_clicked','link_url','read_speed_score',
                'event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($p['product_id']       ?? '') . "'",
                    "'" . $this->esc($p['sku']              ?? '') . "'",
                    "'" . $this->esc($p['page_path']        ?? '') . "'",
                    "'" . $this->esc($p['section_name']     ?? '') . "'",
                    (int) ($p['section_position']           ?? 0),
                    (int) ($p['entered_viewport']           ?? 0),
                    (int) ($p['max_read_pct']               ?? 0),
                    (int) ($p['visible_ms']                 ?? 0),
                    (int) ($p['active_read_ms']             ?? 0),
                    (int) ($p['scroll_passes']              ?? 0),
                    (int) ($p['was_expanded']               ?? 0),
                    (int) ($p['was_copied']                 ?? 0),
                    (int) ($p['link_clicked']               ?? 0),
                    "'" . $this->esc(substr($p['link_url'] ?? '', 0, 300)) . "'",
                    (float) ($p['read_speed_score']         ?? 0),
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.description_reads (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert description reads', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // SEARCH EVENTS
    // ─────────────────────────────────────────
    public function insertSearchEvents(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','user_id','anonymous_id',
                'query','query_normalized','query_word_count',
                'results_count','no_results',
                'clicked_result_id','clicked_result_pos','click_through','time_to_click_ms',
                'refined_query','next_query',
                'search_source','applied_filters','page_path',
                'event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);
                $query = $p['query'] ?? '';

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc(substr($query, 0, 500)) . "'",
                    "'" . $this->esc($p['query_normalized'] ?? strtolower(trim($query))) . "'",
                    (int) ($p['query_word_count']           ?? str_word_count($query)),
                    (int) ($p['results_count']              ?? 0),
                    (int) ($p['no_results']                 ?? 0),
                    "'" . $this->esc($p['clicked_result_id']  ?? '') . "'",
                    (int) ($p['clicked_result_pos']           ?? 0),
                    (int) ($p['click_through']                ?? 0),
                    (int) ($p['time_to_click_ms']             ?? 0),
                    (int) ($p['refined_query']                ?? 0),
                    "'" . $this->esc($p['next_query']          ?? '') . "'",
                    "'" . $this->esc($p['search_source']       ?? 'site') . "'",
                    "'" . $this->esc($p['applied_filters']     ?? '{}') . "'",
                    "'" . $this->esc($p['page_path']           ?? '') . "'",
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.search_events (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert search events', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // ATTENTION SPANS
    // ─────────────────────────────────────────
    public function insertAttentionSpans(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','user_id','anonymous_id',
                'element_id','element_type','element_label','element_position',
                'page_path','page_type',
                'total_visible_ms','active_visible_ms','visibility_pct','view_count',
                'was_clicked','click_delay_ms','was_hovered','hover_ms',
                'product_id','event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($p['element_id']       ?? '') . "'",
                    "'" . $this->esc($p['element_type']     ?? '') . "'",
                    "'" . $this->esc(substr($p['element_label'] ?? '', 0, 200)) . "'",
                    (int) ($p['element_position']           ?? 0),
                    "'" . $this->esc($p['page_path']        ?? '') . "'",
                    "'" . $this->esc($p['page_type']        ?? '') . "'",
                    (int) ($p['total_visible_ms']           ?? 0),
                    (int) ($p['active_visible_ms']          ?? 0),
                    (int) ($p['visibility_pct']             ?? 0),
                    (int) ($p['view_count']                 ?? 1),
                    (int) ($p['was_clicked']                ?? 0),
                    (int) ($p['click_delay_ms']             ?? 0),
                    (int) ($p['was_hovered']                ?? 0),
                    (int) ($p['hover_ms']                   ?? 0),
                    "'" . $this->esc($p['product_id']       ?? '') . "'",
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.attention_spans (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert attention spans', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // SCROLL EVENTS
    // ─────────────────────────────────────────
    public function insertScrollEvents(array $events, string $ipAddress = ''): bool
    {
        if (empty($events)) return true;

        try {
            $cols = [
                'project_id','session_id','user_id','anonymous_id',
                'page_path','page_type',
                'scroll_depth_pct','scroll_depth_px','page_height_px','viewport_height_px',
                'direction','is_milestone','scroll_velocity',
                'time_since_page_entry_ms','active_time_ms',
                'event_timestamp',
            ];

            $rows = [];
            foreach ($events as $event) {
                $p  = $event['properties'] ?? [];
                $ts = $this->parseTimestamp($event['timestamp'] ?? null);

                $rows[] = '(' . implode(',', [
                    "'" . $this->esc($event['project_id']   ?? 'default') . "'",
                    "'" . $this->esc($event['session_id']   ?? '') . "'",
                    "'" . $this->esc($event['user_id']      ?? '') . "'",
                    "'" . $this->esc($event['anonymous_id'] ?? '') . "'",
                    "'" . $this->esc($p['page_path']        ?? '') . "'",
                    "'" . $this->esc($p['page_type']        ?? '') . "'",
                    (int)   ($p['depth_percent']            ?? 0),
                    (int)   ($p['depth_px']                 ?? 0),
                    (int)   ($p['page_height_px']           ?? 0),
                    (int)   ($p['viewport_height_px']       ?? 0),
                    "'" . $this->esc($p['direction']        ?? 'down') . "'",
                    (int)   ($p['is_milestone']             ?? 1),
                    (float) ($p['scroll_velocity']          ?? 0),
                    (int)   ($p['time_since_page_entry_ms'] ?? 0),
                    (int)   ($p['active_time_ms']           ?? $event['active_time_ms'] ?? 0),
                    "'" . $this->esc($ts) . "'",
                ]) . ')';
            }

            $sql = "INSERT INTO enox_tracker.scroll_events (" . implode(',', $cols) . ") VALUES " . implode(',', $rows);
            return $this->ch->statement($sql);

        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Failed to insert scroll events', ['message' => $e->getMessage()]);
            return false;
        }
    }

    // ─────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────
    protected function esc(string $value): string
    {
        return addslashes($value);
    }

    protected function parseTimestamp($timestamp): string
    {
        if (!$timestamp) {
            return now()->format('Y-m-d H:i:s.v');
        }
        if (is_numeric($timestamp)) {
            return date('Y-m-d H:i:s.v', (int) ($timestamp / 1000));
        }
        return (string) $timestamp;
    }

    /**
     * Health check — delegates to ClickHouseService.
     */
    public function healthCheck(): ?array
    {
        try {
            return $this->ch->query('SELECT 1 AS ok');
        } catch (\Exception $e) {
            Log::error('[EnoxTracker] Health check failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Run an arbitrary SELECT query and return results (for debug endpoint only).
     */
    public function rawQuery(string $sql): ?array
    {
        return $this->ch->query($sql);
    }
}

