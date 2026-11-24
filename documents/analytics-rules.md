# EnoxTracker analytics rules

**Traffic source / UTM attribution (CEO rules):** see [traffic-attribution-rules.md](./traffic-attribution-rules.md).

Dashboard sync: **`TrackerDashboardSyncJob`** (planner) plans chunks via **`planChunks()`** and queues one **`TrackerDashboardSyncChunkJob`** per chunk (`TRACKER_DASHBOARD_SYNC_BATCH_SIZE` session ids each). Chunk work: **`syncSessionIds()`**. Scheduler and Sync button both call **`start()`**. Queue: **`TRACKER_DATABASE_QUEUE_CONNECTION`** (default `tracking_database`; `jobs` on **tracking** MySQL). Worker queue name = **`DB_QUEUE`**. **`isRunning()`** checks for pending planner/chunk rows in that `jobs` table.

**Worker (stay running, no job time limit):** `php artisan queue:work tracking_database --queue=default --sleep=3 --timeout=0 --memory=0` — do not use `--stop-when-empty`. Default `queue:work` uses **60s** job timeout and **128MB** memory, which stops the process while rows remain in `jobs`.

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
