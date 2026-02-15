<?php

namespace App\Http\Controllers;

use App\ApiServices\FabricationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Gate;

class FabricationController extends Controller
{
    public function __construct(
        protected FabricationService $service
    ) {}

    public function index(Request $request)
    {
         Gate::authorize('general.fabrication.index');
        $data = [
            'lookup_names' => collect(),
            'start' => 0,
        ];

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = $this->service->get([
                'page' => $request->integer('page', 1),
                'name' => $request->name,
                'status' => $request->status,
            ]);
            if ($response->failed()) {
                throw new \Exception('API request failed');
            }
            $apiData = $response->json();

            $items = collect($apiData['data'] ?? [])
                ->map(fn ($item) => (object) $item);

            $data['lookup_names'] = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $apiData['pagination']['total'] ?? 0,
                $apiData['pagination']['per_page'] ?? 30,
                $apiData['pagination']['current_page'] ?? 1,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
            $data['start'] = $apiData['pagination']['start'] ?? 0;
        } catch (\Exception $e) {
            Log::error('Fabrication API Error', [
                'message' => $e->getMessage(),
            ]);
        }
        return view('selling_chart.fabrication.index', $data);
    }

    public function create()
    {
        Gate::authorize('general.fabrication.create');
        return view('selling_chart.fabrication.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $payload = [
            'name'   => $request->name,
            'status' => $request->has('status'),
        ];

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = $this->service->store($payload);

            activity()
                ->causedBy(auth()->user())
                ->withProperties([
                    'fabrication_name' => $request->name,
                    'status' => $request->has('status') ? 'Active' : 'Inactive'
                ])
                ->log('Created new fabrication: ' . $request->name . ' (Status: ' . ($request->has('status') ? 'Active' : 'Inactive') . ')');

            notify()->success('Febrication created successfully', 'Success');
            return redirect()->route('admin.selling_chart.fabrication.index');

        }catch (RequestException $e) {
            // Catch API validation errors (422)
            if ($e->response && $e->response->status() === 422) {
                return back()
                    ->withErrors($e->response->json('errors') ?? [])
                    ->withInput();
            }

            // Other exceptions
            Log::error('Fabrication store failed', [
                'error' => $e->getMessage(),
            ]);
            notify()->error('Something went wrong. Please try again', 'Error');
            return back()->withInput();
        }
        catch (\Throwable $e) {
            Log::error('Fabrication store failed', [
                'error' => $e->getMessage(),
            ]);
            notify()->error('Something went wrong. Please try again', 'Error');
            return back()->withInput();
        }
    }
}
