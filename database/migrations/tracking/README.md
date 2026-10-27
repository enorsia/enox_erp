# Tracking connection migrations

## Server cutover

1. Stop queue workers.
2. `.env`:

```env
TRACKING_DB_HOST=...
TRACKING_DB_DATABASE=...
TRACKING_DB_USERNAME=...
TRACKING_DB_PASSWORD=...
TRACKER_DATABASE_QUEUE_CONNECTION=tracking_database
```

3. Cutover:

**Load new `.env`:**

```bash
php artisan config:clear
```

**Clean legacy tracker on ERP DB + queues + Redis:**

```bash
php artisan tracker:purge-data --force --drop-tables --queues --redis
```

**Empty tracking DB data only (truncate — keeps tables, no migrate):**

```bash
php artisan tracker:empty-tracking --dry-run
php artisan tracker:empty-tracking --force --queues --redis
```

**Tracking DB migrations:**

```bash
php artisan migrate --database=tracking --path=database/migrations/tracking
```

**Cache config (prod):**

```bash
php artisan config:cache
```

**Sync ingest → dashboard tables:**

```bash
php artisan tracker:sync-dashboard
```

**CTR rollup** (`tracking_ctr_categories_impression` + legacy `activity_ecom_user_actions` → `tracking_ctr_daily_summaries` / `tracking_ctr_summaries`):

```bash
php artisan tracker:sync-ctr
```

Force re-rollup for a calendar-day range (visitor timezone; clears `tracking_ctr_daily_summaries` for those days first):

```bash
php artisan tracker:sync-ctr --force --from=2026-10-27
php artisan tracker:sync-ctr --force --from=2026-10-01 --to=2026-10-07
```

4. Workers:

**Dashboard sync + CTR jobs** (`jobs` on `TRACKING_DB_*` — run worker before or after `tracker:sync-ctr`; both commands use this queue):

```bash
php artisan queue:work tracking_database --queue=default --sleep=3 --timeout=0 --memory=0
```

**Shop ingest** (Redis):

```bash
php artisan queue:work tracker --queue=tracker --sleep=3 --timeout=0 --memory=0
```
