# Conversion attribution and platform params (v1)

## Platform query params (single registry)

All non-UTM click IDs are defined in `AttributionRules::PLATFORM_QUERY_PARAMS` (email/SMS, affiliates, paid ads). Add new vendors there once — parsing, paid-touch, and backfills pick it up automatically.

## Verified production Klaviyo params

Production `landing_page` samples use **`_kx`** (281 sessions); `kxcid` not observed. Many links also include `utm_source=Klaviyo&utm_medium=email`.

## Build checklist

1. Spec (this document)
2. Verify real Klaviyo/campaign link query params from production samples
3. `AttributionRules` + extended `SessionTrafficAttribution` (Klaviyo, Mailchimp, etc.)
4. Migrations: `conversion_*`, `visitor_last_paid_touch`, `attribution_touch_log`
5. Ingest: paid-touch upsert + G1 client merge
6. `payment_success`: `ConversionAttributionService`
7. Client: `enox_last_paid_touch` (not cleared on new session)
8. Dashboard: revenue/purchases on `conversion_utm_source`; dual columns in admin
9. Backfills: **7a → 7b** (7c optional)

## Acceptance criteria

1. **Storage (Option C):** At `payment_success`, write `conversion_*` on `activity_ecom_user` and copy the same fields onto `activity_ecom_orders` in the same transaction.
2. **Single allowlist:** `AttributionRules` is the only source for paid click IDs, paid mediums, and affiliate aliases; ingest and touch upsert both use it.
3. **Conversion at payment:** Always write `conversion_*` from the **newest marketing touch** within the 7-day window (no skip when the purchase session is already paid-attributed).
4. **Last marketing touch:** Any resolved marketing `utm_source` (paid, affiliate, email/CRM, explicit UTMs) updates `visitor_last_paid_touch`. Organic-only landings with no platform signal are a no-op for that row.
5. **G1/G2:** Newest `captured_at` wins; re-read `visitor_last_paid_touch` inside the `payment_success` transaction before writing `conversion_*`.
6. **Dashboard filters:** Purchases/revenue views filter on `conversion_utm_source`; session lists and funnels stay on session `utm_source`.
7. **Missing `visitor_id`:** Session-only attribution; log/metric for coverage (`conversion.missing_visitor_id`).
8. **Retention:** Purge `attribution_touch_log` at 90 days; `visitor_last_paid_touch` has no TTL job (7-day conversion window at read).

### Conversion scope (v1)

`conversion_*` uses **last marketing touch** within **7 days** (`TRACKER_CONVERSION_WINDOW_DAYS`): paid, affiliate, email/CRM (Klaviyo, Mailchimp, etc.) — **last touch wins** the purchase credit. Session source remains per-visit; conversion is cross-session last-touch.

### Backfill order (mandatory)

Always run **7a (session UTMs) before 7b (conversion)**. Conversion reads `visitor_last_paid_touch` (last marketing touch), not session `utm_source`.

### 7a overwrite rule

Update `utm_source` / `utm_medium` / `utm_campaign` only when current `utm_source` is null or empty (treat `(direct)` as empty). Examples: empty → `klaviyo`, empty → `google`+`organic`, empty → `mailchimp`. **Do not** overwrite an existing paid-qualifying session source.

### Stakeholder note (pre-ship orders)

Without optional **7c** (rebuild `visitor_last_paid_touch` from historical `page_url`), old orders may have blank `conversion_*` even if the visitor clicked a paid ad before launch. Session labels are fixed by 7a; conversion on legacy orders needs 7c or purchases after go-live.

## Test matrix

| Case | Expected |
|------|----------|
| URL with `gclid` only | Session google/paid; paid touch recorded |
| URL with `awc` | Session awin/affiliate; paid touch |
| Klaviyo param (`_kx` / verified) | Session klaviyo/email; marketing touch recorded |
| `utm_source=klaviyo` | Session klaviyo; marketing touch |
| Paid session A → Klaviyo purchase session B (within 7d) | `utm_source=klaviyo`, `conversion_utm_source=klaviyo` (last touch wins) |
| Klaviyo session A → paid purchase session B (within 7d) | `utm_source=google`, `conversion_utm_source=google` (last touch wins) |
| Facebook `fbclid` | Paid touch |

## Internal note

7-day conversion window is for internal analytics (config: `TRACKER_CONVERSION_WINDOW_DAYS`); it does not match Awin/Google billing windows.

## MySQL integration tests (no rollback)

Conversion last-touch API checks live in `tests/Integration/ConversionAttributionTrackTest.php`. They insert into your current `.env` database and do not roll back or delete rows.

```bash
cd enox_erp && ./vendor/bin/pest tests/Integration/ConversionAttributionTrackTest.php -c phpunit.mysql.xml
```

Unit rules (no DB) still run with default `phpunit.xml` / sqlite Feature suite.
