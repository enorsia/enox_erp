<?php

namespace App\Http\Controllers;

use App\Exports\ReturnReasonTypeExport;
use App\Models\ReturnReasonType;
use App\Services\ReturnReasonTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReturnReasonTypeController extends Controller
{
    const ROUTES = [
        'index' => 'admin.return-reason-types.index',
    ];

    public function __construct(private ReturnReasonTypeService $service) {}

    public function index(Request $request) : View
    {
        Gate::authorize('general.return_reason_type.index');

        $data['reasonTypes'] = $this->service->getList($request->all());
        $data['start']       = ($data['reasonTypes']->currentPage() - 1) * $data['reasonTypes']->perPage() + 1;

        return view('return_reason_types.index', $data);
    }

    public function create() : View
    {
        Gate::authorize('general.return_reason_type.create');

        return view('return_reason_types.create');
    }

    public function store(Request $request) : RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:150|unique:return_reason_types,name',
            'slug'        => 'nullable|string|max:150|unique:return_reason_types,slug',
            'description' => 'nullable|string',
            'is_active'   => 'nullable|boolean',
            'sort_order'  => 'nullable|integer|min:0|max:255',
        ]);

        try {
            $reasonType = $this->service->create($validated, $request->has('is_active'));

            activity()
                ->causedBy(Auth::user())
                ->performedOn($reasonType)
                ->withProperties(['attributes' => $reasonType->toArray()])
                ->log('Created new return reason type: ' . $reasonType->name);

            notify()->success("Return reason type created successfully.", "Success");
            return redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('RETURN REASON TYPES - creation failed: ' . $e->getMessage());
            notify()->error('Failed to create return reason type', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function show(ReturnReasonType $returnReasonType) : View
    {
        Gate::authorize('general.return_reason_type.show');

        return view('return_reason_types.show', compact('returnReasonType'));
    }

    public function edit(ReturnReasonType $returnReasonType) : View
    {
        Gate::authorize('general.return_reason_type.edit');

        return view('return_reason_types.edit', compact('returnReasonType'));
    }

    public function update(Request $request, ReturnReasonType $returnReasonType) : RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:150|unique:return_reason_types,name,' . $returnReasonType->id,
            'slug'        => 'nullable|string|max:150|unique:return_reason_types,slug,' . $returnReasonType->id,
            'description' => 'nullable|string',
            'is_active'   => 'nullable|in:on,off',
            'sort_order'  => 'nullable|integer|min:0|max:255',
        ]);

        try {
            $oldValues  = $returnReasonType->only(['name', 'slug', 'description', 'is_active', 'sort_order']);
            $reasonType = $this->service->update($returnReasonType, $validated, $request->has('is_active'));
            $newValues  = $reasonType->only(['name', 'slug', 'description', 'is_active', 'sort_order']);

            $changes = array_filter($newValues, fn($v, $k) => $v != $oldValues[$k], ARRAY_FILTER_USE_BOTH);

            if (!empty($changes)) {
                activity()
                    ->causedBy(Auth::user())
                    ->performedOn($reasonType)
                    ->withProperties(['old' => $oldValues, 'attributes' => $newValues])
                    ->log('Updated return reason type: ' . $reasonType->name
                        . ' (Changed: ' . implode(', ', array_keys($changes)) . ')');
            }

            notify()->success("Return reason type updated successfully.", "Success");
            return redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('RETURN REASON TYPES - update failed: ' . $e->getMessage());
            notify()->error('Failed to update return reason type', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function export(Request $request)
    {
        Gate::authorize('general.return_reason_type.index');

        $columns = $request->input('columns', []);
        if (is_string($columns)) {
            $columns = array_filter(explode(',', $columns));
        }
        $allCols = ReturnReasonTypeExport::allColumns();
        $columns = array_values(array_intersect($allCols, $columns ?: $allCols));

        $query = $this->service->getExportQuery($request->except(['columns']));

        return Excel::download(
            new ReturnReasonTypeExport($query, $columns),
            'return-reason-types-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function destroy(ReturnReasonType $returnReasonType) : RedirectResponse
    {
        Gate::authorize('general.return_reason_type.delete');

        try {
            activity()
                ->causedBy(Auth::user())
                ->performedOn($returnReasonType)
                ->withProperties(['deleted_reason_type' => $returnReasonType->name])
                ->log('Deleted return reason type: ' . $returnReasonType->name);

            $this->service->delete($returnReasonType);

            notify()->success("Return reason type deleted successfully.", "Deleted");
            return redirect()->back();
        } catch (\Exception $e) {
            Log::error('RETURN REASON TYPES - deletion failed: ' . $e->getMessage());
            notify()->error('Failed to delete return reason type', 'Error');
            return redirect()->back();
        }
    }
}
