# EnoxTracker analytics rules

Dashboard sync: `POST admin/ecom-tracker/dashboard/sync` (or the scheduler) dispatches **`TrackerDashboardSyncJob`** on the **database** queue. Batch size: **`config('tracker.dashboard_sync_batch_size')`** (env `TRACKER_DASHBOARD_SYNC_BATCH_SIZE`). Each job runs **`processBatch()`** in a loop (~50s wall time, no self-dispatch chain). Job implements **`ShouldBeUnique`**.

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
