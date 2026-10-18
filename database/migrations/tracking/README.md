# Tracking connection migrations

```bash
php artisan migrate --database=tracking --path=database/migrations/tracking
php artisan migrate:fresh --database=tracking --path=database/migrations/tracking
```

New files: `php artisan make:migration ... --path=database/migrations/tracking` and extend `App\Database\Migrations\TrackingMigration`.

## Column order convention

Within each `Schema::create` callback, define columns in this order (indexes/uniques/foreign keys after all columns):

1. `id`
2. Foreign keys (`foreignId` / `->constrained`), alphabetical by column name when multiple
3. Natural / business keys (`session_id`, `order_number`, `visitor_id`, …)
4. Identity (`name`, `email`, `phone`, `user_*`)
5. Catalog dimensions (`code`, `name`, `sku`, category/product/color fields)
6. Money (`decimal` fields, alphabetical)
7. URLs and long text (`page_url`, `referer`, `landing_page`, …)
8. JSON payloads
9. Event / metric dates (`metric_date`, `ordered_at`, `captured_at`, `start_time`, `end_time`)
10. Counters and durations
11. Booleans, enums, flags (`is_*`, `has_*`, `type`, funnel stage)
12. `timestamps()` (or `created_at` only where the table is append-only)
13. Ingest sync columns (`is_sync`, `sync_try`, `sync_at`) on `activity_ecom_*` tables
