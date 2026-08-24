<?php

declare(strict_types=1);

use App\Http\Controllers\EcomActivityController;
use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\EcomActivityFunnelSessions;
use App\Services\EcomTrackerDashboardService;
use App\Support\EcomActivityFocus;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dashboard = app(EcomTrackerDashboardService::class);
$funnelSessions = app(EcomActivityFunnelSessions::class);

$period = $argv[1] ?? '24h';

if ($period === 'all') {
    $suites = [
        ['period' => '24h'],
        ['period' => 'yesterday'],
        ['period' => '7d'],
        ['period' => '30d'],
        ['period' => 'custom', 'date_from' => '2026-08-01', 'date_to' => '2026-08-07'],
        ['period' => 'custom', 'date_from' => '2026-08-10', 'date_to' => '2026-08-14'],
    ];

    $totalFailed = 0;

    foreach ($suites as $filters) {
        $totalFailed += runDashboardActivityParityAudit($filters);
        echo "\n";
    }

    echo str_repeat('=', 72)."\n";
    echo $totalFailed === 0
        ? "All periods passed.\n"
        : "Some periods failed ({$totalFailed} suite(s) with failures).\n";

    exit($totalFailed > 0 ? 1 : 0);
}

if ($period === 'custom') {
    $filters = [
        'period' => 'custom',
        'date_from' => $argv[2] ?? '',
        'date_to' => $argv[3] ?? '',
    ];
} else {
    $filters = ['period' => $period];
}

exit(runDashboardActivityParityAudit($filters));

/**
 * @param  array<string, mixed>  $filters
 */
