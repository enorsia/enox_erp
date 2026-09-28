<?php

/**
 * One-off QA: store dashboard sections vs ground truth. Run from enox_erp:
 *   php scripts/verify-dashboard-sections.php
 */

use App\Services\EcomTrackerDashboardService;
use App\Support\CommerceFunnelQuery;
use Illuminate\Support\Carbon;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$svc = app(EcomTrackerDashboardService::class);
$ref = new ReflectionClass($svc);

$invoke = function (string $method, array $args = []) use ($svc, $ref) {
    $m = $ref->getMethod($method);
    $m->setAccessible(true);

    return $m->invoke($svc, ...$args);
};

$presets = [
    '24h' => ['period' => '24h'],
    'yesterday' => ['period' => 'yesterday'],
    '2026-09-26' => ['period' => 'custom', 'date_from' => '2026-09-26', 'date_to' => '2026-09-26'],
    '7d' => ['period' => '7d'],
    '30d' => ['period' => '30d'],
];

$kpiValue = function (array $kpis, string $label): mixed {
    foreach ($kpis as $k) {
        if (($k['label'] ?? '') === $label) {
            return $k['value'] ?? null;
        }
    }

    return null;
};

$results = [];

foreach ($presets as $presetKey => $filters) {
    $row = ['preset' => $presetKey, 'sections' => []];

    $d = $svc->getDashboardData($filters);
    $from = $d['range']['from'];
    $to = $d['range']['to'];
    $period = $d['range']['period'] ?? null;
    $range = $d['range'];

    // A – header
    $aOk = isset($d['live']) && ($range['label'] ?? '') !== '';
    $row['sections']['A'] = $aOk ? 'PASS' : 'FAIL';

    // B – audience
    $stats = $invoke('sessionAggregateStats', [$from, $to, $period]);
    $bOk = true;
    $bNotes = [];
    foreach (['Unique visitors' => 'unique_visitors', 'Sessions' => 'sessions', 'Total stay time' => 'total_stay_seconds', 'Avg stay time' => 'avg_stay_seconds'] as $label => $key) {
        $card = $kpiValue($d['kpis'], $label);
        if ((int) $card !== (int) $stats[$key]) {
            $bOk = false;
            $bNotes[] = "$label card=$card truth={$stats[$key]}";
        }
    }
    $prevRange = $svc->resolvePreviousPeriodRange($range);
    $prevSame = $prevRange['from']->eq($from) && $prevRange['to']->eq($to);
    if ($prevSame) {
        $bOk = false;
        $bNotes[] = 'prev range equals current';
    }
    $row['sections']['B'] = $bOk ? 'PASS' : 'FAIL';
    if ($bNotes !== []) {
        $row['B_notes'] = implode('; ', $bNotes);
    }

    // C – sale
    $truth = CommerceFunnelQuery::paymentMetricTotals($from, $to, null, $period);
    $sale = $d['sale_conversion'];
    $cOk = (int) $sale['item_qty']['value'] === (int) $truth['item_qty']
        && abs((float) $sale['revenue']['value'] - (float) $truth['revenue']) < 0.02;
    $row['sections']['C'] = $cOk ? 'PASS' : 'FAIL';
    if (! $cOk) {
        $row['C_notes'] = 'dash qty='.$sale['item_qty']['value'].' rev='.$sale['revenue']['value']
            .' truth qty='.$truth['item_qty'].' rev='.$truth['revenue'];
    }

    // D – funnel
    $funnelTruth = $invoke('computeFunnelKpisFromAggregates', [$from, $to, $period]);
    $fd = $d['funnel_dropoff'];
    $dOk = (int) ($fd['payments']['count'] ?? 0) === (int) $funnelTruth['payment_success_count']
        && (int) ($fd['cart_drop']['count'] ?? 0) === (int) $funnelTruth['cart_abandoned_count']
        && (int) ($fd['checkout_drop']['count'] ?? 0) === (int) $funnelTruth['begin_checkout_abandoned_count']
        && (int) ($fd['proceed_drop']['count'] ?? 0) === (int) $funnelTruth['proceed_checkout_abandoned_count'];
    $row['sections']['D'] = $dOk ? 'PASS' : 'FAIL';

    // C vs D payments (expected diff)
    $itemsSold = (int) $sale['item_qty']['value'];
    $paySessions = (int) ($fd['payments']['count'] ?? 0);
    if ($itemsSold !== $paySessions && $itemsSold > 0 && $paySessions > 0) {
        $row['sections']['C_vs_D'] = 'EXPECTED_DIFF';
        $row['C_vs_D_notes'] = "items_sold=$itemsSold payment_sessions=$paySessions";
    }

    // E – trend
    $trend = $d['trend'];
    $series = collect($trend['series'] ?? []);
    $sessSeries = $series->firstWhere('key', 'sessions');
    $uvSeries = $series->firstWhere('key', 'unique_visitors');
    $soldSeries = $series->firstWhere('key', 'items_sold_qty');
    $sumSessions = array_sum($sessSeries['data'] ?? []);
    $sumUv = array_sum($uvSeries['data'] ?? []);
    $eOk = true;
    $eNotes = [];
    if ($presetKey === '7d' || $presetKey === '30d') {
        if (abs($sumSessions - (int) $stats['sessions']) > 2) {
            $eOk = false;
            $eNotes[] = "trend sessions sum=$sumSessions kpi={$stats['sessions']}";
        }
        $daySold = array_sum($soldSeries['data'] ?? []);
        if ((int) $daySold !== (int) $truth['item_qty']) {
            $eOk = false;
            $eNotes[] = "trend items_sold sum=$daySold truth={$truth['item_qty']}";
        }
    }
    if ($presetKey === '2026-09-26' && $soldSeries) {
        $daySold = array_sum($soldSeries['data'] ?? []);
        if ((int) $daySold !== (int) $truth['item_qty']) {
            $eOk = false;
            $eNotes[] = "trend items_sold=$daySold truth={$truth['item_qty']}";
        }
    }
    $row['sections']['E'] = $eOk ? 'PASS' : 'FAIL';
    if ($eNotes !== []) {
        $row['E_notes'] = implode('; ', $eNotes);
    }

    // F & G – merchandising totals are full-period; displayed tables are top-N only
    $ct = $d['category_catalog_totals'] ?? [];
    $pt = $d['product_catalog_totals'] ?? [];
    $fOk = (int) ($ct['category_count'] ?? 0) >= 0
        && (int) ($ct['sale_items'] ?? 0) <= (int) $truth['item_qty'] + 1;
    $gOk = (int) ($pt['qty'] ?? 0) <= (int) $truth['item_qty'] + 1
        && (float) ($pt['revenue'] ?? 0) <= (float) $truth['revenue'] + 0.02;
    $row['sections']['F'] = $fOk ? 'PASS' : 'FAIL';
    $row['sections']['G'] = $gOk ? 'PASS' : 'FAIL';
    if ((int) $truth['item_qty'] > 0 && (int) ($pt['qty'] ?? 0) === 0 && $presetKey === '2026-09-26') {
        $row['G_warn'] = 'C>0 but product catalog qty 0 (attribution/rollup)';
    }

    // H – recoverable payment panel (session_count / at_stake are full totals; rows are limited)
    $payPanel = $d['payment_success_events'] ?? [];
    $allowedSessionIds = $invoke('activitySessionIds', [$from, $to, [], $period]);
    $payRows = CommerceFunnelQuery::paymentRows($from, $to, $allowedSessionIds, $period);
    $panelCount = (int) ($payPanel['session_count'] ?? 0);
    $panelStake = round((float) ($payPanel['at_stake'] ?? 0), 2);
    $rowsStake = round((float) collect($payRows)->sum(fn ($r) => (float) ($r['value'] ?? 0)), 2);
    $hOk = $panelCount === count($payRows) && abs($panelStake - $rowsStake) < 0.05
        && abs($panelStake - (float) $truth['revenue']) < 0.05;
    $row['sections']['H'] = $hOk ? 'PASS' : 'FAIL';
    if (! $hOk) {
        $row['H_notes'] = "count panel=$panelCount truth=".count($payRows)
            ." stake panel=$panelStake rowsStake=$rowsStake saleRev={$truth['revenue']}";
    }

    // I – devices
    $devTotal = (int) collect($d['devices']['devices'] ?? $d['devices'] ?? [])->sum('sessions');
    $iOk = $devTotal <= (int) $stats['sessions'] + 1;
    $row['sections']['I'] = $iOk ? 'PASS' : 'FAIL';

    // J – traffic
    $traffic = $d['traffic_sources'] ?? [];
    $jTotal = (int) collect($traffic)->sum('sessions');
    $row['sections']['J'] = ($jTotal <= (int) $stats['sessions'] + 5) ? 'PASS' : 'FAIL';

    // K – duration
    $dur = $d['duration_distribution'] ?? [];
    $durSum = (int) collect($dur['buckets'] ?? [])->sum('count');
    $kOk = abs($durSum - (int) $stats['sessions']) <= 2;
    $row['sections']['K'] = $kOk ? 'PASS' : 'FAIL';
    if (! $kOk) {
        $row['K_notes'] = "durSum=$durSum sessions={$stats['sessions']}";
    }

    $row['snapshot'] = [
        'sessions' => $stats['sessions'],
        'sale_qty' => $truth['item_qty'],
        'sale_rev' => $truth['revenue'],
        'payments_sessions' => $funnelTruth['payment_success_count'],
    ];

    $results[] = $row;
}

echo json_encode($results, JSON_PRETTY_PRINT)."\n";
