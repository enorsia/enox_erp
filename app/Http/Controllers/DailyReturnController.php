<?php

namespace App\Http\Controllers;

use App\Exports\DailyReturnExport;
use App\Models\DailyReturn;
use App\Services\DailyReturnService;
use App\Services\ReturnReasonTypeService;
use App\Services\SalePlatformService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class DailyReturnController extends Controller
{
    const ROUTES = [
        'index' => 'admin.daily-returns.index',
    ];

    public function __construct(
        private DailyReturnService    $service,
        private SalePlatformService   $salePlatformService,
        private ReturnReasonTypeService $returnReasonTypeService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('general.daily_return.index');

        $filters = $request->all();
        $dailyReturns = $this->service->getList($filters);

        // Get all data needed for the view
        $filterData = $this->service->getFilterData();
        $dateGroups = $this->service->buildTreeView($dailyReturns);
        $activeFilterCount = $this->service->getActiveFilterCount($filters);
        $activeFilterTags = $this->service->getActiveFilterTags(
            $filters,
            $filterData['salePlatforms'],
            $filterData['reasonTypes']
        );

        $returnUrl = urlencode($request->fullUrl());
        $exportLabels = \App\Exports\DailyReturnExport::columnLabels();
        $exportCols = \App\Exports\DailyReturnExport::allColumns();

        return view('sale-spend.daily_returns.index', compact(
            'dailyReturns',
            'dateGroups',
            'filterData',
            'activeFilterCount',
            'activeFilterTags',
            'returnUrl',
            'exportLabels',
            'exportCols'
        ));
    }

    public function create(): View
    {
        Gate::authorize('general.daily_return.create');

        $data['salePlatforms'] = $this->salePlatformService->getParentOptions();
        $data['reasonTypes']   = $this->returnReasonTypeService->getList(['is_active' => 1])->items();

        return view('sale-spend.daily_returns.create', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->service->bulkStoreRules());

        $date    = $validated['date'];
        $entries = $validated['entries'];

        // Duplicate platform+reason within the same submission
        $combos = array_map(
            fn($e) => $e['sale_platform_id'] . '_' . $e['return_reason_type_id'],
            $entries
        );
        if (count($combos) !== count(array_unique($combos))) {
            return redirect()->back()->withInput()
                ->withErrors(['entries' => 'Duplicate platform + reason entries within the same submission.']);
        }

        // Check duplicates against DB for this date
        $existing = DailyReturn::whereDate('date', $date)->get();
        foreach ($entries as $entry) {
            $dupe = $existing->first(fn($r) =>
                $r->sale_platform_id == $entry['sale_platform_id'] &&
                $r->return_reason_type_id == $entry['return_reason_type_id']
            );
            if ($dupe) {
                return redirect()->back()->withInput()
                    ->withErrors(['entries' => "A return record for this platform + reason already exists on {$date}."]);
            }
        }

        try {
            $created = $this->service->bulkCreate($date, $entries);

            foreach ($created as $dailyReturn) {
                activity()->causedBy(Auth::user())->performedOn($dailyReturn)
                    ->withProperties(['attributes' => $dailyReturn->toArray()])
                    ->log('Created daily return for ' . ($dailyReturn->salePlatform->name ?? '-')
                        . ' — ' . ($dailyReturn->returnReasonType->name ?? '-')
                        . ' on ' . $dailyReturn->date->format('Y-m-d'));
            }

            notify()->success(count($created) . ' daily return record(s) created successfully.', 'Success');
            $returnUrl = $request->input('return_url');
            return $returnUrl ? redirect()->to(urldecode($returnUrl)) : redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('DAILY RETURNS - bulk creation failed: ' . $e->getMessage());
            notify()->error('Failed to create daily returns', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function show(DailyReturn $dailyReturn): View
    {
        Gate::authorize('general.daily_return.show');

        $dailyReturn->load(['salePlatform', 'returnReasonType']);

        return view('sale-spend.daily_returns.show', compact('dailyReturn'));
    }

    /**
     * Edit all entries for the same date as the given record.
     */
    public function edit(DailyReturn $dailyReturn): View
    {
        Gate::authorize('general.daily_return.edit');

        $date = $dailyReturn->date->format('Y-m-d');

        $existingEntries = $this->service->getByDate($date)->map(fn($r) => [
            'id'                                  => $r->id,
            'sale_platform_id'                    => $r->sale_platform_id,
            'return_reason_type_id'               => $r->return_reason_type_id,
            'return_amount'                       => $r->return_amount,
            'number_of_returns'                   => $r->number_of_returns,
            'number_of_return_quantities'         => $r->number_of_return_quantities,
            'number_of_male_returns'              => $r->number_of_male_returns,
            'number_of_female_returns'            => $r->number_of_female_returns,
            'number_of_kids_returns'              => $r->number_of_kids_returns,
            'number_of_male_return_quantities'    => $r->number_of_male_return_quantities,
            'number_of_female_return_quantities'  => $r->number_of_female_return_quantities,
            'number_of_kids_return_quantities'    => $r->number_of_kids_return_quantities,
        ])->values()->toArray();

        $data['dailyReturn']     = $dailyReturn;
        $data['date']            = $date;
        $data['salePlatforms']   = $this->salePlatformService->getParentOptions();
        $data['reasonTypes']     = $this->returnReasonTypeService->getList(['is_active' => 1])->items();
        $data['existingEntries'] = $existingEntries;

        return view('sale-spend.daily_returns.edit', $data);
    }

    /**
     * Sync (upsert + delete) all entries for the date taken from the given record.
     */
    public function update(Request $request, DailyReturn $dailyReturn): RedirectResponse
    {
        Gate::authorize('general.daily_return.edit');

        $validated = $request->validate($this->service->bulkUpdateRules());

        $date      = $dailyReturn->date->format('Y-m-d');
        $entries   = $validated['entries'] ?? [];
        $deleteIds = array_values(array_filter(
            (array) $request->input('entries_delete', []),
            fn($v) => is_numeric($v)
        ));
        $deleteIds = array_map('intval', $deleteIds);

        // Duplicate platform+reason within the same submission
        if (!empty($entries)) {
            $combos = array_map(
                fn($e) => $e['sale_platform_id'] . '_' . $e['return_reason_type_id'],
                $entries
            );
            if (count($combos) !== count(array_unique($combos))) {
                return redirect()->back()->withInput()
                    ->withErrors(['entries' => 'Duplicate platform + reason entries within the same submission.']);
            }
        }

        try {
            $this->service->syncForDate($date, $entries, $deleteIds);

            activity()->causedBy(Auth::user())
                ->withProperties(['date' => $date, 'entries_count' => count($entries)])
                ->log("Updated daily returns for {$date}");

            notify()->success('Daily returns updated successfully.', 'Success');
            $returnUrl = $request->input('return_url');
            return $returnUrl ? redirect()->to(urldecode($returnUrl)) : redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('DAILY RETURNS - update failed: ' . $e->getMessage());
            notify()->error('Failed to update daily returns', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        Gate::authorize('general.daily_return.index');

        $columns = $request->input('columns', []);
        if (is_string($columns)) {
            $columns = array_filter(explode(',', $columns));
        }
        $allCols = DailyReturnExport::allColumns();
        $columns = array_values(array_intersect($allCols, $columns ?: $allCols));

        $query = $this->service->getExportQuery($request->except(['columns']));

        return Excel::download(
            new DailyReturnExport($query, $columns),
            'Daily Returns Report - ' . now()->format('d M Y') . '.xlsx'
        );
    }

    public function destroy(DailyReturn $dailyReturn): RedirectResponse
    {
        Gate::authorize('general.daily_return.delete');

        try {
            $label = $dailyReturn->salePlatform->name
                . ' — ' . $dailyReturn->returnReasonType->name
                . ' on ' . $dailyReturn->date->format('Y-m-d');

            activity()
                ->causedBy(Auth::user())
                ->performedOn($dailyReturn)
                ->withProperties(['deleted' => $label])
                ->log('Deleted daily return: ' . $label);

            $this->service->delete($dailyReturn);

            notify()->success("Daily return deleted successfully.", "Deleted");
            return redirect()->back();
        } catch (\Exception $e) {
            Log::error('DAILY RETURNS - deletion failed: ' . $e->getMessage());
            notify()->error('Failed to delete daily return', 'Error');
            return redirect()->back();
        }
    }
}
