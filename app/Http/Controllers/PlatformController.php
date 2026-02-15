<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class PlatformController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize('settings.platforms.index');
        $q = $request->q;
        $data['platforms'] = Platform::query()
            ->when($q, function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%');
            })
            // ->latest()
            ->paginate($this->perPage)
            ->withQueryString();

        $data['start'] = ($data['platforms']->currentPage() - 1) * $data['platforms']->perPage() + 1;
        return view('platforms.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('settings.platforms.create');
        return view('platforms.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'platform_name' => 'required|string|max:255|unique:platforms,name',
            'shipping_charge' => 'nullable|numeric|min:0',
            'min_profit' => 'required|numeric',
            'code' => 'required|string|max:255|unique:platforms,code',
            'commission' => 'required|numeric',
            'note' => 'nullable|string',
        ]);
        try {
            $platform = Platform::create([
                'name' => $validated['platform_name'],
                'code' => $validated['code'],
                'shipping_charge' => $validated['shipping_charge'] ?? 0,
                'min_profit' => $validated['min_profit'],
                'commission' => $validated['commission'],
                'note' => $validated['note'],
            ]);

            activity()
                ->causedBy(auth()->user())
                ->performedOn($platform)
                ->withProperties([
                    'platform_name' => $platform->name,
                    'code' => $platform->code,
                    'commission' => $platform->commission . '%'
                ])
                ->log('Created new platform: ' . $platform->name . ' (Code: ' . $platform->code . ')');

            notify()->success('Platform created successfully', 'Success');
            return redirect()->route('admin.platforms.index');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            notify()->error('Something went wrong', 'Error');
            return back();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Platform $platform)
    {
        Gate::authorize('settings.platforms.index');
        return view('platforms.show', compact('platform'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Platform $platform)
    {
        Gate::authorize('settings.platforms.edit');
        return view('platforms.edit', compact('platform'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Platform $platform)
    {
        $validated = $request->validate([
            'shipping_charge' => 'nullable|numeric|min:0',
            'min_profit' => 'required|numeric',
            'commission' => 'required|numeric',
            'status'     => 'nullable',
            'note' => 'nullable|string',
        ]);

        try {
            // Capture old values
            $oldValues = [
                'shipping_charge' => $platform->shipping_charge,
                'min_profit' => $platform->min_profit,
                'commission' => $platform->commission,
                'status' => $platform->status,
                'note' => $platform->note,
            ];

            $validated['status'] = $request->has('status') ? 1 : 0;
            $platform->update($validated);
            Cache::forget('platforms_by_code');

            // Capture new values
            $platform->refresh();
            $newValues = [
                'shipping_charge' => $platform->shipping_charge,
                'min_profit' => $platform->min_profit,
                'commission' => $platform->commission,
                'status' => $platform->status,
                'note' => $platform->note,
            ];

            // Detect changes
            $changes = [];
            foreach ($oldValues as $key => $oldValue) {
                if ($oldValue != $newValues[$key]) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $newValues[$key]
                    ];
                }
            }

            if (count($changes) > 0) {
                $changedFields = array_keys($changes);
                $description = 'Updated platform: ' . $platform->name . ' (Changed: ' . implode(', ', array_map(fn($f) => ucwords(str_replace('_', ' ', $f)), $changedFields)) . ')';

                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($platform)
                    ->withProperties(['old' => $oldValues, 'attributes' => $newValues])
                    ->log($description);
            }

            notify()->success('Platform updated successfully', 'Success');
            return redirect()->route('admin.platforms.index');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            notify()->error('Something went wrong', 'Error');
            return back();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Platform $platform)
    {
        Gate::authorize('settings.platforms.delete');
        try {
            activity()
                ->causedBy(auth()->user())
                ->performedOn($platform)
                ->withProperties([
                    'platform_name' => $platform->name,
                    'code' => $platform->code
                ])
                ->log('Deleted platform: ' . $platform->name . ' (Code: ' . $platform->code . ')');

            $platform->delete();
            notify()->success('Platform deleted successfully', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            notify()->error($e->getMessage(), 'Error');
            return back();
        }
    }
}
