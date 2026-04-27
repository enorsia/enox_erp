<?php

namespace App\Http\Controllers;

use App\Services\ClickHouseService;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function __construct(protected ClickHouseService $ch) {}

    /* ──────────────────────────────────────────────────
     | USER LIST  — paginated, filterable
     ─────────────────────────────────────────────────── */
    public function index(Request $request)
    {
        $search   = $request->input('search', '');
        $perPage  = 30;
        $page     = max(1, (int) $request->input('page', 1));
        $offset   = ($page - 1) * $perPage;

        $where = "WHERE anonymous_id != ''";
        if ($search) {
            $safe = addslashes($search);
            $where .= " AND (anonymous_id ILIKE '%{$safe}%' OR ip_address ILIKE '%{$safe}%' OR country ILIKE '%{$safe}%' OR city ILIKE '%{$safe}%')";
        }

        // Total count
        $countSql = "SELECT count() AS total
                     FROM (
                         SELECT anonymous_id
                         FROM enox_tracker.events
                         {$where}
                         GROUP BY anonymous_id
                     )";
        $countResult = $this->ch->query($countSql);
        $total = (int) ($countResult['data'][0]['total'] ?? 0);

        // User rows
        $sql = "SELECT
                    anonymous_id,
                    anyIf(user_id, user_id != '')               AS user_id,
                    count()                                      AS total_events,
                    uniqExact(session_id)                        AS total_sessions,
                    min(event_timestamp)                         AS first_seen,
                    max(event_timestamp)                         AS last_seen,
                    anyIf(device_type, device_type != '')        AS device_type,
                    anyIf(browser,     browser     != '')        AS browser,
                    anyIf(os,          os          != '')        AS os,
                    anyIf(country,     country     != '')        AS country,
                    anyIf(city,        city        != '')        AS city,
                    anyIf(ip_address,  ip_address  != '')        AS ip_address,
                    countIf(event_name = 'order_placed')         AS orders,
                    sumIf(event_value, event_name = 'order_placed') AS revenue,
                    countIf(event_name = 'page_viewed')          AS page_views,
                    countIf(event_name = 'product_viewed')       AS product_views,
                    if(uniqExact(session_id) > 1, 0, 1)          AS single_session_user
                FROM enox_tracker.events
                {$where}
                GROUP BY anonymous_id
                ORDER BY last_seen DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $result = $this->ch->query($sql);
        $users  = $result['data'] ?? [];

        $totalPages = (int) ceil($total / $perPage);

        return view('tracking.index', compact('users', 'total', 'page', 'totalPages', 'perPage', 'search'));
    }

    /* ──────────────────────────────────────────────────
     | USER JOURNEY  — page-batch event timeline
     ─────────────────────────────────────────────────── */
    public function journey(Request $request, string $anonymousId)
    {
        $safeId = addslashes($anonymousId);

        // ── Summary stats ──────────────────────────────
        $summarySql = "SELECT
                count()                                      AS total_events,
                uniqExact(session_id)                        AS total_sessions,
                min(event_timestamp)                         AS first_seen,
                max(event_timestamp)                         AS last_seen,
                anyIf(device_type, device_type != '')        AS device_type,
                anyIf(browser,     browser     != '')        AS browser,
                anyIf(os,          os          != '')        AS os,
                anyIf(country,     country     != '')        AS country,
                anyIf(city,        city        != '')        AS city,
                anyIf(ip_address,  ip_address  != '')        AS ip_address,
                anyIf(screen_resolution, screen_resolution != '') AS screen_resolution,
                anyIf(language,    language    != '')        AS language,
                countIf(event_name = 'order_placed')         AS orders,
                sumIf(event_value,  event_name = 'order_placed') AS revenue,
                countIf(event_name = 'page_viewed')          AS page_views,
                anyIf(user_id,     user_id     != '')        AS user_id
            FROM enox_tracker.events
            WHERE anonymous_id = '{$safeId}'";

        $summaryResult = $this->ch->query($summarySql);
        $summary = $summaryResult['data'][0] ?? [];

        // ── Sessions ───────────────────────────────────
        $sessionsSql = "SELECT
                session_id,
                min(event_timestamp) AS start_time,
                max(event_timestamp) AS end_time,
                dateDiff('second', min(event_timestamp), max(event_timestamp)) AS duration_seconds,
                count()              AS event_count,
                uniqExact(page_path) AS page_count,
                anyIf(page_path,  event_name = 'session_started') AS landing_page,
                anyIf(utm_source, utm_source  != '')               AS utm_source,
                anyIf(utm_medium, utm_medium  != '')               AS utm_medium,
                anyIf(utm_campaign, utm_campaign != '')            AS utm_campaign,
                anyIf(referrer,   referrer    != '')               AS referrer,
                countIf(event_name = 'order_placed')               AS order_placed,
                sumIf(event_value, event_name = 'order_placed')    AS revenue
            FROM enox_tracker.events
            WHERE anonymous_id = '{$safeId}'
            GROUP BY session_id
            ORDER BY start_time ASC";

        $sessionsResult = $this->ch->query($sessionsSql);
        $sessions       = $sessionsResult['data'] ?? [];

        // ── All events (ordered) ────────────────────────
        $eventsSql = "SELECT
                event_name,
                event_category,
                event_action,
                event_label,
                session_id,
                page_url,
                page_title,
                page_path,
                page_type,
                product_id,
                product_name,
                sku,
                event_value,
                active_time_ms,
                total_time_on_page_ms,
                scroll_depth_pct,
                scroll_depth_px,
                device_type,
                browser,
                country,
                city,
                event_timestamp,
                properties,
                is_rage_click,
                is_dead_click,
                utm_source,
                utm_medium,
                utm_campaign,
                referrer,
                referrer_domain,
                target_element_tag,
                target_element_text,
                target_data_track,
                order_id,
                quantity,
                price
            FROM enox_tracker.events
            WHERE anonymous_id = '{$safeId}'
            ORDER BY event_timestamp ASC
            LIMIT 3000";

        $eventsResult = $this->ch->query($eventsSql);
        $events       = $eventsResult['data'] ?? [];

        // ── Group events → session → page-batches ──────
        // A "page batch" = all consecutive events with the same page_path.
        // When page_path changes, a new batch starts.
        $eventsBySession  = [];
        $pagesBySession   = [];  // ['session_id' => [ ['page_path'=>…, 'events'=>[…], …], … ]]

        foreach ($events as $ev) {
            $sid = $ev['session_id'];
            $eventsBySession[$sid][] = $ev;
        }

        foreach ($eventsBySession as $sid => $sessEvents) {
            $batches      = [];
            $curBatch     = null;
            $batchNum     = 0;
            $visitedPaths = [];          // track revisits within this session

            foreach ($sessEvents as $ev) {
                $path = $ev['page_path'] ?: '/';

                // Start new batch when path changes (or very first event)
                if ($curBatch === null || $path !== $curBatch['page_path']) {
                    if ($curBatch !== null) {
                        $batches[] = $curBatch;
                    }
                    $batchNum++;
                    $isRevisit = in_array($path, $visitedPaths, true);
                    if (!$isRevisit) {
                        $visitedPaths[] = $path;
                    }
                    $curBatch = [
                        'batch_num'       => $batchNum,
                        'page_path'       => $path,
                        'page_url'        => $ev['page_url'],
                        'page_title'      => $ev['page_title'],
                        'page_type'       => $ev['page_type'],
                        'start_time'      => $ev['event_timestamp'],
                        'end_time'        => $ev['event_timestamp'],
                        'referrer'        => $ev['referrer'],
                        'referrer_domain' => $ev['referrer_domain'] ?? '',
                        'utm_source'      => $ev['utm_source'],
                        'utm_medium'      => $ev['utm_medium'],
                        'utm_campaign'    => $ev['utm_campaign'],
                        'events'          => [],
                        'product_views'   => 0,
                        'clicks'          => 0,
                        'order_placed'    => false,
                        'is_revisit'      => $isRevisit,   // true when user returned to this URL
                    ];
                }

                // Accumulate page metrics
                $curBatch['end_time'] = $ev['event_timestamp'];
                $curBatch['events'][] = $ev;

                $evName = $ev['event_name'];
                if ($evName === 'product_viewed')  $curBatch['product_views']++;
                if (str_contains($evName, 'click')) $curBatch['clicks']++;
                if ($evName === 'order_placed')     $curBatch['order_placed'] = true;
            }

            if ($curBatch !== null) {
                $batches[] = $curBatch;
            }

            $pagesBySession[$sid] = $batches;
        }

        return view('tracking.journey', compact(
            'anonymousId', 'summary', 'sessions', 'events',
            'eventsBySession', 'pagesBySession'
        ));
    }
}

