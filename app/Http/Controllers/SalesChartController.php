<?php

namespace App\Http\Controllers;

use App\Models\SellingChartBasicInfo;
use App\Models\SellingChartType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class SalesChartController extends Controller
{
   public function index(Request $request)
    {

        $action = $request->input('action');

        if ($action == 'excel') return $this->exportReport($request);

        if ($action == 'bulkEdit') return $this->bulkEdit($request);

        $data = $this->getCommonData();

        // colors count calculation start
        // $data['mini_total_colors'] = SellingChartBasicInfo::filter($request)
        //     ->select('mini_category')
        //     ->selectRaw('COUNT(selling_chart_prices.id) as total_count')
        //     ->join('selling_chart_prices', 'selling_chart_basic_infos.id', '=', 'selling_chart_prices.basic_info_id')
        //     ->with('miniCategory')
        //     ->groupBy('mini_category')
        //     ->get();

        // style count calculation start
        $data['mini_total_styles'] = SellingChartBasicInfo::filter($request)
            ->select('mini_category')
            ->selectRaw('COUNT(id) as total_count')
            ->with('miniCategory')
            ->groupBy('mini_category')
            ->get();

        // Step 1: Get all distinct mini categories
        // $miniCategories = SellingChartBasicInfo::distinct('mini_category')->pluck('mini_category');
        $miniCategories = SellingChartType::orderBy('id', 'asc')->pluck('name', 'id');

        // Step 2: Get counts grouped by department and mini category
        $counts = SellingChartBasicInfo::filter($request)
            ->select('department_id', 'department_name', 'mini_category')
            ->leftJoin('selling_chart_prices', 'selling_chart_basic_infos.id', '=', 'selling_chart_prices.basic_info_id')
            ->groupBy('department_id', 'mini_category')
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

        $designNos = $data['chartInfos']->pluck('design_no')->unique();

        $ecommerceProducts = EcommerceProduct::whereHas('style', function ($query) use ($designNos) {
            $query->whereIn('name', $designNos);
        })->with('style')->get();

        $data['ecommerceMap'] = $ecommerceProducts->keyBy(fn($item) => $item?->style?->name);

        $data['start'] = ($data['chartInfos']->currentPage() - 1) * $data['chartInfos']->perPage() + 1;

        return view('backend.selling_chart.index', $data);
    }

    public function approve(Request $request, int | string $id)
    {
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
            return back();
        }
    }

    public function exportReport(Request $request)
    {
        $chartInfos = SellingChartBasicInfo::filter($request)
            ->with(['sellingChartPrices'])
            ->get();
        return Excel::download(new SellingChartExport($chartInfos), 'selling_chart_reports.xlsx');
    }

    public function import(Request $request)
    {

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
            return back();
        }
    }
    public function create(): View
    {

        $data = $this->getCommonData();
        $data['expenses'] = SellingChartExpense::where('status', 1)->get();

        return view('backend.selling_chart.create', $data);
    }

    public function uploadSheet()
    {

        return view('backend.selling_chart.import');
    }

    public function getCommonData(): array
    {
        $departmentId = LookupType::where('name', 'Department')
            ->whereStatus(1)
            ->first()?->id ?? 1;

        $seasonId = LookupType::where('name', 'Season')
            ->whereStatus(1)
            ->first()?->id ?? 10;

        $seasonPhaseId = LookupType::where('name', 'Season Phase')
            ->whereStatus(1)
            ->first()?->id ?? 11;


        // $data['departments'] = LookupName::where('type_id', $departmentId)
        //     ->select('id', 'name')
        //     ->whereStatus(1)
        //     ->get();
        $data['departments'] = LookupName::getDepartments();

        $data['seasons'] = LookupName::where('type_id', $seasonId)
            ->select('id', 'name')
            ->whereStatus(1)
            ->get();

        $data['seasons_phases'] = LookupName::where('type_id', $seasonPhaseId)
            ->select('id', 'name')
            ->whereStatus(1)
            ->get();

        $data['initialRepeats'] = LookupName::where('type_id', 8)->where('status', 1)->get();
        $data['fabrics'] = LookupName::where('type_id', 5)->where('status', 1)->get();

        $data['selling_chart_cats'] = ProductCategory::get();
        $data['selling_chart_types'] = SellingChartType::get();
        // dd($data['sizes']->toArray()[0]);

        return $data;
    }

    public function getSizeRange($lookup_id)
    {

        $department_id = $lookup_id;

        $sizes = $this->getSizes($lookup_id);
        $ranges = LookupName::where('type_id', 13)->where('status', 1)->get();
        return view('backend.selling_chart.color-table', compact('sizes', 'ranges', 'department_id'))->render();
    }

    public function getColorBySearch($searchTerm)
    {
        $colors = ProductColor::select('id', 'lookup_id', 'code')
            ->with('lookupName:id,name')
            ->where('status', 1)
            ->where(function ($query) use ($searchTerm) {
                $query->whereHas('lookupName', function ($query) use ($searchTerm) {
                    $query->where('name', 'like', '%' . $searchTerm . '%');
                })
                    ->orWhere('code', 'like', '%' . $searchTerm . '%');
            })
            ->get()
            ->take(100);

        $productColors = [];
        foreach ($colors as $color) {
            $productColors[] = [
                'id' => $color->id,
                'name' => $color->lookupName->name,
                'code' => $color->code,
            ];
        }
        return view('backend.selling_chart.color-list', compact('productColors'))->render();
    }

    public function getSizes($lookup_id)
    {
        if (!($lookup_id == 1928 || $lookup_id == 1929)) $lookup_id = 0;

        $bgCatIds = ProductCategory::where('type', 1)
            ->where('lookup_id', $lookup_id)->pluck('id')->toArray();

        $sizes = ProductSize::whereIn('product_category_id', $bgCatIds)
            ->with('lookupName')
            ->groupBy('lookup_id')
            ->orderBy('lookup_id', 'asc')
            // ->take(11)
            ->get();

        return $sizes;
    }

    public function storeCommonData($request): array
    {
        if ($request->department_id) {
            $data['department'] = LookupName::where('type_id', 1)
                ->select('id', 'name')
                ->whereStatus(1)
                ->findOrFail($request->department_id);
        }

        $data['season'] = LookupName::where('type_id', 10)
            ->select('id', 'name')
            ->whereStatus(1)
            ->findOrFail($request->season_id);

        $data['seasons_phase'] = LookupName::where('type_id', 11)
            ->select('id', 'name')
            ->whereStatus(1)
            ->findOrFail($request->season_phase_id);

        $data['initialRepeat'] = LookupName::where('type_id', 8)
            ->select('id', 'name')
            ->whereStatus(1)
            ->findOrFail($request->order_type_id);

        $data['fabrication'] = LookupName::where('type_id', 5)
            ->select('id', 'name')
            ->whereStatus(1)
            ->findOrFail($request->fabrication);

        if ($request->category_id) {
            $data['category'] = ProductCategory::findOrFail($request->category_id);
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
            $range = LookupName::findOrFail($range_id);
            // $sizeId = $size->id;
            // $sizeName = $size->name;
            $rangeId = $range->id;
            $rangeName = $range->name;
        }

        return compact('sizeId', 'sizeName', 'rangeId', 'rangeName');
    }

    public function getMinMaxSize($min_size = null, $max_size = null)
    {
        $minSizeId = null;
        $maxSizeId = null;
        $minSizeName = null;
        $maxSizeName = null;

        if ($min_size != null && $max_size != null) {
            $minSize = LookupName::findOrFail($min_size);
            $maxSize = LookupName::findOrFail($max_size);
            $minSizeId = $minSize->id;
            $minSizeName = $minSize->name;
            $maxSizeId = $maxSize->id;
            $maxSizeName = $maxSize->name;
        }

        return compact('minSizeId', 'maxSizeId', 'minSizeName', 'maxSizeName');
    }

    public function store(Request $request): RedirectResponse
    {

        $this->validate(
            $request,
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
            return back();
        }
    }

    public function edit(string | int $id): View
    {

        $data = $this->getCommonData();
        $data['chartInfo'] = SellingChartBasicInfo::withCount('sellingChartPrices')->findOrFail($id);
        $data['sizes'] = $this->getSizes($data['chartInfo']->department_id);
        $data['ranges'] = LookupName::where('type_id', 13)->where('status', 1)->get();
        Session::put('backUrl', url()->previous());

        return view('backend.selling_chart.edit', $data);
    }

    public function update(Request $request, $id): RedirectResponse
    {

        $this->validate(
            $request,
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
            return redirect(session('backUrl'));
        }
    }

    public function destroy(string | int $id): RedirectResponse
    {

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
            return back();
        }
    }

    public function bulkEdit(Request $request): RedirectResponse|View
    {
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
            $data['sizes'] = $this->getSizes($data['chartInfos'][0]['department_id']);
            $data['ranges'] = LookupName::where('type_id', 13)->where('status', 1)->get();
        }

        Session::put('backUrl', url()->previous());
        return view('backend.selling_chart.bulk-edit', $data);
    }

    public function bulkUpdate(Request $request)
    {
        $this->validate(
            $request,
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
            return back();
        }
    }

    public function viewSingleChart($id)
    {
        $data['chartInfo'] = SellingChartBasicInfo::with('sellingChartPrices')->findOrFail($id);
        $designNumber = $data['chartInfo']->design_no;

        $data['skus'] = EcommerceProduct::whereHas('style', function ($query) use ($designNumber) {
            $query->where('name', $designNumber);
        })->first();
        $data['approveBtnAccess'] = SellingChartBasicInfo::sellingApprovedBtnAccess();


        $html = view('backend.selling_chart.view-item', $data)->render();
        return response()->json(['status' => true, 'data' => $html]);
    }
}
