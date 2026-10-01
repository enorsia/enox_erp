# EnoxTracker analytics rules

Authoritative behavior for rollups, catalog snapshots, bots, and reconcile. See also [daily-rollups.md](daily-rollups.md).

## Day boundary

- Store timezone: `config('tracker.visitor_timezone')`.
- Closed-day windows: `TrackerTime::localCalendarDateStorageRange($metricDate)` (UTC storage bounds).

## Bots (narrow funnel, never revenue)

**Exclude** sessions classified as bots (`activity_ecom_user_bot_context.is_bot = 1`) from:

- Session and visitor counts in daily rollups
- Action counts tied to human browsing
- Product/category **views** and **add_to_cart** / **begin_checkout** / **proceed_checkout** on commerce line items
- Traffic source dimension rollups and session facet dimensions (device, browser, geo, etc.)

**Never exclude** bots from:

- `CommerceFunnelQuery::paymentMetricTotals` (orders on `ordered_at`)
- Site `revenue_total`, `order_count`, `items_sold_qty`
- Payment-success line revenue/units (bot sessions may still pay)

**Unclassified** sessions (no bot context row) count as human.

Config: `TRACKER_ROLLUPS_EXCLUDE_BOTS` (default `true`).

## Category and product catalog

- **Category history** = event-time snapshot on each line (`tracker_department_id`, `tracker_category_id`). Rollups must not rewrite old days from today’s catalog.
- **Catalog key:** `tracker_categories` unique `(type, parent_key, name_key)`; `code` is optional and not part of the key.
- **Aliases:** `tracker_category_name_aliases` applied **before** `insertOrIgnore` on categories (see P0b migrations).
- **Product grain:** `tracker_products.product_code` = variant/SKU; `activity_ecom_commerce_line_items.product_id` = storefront parent ID; `tracker_product_id` = analytics surrogate.
- **Sentinels:** fixed IDs for uncategorized department/category and unknown product (`TrackerCatalogSentinels`). Missing identity → sentinel, not NULL after stamp.

## Revenue reconcile

- Site-level only: `SUM(amount_paid)` and order count vs `activity_ecom_daily_site_metrics`, grain **`ordered_at`**, per currency via `activity_ecom_daily_dimension_metrics` (`dimension_type = currency`).
- Product/category rollup revenue may differ from order revenue (line-based payment_success vs orders); reconcile does not cover product rows.

Command: `php artisan tracker:reconcile-rollups`.

### Triage

| Symptom | Likely cause |
|--------|----------------|
| `rollup_source_*` matches rollup, differs from `orders_*` | `paymentMetricTotals` action fallback |
| Rollup differs from `orders_*` | `ordered_at` window or rollup job bug |
| Currency mismatch | Re-run rollups after currency dimension deploy |

## Closed days and cache

- Last **two** closed days re-rolled nightly (`tracker:rollup-analytics-recent --days=2`).
- Dashboard cache keys include `MAX(rolled_up_at)` for closed ranges (`activity_ecom_rollup_day_status`).
- **Today** slice: separate cache entry, TTL ≤ 60s (`TRACKER_ANALYTICS_CACHE_TODAY_SECONDS`).

## ID rollup switch

- Before enabling `TRACKER_ROLLUPS_AGGREGATE_BY_CATALOG_IDS=true`, run `tracker:compare-rollup-keys` for the backfilled range.

## Prod grain evidence (P0.5)

Re-run on **production** before locking keys. Dev (`enox_erp_v2`, 2026-09-29) results below are **provisional**.

| Check | Dev summary |
|-------|-------------|
| `category_code` → multiple departments | Only 12 codes; ~99% rows empty code — **code cannot be unique key** |
| `category_name` → multiple departments | Names repeat across departments — use `(type, parent_key, name_key)` |
| Empty dept/cat/code counts | High empty `category_code`; names often present |
| `product_id` → multiple `product_code` | 174 store IDs map to multiple codes — **variant-level `product_code`** |

Scripts: `php scripts/run-prod-grain-checks.php` (read-only SQL).

## `/track` latency (P7)

- Structured logs: `api.track.success` with `duration_ms` in `TrackController`.
- Prefer beacon / non-blocking client; use async `202` + queue only if p95 regresses **and** the storefront awaits the HTTP response.
- Target: monitor p95 in log stack; no code change required until SLO breach.
