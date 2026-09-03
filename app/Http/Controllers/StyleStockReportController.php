<?php

namespace App\Http\Controllers;

use App\ApiServices\SellingChartApiService;
use App\Models\Platform;
use App\Models\SellingChartBasicInfo;
use App\Models\SellingChartExpense;
use App\Services\StyleStockReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StyleStockReportController extends Controller
{
    public function __construct(
        protected StyleStockReportService $service,
        protected SellingChartApiService $sellingChartApiService,
    ) {}


    public function index(Request $request): View|RedirectResponse|StreamedResponse
    {
        Gate::authorize('ecommerce.wh_stock_in_out.index');

        if ($request->input('action') === 'export_stock_analysis') {
            return $this->export($request);
        }

        $result = $this->service->getReport();

        if (! $result['success']) {
            notify()->error($result['error'], 'Error');
        }
        $result['sc_infos'] = SellingChartBasicInfo::select('id', 'design_no')->with([
            'sellingChartPrices:id,basic_info_id,range',
            'sellingChartPrices.discounts:id,selling_chart_price_id,platform_id,price',
            'sellingChartPrices.discounts.platform:id,code',
        ])->get()->keyBy('design_no');

        return view('style_stocks.index', [
            'style_stocks' => $result['style_stocks'],
            'sc_infos' => $result['sc_infos'],
            'load_error' => $result['error'],
        ]);
    }

    protected function export(Request $request): RedirectResponse|StreamedResponse
    {
        Gate::authorize('ecommerce.wh_stock_in_out.export');

        $result = $this->service->exportReport($request->except(['_token']));

        if (! $result['success'] || $result['body'] === null) {
            notify()->error($result['error'] ?? 'Export failed.', 'Error');

            return redirect()->route('admin.style.stock.index');
        }

        return response()->streamDownload(
            static function () use ($result): void {
                echo $result['body'];
            },
            $result['filename'],
            [
                'Content-Type' => $result['content_type'],
            ],
        );
    }

    public function viewDiscount(Request $request, $style)
    {
        $data['chartInfo'] = SellingChartBasicInfo::with('sellingChartPrices.discounts')->firstWhere('design_no', $style);

        $ecommerceProducts = $this->sellingChartApiService->getEcomProducts([
            'designNos' => [$style]
        ]);

        $data['ecommerceProduct'] = $ecommerceProducts->first();

        $data["platform_ncs"] = Platform::selectedPlatforms();
        $data["platforms"] = Platform::all()->keyBy('code');

        $data['expenseConfig'] = SellingChartExpense::configForSeason($data['chartInfo']->season_name);

        $html = view('selling_chart.discounts.partials.card-edit-panel', $data)->render();

        return response()->json(['status' => true, 'data' => $html]);
    }
}
