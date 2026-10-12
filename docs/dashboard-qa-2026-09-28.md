# Store dashboard QA — 2026-09-28

Verification run against local DB via `php scripts/verify-dashboard-sections.php`.

**Re-check after query/time optimizations (slim batch, payment-row cache):** 2026-09-28 — all sections **PASS** on all presets; ground-truth numbers unchanged from first pass (see snapshot table below).

## Section × date matrix

| Section | 24h | yesterday | 2026-09-26 | 7d | 30d | Notes |
|---------|-----|-----------|------------|----|----|-------|
| A Header / period | PASS | PASS | PASS | PASS | PASS | |
| B Audience KPIs | PASS | PASS | PASS | PASS | PASS | Prev period ≠ current |
| C Sale & conversion | PASS | PASS | PASS | PASS | PASS | Matches `CommerceFunnelQuery::paymentMetricTotals` |
| D Funnel drop-off | PASS | PASS | PASS | PASS | PASS | Matches `computeFunnelKpisFromAggregates` |
| C vs D payments | — | — | EXPECTED_DIFF | EXPECTED_DIFF | EXPECTED_DIFF | Items sold (events) vs payment sessions |
| E Shopper journey trend | PASS | PASS | PASS | PASS | PASS | |
| F Merchandising categories | PASS | PASS | PASS | PASS | PASS | Totals ≤ sale KPI |
| G Merchandising products | PASS | PASS | PASS | PASS | PASS | Totals ≤ sale KPI |
| H Recoverable payment panel | PASS | PASS | PASS | PASS | PASS | `session_count` / `at_stake` vs `paymentRows` |
| I Devices | PASS | PASS | PASS | PASS | PASS | |
| J Traffic sources | PASS | PASS | PASS | PASS | PASS | |
| K Duration / new vs returning | PASS | PASS | PASS | PASS | PASS | |

## Snapshot numbers (ground truth highlights)

| Preset | Sessions | Items sold | Sale £ | Payment sessions (funnel) |
|--------|----------|------------|--------|---------------------------|
| 24h | 0 | 0 | 0 | 0 |
| yesterday | 64 | 0 | 0 | 0 |
| 2026-09-26 | 828 | 5 | 137.46 | 4 |
| 7d | 3481 | 730 | 14214.15 | 39 |
| 30d | 20415 | 755 | 14784.75 | 195 |

## Expected difference (not a bug)

**Funnel “Payments”** counts sessions with `has_payment_success` in the period. **Items sold** sums units from deduped payment rows (orders + `payment_success` actions). Multi-unit orders or multiple events per session produce higher item counts than session counts (e.g. 5 vs 4 on 2026-09-26; 730 vs 39 on 7d).

## Performance (informational)

After slim batch + cached payment rows: ~20 SQL / ~300 ms for 30d in Debugbar (local). Data matrix unchanged vs first QA pass.

## Fixes applied this run

None — all checks passed with batch read on and off; post-optimization re-check also clean.

## Regression tests

`./vendor/bin/pest tests/Unit/CommerceFunnelQueryTest.php` — 16 passed.

## Re-run

```bash
cd enox_erp
php scripts/verify-dashboard-sections.php
TRACKER_DASHBOARD_BATCH_READ=false php scripts/verify-dashboard-sections.php
```
