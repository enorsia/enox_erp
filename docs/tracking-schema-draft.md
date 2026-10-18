# Tracking schema (final)

See migrations `2026_10_04_100001` … `2026_10_04_100012` and models `App\Models\Tracking*`.

**Keep:** `activity_ecom_user_actions`

## Dedicated database (local / prod)

Connection `tracking` (`TRACKING_DB_*` in `.env`). One migration per table in `database/migrations/tracking/` (squashed schema; same columns/indexes as before).

```bash
php artisan migrate --database=tracking --path=database/migrations/tracking
```
