<?php

namespace App\Http\Controllers;

use App\Models\ReturnReasonType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReturnReasonTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize('general.return_reason_type.index');

        $data['reasonTypes'] = ReturnReasonType::filter($request->all())
            ->orderBy('sort_order')
            ->latest('id')
            ->paginate(30);

        $data['start'] = ($data['reasonTypes']->currentPage() - 1) * $data['reasonTypes']->perPage() + 1;

        return view('return_reason_types.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('general.return_reason_type.create');

        return view('return_reason_types.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150|unique:return_reason_types,name',
            'slug' => 'nullable|string|max:150|unique:return_reason_types,slug',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:255',
        ]);

        try {
            $slug = $validated['slug'] ?? Str::slug($request->name);

            $reasonType = ReturnReasonType::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'is_active' => $request->has('is_active') ? true : false,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($reasonType)
                ->withProperties(['attributes' => $reasonType->toArray()])
                ->log('Created new return reason type: ' . $reasonType->name);

            notify()->success("Return reason type created successfully.", "Success");
            return redirect()->route('admin.return-reason-types.index');
        } catch (\Exception $e) {
            Log::error('Return reason type creation failed: ' . $e->getMessage());
            notify()->error('Failed to create return reason type', 'Error');
            return redirect()->route('admin.return-reason-types.index');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ReturnReasonType $returnReasonType)
    {
        Gate::authorize('general.return_reason_type.show');

        return view('return_reason_types.show', compact('returnReasonType'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReturnReasonType $returnReasonType)
    {
        Gate::authorize('general.return_reason_type.edit');

        return view('return_reason_types.edit', compact('returnReasonType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReturnReasonType $returnReasonType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150|unique:return_reason_types,name,' . $returnReasonType->id,
            'slug' => 'nullable|string|max:150|unique:return_reason_types,slug,' . $returnReasonType->id,
            'description' => 'nullable|string',
            'is_active' => 'nullable|in:on,off',
            'sort_order' => 'nullable|integer|min:0|max:255',
        ]);


        $validated['is_active'] = $request->boolean('is_active');


        try {
            // Capture old values before update
            $oldValues = [
                'name' => $returnReasonType->name,
                'slug' => $returnReasonType->slug,
                'description' => $returnReasonType->description,
                'is_active' => $returnReasonType->is_active,
                'sort_order' => $returnReasonType->sort_order,
            ];

            $slug = $validated['slug'] ?? Str::slug($request->name);

            $returnReasonType->update([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'is_active' => $request->has('is_active') ? true : false,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            // Capture new values after update
            $newValues = [
                'name' => $returnReasonType->name,
                'slug' => $returnReasonType->slug,
                'description' => $returnReasonType->description,
                'is_active' => $returnReasonType->is_active,
                'sort_order' => $returnReasonType->sort_order,
            ];

            // Detect actual changes
            $changes = [];
            foreach ($oldValues as $key => $oldValue) {
                if ($oldValue != $newValues[$key]) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $newValues[$key]
                    ];
                }
            }

            // Log only if there are actual changes
            if (count($changes) > 0) {
                $changedFields = array_keys($changes);
                $description = 'Updated return reason type: ' . $returnReasonType->name;
                $description .= ' (Changed: ' . implode(', ', array_map(fn($f) => ucfirst($f), $changedFields)) . ')';

                activity()
                    ->causedBy(Auth::user())
                    ->performedOn($returnReasonType)
                    ->withProperties(['old' => $oldValues, 'attributes' => $newValues])
                    ->log($description);
            }

            notify()->success("Return reason type updated successfully.", "Success");
            return redirect()->route('admin.return-reason-types.index');
        } catch (\Exception $e) {
            Log::error('Return reason type update failed: ' . $e->getMessage());
            notify()->error('Failed to update return reason type', 'Error');
            return redirect()->route('admin.return-reason-types.index');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReturnReasonType $returnReasonType)
    {
        Gate::authorize('general.return_reason_type.delete');

        try {
            activity()
                ->causedBy(Auth::user())
                ->performedOn($returnReasonType)
                ->withProperties(['deleted_reason_type' => $returnReasonType->name])
                ->log('Deleted return reason type: ' . $returnReasonType->name);

            $returnReasonType->delete();

            notify()->success("Return reason type deleted successfully.", "Deleted");
            return redirect()->back();
        } catch (\Exception $e) {
            Log::error('Return reason type deletion failed: ' . $e->getMessage());
            notify()->error('Failed to delete return reason type', 'Error');
            return redirect()->back();
        }
    }
}
