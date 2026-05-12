<?php

namespace App\Http\Controllers;

use App\Exports\MonthlyBudgetExport;
use App\Models\MonthlyBudget;
use App\Services\MonthlyBudgetService;
use App\Services\SalePlatformService;
use App\Support\DateOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class MonthlyBudgetController extends Controller
{

    const ROUTES = [
        'index'   => 'admin.monthly-budgets.index',
    ];

    public function __construct(
        private MonthlyBudgetService $service,
    ) {}


    public function index(SalePlatformService $salePlatformService, Request $request) : View
    {
        Gate::authorize('general.monthly_budget.index');

        $data['years']         = DateOptions::years();
        $data['months']        = config('constants.months');
        $data['salePlatforms'] = $salePlatformService->getParentOptions();

        $monthlyBudgets        = $this->service->getList($request->all());
        $data['monthlyBudgets']= $monthlyBudgets;
        $data['start']         = ($monthlyBudgets->currentPage() - 1) * $monthlyBudgets->perPage() + 1;

        return view('daily_sales.monthly_budgets.index', $data);
    }

    public function create(SalePlatformService $salePlatformService) : View
    {
        Gate::authorize('general.monthly_budget.create');

        $data['salePlatforms'] = $salePlatformService->getParentOptions();
        $data['years'] = DateOptions::years();
        $data['months'] = config('constants.months');

        return view('daily_sales.monthly_budgets.create', $data);
    }

    public function store(Request $request) : RedirectResponse
    {
        $validated = $request->validate([
            'sale_platform_id' => 'required|exists:sale_platforms,id',
            'year' => 'required|integer|min:1900|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'budget' => 'required|numeric|min:1',
            'currency' => 'required|string|size:3',
            'notes' => 'nullable|string',
        ]);

        $request->validate([
            'sale_platform_id' => Rule::unique('monthly_budgets')->where(function ($query) use ($validated) {
                return $query->where('sale_platform_id', $validated['sale_platform_id'])
                    ->where('year', $validated['year'])
                    ->where('month', $validated['month']);
            }),
        ], [
            'sale_platform_id.unique' => 'A monthly budget for this sale platform, year, and month already exists.',
        ]);

        try {
            $monthlyBudget = MonthlyBudget::create($validated);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($monthlyBudget)
                ->withProperties(['attributes' => $monthlyBudget->toArray()])
                ->log('Created new monthly budget for ' . $monthlyBudget->salePlatform->name . ' - ' . $monthlyBudget->year . '/' . $monthlyBudget->month);

            notify()->success("Monthly budget created successfully.", "Success");
            return redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('MONTHLY BUDGET - creation failed: ' . $e->getMessage());
            notify()->error('Failed to create monthly budget', 'Error');
            return redirect()->route(self::ROUTES['index']);
        }
    }

    public function show(MonthlyBudget $monthlyBudget) : View
    {
        Gate::authorize('general.monthly_budget.show');

        $months = config('constants.months');
        return view('daily_sales.monthly_budgets.show', compact('monthlyBudget', 'months'));
    }

    public function edit(MonthlyBudget $monthlyBudget, SalePlatformService $salePlatformService) : View
    {
        Gate::authorize('general.monthly_budget.edit');

        $data['monthlyBudget'] = $monthlyBudget;
        $data['salePlatforms'] = $salePlatformService->getParentOptions();
        $data['years'] = DateOptions::years();
        $data['months'] = config('constants.months');

        return view('daily_sales.monthly_budgets.edit', $data);
    }

    public function update(Request $request, MonthlyBudget $monthlyBudget) : RedirectResponse
    {
        $validated = $request->validate([
            'sale_platform_id' => 'required|exists:sale_platforms,id',
            'year' => 'required|integer|min:1900|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'budget' => 'required|numeric|min:1',
            'currency' => 'required|string|size:3',
            'notes' => 'nullable|string',
        ]);

        $request->validate([
            'sale_platform_id' => Rule::unique('monthly_budgets')->where(function ($query) use ($validated) {
                return $query->where('sale_platform_id', $validated['sale_platform_id'])
                    ->where('year', $validated['year'])
                    ->where('month', $validated['month']);
            })->ignore($monthlyBudget->id),
        ], [
            'sale_platform_id.unique' => 'A monthly budget for this sale platform, year, and month already exists.',
        ]);

        try {
            $oldValues = $monthlyBudget->toArray();

            $monthlyBudget->update($validated);

            $newValues = $monthlyBudget->toArray();

            $changes = array_diff_assoc($newValues, $oldValues);

            if (count($changes) > 0) {
                activity()
                    ->causedBy(Auth::user())
                    ->performedOn($monthlyBudget)
                    ->withProperties(['old' => $oldValues, 'attributes' => $newValues])
                    ->log('Updated monthly budget for ' . $monthlyBudget->salePlatform->name . ' - ' . $monthlyBudget->year . '/' . $monthlyBudget->month);
            }

            notify()->success("Monthly budget updated successfully.", "Success");
            return redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('MONTHLY BUDGET - update failed: ' . $e->getMessage());
            notify()->error('Failed to update monthly budget', 'Error');
            return redirect()->route(self::ROUTES['index']);
        }
    }

    public function export(Request $request)
    {
        Gate::authorize('general.monthly_budget.index');

        $columns = $request->input('columns', []);
        if (is_string($columns)) {
            $columns = array_filter(explode(',', $columns));
        }
        $allCols = MonthlyBudgetExport::allColumns();
        $columns = array_values(array_intersect($allCols, $columns ?: $allCols));

        $query = $this->service->getExportQuery($request->except(['columns']));

        return Excel::download(
            new MonthlyBudgetExport($query, $columns),
            'monthly-budgets-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function destroy(MonthlyBudget $monthlyBudget) : RedirectResponse
    {
        Gate::authorize('general.monthly_budget.delete');

        try {
            activity()
                ->causedBy(Auth::user())
                ->performedOn($monthlyBudget)
                ->withProperties(['deleted_monthly_budget' => $monthlyBudget->salePlatform->name . ' - ' . $monthlyBudget->year . '/' . $monthlyBudget->month])
                ->log('Deleted monthly budget for ' . $monthlyBudget->salePlatform->name . ' - ' . $monthlyBudget->year . '/' . $monthlyBudget->month);

            $monthlyBudget->delete();

            notify()->success("Monthly budget deleted successfully.", "Deleted");
            return redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('MONTHLY BUDGET - deletion failed: ' . $e->getMessage());
            notify()->error('Failed to delete monthly budget', 'Error');
            return redirect()->route(self::ROUTES['index']);
        }
    }
}
