<?php

namespace App\Http\Controllers;

use App\Models\DailyReturn;
use App\Services\DailyReturnService;
use App\Services\ReturnReasonTypeService;
use App\Services\SalePlatformService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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

        $data['dailyReturns']   = $this->service->getList($request->all());
        $data['salePlatforms']  = $this->salePlatformService->getParentOptions();
        $data['reasonTypes']    = $this->returnReasonTypeService->getList(['is_active' => 1])->items();
        $data['start']          = ($data['dailyReturns']->currentPage() - 1) * $data['dailyReturns']->perPage() + 1;

        return view('daily_returns.index', $data);
    }

    public function create(): View
    {
        Gate::authorize('general.daily_return.create');

        $data['salePlatforms'] = $this->salePlatformService->getParentOptions();
        $data['reasonTypes']   = $this->returnReasonTypeService->getList(['is_active' => 1])->items();

        return view('daily_returns.create', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(array_merge(
            $this->service->storeRules(),
            [
                'sale_platform_id' => [
                    'required',
                    'exists:sale_platforms,id',
                    Rule::unique('daily_returns', 'sale_platform_id')
                        ->where(fn($q) => $q
                            ->where('date', $request->input('date'))
                            ->where('return_reason_type_id', $request->input('return_reason_type_id'))),
                ],
            ]
        ), [
            'sale_platform_id.unique' => 'A return record for this platform, date, and reason already exists.',
        ]);

        try {
            $dailyReturn = $this->service->create($validated);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($dailyReturn)
                ->withProperties(['attributes' => $dailyReturn->toArray()])
                ->log('Created daily return for ' . $dailyReturn->salePlatform->name
                    . ' — ' . $dailyReturn->returnReasonType->name
                    . ' on ' . $dailyReturn->date->format('Y-m-d'));

            notify()->success("Daily return created successfully.", "Success");
            return redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('DAILY RETURNS - creation failed: ' . $e->getMessage());
            notify()->error('Failed to create daily return', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function show(DailyReturn $dailyReturn): View
    {
        Gate::authorize('general.daily_return.show');

        $dailyReturn->load(['salePlatform', 'returnReasonType']);

        return view('daily_returns.show', compact('dailyReturn'));
    }

    public function edit(DailyReturn $dailyReturn): View
    {
        Gate::authorize('general.daily_return.edit');

        $data['dailyReturn']   = $dailyReturn;
        $data['salePlatforms'] = $this->salePlatformService->getParentOptions();
        $data['reasonTypes']   = $this->returnReasonTypeService->getList(['is_active' => 1])->items();

        return view('daily_returns.edit', $data);
    }

    public function update(Request $request, DailyReturn $dailyReturn): RedirectResponse
    {
        $validated = $request->validate(array_merge(
            $this->service->storeRules(),
            [
                'sale_platform_id' => [
                    'required',
                    'exists:sale_platforms,id',
                    Rule::unique('daily_returns', 'sale_platform_id')
                        ->where(fn($q) => $q
                            ->where('date', $request->input('date'))
                            ->where('return_reason_type_id', $request->input('return_reason_type_id')))
                        ->ignore($dailyReturn->id),
                ],
            ]
        ), [
            'sale_platform_id.unique' => 'A return record for this platform, date, and reason already exists.',
        ]);

        try {
            $oldValues = $dailyReturn->toArray();
            $updated   = $this->service->update($dailyReturn, $validated);
            $newValues = $updated->toArray();
            $changes   = array_diff_assoc($newValues, $oldValues);

            if (!empty($changes)) {
                activity()
                    ->causedBy(Auth::user())
                    ->performedOn($updated)
                    ->withProperties(['old' => $oldValues, 'attributes' => $newValues])
                    ->log('Updated daily return for ' . $updated->salePlatform->name
                        . ' — ' . $updated->returnReasonType->name
                        . ' on ' . $updated->date->format('Y-m-d'));
            }

            notify()->success("Daily return updated successfully.", "Success");
            return redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('DAILY RETURNS - update failed: ' . $e->getMessage());
            notify()->error('Failed to update daily return', 'Error');
            return redirect()->back()->withInput();
        }
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

