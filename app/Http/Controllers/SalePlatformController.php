<?php

namespace App\Http\Controllers;

use App\Exports\SalePlatformExport;
use App\Models\SalePlatform;
use App\Services\SalePlatformService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalePlatformController extends Controller
{
    const ROUTES = [
        'index' => 'admin.sale-platforms.index',
    ];

    public function __construct(private SalePlatformService $service) {}

    // ─────────────────────────────────────────────────────────────
    // Resource methods
    // ─────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        Gate::authorize('general.sale_platform.index');

        $isActive  = $request->input('is_active');
        $hasFilter = $request->input('search')
            || $request->input('type')
            || ($isActive !== null && $isActive !== '');

        if ($hasFilter) {
            $platforms           = $this->service->getFilteredList($request->all());
            $data['platforms']   = $platforms;
            $data['flat_list']   = $platforms->items();
            $data['is_filtered'] = true;
            $data['start']       = ($platforms->currentPage() - 1) * $platforms->perPage() + 1;
        } else {
            $data['platforms']   = null;
            $data['flat_list']   = $this->service->getFullTreeList();
            $data['is_filtered'] = false;
            $data['start']       = 1;
        }

        $data['stats']         = $this->service->getStats();
        $data['channel_lists'] = SalePlatform::CHANNEL_LIST;

        return view('sale-spend.sale_platforms.index', $data);
    }

    public function create(): View
    {
        Gate::authorize('general.sale_platform.create');

        $data['parentOptions'] = $this->service->getParentOptions();
        $data['types']         = SalePlatform::CHANNEL_LIST;

        return view('sale-spend.sale_platforms.create', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                   => ['required', 'string', 'max:100', 'unique:sale_platforms,name'],
            'slug'                   => ['nullable', 'string', 'max:100', 'unique:sale_platforms,slug'],
            'parent_id'              => ['nullable', 'exists:sale_platforms,id'],
            'type'                   => ['required', Rule::in(array_keys(SalePlatform::CHANNEL_LIST))],
            'is_active'              => ['nullable', 'in:on,off'],
            'is_spent'               => ['nullable', 'in:on,off'],
            'is_sales'               => ['nullable', 'in:on,off'],
            'allows_direct_entry'    => ['nullable', 'in:on,off'],
            'show_in_analytics'      => ['nullable', 'in:on,off'],
            'show_in_sale_tracking'  => ['nullable', 'in:on,off'],
            'track_reach'            => ['nullable', 'in:on,off'],
            'track_impressions'      => ['nullable', 'in:on,off'],
            'track_clicks'           => ['nullable', 'in:on,off'],
            'track_sessions'         => ['nullable', 'in:on,off'],
            'track_engaged_sessions' => ['nullable', 'in:on,off'],
            'track_users'            => ['nullable', 'in:on,off'],
            'sort_order'             => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        try {
            $platform = SalePlatform::create([
                'name'                   => $validated['name'],
                'slug'                   => $validated['slug'] ?? Str::slug($validated['name']),
                'parent_id'              => $validated['parent_id'] ?? null,
                'type'                   => $validated['type'],
                'is_active'              => $request->has('is_active'),
                'is_spent'               => $request->has('is_spent'),
                'is_sales'               => $request->has('is_sales'),
                'allows_direct_entry'    => $request->has('allows_direct_entry'),
                'show_in_analytics'      => $request->has('show_in_analytics'),
                'show_in_sale_tracking'  => $request->has('show_in_sale_tracking'),
                'track_reach'            => $request->has('track_reach'),
                'track_impressions'      => $request->has('track_impressions'),
                'track_clicks'           => $request->has('track_clicks'),
                'track_sessions'         => $request->has('track_sessions'),
                'track_engaged_sessions' => $request->has('track_engaged_sessions'),
                'track_users'            => $request->has('track_users'),
                'sort_order'             => $validated['sort_order'] ?? 0,
            ]);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($platform)
                ->withProperties(['attributes' => $platform->toArray()])
                ->log('Created new sale platform: ' . $platform->name);

            notify()->success("Sale platform created successfully.", "Success");
            $returnUrl = $request->input('return_url');
            return $returnUrl ? redirect()->to(urldecode($returnUrl)) : redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('Sale platform creation failed: ' . $e->getMessage());
            notify()->error('Failed to create sale platform', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function show(SalePlatform $salePlatform): View
    {
        Gate::authorize('general.sale_platform.show');

        $salePlatform->load(['parent', 'children' => fn($q) => $q->orderBy('sort_order')]);

        $breadcrumbs = [];
        $current     = $salePlatform->parent;
        while ($current) {
            array_unshift($breadcrumbs, $current);
            $current = $current->parent ?? null;
            if ($current) {
                $current->load('parent');
            }
        }

        $siblingsCount = SalePlatform::where('parent_id', $salePlatform->parent_id)
            ->where('id', '!=', $salePlatform->id)
            ->count();

        return view('sale-spend.sale_platforms.show', [
            'salePlatform'  => $salePlatform,
            'breadcrumbs'   => $breadcrumbs,
            'siblingsCount' => $siblingsCount,
        ]);
    }

    public function edit(SalePlatform $salePlatform): View
    {
        Gate::authorize('general.sale_platform.edit');

        $data['salePlatform']  = $salePlatform;
        $data['parentOptions'] = $this->service->getParentOptions($salePlatform->id);
        $data['types']         = SalePlatform::CHANNEL_LIST;

        return view('sale-spend.sale_platforms.edit', $data);
    }

    public function update(Request $request, SalePlatform $salePlatform): RedirectResponse
    {
        $validated = $request->validate([
            'name'                   => ['required', 'string', 'max:100', Rule::unique('sale_platforms', 'name')->ignore($salePlatform->id)],
            'slug'                   => ['nullable', 'string', 'max:100', Rule::unique('sale_platforms', 'slug')->ignore($salePlatform->id)],
            'parent_id'              => ['nullable', 'exists:sale_platforms,id'],
            'type'                   => ['required', Rule::in(array_keys(SalePlatform::CHANNEL_LIST))],
            'is_active'              => ['nullable', 'in:on,off'],
            'is_spent'               => ['nullable', 'in:on,off'],
            'is_sales'               => ['nullable', 'in:on,off'],
            'allows_direct_entry'    => ['nullable', 'in:on,off'],
            'show_in_analytics'      => ['nullable', 'in:on,off'],
            'show_in_sale_tracking'  => ['nullable', 'in:on,off'],
            'track_reach'            => ['nullable', 'in:on,off'],
            'track_impressions'      => ['nullable', 'in:on,off'],
            'track_clicks'           => ['nullable', 'in:on,off'],
            'track_sessions'         => ['nullable', 'in:on,off'],
            'track_engaged_sessions' => ['nullable', 'in:on,off'],
            'track_users'            => ['nullable', 'in:on,off'],
            'sort_order'             => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        try {
            $oldValues = $salePlatform->only(['name', 'slug', 'parent_id', 'type', 'is_active', 'is_spent', 'is_sales', 'allows_direct_entry', 'show_in_analytics', 'show_in_sale_tracking', 'track_reach', 'track_impressions', 'track_clicks', 'track_sessions', 'track_engaged_sessions', 'track_users', 'sort_order']);

            $salePlatform->update([
                'name'                   => $validated['name'],
                'slug'                   => $validated['slug'] ?? Str::slug($validated['name']),
                'parent_id'              => $validated['parent_id'] ?? null,
                'type'                   => $validated['type'],
                'is_active'              => $request->has('is_active'),
                'is_spent'               => $request->has('is_spent'),
                'is_sales'               => $request->has('is_sales'),
                'allows_direct_entry'    => $request->has('allows_direct_entry'),
                'show_in_analytics'      => $request->has('show_in_analytics'),
                'show_in_sale_tracking'  => $request->has('show_in_sale_tracking'),
                'track_reach'            => $request->has('track_reach'),
                'track_impressions'      => $request->has('track_impressions'),
                'track_clicks'           => $request->has('track_clicks'),
                'track_sessions'         => $request->has('track_sessions'),
                'track_engaged_sessions' => $request->has('track_engaged_sessions'),
                'track_users'            => $request->has('track_users'),
                'sort_order'             => $validated['sort_order'] ?? 0,
            ]);

            $newValues = $salePlatform->only(['name', 'slug', 'parent_id', 'type', 'is_active', 'is_spent', 'is_sales', 'allows_direct_entry', 'show_in_analytics', 'show_in_sale_tracking', 'track_reach', 'track_impressions', 'track_clicks', 'track_sessions', 'track_engaged_sessions', 'track_users', 'sort_order']);
            $changes   = array_filter($newValues, fn(mixed $v, string|int $k): bool => $v != $oldValues[$k], ARRAY_FILTER_USE_BOTH);

            if (!empty($changes)) {
                activity()
                    ->causedBy(Auth::user())
                    ->performedOn($salePlatform)
                    ->withProperties(['old' => $oldValues, 'attributes' => $newValues])
                    ->log('Updated sale platform: ' . $salePlatform->name . ' (Changed: ' . implode(', ', array_keys($changes)) . ')');
            }

            notify()->success("Sale platform updated successfully.", "Success");
            $returnUrl = $request->input('return_url');
            return $returnUrl ? redirect()->to(urldecode($returnUrl)) : redirect()->route(self::ROUTES['index']);
        } catch (\Exception $e) {
            Log::error('Sale platform update failed: ' . $e->getMessage());
            notify()->error('Failed to update sale platform', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function export(Request $request): BinaryFileResponse
    {
        Gate::authorize('general.sale_platform.index');

        $columns = $request->input('columns', []);
        if (is_string($columns)) {
            $columns = array_filter(explode(',', $columns));
        }
        $allCols = SalePlatformExport::allColumns();
        $columns = array_values(array_intersect($allCols, $columns ?: $allCols));

        $query = $this->service->getExportQuery($request->except(['columns']));

        return Excel::download(
            new SalePlatformExport($query, $columns),
            'Sale Platforms - ' . now()->format('d M Y') . '.xlsx'
        );
    }

    public function destroy(SalePlatform $salePlatform): RedirectResponse
    {
        Gate::authorize('general.sale_platform.delete');

        try {
            activity()
                ->causedBy(Auth::user())
                ->performedOn($salePlatform)
                ->withProperties(['deleted_platform' => $salePlatform->name])
                ->log('Deleted sale platform: ' . $salePlatform->name);

            $salePlatform->delete();

            notify()->success("Sale platform deleted successfully.", "Deleted");
            return redirect()->back();
        } catch (\Exception $e) {
            Log::error('Sale platform deletion failed: ' . $e->getMessage());
            notify()->error('Failed to delete sale platform', 'Error');
            return redirect()->back();
        }
    }
}