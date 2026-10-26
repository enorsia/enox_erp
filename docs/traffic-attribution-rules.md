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
3. **24-hour visitor carry** (below).
4. **Replace** stored touches when the collected list changes (not append-only).

**User activity UTM filter** and **traffic drill-down** (`utm_source` = `tracking_traffic_source.id`):

- Match **last touch** in that session (`MAX(touch_sequence)`), including `utm_medium` on that row.

**Session detail → “Session traffic”:**

- Shows the same synced rows as the filter (after sync). Falls back to live action collection only if sync has not run.

## 3. Source 1 → Source 2 → “sticks” to Source 2 (visitor + visit)

**Business rule (CEO):** If a user comes on **source 1**, then later on **source 2**, then **after source 2** the same user’s activity should count under **source 2** until a **newer** campaign URL or the carry window ends.

How the product implements that:

| Step | What happens |
|------|----------------|
| Visit A | Campaign **source 1** on URL → stored as touch 1; filters = source 1. |
| Visit B (later) | Campaign **source 2** on URL → stored; filters = **source 2**. |
| Visit C (no UTMs) | No campaign on landing/browse → would be Direct, but **24h carry** reuses visitor’s **latest** qualifying touch → **source 2**. |
| Visit D | New campaign **source 3** on URL → **source 3** takes over (latest marketing wins). |
| After 24h | No qualifying touch in window → **Direct**. |

**Same visit:** If the user hits source 1 then source 2 then keeps browsing with no UTMs, we **drop trailing Direct** touches and keep the **last in-session marketing** touch (source 2), not Direct.

Implementation:

- `normalizeTouchesForAttributionStorage()` — remove trailing Direct after a marketing touch in the same session.
- `applySessionAttributionCarry()` — Direct-only visits reuse **last qualifying touch for that visitor** in the carry window (always the **newest** source, e.g. source 2 after source 1).

## 4. Twenty-four-hour visitor carry (config)

| Setting | Default | Rule |
|---------|---------|------|
| `TRACKER_ATTRIBUTION_CARRY_HOURS` | **24** | Window for §3 cross-visit stickiness. After 24h with no qualifying touch → **Direct**. |

Qualifying touch = UTM / click-id on URL (`AttributionRules::isMarketingQualifyingTouch`).

## 5. Seven-day visitor marketing (list columns & conversion copy)

| Setting | Default | Rule |
|---------|---------|------|
| `TRACKER_CONVERSION_WINDOW_DAYS` | **7** | For `list_traffic_utm_*` on `activity_ecom_user`, conversion blocks, and some dashboard SQL — last marketing touch in **7 days** before session end / payment (skips Direct for order credit). |

**Not** used for User activity UTM source/medium filters (those use §2 last in-session touch).

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
