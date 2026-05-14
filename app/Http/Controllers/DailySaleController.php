<?php

namespace App\Http\Controllers;

use App\Exports\DailySaleExport;
use App\Models\DailySale;
use App\Models\SalePlatform;
use App\Services\DailySaleService;
use App\Services\SalePlatformService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class DailySaleController extends Controller
{
    const ROUTES = [
        'index' => 'admin.daily-sales.index',
    ];

    public function __construct(
        private DailySaleService    $service,
        private SalePlatformService $salePlatformService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('general.daily_sale.index');

        $data['dailySales']    = $this->service->getList($request->all());
        $data['salePlatforms'] = $this->salePlatformService->getParentOptions();
        $data['dateGroups']    = $this->service->buildDateViewGroups($data['dailySales']);

        return view('daily_sales.daily_sales.index', $data);
    }

    public function create(): View
    {
        Gate::authorize('general.daily_sale.create');

        $defaultDate   = old('date', date('Y-m-d'));
        $usedPlatformIds = DailySale::whereDate('date', $defaultDate)
            ->pluck('sale_platform_id')
            ->map(fn($id) => (string) $id)
            ->values()
            ->toArray();

        $data['salePlatforms']    = $this->salePlatformService->getParentOptions();
        $data['usedPlatformIds']  = $usedPlatformIds;

        return view('daily_sales.daily_sales.create', $data);
    }

    /**
     * Return platform IDs already used for a given date (JSON).
     * Used by the JS to disable already-taken platforms in dropdowns.
     */
    public function usedPlatformsForDate(Request $request): JsonResponse
    {
        $date = $request->input('date');
        if (!$date) {
            return response()->json([]);
        }

        // In edit mode, exclude the records that belong to the current edit form
        // (their platform slots will be freed on save).
        $excludeIds = array_filter(
            (array) $request->input('exclude_ids', []),
            fn($v) => is_numeric($v)
        );

        $query = DailySale::whereDate('date', $date)->select('sale_platform_id');
        if (!empty($excludeIds)) {
            $query->whereNotIn('id', array_map('intval', $excludeIds));
        }

        return response()->json(
            $query->pluck('sale_platform_id')->map(fn($id) => (string) $id)->values()
        );
    }

    /**
     * Store one or more daily sale entries (bulk via entries[] array).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->service->bulkStoreRules());

        $date        = $validated['date'];
        $entries     = $validated['entries'];
        $platformIds = array_column($entries, 'sale_platform_id');

        // Duplicate within submission
        if (count($platformIds) !== count(array_unique($platformIds))) {
            return redirect()->back()->withInput()
                ->withErrors(['entries' => 'Duplicate platform entries within the same submission.']);
        }

        // Platforms that don't allow direct entry
        $notAllowed = SalePlatform::whereIn('id', $platformIds)
            ->where('allows_direct_entry', false)->pluck('name');
        if ($notAllowed->isNotEmpty()) {
            return redirect()->back()->withInput()
                ->withErrors(['entries' => 'Direct entry not allowed for: ' . $notAllowed->join(', ')]);
        }

        // Already exist in DB
        $existing = DailySale::whereDate('date', $date)
            ->whereIn('sale_platform_id', $platformIds)
            ->with('salePlatform')->get();
        if ($existing->isNotEmpty()) {
            $names = $existing->map(fn($s) => $s->salePlatform->name ?? $s->sale_platform_id)->join(', ');
            return redirect()->back()->withInput()
                ->withErrors(['entries' => "Records already exist for: {$names} on {$date}."]);
        }

        try {
            $created = $this->service->bulkCreate($date, $entries);

            foreach ($created as $sale) {
                activity()->causedBy(Auth::user())->performedOn($sale)
                    ->withProperties(['attributes' => $sale->toArray()])
                    ->log('Created daily sale for ' . ($sale->salePlatform->name ?? '-') . ' on ' . $sale->date->format('Y-m-d'));
            }

            notify()->success(count($created) . ' daily sale record(s) created successfully.', 'Success');
            return redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('DAILY SALES - bulk creation failed: ' . $e->getMessage());
            notify()->error('Failed to create daily sales', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function show(DailySale $dailySale): View
    {
        Gate::authorize('general.daily_sale.show');
        $dailySale->load('salePlatform');
        return view('daily_sales.daily_sales.show', compact('dailySale'));
    }

    /**
     * Edit all entries for the same date as the given record.
     */
    public function edit(DailySale $dailySale): View
    {
        Gate::authorize('general.daily_sale.edit');

        $date = $dailySale->date->format('Y-m-d');

        $existingEntries = $this->service->getByDate($date)->map(fn($s) => [
            'id'                          => $s->id,
            'sale_platform_id'            => $s->sale_platform_id,
            'spent'                       => $s->spent,
            'sales'                       => $s->sales,
            'number_of_orders'            => $s->number_of_orders,
            'number_of_quantities'        => $s->number_of_quantities,
            'number_of_male_orders'       => $s->number_of_male_orders,
            'number_of_female_orders'     => $s->number_of_female_orders,
            'number_of_kids_orders'       => $s->number_of_kids_orders,
            'number_of_male_quantities'   => $s->number_of_male_quantities,
            'number_of_female_quantities' => $s->number_of_female_quantities,
            'number_of_kids_quantities'   => $s->number_of_kids_quantities,
        ])->values()->toArray();

        $data['dailySale']       = $dailySale;
        $data['date']            = $date;
        $data['salePlatforms']   = $this->salePlatformService->getParentOptions();
        $data['existingEntries'] = $existingEntries;

        return view('daily_sales.daily_sales.edit', $data);
    }

    /**
     * Sync (upsert + delete) all entries for the date taken from the given record.
     */
    public function update(Request $request, DailySale $dailySale): RedirectResponse
    {
        Gate::authorize('general.daily_sale.edit');

        $validate = $request->validate($this->service->bulkUpdateRules());

        $date      = $dailySale->date->format('Y-m-d');
        $entries   = $validate['entries'] ?? [];
        $deleteIds = array_values(array_filter(
            (array) $request->input('entries_delete', []),
            fn($v) => is_numeric($v)
        ));
        $deleteIds = array_map('intval', $deleteIds);

        // Duplicate platform within the same submission
        if (!empty($entries)) {
            $platIds = array_column($entries, 'sale_platform_id');
            if (count($platIds) !== count(array_unique($platIds))) {
                return redirect()->back()->withInput()
                    ->withErrors(['entries' => 'Duplicate platform entries within the same submission.']);
            }

            $notAllowed = SalePlatform::whereIn('id', $platIds)
                ->where('allows_direct_entry', false)->pluck('name');
            if ($notAllowed->isNotEmpty()) {
                return redirect()->back()->withInput()
                    ->withErrors(['entries' => 'Direct entry not allowed for: ' . $notAllowed->join(', ')]);
            }
        }

        try {
            $this->service->syncForDate($date, $entries, $deleteIds);

            activity()->causedBy(Auth::user())
                ->withProperties(['date' => $date, 'entries_count' => count($entries)])
                ->log("Updated daily sales for {$date}");

            notify()->success('Daily sales updated successfully.', 'Success');
            return redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('DAILY SALES - update failed: ' . $e->getMessage());
            notify()->error('Failed to update daily sales', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function export(Request $request)
    {
        Gate::authorize('general.daily_sale.index');

        $columns = $request->input('columns', []);
        if (is_string($columns)) {
            $columns = array_filter(explode(',', $columns));
        }
        $allCols = DailySaleExport::allColumns();
        $columns = array_values(array_intersect($allCols, $columns ?: $allCols));

        return Excel::download(
            new DailySaleExport($this->service->getExportQuery($request->except(['columns'])), $columns),
            'daily-sales-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function destroy(DailySale $dailySale): RedirectResponse
    {
        Gate::authorize('general.daily_sale.delete');

        try {
            $label = $dailySale->salePlatform->name . ' on ' . $dailySale->date->format('Y-m-d');

            activity()->causedBy(Auth::user())->performedOn($dailySale)
                ->withProperties(['deleted' => $label])
                ->log('Deleted daily sale: ' . $label);

            $this->service->delete($dailySale);

            notify()->success('Daily sale deleted successfully.', 'Deleted');
            return redirect()->back();
        } catch (\Exception $e) {
            Log::error('DAILY SALES - deletion failed: ' . $e->getMessage());
            notify()->error('Failed to delete daily sale', 'Error');
            return redirect()->back();
        }
    }
}
