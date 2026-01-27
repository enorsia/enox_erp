<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FabricationController extends Controller
{
    public function index(Request $request): View
    {

        $name = $request->name;
        $status = $request->status;

        $query = LookupName::query();

        $query->when($name, function ($query, $name) {
            return $query->where('name', 'like', "%{$name}%");
        })
            ->when($status !== null, function ($query) use ($status) {
                return $query->where('status', $status);
            });

        $data['lookup_names'] = $query->where('type_id', 5)
            ->orderBy('id', 'desc')
            ->with('type')
            ->paginate(30);

        $data['start'] = ($data['lookup_names']->currentPage() - 1) * $data['lookup_names']->perPage() + 1;

        return view('selling_chart.fabrication.index', $data);
    }

    public function create(): View
    {

        return view('selling_chart.fabrication.create');
    }

    public function store(Request $request): RedirectResponse
    {

        $this->validate($request, [
            'name' => 'required|unique:lookup_names,name'
        ]);

        try {
            LookupName::create([
                'name' => $request->name,
                'type_id' => 5,
                'status' => $request->filled('status'),
            ]);

            return redirect()->route('admin.selling_chart.fabrication.index');
        } catch (\Throwable $th) {
            return back();
        }
    }
}
