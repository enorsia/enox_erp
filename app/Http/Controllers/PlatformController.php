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
        $platforms = Platform::latest()->paginate(15);
        return view('platforms.index', compact('platforms'));
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
            'name' => 'required|string|max:255|unique:platforms,name',
            'shipping_charge' => 'required|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        Platform::create($validated);

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
