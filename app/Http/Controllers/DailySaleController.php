<?php

namespace App\Http\Controllers;

use App\Exports\DailySaleExport;
use App\Models\DailySale;
use App\Services\DailySaleService;
use App\Services\SalePlatformService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $data['start']         = ($data['dailySales']->currentPage() - 1) * $data['dailySales']->perPage() + 1;

        return view('daily_sales.daily_sales.index', $data);
    }

    public function create(): View
    {
        Gate::authorize('general.daily_sale.create');

        $data['salePlatforms'] = $this->salePlatformService->getParentOptions();

        return view('daily_sales.daily_sales.create', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(array_merge(
            $this->service->storeRules(),
            [
                'sale_platform_id' => [
                    'required',
                    'exists:sale_platforms,id',
                    Rule::unique('daily_sales', 'sale_platform_id')
                        ->where(fn($q) => $q->where('date', $request->input('date'))),
                ],
            ]
        ), [
            'sale_platform_id.unique' => 'A daily sale record already exists for this platform on the selected date.',
        ]);

        try {
            $dailySale = $this->service->create($validated);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($dailySale)
                ->withProperties(['attributes' => $dailySale->toArray()])
                ->log('Created daily sale for ' . $dailySale->salePlatform->name . ' on ' . $dailySale->date->format('Y-m-d'));

            notify()->success("Daily sale created successfully.", "Success");
            return redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('DAILY SALES - creation failed: ' . $e->getMessage());
            notify()->error('Failed to create daily sale', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function show(DailySale $dailySale): View
    {
        Gate::authorize('general.daily_sale.show');

        $dailySale->load('salePlatform');

        return view('daily_sales.daily_sales.show', compact('dailySale'));
    }

    public function edit(DailySale $dailySale): View
    {
        Gate::authorize('general.daily_sale.edit');

        $data['dailySale']     = $dailySale;
        $data['salePlatforms'] = $this->salePlatformService->getParentOptions();

        return view('daily_sales.daily_sales.edit', $data);
    }

    public function update(Request $request, DailySale $dailySale): RedirectResponse
    {
        $validated = $request->validate(array_merge(
            $this->service->storeRules(),
            [
                'sale_platform_id' => [
                    'required',
                    'exists:sale_platforms,id',
                    Rule::unique('daily_sales', 'sale_platform_id')
                        ->where(fn($q) => $q->where('date', $request->input('date')))
                        ->ignore($dailySale->id),
                ],
            ]
        ), [
            'sale_platform_id.unique' => 'A daily sale record already exists for this platform on the selected date.',
        ]);

        try {
            $oldValues = $dailySale->toArray();
            $updated   = $this->service->update($dailySale, $validated);
            $newValues = $updated->toArray();
            $changes   = array_diff_assoc($newValues, $oldValues);

            if (!empty($changes)) {
                activity()
                    ->causedBy(Auth::user())
                    ->performedOn($updated)
                    ->withProperties(['old' => $oldValues, 'attributes' => $newValues])
                    ->log('Updated daily sale for ' . $updated->salePlatform->name . ' on ' . $updated->date->format('Y-m-d'));
            }

            notify()->success("Daily sale updated successfully.", "Success");
            return redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('DAILY SALES - update failed: ' . $e->getMessage());
            notify()->error('Failed to update daily sale', 'Error');
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

        $query = $this->service->getExportQuery($request->except(['columns']));

        return Excel::download(
            new DailySaleExport($query, $columns),
            'daily-sales-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function destroy(DailySale $dailySale): RedirectResponse
    {
        Gate::authorize('general.daily_sale.delete');

        try {
            $label = $dailySale->salePlatform->name . ' on ' . $dailySale->date->format('Y-m-d');

            activity()
                ->causedBy(Auth::user())
                ->performedOn($dailySale)
                ->withProperties(['deleted' => $label])
                ->log('Deleted daily sale: ' . $label);

            $this->service->delete($dailySale);

            notify()->success("Daily sale deleted successfully.", "Deleted");
            return redirect()->back();
        } catch (\Exception $e) {
            Log::error('DAILY SALES - deletion failed: ' . $e->getMessage());
            notify()->error('Failed to delete daily sale', 'Error');
            return redirect()->back();
        }
    }
}

