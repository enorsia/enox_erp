# E‑commerce activity list — filters, indexes, queries

## Session scope (every list query)

| Period | SQL rule | Indexes used |
|--------|----------|--------------|
| `24h` | `created_at` **or** `last_active_at` in range | `created_at`, `idx_last_active` (OR is harder to optimize) |
| Other presets | `created_at` between start/end of calendar days (local TZ) | `created_at`, composites below |

## Precomputed traffic (preferred)

Columns on `activity_ecom_user`:

- `list_traffic_utm_source`, `list_traffic_utm_medium` — activity list + dashboard traffic table
- `conversion_utm_*` — paid conversion attribution (conversion focus / orders)

Indexes:

- `idx_activity_ecom_user_list_traffic_source`
- `idx_aeus_created_list_traffic_source` (`created_at`, `list_traffic_utm_source`)
- `idx_activity_ecom_user_conversion_source`

List traffic **filters** and **facet counts** use stored columns when set; legacy landing/UTM SQL applies only when list columns are null/empty.

## Other activity filters

| Filter | Column / mechanism | Index |
|--------|-------------------|--------|
| Device | `device_type` | `idx_aeus_created_device_type` |
| Logged in | `is_logged_in` | `idx_aeus_created_logged_in` |
| Has order (period) | `EXISTS` on `activity_ecom_orders` by `session_id` + `ordered_at` | `idx_orders_ordered_session` |
| Has order (simple) | `has_payment_success` | `idx_aeus_has_payment_created` |
| Visitor type | `EXISTS` / `NOT EXISTS` on `activity_ecom_user_bot_context.session_id` | unique on `session_id` |
| Session UTM (legacy) | `utm_*`, `landing_page` URL needles | session columns only |
| Conversion UTM | `conversion_utm_source` / `utm_medium` | conversion source index |

## Per-request query budget (typical full index page)

1. Paginated session list (+ `botContext` eager load)
2. Row metrics batch (line items / orders as needed)
3. Five facet dimensions (`device_type`, `logged_in`, `has_order`, `utm_source`, `utm_medium`) — each reuses the same filter set except the dimension being counted
4. Visitor quality summary (one grouped subquery + bot join)
5. Optional funnel / catalog helpers when focus or drawer filters apply

Avoid per-row PHP that calls `lastMarketingTouchForVisitorBefore()`; list columns must be backfilled for scale.

## Performance (real-time, no response caching)

- **Default funnel sort** uses session `has_*` flags and timestamps only — no period-wide line-item or order subqueries (catalog drill-down still uses line items).
- **Category filter options** load only when department/category focus or filters need them (avoids `buildCategoryPerformance` on every list view).
- **Filter facets**: device + logged-in counts share one scan when filters match; has-order facet uses one conditional join to `activity_ecom_orders`; UTM facets use combined source/medium grouping.
- **Dashboard traffic sources** aggregate in SQL (`GROUP BY` list/conversion/UTM buckets), not PHP over every session row.
- **Analytics cache** (`TRACKER_ANALYTICS_CACHE_ENABLED`) defaults to **off** — visitor quality and related dashboard blocks always hit the database.
- Run `php artisan migrate` for composite indexes (`idx_aeus_created_list_traffic_source`, `idx_aeus_created_funnel_sort`, etc.).

## Related tables (lineage)

- `activity_ecom_user` — session grain
- `activity_ecom_user_actions` — events (`session_id`, `created_at`, `action_type`)
- `activity_ecom_commerce_line_items` — catalog funnel (`staged_at`, `funnel_stage`, `session_id`)
- `activity_ecom_orders` — revenue (`ordered_at`, `session_id`)
- `activity_ecom_user_bot_context` — bot/human (`session_id` unique)
- `visitor_last_paid_touch` / `attribution_touch_log` — ingest & conversion, not activity list filters
