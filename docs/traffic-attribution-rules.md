# Traffic source attribution rules

Canonical reference for CEO reporting, User activity filters, dashboard sync, and session detail.  
Config: `config/tracker.php` (`TRACKER_*` env vars).

## 1. Session boundary (visit)

| Setting | Default | Rule |
|---------|---------|------|
| `TRACKER_SESSION_GAP_MINUTES` | **30** | No activity for 30 minutes → **new session** (new `session_id`). |

Each session is attributed on its own before visitor-level rules apply.

## 2. In-session touches (sync → filters & dashboard audience)

**Storage:** `tracking_session_details` (`touch_sequence` ascending).

**Built during dashboard sync** (`TrackerDashboardSyncService::upsertSessionDetails`):

1. Collect touches from **all** `activity_ecom_user_actions` for the session (landing URL + marketing params on later `page_url`s). Same-site referer alone does **not** create a new paid/affiliate touch.
2. If none, fall back to session row / landing heuristics (`resolvedTrafficBucket`).
3. **Replace** stored touches when the collected list changes (not append-only).
4. **Calendar-day visitor bucket** (below) overwrites `utm_source` / `utm_medium` on that visitor's sessions for the visit date.

**User activity UTM filter** and **traffic drill-down** (`utm_source` = `tracking_traffic_source.id`):

- Match the synced day bucket on the session (`utm_source` / `utm_medium` on `tracking_session_details`), including `utm_medium`.

**Session detail → “Session traffic”:**

- Shows the same synced rows as the filter (after sync). Falls back to live action collection only if sync has not run.

## 3. One source and one medium per visitor per calendar day

**Business rule:** Source and medium are counted from the start of the calendar day to the end of that day (`TRACKER_VISITOR_TIMEZONE`). The next calendar day starts again. Paid qty and sale stay on the order placed time.

| That day | Source | Medium |
|----------|--------|--------|
| Only Direct | `(direct)` | `none` |
| Direct, then one real source (for example Google) | That source, on **every** session that day | That session's real medium, on **every** session that day |
| Two or more different real sources | `multi sources` on every session that day | Resolved on its own |
| Two or more different real mediums | Resolved on its own | `multi mediums` on every session that day |
| Next day, no source | `(direct)` | `none` |

Direct and medium `none` do not count as a second source or medium. The same source twice is still that one source. Source and medium are decided separately.

**Same visit:** Trailing Direct after a marketing touch in that session is dropped before the day is counted (`normalizeTouchesForAttributionStorage()`).

Implementation: `SessionTrafficAttribution::resolveVisitorDayBucket()` then `TrackerDashboardSyncService::applyVisitorDayTrafficBucket()` during dashboard sync.

## 4. Day boundary

This is the calendar day of the session visit date, not a rolling 24 hours and not the 7-day conversion window. A return the next day with no campaign URL is Direct.

## 5. Seven-day visitor marketing (list columns & conversion copy)

| Setting | Default | Rule |
|---------|---------|------|
| `TRACKER_CONVERSION_WINDOW_DAYS` | **7** | For `list_traffic_utm_*` on `activity_ecom_user`, conversion blocks, and some dashboard SQL — last marketing touch in **7 days** before session end / payment (skips Direct for order credit). |

**Not** used for User activity UTM source/medium filters (those use the calendar-day bucket in §3).

## 6. Orders / revenue

- **Conversion** on paid orders: last **non-direct** touch in the 7-day window (`conversion_utm_*`, order traffic fields).
- Revenue metrics are not bot-filtered; funnel session counts may exclude bots when `TRACKER_DASHBOARD_EXCLUDE_BOTS=true`.

## 7. Direct `(direct)` / medium `none`

- **Direct** = no qualifying marketing on the URL for that touch (internal navigation, login, returns pages without `awc` / UTMs, etc.).
- Filter id **1** in `tracking_traffic_source` is typically `(direct)`.

## 8. Operations

- After code changes to touch logic: run **Ecom Tracker → Sync** (or `TrackerDashboardSyncService::syncSessionIds`) so `tracking_session_details` and daily rollups refresh.
- Related: `docs/activity-list-queries.md` (indexes & `list_traffic_*` columns).

## Changelog

| Date | Change |
|------|--------|
| 2026-10-26 | Document rules; sync rebuilds touches from full action history; 24h visitor carry; strip trailing Direct after in-session marketing; session page prefers synced touches; source-2 stickiness documented. |
| 2026-11-08 | Sync and user-activity filters use one source and one medium per visitor per calendar day. More than one real value that day is `multi sources` / `multi mediums`. The next day starts again. |
