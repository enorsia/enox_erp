# EnoxTracker analytics rules

Dashboard sync: **`TrackerDashboardSyncJob`** (planner, unique) plans chunks via **`planChunks()`** and dispatches a **`Bus::batch`** of **`TrackerDashboardSyncChunkJob`** (25 ids each, `TRACKER_DASHBOARD_SYNC_BATCH_SIZE`). Max sessions per plan: **`TRACKER_DASHBOARD_SYNC_MAX_PER_RUN`** (default 5000). Chunk work: **`syncSessionIds()`**. Scheduler and Sync button both dispatch the planner. Queue: **database**; requires **`job_batches`** table.

## Day boundary

- Store timezone: `config('tracker.visitor_timezone')`.
- Dashboard ranges use `TrackerTime::localCalendarDateStorageRange()` / `TrackerTime::storageRange()` for UTC bounds.

## Bots (narrow funnel, never revenue)

**Exclude** bot-classified sessions from session/view/cart funnel SQL when `TRACKER_DASHBOARD_EXCLUDE_BOTS=true` (`CommerceHumanSessionFilter`).

**Never exclude** bots from order revenue (`activity_ecom_orders`, `CommerceFunnelQuery::paymentMetricTotals`).

**Unclassified** sessions (no bot context row) count as human.

## Category and product catalog

- Line items store event-time department/category/product fields; optional `tracker_*_id` columns after catalog stamp.
- Command: `php artisan tracker:backfill-catalog` stamps line item catalog IDs.
