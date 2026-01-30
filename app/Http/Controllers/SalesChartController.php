<?php

namespace App\Http\Controllers;

use App\ApiServices\FabricationService;
use App\ApiServices\SellingChartApiService;
use App\Exports\SellingChartExport;
use App\Imports\SellingChartImport;
use App\Jobs\CloudflareFileDeleteJob;
use App\Jobs\CloudflareFileUploadJob;
use App\Models\SellingChartBasicInfo;
use App\Models\SellingChartExpense;
use App\Models\SellingChartPrice;
use App\Models\SellingChartType;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SalesChartController extends Controller
{

    public function __construct(
        protected SellingChartApiService $sellingChartApiService,
        protected FabricationService $fabricationService
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('general.chart.index');

        $action = $request->input('action');

        if ($action == 'excel') return $this->exportReport($request);

        if ($action == 'bulkEdit') return $this->bulkEdit($request);

        $data = $this->sellingChartApiService->getCommonData();
        // dd($data['departments']->toArray());

        // style count calculation start
        $data['mini_total_styles'] = SellingChartBasicInfo::filter($request)
            ->select('mini_category')
            ->selectRaw('COUNT(id) as total_count')
            ->with('miniCategory')
            ->groupBy('mini_category')
            ->get();

        // Step 1: Get all distinct mini categories
        $miniCategories = SellingChartType::orderBy('id', 'asc')->pluck('name', 'id');

        // Step 2: Get counts grouped by department and mini category
        // $counts = SellingChartBasicInfo::filter($request)
        //     ->leftJoin('selling_chart_prices', 'selling_chart_basic_infos.id', '=', 'selling_chart_prices.basic_info_id')
        //     ->select('department_id', 'department_name', 'mini_category')
        //     ->selectRaw('COUNT(selling_chart_prices.id) as count')
        //     ->selectRaw('SUM(selling_chart_prices.po_order_qty) as total_quantity')
        //     ->groupBy('department_id', 'department_name', 'mini_category')
        //     ->with(['miniCategory'])
        //     ->get();
        $counts = SellingChartBasicInfo::filter($request)
            ->select('department_id', 'department_name', 'mini_category')
            ->leftJoin('selling_chart_prices', 'selling_chart_basic_infos.id', '=', 'selling_chart_prices.basic_info_id')
            ->groupBy('department_id', 'department_name', 'mini_category')
            ->selectRaw('mini_category, COUNT(selling_chart_prices.id) as count')
            ->selectRaw('SUM(selling_chart_prices.po_order_qty) as total_quantity')
            ->with(['miniCategory'])
            ->get();

        $finalResults = [];
        $data['totalQuantity'] = 0;
        $data['totalColors'] = 0;

        // Initialize each department with zero counts for each mini category
        foreach ($counts as $item) {
            $departmentId = $item->department_id;

            // Ensure the department array exists
            if (!isset($finalResults[$departmentId])) {
                $finalResults[$departmentId] = [
                    'department_id' => $departmentId,
                    'department_name' => $item->department_name,
                    'mini_categories' => []
                ];

                // Add all mini categories with count initialized to 0
                foreach ($miniCategories as $key => $miniCategory) {
                    $finalResults[$departmentId]['mini_categories'][] = [
                        'mini_category' => $key,
                        // 'mini_category_name' => SellingChartType::find($miniCategory)->name ?? '',
                        'mini_category_name' => $miniCategory,
                        'count' => 0
                    ];
                }
            }

            // Update the count and mini category name for the specific mini category
            foreach ($finalResults[$departmentId]['mini_categories'] as $key => $category) {
                if ($category['mini_category'] == $item->mini_category) {
                    $data['totalQuantity'] += $item->total_quantity;
                    $data['totalColors'] += $item->count;
                    $finalResults[$departmentId]['mini_categories'][$key]['count'] = $item->count;
                    $finalResults[$departmentId]['mini_categories'][$key]['mini_category_name'] = $item?->miniCategory?->name ?? '';
                    break;
                }
            }
        }

        $data['deparment_total_colors'] = array_values($finalResults);
        // colors count calculation end

        $data['chartInfos'] = SellingChartBasicInfo::filter($request)
            ->with(['sellingChartPrices'])
            ->withCount(['sellingChartPrices'])
            ->orderByDesc('id')
            ->paginate(30);

        $designNos = $data['chartInfos']->pluck('design_no')->unique()->toArray();

        $ecommerceProducts = $this->sellingChartApiService->getEcomProducts([
            'designNos' => $designNos
        ]);

        $data['ecommerceMap'] = $ecommerceProducts->keyBy(fn($item) => $item['style']['name'] ?? null);

        $data['start'] = ($data['chartInfos']->currentPage() - 1) * $data['chartInfos']->perPage() + 1;

        return view('selling_chart.index', $data);
    }

    public function approve(Request $request, int | string $id)
    {
        Gate::authorize('general.chart.approve');
        try {
            if (empty($request->action_type)) {
                notify()->error("Action is not selected", "Error");
                return redirect()->back();
            }

            $status  = $request->action_type == 'approve' ? 1 : 2;

            $sel = SellingChartBasicInfo::findOrFail($id);
            $sel->status = $status;
            $sel->save();

            notify()->success("Selling Chart Approved Successfully.", "Success");
            return redirect()->route('admin.selling_chart.index');
        } catch (\Throwable $th) {
            notify()->error("Approval Failed.", "Error");
            Log::error('Selling Chart approve failed', [
                'message'   => $th->getMessage()
            ]);
            return back();
        }
    }

    public function exportReport(Request $request)
    {
        Gate::authorize('general.chart.export');
        $chartInfos = SellingChartBasicInfo::filter($request)
            ->with(['sellingChartPrices'])
            ->get();
        return Excel::download(new SellingChartExport($chartInfos), 'selling_chart_reports.xlsx');
    }

    public function import(Request $request)
    {
        Gate::authorize('general.chart.import');

        $request->validate([
            'sheet' => 'required|mimes:xlsx,csv,xls',
        ]);

        try {
            DB::beginTransaction();

            Excel::import(new SellingChartImport, $request->file('sheet'));

            Session::forget('import_msg');

            DB::commit();
            notify()->success("Selling infos Created Successfully.", "Success");
            return redirect()->back();
        } catch (\Throwable $th) {
            DB::rollback();
            notify()->error("Selling infos Create Failed.", "Error");
            Log::error('Selling infos creation failed', [
                'message'   => $th->getMessage()
            ]);
            return back();
        }
    }

    public function create()
    {
        Gate::authorize('general.chart.create');
        $data = $this->sellingChartApiService->getCommonData();
        $data['expenses'] = SellingChartExpense::where('status', 1)->get();
        return view('selling_chart.create', $data);
    }

    public function uploadSheet()
    {
        Gate::authorize('general.chart.import');
        return view('selling_chart.import');
    }

    public function getSizeRange($lookup_id)
    {
        $department_id = $lookup_id;

        // $sizes = $this->getSizes($lookup_id);
        $sizes = collect();
        $lookupData = $this->sellingChartApiService->getLookupResponse([13]);
        $ranges = collect($lookupData)->map(fn($item) => (object) $item);
        return view('selling_chart.color-table', compact('sizes', 'ranges', 'department_id'))->render();
    }

    public function getColorBySearch($searchTerm)
    {

        $productColors = [];
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = $this->sellingChartApiService->get(config('enox.endpoints.selling_chart_color_by_search'), [
                'search' => $searchTerm
            ]);
            if ($response->failed()) {
                throw new Exception('API request failed');
            }

            $colors = collect($response->json('data.colors', []));

            $productColors = $colors->map(function ($color) {
                return [
                    'id'   => $color['id'],
                    'name' => $color['lookup_name']['name'],
                    'code' => $color['code'],
                ];
            })->values()->all();
        } catch (Exception $e) {
            Log::error('Selling_chart Colors API Error', [
                'message' => $e->getMessage(),
            ]);
        }

        return view('selling_chart.color-list', compact('productColors'))->render();
    }

    public function getDepWiseCats(int|string $id)
    {
        $categories = collect($this->sellingChartApiService->getCategoryResponse())->where('lookup_id', $id)->map(fn($item) => (object) $item);

        return response()->json($categories);
    }

    public function getSizes($lookup_id)
    {
        if (!($lookup_id == 1928 || $lookup_id == 1929)) $lookup_id = 0;

        $categories = collect($this->sellingChartApiService->getCategoryResponse())
            ->where('type', 1)
            ->where('lookup_id', $lookup_id)
            ->map(fn($item) => (object) $item);

        $bgCatIds = $categories->pluck('id')->toArray();

        $sizes = collect();
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = $this->sellingChartApiService->get(config('enox.endpoints.selling_chart_sizes_by_category'), [
                'category_ids' => $bgCatIds
            ]);

            if ($response->failed()) {
                throw new Exception('API request failed');
            }

            $sizes = $response->json('data.sizes', collect())->map(fn($item) => (object) $item);
        } catch (Exception $e) {
            Log::error('Selling_chart sizes API Error', [
                'message' => $e->getMessage(),
            ]);
        }

        return $sizes;
    }

    public function storeCommonData($request): array
    {
        // $data['departments'] = collect($lookupData)->where('type_id', 1)->map(fn($item) => (object) $item);
        //     $data['fabrics'] = collect($lookupData)->where('type_id', 5)->map(fn($item) => (object) $item);
        //     $data['initialRepeats'] = collect($lookupData)->where('type_id', 8)->map(fn($item) => (object) $item);
        //     $data['seasons'] = collect($lookupData)->where('type_id', 10)->map(fn($item) => (object) $item);
        //     $data['seasons_phases'] = collect($lookupData)->where('type_id', 11)->map(fn($item) => (object) $item);


        //     // 2️⃣ Product Categories
        //     $getCategoryData = $this->getCategoryResponse();
        //     $data['selling_chart_cats'] = $getCategoryData->map(fn($item) => (object) $item);

        $get_datas = $this->sellingChartApiService->getCommonData();

        if ($request->department_id) {
            $data['department'] = $get_datas['departments']->where('id', $request->department_id)
                ->firstOrFail();
        }

        $data['season'] = $get_datas['seasons']->where('id', $request->season_id)
            ->firstOrFail();

        $data['seasons_phase'] = $get_datas['seasons_phases']->where('id', $request->season_phase_id)
            ->firstOrFail();

        $data['initialRepeat'] = $get_datas['initialRepeats']->where('id', $request->order_type_id)
            ->firstOrFail();

        $data['fabrication'] = $get_datas['fabrics']->where('id', $request->fabrication)
            ->firstOrFail();

        if ($request->category_id) {
            $data['category'] = $get_datas['selling_chart_cats']->where('id', $request->category_id)->firstOrFail();
        }

        $data['mini_category'] = SellingChartType::findOrFail($request->mini_category);

        return $data;
    }

    public function getColorSizeRange($size_id = null, $range_id = null)
    {
        $sizeId = null;
        $sizeName = null;
        $rangeId = null;
        $rangeName = null;

        if ($range_id != null) {
            // $size = LookupName::findOrFail($size_id);
            $lookupData = $this->sellingChartApiService->getLookupResponse([13]);
            $ranges = collect($lookupData)->map(fn($item) => (object) $item);
            $range = $ranges->where('id', $range_id)->firstOrFail();
            // $sizeId = $size->id;
            // $sizeName = $size->name;
            $rangeId = $range->id;
            $rangeName = $range->name;
        }

        return compact('sizeId', 'sizeName', 'rangeId', 'rangeName');
    }

    // public function getMinMaxSize($min_size = null, $max_size = null)
    // {
    //     $minSizeId = null;
    //     $maxSizeId = null;
    //     $minSizeName = null;
    //     $maxSizeName = null;

    //     if ($min_size != null && $max_size != null) {
    //         $minSize = LookupName::findOrFail($min_size);
    //         $maxSize = LookupName::findOrFail($max_size);
    //         $minSizeId = $minSize->id;
    //         $minSizeName = $minSize->name;
    //         $maxSizeId = $maxSize->id;
    //         $maxSizeName = $maxSize->name;
    //     }

    //     return compact('minSizeId', 'maxSizeId', 'minSizeName', 'maxSizeName');
    // }

    public function store(Request $request)
    {

        $request->validate(
            [
                'department_id' => 'required',
                'season_id' => 'required',
                'season_phase_id' => 'required',
                'order_type_id' => 'required',
                'product_launch_month' => 'required',
                'category_id' => 'required',
                'mini_category' => 'required',
                'product_code' => 'required',
                'design_no' => 'required',
                'product_description' => 'required',
                'fabrication' => 'required',
                'image' => 'nullable|image',
                'color_code.*' => 'required',
                'po_order_qty.*' => 'required',
                'price_fob.*' => 'required',
                'unit_price.*' => 'required',
                'design_image' => 'nullable|image',
            ]
        );

        try {
            DB::beginTransaction();
            $filename = null;
            $filename1 = null;
            $imageUrl = null;
            $imageDesignUrl = null;
            $storeCommonData = $this->storeCommonData($request);
            $productDescription = ucwords(strtolower($request->product_description));
            $paths = [];

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = 'img_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('upload/selling_images'), $filename);
                $imageUrl = 'upload/selling_images/' . $filename;
                $paths[] = $imageUrl;

                if (app()->environment('production')) {
                    CloudflareFileUploadJob::dispatch($imageUrl);
                }
            }

            if ($request->hasFile('design_image')) {
                $file1 = $request->file('design_image');
                $filename1 = 'design_image_' . time() . '.' . $file1->getClientOriginalExtension();
                $file1->move(public_path('upload/selling_design_images'), $filename1);
                $imageDesignUrl = 'upload/selling_design_images/' . $filename1;
                $paths[] = $imageDesignUrl;

                if (app()->environment('production')) {
                    CloudflareFileUploadJob::dispatch($imageDesignUrl);
                }
            }

            $basic_info = SellingChartBasicInfo::create(attributes: [
                'department_id' => $storeCommonData['department']->id,
                'department_name' => $storeCommonData['department']->name,
                'season_id' => $storeCommonData['season']->id,
                'season_name' => $storeCommonData['season']->name,
                'phase_id' => $storeCommonData['seasons_phase']->id,
                'phase_name' => $storeCommonData['seasons_phase']->name,
                'initial_repeated_id' => $storeCommonData['initialRepeat']->id,
                'initial_repeated_status' => $storeCommonData['initialRepeat']->name,
                'product_launch_month' => $request->product_launch_month,
                'category_id' => $storeCommonData['category']->id,
                'category_name' => $storeCommonData['category']->name,
                'mini_category' => $storeCommonData['mini_category']->id,
                'mini_category_name' => $storeCommonData['mini_category']->name,
                'product_code' => $request->product_code,
                'design_no' => $request->design_no,
                'product_description' => $productDescription,
                'fabrication_id' => $storeCommonData['fabrication']->id,
                'fabrication' => $storeCommonData['fabrication']->name,
                'inspiration_image' => $filename ?? null,
                'design_image' => $filename1 ?? null,
            ]);

            foreach (array_keys($request->color_code) as $key) {
                $sizeRangeD = $this->getColorSizeRange(
                    size_id: null,
                    range_id: $request->range_id[$key] ?? null
                );

                SellingChartPrice::create(attributes: [
                    'basic_info_id' => $basic_info->id,
                    'color_id' => $request->color_id[$key] ?? null,
                    'color_code' => $request->color_code[$key] ?? null,
                    'color_name' => $request->color_name[$key] ?? null,
                    'size_id' => null,
                    'size' => null,
                    'range_id' => $sizeRangeD['rangeId'] ?? null,
                    'range' => $sizeRangeD['rangeName'] ?? null,
                    'po_order_qty' => $request->po_order_qty[$key] ?? null,
                    'price_fob' => $request->price_fob[$key] ?? null,
                    'unit_price' => $request->unit_price[$key] ?? null,
                ]);
            }

            DB::commit();
            notify()->success("Selling chart created successfully.", "Success");
            return redirect()->route('admin.selling_chart.index');
        } catch (\Throwable $th) {
            DB::rollback();
            notify()->error("Selling chart create failed.", "Error");
            Log::error('Selling Chart create failed', [
                'message'   => $th->getMessage()
            ]);
            return back();
        }
    }

    public function edit(string | int $id)
    {
        Gate::authorize('general.chart.edit');
        $data = $this->sellingChartApiService->getCommonData();
        $data['chartInfo'] = SellingChartBasicInfo::withCount('sellingChartPrices')->findOrFail($id);
        // $data['sizes'] = $this->getSizes($data['chartInfo']->department_id);
        $lookupData = $this->sellingChartApiService->getLookupResponse([13]);
        $data['ranges'] = collect($lookupData)->map(fn($item) => (object) $item);
        Session::put('backUrl', url()->previous());

        return view('selling_chart.edit', $data);
    }

    public function update(Request $request, $id)
    {

        $request->validate(
            [
                'season_id' => 'required',
                'season_phase_id' => 'required',
                'order_type_id' => 'required',
                'product_launch_month' => 'required',
                // 'category_id' => 'required',
                'mini_category' => 'required',
                'product_code' => 'required',
                'design_no' => 'required',
                'product_description' => 'required',
                'fabrication' => 'required',
                'image' => 'nullable|image',
                'color_code.*' => 'required',
                'po_order_qty.*' => 'required',
                'price_fob.*' => 'required',
                'unit_price.*' => 'required',
                'design_image' => 'nullable|image',
            ]
        );

        try {
            DB::beginTransaction();
            $fileUrl = null;
            $fileUrl2 = null;
            $storeCommonData = $this->storeCommonData($request);
            $basic_info = SellingChartBasicInfo::findOrFail($id);
            $filePath = public_path('upload/selling_images/' . $basic_info->inspiration_image);
            $filePath2 = public_path('upload/selling_design_images/' . $basic_info->design_image);
            $paths = [];

            if ($request->hasFile('image')) {
                if ($basic_info->inspiration_image) {
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }

                    if (app()->environment('production')) {
                        $fileUrl = 'upload/selling_images/' . $basic_info->inspiration_image;
                        CloudflareFileDeleteJob::dispatch(basename($fileUrl));
                    }
                }

                $file = $request->file('image');
                $filename = 'img_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('upload/selling_images'), $filename);
                $imageUrl = 'upload/selling_images/' . $filename;
                $paths[] = $imageUrl;

                if (app()->environment('production')) {
                    CloudflareFileUploadJob::dispatch($imageUrl);
                }
            }

            if ($request->hasFile('design_image')) {
                if ($basic_info->design_image) {
                    if (file_exists($filePath2)) {
                        unlink($filePath2);
                    }

                    if (app()->environment('production')) {
                        $fileUrl2 = 'upload/selling_design_images/' . $basic_info->design_image;
                        CloudflareFileDeleteJob::dispatch(basename($fileUrl2));
                    }
                }

                $file1 = $request->file('design_image');
                $filename1 = 'design_image_' . time() . '.' . $file1->getClientOriginalExtension();
                $file1->move(public_path('upload/selling_design_images'), $filename1);
                $imageDesignUrl = 'upload/selling_design_images/' . $filename1;
                $paths[] = $imageDesignUrl;

                if (app()->environment('production')) {
                    CloudflareFileUploadJob::dispatch($imageDesignUrl);
                }
            }

            $basic_info->season_id = $storeCommonData['season']->id;
            $basic_info->season_name = $storeCommonData['season']->name;
            $basic_info->phase_id = $storeCommonData['seasons_phase']->id;
            $basic_info->phase_name = $storeCommonData['seasons_phase']->name;
            $basic_info->initial_repeated_id = $storeCommonData['initialRepeat']->id;
            $basic_info->initial_repeated_status = $storeCommonData['initialRepeat']->name;
            $basic_info->product_launch_month = $request->product_launch_month;
            // $basic_info->category_id = $storeCommonData['category']->id;
            // $basic_info->category_name = $storeCommonData['category']->name;
            $basic_info->mini_category = $storeCommonData['mini_category']->id;
            $basic_info->mini_category_name = $storeCommonData['mini_category']->name;
            $basic_info->product_code = $request->product_code;
            $basic_info->design_no = $request->design_no;
            $basic_info->product_description = ucwords(strtolower($request->product_description));
            $basic_info->fabrication_id = $storeCommonData['fabrication']->id;
            $basic_info->fabrication = $storeCommonData['fabrication']->name;

            if ($request->hasFile('image')) {
                $basic_info->inspiration_image = $filename;
            }

            if ($request->hasFile('design_image')) {
                $basic_info->design_image = $filename1;
            }

            $basic_info->save();

            // Remove SellingChartPrice
            SellingChartPrice::where('basic_info_id', $basic_info->id)
                ->whereNotIn('id', $request->price_id)
                ->delete();
            // Remove SellingChartPrice

            foreach (array_keys($request->price_id) as $key) {
                $sizeRangeD = $this->getColorSizeRange(
                    size_id: null,
                    range_id: $request->range_id[$key] ?? null
                );

                SellingChartPrice::updateOrCreate(
                    [
                        'basic_info_id' => $basic_info->id,
                        'id' => $request->price_id[$key]
                    ],
                    [
                        'color_id' => $request->color_id[$key],
                        'color_code' => $request->color_code[$key],
                        'color_name' => $request->color_name[$key],
                        'size_id' => null,
                        'size' => null,
                        'range_id' => $sizeRangeD['rangeId'],
                        'range' => $sizeRangeD['rangeName'],
                        'po_order_qty' => $request->po_order_qty[$key],
                        'price_fob' => $request->price_fob[$key],
                        'unit_price' => $request->unit_price[$key],
                    ]
                );
            }

            DB::commit();
            notify()->success("Selling chart updated successfully.", "Success");
            return redirect(session('backUrl'));
        } catch (\Throwable $th) {
            DB::rollback();
            notify()->error("Selling chart update failed.", "Error");
            Log::error('Selling Chart update failed', [
                'message'   => $th->getMessage()
            ]);
            return redirect(session('backUrl'));
        }
    }

    public function destroy(string | int $id)
    {
        Gate::authorize('general.chart.delete');
        try {
            DB::beginTransaction();
            $basic_info = SellingChartBasicInfo::with('sellingChartPrices')->find($id);
            $filePath = public_path('upload/selling_images/' . $basic_info->inspiration_image);
            $filePath2 = public_path('upload/selling_design_images/' . $basic_info->design_image);
            $fileUrl = null;
            $fileUrl2 = null;

            if (!empty($basic_info->sellingChartPrices)) {
                foreach ($basic_info->sellingChartPrices as $ch_price) {
                    $ch_price->delete();
                }
            }

            if ($basic_info->inspiration_image) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                if (app()->environment('production')) {
                    $fileUrl = 'upload/selling_images/' . $basic_info->inspiration_image;
                    CloudflareFileDeleteJob::dispatch(basename($fileUrl));
                }
            }

            if ($basic_info->design_image) {
                if (file_exists($filePath2)) {
                    unlink($filePath2);
                }

                if (app()->environment('production')) {
                    $fileUrl2 = 'upload/selling_design_images/' . $basic_info->design_image;
                    CloudflareFileDeleteJob::dispatch(basename($fileUrl2));
                }
            }

            $basic_info->delete();

            DB::commit();
            notify()->success("Selling chart deleted successfully.", "Success");
            return redirect()->back();
        } catch (\Throwable $th) {
            DB::rollback();
            notify()->error("Selling chart delete failed.", "Error");
            Log::error('Selling Chart delete failed', [
                'message'   => $th->getMessage()
            ]);
            return back();
        }
    }

    public function bulkEdit(Request $request): RedirectResponse|View
    {
        Gate::authorize('general.chart.bulk_edit');
        if (!$request->department_id) {
            notify()->error("Please select department.", "Error");
            return redirect()->back();
        }

        $data['chartInfos'] = SellingChartBasicInfo::filter($request)
            ->with(['sellingChartPrices'])
            ->get();

        $data['expenses'] = SellingChartExpense::where('status', 1)
            ->get();

        if (!$data['chartInfos']->isEmpty()) {
            // $data['sizes'] = $this->getSizes($data['chartInfos'][0]['department_id']);
            $lookupData = $this->sellingChartApiService->getLookupResponse([13]);
            $data['ranges'] = collect($lookupData)->map(fn($item) => (object) $item);
        }

        Session::put('backUrl', url()->previous());
        return view('selling_chart.bulk-edit', $data);
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate(
            [
                'po_order_qty.*' => 'required',
                'price_fob.*' => 'required',
                'unit_price.*' => 'required'

            ]
        );

        try {

            if ($request->price_id) {
                DB::beginTransaction();
                foreach ($request->price_id_all as $index => $value) {
                    $has = array_search($value, $request->price_id);
                    $sellingChartPrice = SellingChartPrice::find($value);

                    // If the index is found, proceed with the update
                    if ($has !== false) {
                        $sizeRangeD = $this->getColorSizeRange(
                            size_id: null,
                            range_id: $request->range_id[$index] ?? null
                        );
                        $sellingChartPrice->update([
                            'size_id' => null,
                            'size' => null,
                            'range_id' => $sizeRangeD['rangeId'],
                            'range' => $sizeRangeD['rangeName'],
                            'po_order_qty' => $request->po_order_qty[$index],
                            'price_fob' => $request->price_fob[$index],
                            'unit_price' => $request->unit_price[$index],
                            'product_shipping_cost' => $request->shipping_cost[$index],
                            'confirm_selling_price' => $request->confirm_selling_price[$index],
                            'vat_price' => $request->seling_vat[$index],
                            'vat_value' => $request->seling_vat_value[$index],
                            'profit_margin' => $request->profit_margin[$index],
                            'net_profit' => $request->net_profit[$index],
                            'discount' => $request->discount[$index],
                            'discount_selling_price' => $request->discount_selling_price[$index],
                            'discount_vat_price' => $request->selling_vat_dedact_price[$index],
                            'discount_vat_value' => $request->discount_vat_value[$index],
                            'discount_profit_margin' => $request->discount_profit_margin[$index],
                            'discount_net_profit' => $request->discount_net_profit[$index],
                        ]);
                    }
                }
            } else {
                notify()->error("Don't select any item.", "Error");
                return redirect(session('backUrl'));
            }

            DB::commit();
            notify()->success("Selling prices Updated Successfully.", "Success");
            return redirect(session('backUrl'));
        } catch (\Throwable $th) {
            DB::rollback();
            notify()->error("Selling prices Update Failed.", "Error");
            Log::error('Selling prices update failed', [
                'message'   => $th->getMessage()
            ]);
            return back();
        }
    }

    public function viewSingleChart($id)
    {
        $data['chartInfo'] = SellingChartBasicInfo::with('sellingChartPrices')->findOrFail($id);
        $designNumber = $data['chartInfo']->design_no;

        $ecommerceProducts = $this->sellingChartApiService->getEcomProducts([
            'designNos' => [$designNumber]
        ]);

        $data['skus'] = $ecommerceProducts->first();

        $data['approveBtnAccess'] = SellingChartBasicInfo::sellingApprovedBtnAccess();


        $html = view('selling_chart.view-item', $data)->render();
        return response()->json(['status' => true, 'data' => $html]);
    }
}
