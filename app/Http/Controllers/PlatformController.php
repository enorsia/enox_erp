<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data['platforms'] = Platform::latest()->paginate($this->perPage);
        $data['start'] = ($data['platforms']->currentPage() - 1) * $data['platforms']->perPage() + 1;
        return view('platforms.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
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
            'note' => 'nullable|string',
        ]);

        Platform::create([
            'name' => $validated['platform_name'],
            'shipping_charge' => $validated['shipping_charge'] ?? 0,
            'note' => $validated['note'],
        ]);

        return redirect()
            ->route('admin.platforms.index')
            ->with('success', 'Platform created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Platform $platform)
    {
        return view('platforms.show', compact('platform'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Platform $platform)
    {
        return view('platforms.edit', compact('platform'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Platform $platform)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:platforms,name,' . $platform->id,
            'shipping_charge' => 'required|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $platform->update($validated);

        return redirect()
            ->route('admin.platforms.index')
            ->with('success', 'Platform updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Platform $platform)
    {
        $platform->delete();

        return redirect()
            ->route('admin.platforms.index')
            ->with('success', 'Platform deleted successfully.');
    }
}
