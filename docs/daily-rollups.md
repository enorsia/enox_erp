# Daily rollups — existing `activity_ecom_daily_*` tables only

Analytics rules (bots, catalog, reconcile): [analytics-rules.md](analytics-rules.md).

No new tables or columns. Rollups **insert/update summary rows**; raw `activity_ecom_user` / orders / actions are never deleted or altered.

## Tables (from migrations `2026_08_25_*`)

| Table | Keys / grain | Columns used |
|--------|----------------|--------------|
| `activity_ecom_daily_site_metrics` | `metric_date` | `session_count`, `visitor_count`, `action_count`, funnel `*_count`, `order_count`, `revenue_total`, `items_sold_qty` |
| `activity_ecom_daily_visitors` | `visitor_id` + `visit_date` | `total_duration_seconds`, `session_count`, `first_seen_at`, `last_seen_at` |
| `activity_ecom_daily_visitor_metrics` | `metric_date` + `visitor_id` | `session_count`, `payment_count`, `revenue`, timestamps |
| `activity_ecom_daily_dimension_metrics` | `metric_date` + `dimension_type` + `dimension_value` | Traffic: `dimension_type=list_traffic`, value `source\0medium`; `session_count`, `payment_count`, `revenue` |
| `activity_ecom_daily_product_metrics` | `metric_date` + `product_code` | Product views, funnel, revenue (dashboard catalog) |
| `activity_ecom_daily_category_metrics` | `metric_date` + dept + category | Category/product views, funnel, revenue |
| `activity_ecom_daily_dimension_metrics` | also `device`, `browser`, `geo`, `logged_in`, `has_order` | Activity sidebar facets + geo sessions |

## Read strategy

- **Before today** (store TZ): `SUM()` from **`activity_ecom_daily_site_metrics`** (and related `activity_ecom_daily_*` tables) for **each closed day that has a rollup row**. Days without a row contribute **0** (no banner).
- **Today**: live SQL on raw tables (`activity_ecom_user`, orders, commerce lines).
- **Missing rollup for a closed day** (`TRACKER_DASHBOARD_ROLLUPS_ONLY=true`, default): that day counts as **0** on the store dashboard; raw orders/sessions for that day are **not** read. User activity still reads live raw data.
- **Missing rollup** with `TRACKER_DASHBOARD_ROLLUPS_ONLY=false`: full raw query for the whole range (same numbers if backfill were complete, slower).

Primary KPI grain for closed days: **`activity_ecom_daily_site_metrics`** (`session_count`, funnel counts, `order_count`, `revenue_total`, `items_sold_qty`, view counts).

Traffic from rollups includes **sessions, purchases, revenue** per source/medium. Views / cart steps on the traffic table for **past days** are filled from **today’s live slice** when the range includes today; otherwise run backfill and accept rollups for core commerce columns only, or use unfiltered live traffic query.

Abandonment KPIs still use live `abandonedSessionCounts()` (not stored in site metrics schema).

## Daily cron

One run = **yesterday** (store timezone). Scheduled at 01:30 in `routes/console.php`. The command prints the exact UTC window; it uses the same bounds as dashboard/activity (`TrackerTime::localCalendarDateStorageRange`).

**Session repair:** `tracker:rollup-analytics-backfill` runs **`tracker:backfill-attribution`** first by default (session clock, UTMs, list traffic, conversion). Skip with `--skip-attribution`. Rollup backfill also merges duplicate same-day sessions **per day being rolled up**.

```bash
30 1 * * * cd /path/to/enox_erp && php artisan tracker:rollup-analytics
```

Or use `php artisan schedule:run` every minute.

After deploying migration `2026_09_28_000007`, **re-run backfill** so product/category/view and facet dimensions are populated:

```bash
php artisan migrate
php artisan tracker:rollup-analytics-backfill
```

Target: dashboard (date range only — no session/product sidebar filters), activity index, and compare pages under **400ms** with `TRACKER_USE_DAILY_ROLLUPS=true` and rollups complete.

## Dashboard queries vs Debugbar (local)

| Layer | Cached? | Debugbar |
|--------|---------|----------|
| Spatie permissions / roles | Yes (`spatie.permission.cache`, 24h) | Hidden when `DEBUGBAR_HIDE_INFRA_QUERIES=true` (default) |
| Store dashboard KPIs, catalog, traffic, etc. | **No** (`TRACKER_ANALYTICS_CACHE_ENABLED=false`) | Shown (~17 SQL for `period=30d` with batch read) |
| Daily rollup tables | Pre-aggregated by cron, not HTTP cache | Shown (rollup `SELECT`s) |

Debugbar badge counts **tracker SQL only** by default; permission `cache` / `permissions` / `roles` / auth `users` lookups still run but are omitted from the list and count.

With batch read for `period=30d` / `7d` / `yesterday` (including partial rollup coverage), expect about **20–26** tracker SQL queries in Debugbar (one batch load including slim recoverable panels, previous-period funnel compare, live status). Recoverable sale tables are filled from the same snapshot (no full-range `periodLineItems` scan). `24h` is live-only (~23). Custom long ranges with many missing rollup days still use batch read; only rolled-up closed days plus today contribute non-zero totals.

Profile locally: `php scripts/profile-dashboard-periods.php` (query count + ms for yesterday / 7d / 30d / sample custom).

Optional env after migration `2026_09_28_000007`:

```env
TRACKER_DAILY_ROLLUPS_COMMERCE_VIEW_COLUMNS=true
```

Skips `information_schema` column probes on each dashboard request.

## Reconcile (read-only)

Compare closed-day site rollup revenue and order count to `activity_ecom_orders` on **`ordered_at`** (store timezone window). Run on production before trusting rollups for catalog work:

```bash
php artisan tracker:reconcile-rollups
php artisan tracker:reconcile-rollups --days=30
php artisan tracker:reconcile-rollups --from=2026-09-01 --to=2026-09-28
```

Exit code is non-zero when any day is outside tolerance. Output also shows `paymentMetricTotals` (what the rollup job uses, including action fallback when orders are missing), per-currency rollup dimension rows (`dimension_type = currency`), and bot-flagged sessions with paid orders (data quality only).

## Commands

**Daily cron** — only the last closed day:

```bash
php artisan tracker:rollup-analytics
```

**Backfill missing/failed days** — runs **attribution backfill**, then from first session through **today** merges per-day sessions and rolls up. First merges duplicate same-day sessions in that range (same local day, within `session_gap_minutes`), then rolls up only days not already `success` (`--force` re-rolls all). Use `--skip-session-merge` to roll up only; use `--skip-attribution` to skip the four attribution steps.

```bash
php artisan tracker:rollup-analytics-backfill
```

Optional range:

```bash
php artisan tracker:rollup-analytics-backfill 2024-01-01 2025-09-27
```

**Ops / retry** — per-day flags in `activity_ecom_rollup_day_status` (`success`, `failed`, `pending`):

```bash
php artisan tracker:rollup-analytics-status
php artisan tracker:rollup-analytics-status --failed
```

After a failed day, run backfill again (no `--force` needed); only missing/failed days are processed. Dashboard still reads **today** live after rollup until the next calendar day.

Single day if you need to re-run one date:

```bash
php artisan tracker:rollup-analytics 2024-06-01
```

```env
TRACKER_USE_DAILY_ROLLUPS=true
```