function runDashboardActivityParityAudit(array $filters): int
{
    global $dashboard, $funnelSessions;

    $period = $filters['period'] ?? '24h';
    $label = $period === 'custom'
        ? (($filters['date_from'] ?? '').' to '.($filters['date_to'] ?? ''))
        : $period;

    $range = $dashboard->resolveDateRange($filters);
    $from = $range['from'];
    $to = $range['to'];

    $data = $dashboard->getDashboardData($filters);

    $results = [];

    $check = static function (string $id, string $label, mixed $dashboard, mixed $activity, string $note = '') use (&$results): void {
        $match = $dashboard === $activity
            || (is_float($dashboard) && is_float($activity) && abs($dashboard - $activity) <= 0.01)
            || (is_int($dashboard) && is_float($activity) && abs($dashboard - $activity) <= 0.01)
            || (is_float($dashboard) && is_int($activity) && abs($dashboard - $activity) <= 0.01);
        $results[] = [
            'id' => $id,
            'label' => $label,
            'dashboard' => $dashboard,
            'activity' => $activity,
            'match' => $match,
            'note' => $note,
        ];
    };

    // --- Activity base session query (mirrors EcomActivityController, no focus) ---
    $activityBase = ActivityEcomUser::query();
    TrackerTime::applyEcomActivitySessionScope($activityBase, $from, $to, $period);
    $activitySessionCount = (clone $activityBase)->count();
    $activitySessions = (clone $activityBase)->get(['session_id', 'visitor_id', 'session_duration_seconds']);
    $activityUniqueVisitors = $activitySessions->pluck('visitor_id')->filter()->unique()->count();
    $activityTotalStay = (int) $activitySessions->sum('session_duration_seconds');
    $activityAvgStay = $activitySessionCount > 0 ? (int) round($activityTotalStay / $activitySessionCount) : 0;
    $sessionIds = $activitySessions->pluck('session_id');

    $kpiByLabel = collect($data['kpis'])->keyBy('label');

    $check('B1.1', 'Unique visitors', (int) ($kpiByLabel->get('Unique visitors')['value'] ?? 0), $activityUniqueVisitors);
    $check('B1.2', 'Sessions', (int) ($kpiByLabel->get('Sessions')['value'] ?? 0), $activitySessionCount);
    $check('B1.3', 'Total stay time (sec)', (int) ($kpiByLabel->get('Total stay time')['value'] ?? 0), $activityTotalStay);
    $check('B1.4', 'Avg stay time (sec)', (int) ($kpiByLabel->get('Avg stay time')['value'] ?? 0), $activityAvgStay);

    // B2 Sale conversion
    $sale = $data['sale_conversion'] ?? [];
    $paymentActions = ActivityEcomUserAction::query()
        ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
        ->whereIn('session_id', $sessionIds)
        ->where('action_type', 'payment_success')
        ->get();

    $activityItemQty = 0;
    $activityRevenue = 0.0;
    foreach ($paymentActions as $action) {
        $payload = is_array($action->payment_success) ? $action->payment_success : [];
        $activityRevenue += round((float) ($payload['amount_paid'] ?? 0), 2);
        $items = $payload['checkout_info']['items'] ?? [];
        if (is_array($items) && $items !== []) {
            foreach ($items as $item) {
                if (is_array($item)) {
                    $activityItemQty += max(1, (int) ($item['qty'] ?? 1));
                }
            }
        } else {
            $activityItemQty += max(1, (int) ($payload['qty'] ?? $payload['quantity'] ?? 1));
        }
    }

    $check('B2.1', 'Items sold', (int) ($sale['item_qty']['value'] ?? 0), $activityItemQty);
    $check('B2.2', 'Sale amount', round((float) ($sale['revenue']['value'] ?? 0), 2), round($activityRevenue, 2));

    // B3 Funnel drop-off counts — parse from formatted "rate% / count"
    $drop = $data['funnel_dropoff'] ?? [];
    $parseFunnelCount = static function (array $card): int {
        $formatted = (string) ($card['formatted'] ?? '');
        if (preg_match('/\/\s*([\d,]+)\s*$/', $formatted, $m)) {
            return (int) str_replace(',', '', $m[1]);
        }

        return 0;
    };

    $funnelStages = collect($data['funnel'] ?? [])->keyBy('stage');
    $typeSets = [];
    if ($sessionIds->isNotEmpty()) {
        $actions = ActivityEcomUserAction::query()
            ->select('session_id', 'action_type')
            ->whereIn('session_id', $sessionIds)
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->get();
        foreach ($actions as $a) {
            $typeSets[$a->session_id][$a->action_type] = true;
        }
    }
    $cartStage = $beginStage = $proceedStage = $paidStage = 0;
    foreach ($typeSets as $types) {
        if (isset($types['add_to_cart'])) {
            $cartStage++;
        }
        if (isset($types['begin_checkout'])) {
            $beginStage++;
        }
        if (isset($types['proceed_checkout'])) {
            $proceedStage++;
        }
        if (isset($types['payment_success'])) {
            $paidStage++;
        }
    }

    $check('B3.1', 'Cart abandoned (funnel drop card)', $parseFunnelCount($drop['cart_drop'] ?? []), (int) (($data['cart_abandonment'] ?? [])['session_count'] ?? 0));
    $check('B3.2', 'Begin checkout abandoned (funnel drop card)', $parseFunnelCount($drop['checkout_drop'] ?? []), (int) (($data['begin_checkout_abandonment'] ?? [])['session_count'] ?? 0));
    $check('B3.3', 'Proceed checkout abandoned (funnel drop card)', $parseFunnelCount($drop['proceed_drop'] ?? []), (int) (($data['proceed_checkout_abandonment'] ?? [])['session_count'] ?? 0));
    $check('B3.4', 'Payment success sessions', $parseFunnelCount($drop['payments'] ?? []), $paidStage);

    // Funnel stage table (export)
    $check('H1.1', 'Funnel: Product view sessions', (int) ($funnelStages->get('Product view')['count'] ?? 0), count(array_filter($typeSets, fn ($t) => isset($t['product_view']) || isset($t['product_view_popup']))));
    $check('H1.2', 'Funnel: Add to cart sessions', (int) ($funnelStages->get('Add to cart')['count'] ?? 0), $cartStage);
    $check('H1.3', 'Funnel: Purchase sessions', (int) ($funnelStages->get('Purchase')['count'] ?? 0), $paidStage);

    // E Abandonment panels
    $cartAbandon = $data['cart_abandonment'] ?? [];
    $cartFunnel = $funnelSessions->abandonedSessions($from, $to, 'add_to_cart', 'add_to_cart', 'begin_checkout', [], $period);
    $check('E1', 'Cart abandoned session count', (int) ($cartAbandon['session_count'] ?? 0), $cartFunnel['session_ids']->count());

    $beginAbandon = $data['begin_checkout_abandonment'] ?? [];
    $beginFunnel = $funnelSessions->abandonedSessions($from, $to, 'begin_checkout', 'begin_checkout', 'proceed_checkout', [], $period);
    $check('E2', 'Begin checkout abandoned count', (int) ($beginAbandon['session_count'] ?? 0), $beginFunnel['session_ids']->count());

    $proceedAbandon = $data['proceed_checkout_abandonment'] ?? [];
    $proceedFunnel = $funnelSessions->abandonedSessions($from, $to, 'proceed_checkout', 'proceed_to_checkout', 'payment_success', [], $period);
    $check('E3', 'Proceed checkout abandoned count', (int) ($proceedAbandon['session_count'] ?? 0), $proceedFunnel['session_ids']->count());

    $paymentEvents = $data['payment_success_events'] ?? [];
    $paymentFunnel = $funnelSessions->paymentSuccessSessions($from, $to, [], $period);
    $check('E4', 'Payment success events count', (int) ($paymentEvents['session_count'] ?? 0), $paymentFunnel['session_ids']->count());

    // D1 Category views — session-scoped event counts
    $activityCategoryViewEvents = ActivityEcomUserAction::query()
        ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
        ->where('action_type', 'category_view')
        ->whereIn('session_id', $sessionIds)
        ->count();
    $activityProductViewWithCategory = ActivityEcomUserAction::query()
        ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
        ->whereIn('action_type', ['product_view', 'product_view_popup'])
        ->whereIn('session_id', $sessionIds)
        ->where(function ($query) {
            $query->whereNotNull('category_name')->where('category_name', '!=', '')
                ->orWhereNotNull('category_code')->where('category_code', '!=', '');
        })
        ->count();
    $check('D1.1', 'Category view actions (C view total)', (int) ($data['category_catalog_totals']['category_views'] ?? 0), $activityCategoryViewEvents);
    $check('D1.2', 'Product view actions with category (P view total)', (int) ($data['category_catalog_totals']['product_views'] ?? 0), $activityProductViewWithCategory);

    // D2 Product views total (full catalog, session-scoped)
    $fullCatalog = $dashboard->buildProductCatalogPerformance($from, $to, null, [], ['period' => $period]);
    $dashboardProductViews = collect($fullCatalog['products'] ?? [])->sum('views');
    $dashboardTableViews = collect($data['products'] ?? [])->sum('views');
    $scopedProductViews = ActivityEcomUserAction::query()
        ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
        ->whereIn('action_type', ['product_view', 'product_view_popup'])
        ->whereIn('session_id', $sessionIds)
        ->count();

    $check('D2.1', 'Product views (full catalog sum)', (int) $dashboardProductViews, (int) $scopedProductViews, 'Session-scoped product_view actions in date range');
    $check('D2.1b', 'Product views (trend total)', (int) ($data['trend']['series'] ? array_sum(collect($data['trend']['series'])->firstWhere('key', 'product_views')['data'] ?? []) : 0), (int) $scopedProductViews, 'Trend chart product views');
    $check('D2.1c', 'Product table views within catalog total', (int) ($dashboardTableViews <= $dashboardProductViews ? 1 : 0), 1, 'Top-20 table is a subset of full catalog');

    // F1 Device views total
    $deviceViews = collect($data['devices']['by_device'] ?? [])->sum('views');
    $check('F1.2', 'Device views (event sum)', (int) $deviceViews, (int) $scopedProductViews);

    // F2 Traffic views total
    $trafficViews = collect($data['traffic_sources'] ?? [])->sum('views');
    $check('F2.2', 'Traffic views (event sum)', (int) $trafficViews, (int) $scopedProductViews);

    // F3 Session quality
    $vq = $data['visitor_quality'] ?? [];
    $realShoppers = (clone $activityBase)->whereHas('botContext', fn ($b) => $b->where('is_bot', false))->count();
    $bots = (clone $activityBase)->whereHas('botContext', fn ($b) => $b->where('is_bot', true))->count();
    $unclassified = (clone $activityBase)->whereDoesntHave('botContext')->count();
    $check('F3.1', 'Real shoppers', (int) ($vq['real_shoppers']['current'] ?? 0), $realShoppers);
    $check('F3.2', 'Automated traffic', (int) ($vq['automated_traffic']['current'] ?? 0), $bots);
    $check('F3.3', 'Not classified', (int) ($vq['not_classified']['current'] ?? 0), $unclassified);

    // F1 Device sessions total
    $deviceSessions = collect($data['devices']['by_device'] ?? [])->sum('sessions');
    $check('F1.1', 'Device table sessions sum', (int) $deviceSessions, $activitySessionCount, 'Device rows should sum to all sessions');

    // F2 Traffic sessions total
    $trafficSessions = collect($data['traffic_sources'] ?? [])->sum('sessions');
    $check('F2.1', 'Traffic table sessions sum', (int) $trafficSessions, $activitySessionCount);

    // C Trend totals (sum of series)
    $trend = $data['trend'] ?? [];
    $trendSessions = array_sum($trend['sessions'] ?? []);
    $check('C2', 'Trend sessions total', $activitySessionCount, (int) $trendSessions);

    $trendProductViews = 0;
    foreach ($trend['series'] ?? [] as $series) {
        if (($series['key'] ?? '') === 'product_views') {
            $trendProductViews = array_sum($series['data'] ?? []);
        }
    }
    $check('C4', 'Trend product views total', (int) $trendProductViews, (int) $scopedProductViews);

    // Output
    echo "Dashboard vs User Activity parity audit — period: {$label}\n";
    echo str_repeat('=', 72)."\n";
    $passed = 0;
    $failed = 0;
    foreach ($results as $r) {
        $status = $r['match'] ? 'PASS' : 'FAIL';
        if ($r['match']) {
            $passed++;
        } else {
            $failed++;
        }
        $note = $r['note'] !== '' ? " [{$r['note']}]" : '';
        echo sprintf(
            "%-6s %-8s %-35s dash=%-8s activity=%-8s%s\n",
            $r['id'],
            $status,
            $r['label'],
            (string) $r['dashboard'],
            (string) $r['activity'],
            $note,
        );
    }
    echo str_repeat('=', 72)."\n";
    echo "Passed: {$passed}  Failed: {$failed}\n";

    return $failed > 0 ? 1 : 0;
}
