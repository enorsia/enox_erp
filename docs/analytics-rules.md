# EnoxTracker analytics rules

Dashboard sync: **`TrackerDashboardSyncJob`** (planner, unique) plans chunks via **`planChunks()`** for **all** eligible unsynced idle sessions and dispatches a **`Bus::batch`** of **`TrackerDashboardSyncChunkJob`** (`TRACKER_DASHBOARD_SYNC_BATCH_SIZE` ids each). Chunk work: **`syncSessionIds()`**. Scheduler and Sync button both dispatch the planner. Queue: **database**; requires **`job_batches`** table.

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
