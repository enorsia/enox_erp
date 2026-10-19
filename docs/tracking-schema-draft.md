# Tracking schema (final)

Models: `App\Models\Tracking*`.

**Keep on ingest path:** `activity_ecom_user`, `activity_ecom_user_actions`, `activity_ecom_user_bot_context` (see `database/migrations/tracking/`).

## Dedicated database (local / prod)

Connection `tracking` (`TRACKING_DB_*` in `.env`). One migration per table in `database/migrations/tracking/`.

```bash
php artisan migrate --database=tracking --path=database/migrations/tracking
```
