<?php

namespace App\Http\Controllers;

use App\ApiServices\SellingChartApiService;
use App\Models\Platform;
use App\Models\SellingChartBasicInfo;
use App\Models\SellingChartExpense;
use App\Services\StyleStockReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $scInfos = $this->loadSellingChartInfos();
        $styleStocks = $this->enrichStyleStocks($result['style_stocks'], $scInfos);

        // dd($styleStocks->toArray()[1926]);

        return view('style_stocks.index', [
            'style_stocks' => $styleStocks,
            'grand_totals' => $this->buildGrandTotals($styleStocks),
            'load_error' => $result['error'],
        ]);
    }

    protected function loadSellingChartInfos(): Collection
    {
        return SellingChartBasicInfo::select('id', 'design_no')->with([
            'sellingChartPrices:id,basic_info_id,range',
            'sellingChartPrices.discounts:id,selling_chart_price_id,platform_id,price',
            'sellingChartPrices.discounts.platform:id,code',
        ])->get()->keyBy('design_no');
    }

    protected function enrichStyleStocks(Collection $styleStocks, Collection $scInfos): Collection
    {
        return $styleStocks->map(function (array $department) use ($scInfos) {
            $department['categories'] = collect($department['categories'] ?? [])
                ->map(function (array $category) use ($scInfos) {
                    $category['products'] = collect($category['products'] ?? [])
                        ->map(function (array $product) use ($scInfos) {
                            $scInfo = $scInfos->get($product['style'] ?? null);
                            $sellingChartData = $this->resolveSellingChartData($scInfo);

                            $product['image_link_full'] = ! empty($product['image_link'])
                                ? preg_replace('#/w=\d+$#', '/public', $product['image_link'])
                                : null;
                            $product['has_selling_chart'] = $sellingChartData['has_selling_chart'];
                            $product['has_discount'] = $sellingChartData['has_discount'];
                            $product['applied_discounts'] = $sellingChartData['applied_discounts'];

                            return $product;
                        })
                        ->all();

                    return $category;
                })
                ->all();

            return $department;
        });
    }

    /**
     * @return array{has_selling_chart: bool, has_discount: bool, applied_discounts: ?array}
     */
    protected function resolveSellingChartData(?SellingChartBasicInfo $scInfo): array
    {
        if (! $scInfo) {
            return [
                'has_selling_chart' => false,
                'has_discount' => false,
                'applied_discounts' => null,
            ];
        }

        return [
            'has_selling_chart' => true,
            'has_discount' => $this->productHasSellingChartDiscount($scInfo),
            'applied_discounts' => $this->buildAppliedDiscounts($scInfo),
        ];
    }

    protected function productHasSellingChartDiscount(?SellingChartBasicInfo $scInfo): bool
    {
        if (! $scInfo) {
            return false;
        }

        return $scInfo->sellingChartPrices
            ?->flatMap->discounts
            ?->contains(fn ($discount) => $discount->price && $discount->platform) ?? false;
    }

    protected function buildAppliedDiscounts(SellingChartBasicInfo $scInfo): ?array
    {
        $firstPrice = $scInfo->sellingChartPrices?->first();

        if (! $firstPrice) {
            return null;
        }

        if ($firstPrice->range) {
            $platformRanges = [];

            foreach ($scInfo->sellingChartPrices as $price) {
                foreach ($price->discounts as $discount) {
                    if (! $discount->price || ! $discount->platform) {
                        continue;
                    }

                    $code = $discount->platform->code;
                    $platformRanges[$code][] = [
                        'range' => $price->range,
                        'price' => $discount->price,
                    ];
                }
            }

            return [
                'has_range' => true,
                'platform_ranges' => $platformRanges,
                'platform_discounts' => [],
            ];
        }

        $platformDiscounts = $firstPrice->discounts
            ->filter(fn ($discount) => $discount->price && $discount->platform)
            ->map(fn ($discount) => [
                'code' => $discount->platform->code,
                'price' => $discount->price,
            ])
            ->values()
            ->all();

        return [
            'has_range' => false,
            'platform_ranges' => [],
            'platform_discounts' => $platformDiscounts,
        ];
    }

    /**
     * @return array{stock: int|float, sold: int|float, stock_percent: int, sold_percent: int}
     */
    protected function buildGrandTotals(Collection $styleStocks): array
    {
        $grandTotalStock = $styleStocks->sum('stock');
        $grandTotalSold = $styleStocks->sum('sold');
        $grandTotal = $grandTotalStock + $grandTotalSold;

        return [
            'stock' => $grandTotalStock,
            'sold' => $grandTotalSold,
            'stock_percent' => $grandTotal > 0 ? round(($grandTotalStock / $grandTotal) * 100) : 0,
            'sold_percent' => $grandTotal > 0 ? round(($grandTotalSold / $grandTotal) * 100) : 0,
        ];
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
