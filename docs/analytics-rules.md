# EnoxTracker analytics rules

Commerce metrics come from **`activity_ecom_user_actions`** synced by **`ActivityEcomActionSyncWriter`** (dashboard queue job) into line items, daily metric tables, and orders.

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

## Action sync

- Dashboard **Sync** queues `SyncActivityEcomUserActionsJob` on the database `jobs` table.
- Each batch reads pending/failed rows from `activity_ecom_user_actions` and writes commerce line items (and orders for payment).
