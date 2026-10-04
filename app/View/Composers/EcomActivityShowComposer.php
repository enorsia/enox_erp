<?php

namespace App\View\Composers;

use App\Models\ActivityEcomUser;
use App\Support\EcomTrackerViewData;
use App\Support\SessionTrafficAttribution;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class EcomActivityShowComposer
{
    public function compose(View $view): void
    {
        $request = request();
        $session = (string) ($view->getData()['session'] ?? $request->route('session') ?? '');

        $activityUser = new ActivityEcomUser(['session_id' => $session]);
        $showRouteParams = EcomTrackerViewData::activityShowParams($session, $request->input('back'));

        $timeline = (new LengthAwarePaginator(
            [],
            0,
            15,
            max(1, (int) $request->query('timeline_page', 1)),
            [
                'path' => route('admin.ecom-activity.show', $showRouteParams),
                'pageName' => 'timeline_page',
            ],
        ))->appends($request->except('timeline_page'));

        $view->with([
            'activityUser' => $activityUser,
            'timeline' => $timeline,
            'funnelSteps' => [
                'category_view',
                'product_view',
                'add_to_cart',
                'begin_checkout',
                'proceed_checkout',
                'payment_success',
            ],
            'reachedSteps' => [],
            'backUrl' => EcomTrackerViewData::activityListBackUrlForShow($request),
            'trafficAttribution' => SessionTrafficAttribution::displayFields($activityUser),
            'conversionAttribution' => SessionTrafficAttribution::conversionOrMarketingDisplayFields($activityUser),
            'landingPage' => null,
            'latestActionAt' => null,
            'relatedVisitorSessions' => [],
            'badgeColors' => [
                'category_view' => 'badge-blue',
                'product_view' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
                'product_view_popup' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
                'add_to_cart' => 'badge-amber',
                'begin_checkout' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
                'proceed_checkout' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                'payment_success' => 'badge-green',
            ],
        ]);
    }
}
