<?php

namespace App\Http\Controllers;

use App\Exports\SaleTrackingExport;
use App\Models\DailyAdPerformance;
use App\Services\SalePlatformService;
use App\Services\SaleTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SaleTrackingController extends Controller
{
    const ROUTES = ['index' => 'admin.sale-tracking.index'];

    public function __construct(
        private SaleTrackingService $service,
        private SalePlatformService $salePlatformService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('general.sale_tracking.index');

        $paginator          = $this->service->getList($request->all());
        $data['records']    = $paginator;
        $data['monthGroups']= $this->service->buildMonthViewGroups($paginator);
        $data['salePlatforms'] = $this->salePlatformService->getSaleTrackingPlatformOptions();

        return view('daily_sales.sale_tracking.index', $data);
    }

    public function create(): View
    {
        Gate::authorize('general.sale_tracking.create');

        $data['salePlatforms'] = $this->salePlatformService->getSaleTrackingPlatformOptions();

        return view('daily_sales.sale_tracking.create', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('general.sale_tracking.create');

        $validated = $request->validate($this->service->bulkStoreRules());
        $month     = $validated['month'];
        $entries   = $validated['entries'];

        try {
            $created = $this->service->bulkCreate($month, $entries);

            activity()->causedBy(Auth::user())
                ->withProperties(['month' => $month, 'count' => count($created)])
                ->log('Created ' . count($created) . ' sale tracking record(s) for ' . \Carbon\Carbon::parse($month)->format('M Y'));

            notify()->success(count($created) . ' sale tracking record(s) created.', 'Success');
            return redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('SALE TRACKING - bulk create failed: ' . $e->getMessage());
            notify()->error('Failed to create sale tracking records.', 'Error');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Edit all entries for the same month as the given record.
     */
    public function edit(DailyAdPerformance $saleTracking): View
    {
        Gate::authorize('general.sale_tracking.edit');

        $month = optional($saleTracking->month)->format('Y-m-d');

        $existingEntries = $this->service->getByMonth($month)->map(fn($r) => [
            'id'                 => $r->id,
            'sale_platform_id'   => $r->sale_platform_id,
            'reach'              => $r->reach,
            'impressions'        => $r->impressions,
            'clicks'             => $r->clicks,
            'sessions'           => $r->sessions,
            'engaged_sessions'   => $r->engaged_sessions,
            'users'              => $r->users,
            'ads_tax_payments'   => $r->ads_tax_payments,
        ])->values()->toArray();

        $data['saleTracking']    = $saleTracking;
        $data['month']           = $month;
        $data['salePlatforms']   = $this->salePlatformService->getSaleTrackingPlatformOptions();
        $data['existingEntries'] = $existingEntries;

        return view('daily_sales.sale_tracking.edit', $data);
    }

    /**
     * Sync all entries for the month of the given record.
     */
    public function update(Request $request, DailyAdPerformance $saleTracking): RedirectResponse
    {
        Gate::authorize('general.sale_tracking.edit');

        $validated = $request->validate($this->service->bulkUpdateRules());
        $month     = $validated['month'];
        $entries   = $validated['entries'] ?? [];
        $deleteIds = array_values(array_filter(
            (array) $request->input('entries_delete', []),
            fn($v) => is_numeric($v)
        ));
        $deleteIds = array_map('intval', $deleteIds);

        try {
            $this->service->syncForMonth($month, $entries, $deleteIds);

            activity()->causedBy(Auth::user())
                ->withProperties(['month' => $month, 'entries_count' => count($entries)])
                ->log('Updated sale tracking for ' . \Carbon\Carbon::parse($month)->format('M Y'));

            notify()->success('Sale tracking records updated.', 'Success');
            return redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('SALE TRACKING - update failed: ' . $e->getMessage());
            notify()->error('Failed to update sale tracking records.', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function destroy(DailyAdPerformance $saleTracking): RedirectResponse
    {
        Gate::authorize('general.sale_tracking.delete');

        try {
            $label = ($saleTracking->salePlatform?->name ?? '—') . ' – ' . optional($saleTracking->month)->format('M Y');

            activity()->causedBy(Auth::user())->performedOn($saleTracking)
                ->withProperties(['deleted' => $label])
                ->log('Deleted sale tracking record: ' . $label);

            $this->service->delete($saleTracking);
            notify()->success('Record deleted.', 'Deleted');
        } catch (\Exception $e) {
            Log::error('SALE TRACKING - deletion failed: ' . $e->getMessage());
            notify()->error('Failed to delete record.', 'Error');
        }

        return redirect()->back();
    }

    public function export(Request $request)
    {
        Gate::authorize('general.sale_tracking.index');
        return (new SaleTrackingExport($request->except(['page'])))->download($this->service);
    }
}
