<?php

namespace App\Imports;

use App\ApiServices\FabricationService;
use App\ApiServices\SellingChartApiService;
use App\Models\SellingChartBasicInfo;
use App\Models\SellingChartPrice;
use App\Models\SellingChartType;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class SellingChartImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    protected $basic_info_id = null;
    protected $size_range_p = 0;
    protected $sellingChartApiService;
    protected $fabricationService;

    public function __construct()
    {
        $this->sellingChartApiService = app(SellingChartApiService::class);
        $this->fabricationService = app(FabricationService::class);
    }

    public function collection(Collection $rows)
    {
        $rows = $rows->toArray();
        $datas = [];

        foreach ($rows as $key => $row) {

            if ($key > 3) {
                $infoUp = 1;
                $infos = [];
                $prices = [];

                foreach ($row as $col_key => $col) {

                    $col = trim($col);
                    $col_key = trim($col_key);


                    // if ($col_key == 'size_range') {
                    //     $existsTo = strpos($col, 'to') !== false;
                    //     if ($existsTo) {
                    //         $size_range_text = str_replace(" yrs", "", strtolower($col));
                    //         $size_range_text = trim(str_replace(" to", "", $size_range_text));

                    //         $parts = explode(' ', $size_range_text);

                    //         $prices['min_size_range'] = $parts[0];
                    //         $prices['max_size_range'] = $parts[1];
                    //     } else {
                    //         $size_range_text = trim(str_replace("mnths", "", $col));
                    //         $prices['min_size_range'] = $size_range_text;
                    //         $prices['max_size_range'] = $size_range_text;
                    //     }
                    //     if ($this->size_range_p == 0)
                    //         $this->size_range_p = 1;
                    // }

                    if ($col_key == 'size_range') {
                        // $col = strtolower($col);
                        // $existsY = strpos($col, 'years') !== false;
                        // if ($existsY) {
                        //     $size_text = trim(str_replace("years", "", $col));;
                        //     $col = $size_text;
                        // } else {
                        //     $size_text = trim(str_replace("month", "", $col));;
                        //     $col = $size_text;
                        // }
                        if ($this->size_range_p == 0)
                            $this->size_range_p = 1;
                    }

                    // discount & Profit Margin % (In Factory FOB)
                    if ($col_key == "profit_margin_in_factory_fob" || $col_key == "discount_profit_margin_in_factory_fob" || $col_key == 'discount')
                        $col = $col * 100;

                    if (is_numeric($col) && $col_key != "po_order_qty" && $col_key != 'product_code')
                        $col = number_format($col, 2);

                    // discount-selling-price
                    if ($col_key == 'discount_selling_price_gbp_ps')
                        $col = (int)round($col);

                    // $datas[$key][$col_key] = $col;



                    if ($col_key == 'color_code')
                        $infoUp = 0;

                    if ($infoUp == 1 && $col_key != "style_count" && $row['style_count'] != null) {
                        $infos[$col_key] = $col;
                        Session::flash('import_msg', "Please Check Excel Row Number: " . $row['style_count']);
                    } else {
                        if ($infoUp == 0) {
                            if ($col_key == 'po_order_qty' && $row['style_count'] != null)
                                $quantity = $col;

                            if ($col_key == 'po_order_qty') {
                                $prices[$col_key] = $quantity ?? 0;
                            } else {
                                $prices[$col_key] = $col;
                            }
                        }
                    }

                    if ($col_key == 'discount_net_profit')
                        break;
                }
                $this->saveChartInfos($infos, $prices);
            }
        }
        // dd($datas);
    }

    protected function flashMsg($m_value, $value)
    {
        if (!$m_value) {
            Session::flash('in_value', "Incorrect Data Formate: " . $value);
            throw new Exception("Incorrect Data Formate: ", $value);
        }
    }

    protected function saveChartInfos($infos, $prices)
    {
        if (!empty($infos)) {
            $get_datas = $this->sellingChartApiService->getCommonData();


            $departmentText = $this->departmentNormalize($infos['department']);
            $department = $get_datas['departments']->where('name', $departmentText)
                ->first();
            $this->flashMsg($department, $infos['department']);

            $season = $get_datas['seasons']->where('name', $infos['season'])
                ->first();
            $this->flashMsg($season, $infos['season']);

            // $seasons_phase = $get_datas['seasons_phases']->whereRaw('LEFT(name, 2) = ?', [substr($infos['season_phase'], 0, 2)])
            //     ->first();
            $seasons_phase = $get_datas['seasons_phases']
                ->first(function ($item) use ($infos) {
                    return substr($item->name, 0, 2) === substr($infos['season_phase'], 0, 2);
                });
            $this->flashMsg($seasons_phase, $infos['season_phase']);

            $category = $get_datas['selling_chart_cats']->where('lookup_id', $department->id)->where('name', $infos['product_category'])
                ->first();
            $this->flashMsg($category, $infos['product_category']);

            $mini_category = SellingChartType::where('name', $infos['product'])
                ->first();
            $this->flashMsg($mini_category, $infos['product']);

            $initialRepeatValue = $this->intRepNormalize($infos['initial_repeat_order']);
            $initialRepeat = $get_datas['initialRepeats']->where('name', $initialRepeatValue)
                ->first();
            $this->flashMsg($initialRepeat, $infos['initial_repeat_order']);

            $fabrication = $get_datas['fabrics']->where('name', $infos['fabrication'])
                ->first();
            $fabrication_id = $fabrication?->id;
            $fabrication_name = $fabrication?->name;

            if (!$fabrication) {
                $payload = [
                    'name'   => $infos['fabrication'],
                    'status' => 1,
                ];
                /** @var \Illuminate\Http\Client\Response $response */
                $response = $this->fabricationService->store($payload);
                $fabrication = collect($response->json('data'));
                $fabrication_id = $fabrication['id'];
                $fabrication_name = $fabrication['name'];
            }

            if ($department && $season && $seasons_phase && $category && $mini_category) {
                $basic_info = SellingChartBasicInfo::create(attributes: [
                    'department_id' => $department->id,
                    'department_name' => $department->name,
                    'season_id' => $season->id,
                    'season_name' => $season->name,
                    'phase_id' => $seasons_phase->id,
                    'phase_name' => $seasons_phase->name,
                    'initial_repeated_id' => $initialRepeat->id,
                    'initial_repeated_status' => $initialRepeat->name,
                    'product_launch_month' => $infos['product_launch_month'],
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'mini_category' => $mini_category->id,
                    'mini_category_name' => $mini_category->name,
                    'product_code' => $infos['product_code'],
                    'design_no' => $infos['design_no'],
                    'product_description' => $infos['product_description'],
                    'fabrication_id' => $fabrication_id,
                    'fabrication' => $fabrication_name,
                    'inspiration_image' => null,
                ]);

                $this->basic_info_id = $basic_info->id;
            }
        }

        $this->saveChartPrices($prices, $this->basic_info_id);
    }

    protected function saveChartPrices($price, $basic_info_id)
    {
        $sizeId = null;
        $sizeName = null;
        $rangeId = null;
        $rangeName = null;

        if ($this->size_range_p == 1) {
            // $size = LookupName::where('type_id', 3)
            //     ->where('name', $price["size"])
            //     ->first();
            // $this->flashMsg($size, $price["size"]);
            $lookupData = $this->sellingChartApiService->getLookupResponse([13]);
            $ranges = collect($lookupData)->map(fn($item) => (object) $item);
            $range = $ranges->where('name', $price["size_range"])->first();

            $this->flashMsg($range, $price["size_range"]);

            // $sizeId = $size->id;
            // $sizeName = $size->name;
            $rangeId = $range->id;
            $rangeName = $range->name;
        }


        $color_code = $price["color_code"];
        // $color_name = $this->colorNormalize($price["color_name"]);
        $color_name = $price["color_name"];

        $color = collect();
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = $this->sellingChartApiService->get(config('enox.endpoints.selling_chart_color_by_search'), [
                'color_code' => $color_code,
                'color_name' => $color_name
            ]);
            if ($response->failed()) {
                throw new Exception('API request failed');
            }
            $color = collect($response->json('data.colors', []))->first();
        } catch (Exception $e) {
            Log::error('Selling_chart Colors API Error', [
                'message' => $e->getMessage(),
            ]);
        }

        $color_cn = $color_code . ' or ' . $color_name;

        $this->flashMsg($color, $color_cn);

        SellingChartPrice::create(attributes: [
            'basic_info_id' => $basic_info_id,
            'color_id' => $color['id'],
            'color_code' => $color['code'],
            'color_name' => $color['lookup_name']['name'],
            'size_id' => $sizeId,
            'size' => $sizeName,
            'range_id' => $rangeId,
            'range' => $rangeName,
            'po_order_qty' => $price['po_order_qty'],
            'price_fob' => $price['price_fob'],
            'unit_price' => $price['unit_price_ps_factory_fob_with_enorsia_expence'],
            'product_shipping_cost' => $price['product_shipping_cost'],
            'confirm_selling_price' => $price['confirm_selling_price_gbp_ps'],
            'vat_price' => $price['20_selling_vat_dedact_price_ps'],
            'vat_value' => $price['vat_value_ps'],
            'profit_margin' => $price['profit_margin_in_factory_fob'],
            'net_profit' => $price['net_profit'],
            'discount' => $price['discount'],
            'discount_selling_price' => $price['discount_selling_price_gbp_ps'],
            'discount_vat_price' => $price['20_discount_selling_vat_dedact_price_ps'],
            'discount_vat_value' => $price['discount_vat_value_ps'],
            'discount_profit_margin' => $price['discount_profit_margin_in_factory_fob'],
            'discount_net_profit' => $price['discount_net_profit'],
        ]);
    }

    public function departmentNormalize($input)
    {
        $input = strtolower(trim($input));

        $rules = [
            'women' => ['womens', "women's", 'women'],
            'men' => ['mens', "men's", 'men'],
            'girls' => ['girls', "girl's", 'girl'],
            'boys' => ['boys', "boy's", 'boy'],
        ];

        foreach ($rules as $category => $variations) {
            if (in_array($input, $variations)) {
                return ucfirst($category);
            }
        }

        return ucfirst($input);
    }

    public function sizeNormalize($input)
    {
        $input = strtolower(trim($input));

        $rules = [
            'month' => ['months', "month's", 'month', 'mnths', "mnth's", 'mnth'],
            'years' => ['years', "year's", 'year', 'yrs', "yr's"],
        ];

        foreach ($rules as $category => $variations) {
            if (in_array($input, $variations)) {
                return ucfirst($category);
            }
        }

        return ucfirst($input);
    }

    public function intRepNormalize($input)
    {
        $input = strtolower(trim($input));

        $rules = [
            'Inital' => ['initial', "inital"],
            'Repeat Direct' => ['repeat direct', "repeat", 'repeated'],
            'Repeat CF' => ['repeat cf', 'repeated cf'],
        ];

        foreach ($rules as $category => $variations) {
            if (in_array($input, $variations)) {
                return ucfirst($category);
            }
        }

        return ucfirst($input);
    }

    public function colorNormalize($input)
    {
        $rules = [
            'AOP  Scarlet Ibis' => ['AOP  Scarlet Ibis', "AOP Scarlet Ibis"],
            'Aop15  Absinthe Green' => ['Aop15  Absinthe Green', "Aop15 Absinthe Green"],
            'AOP37  Gull' => ['AOP37  Gull', 'AOP37 Gull'],
            'Aop  American Beauty' => ['Aop  American Beauty', 'Aop American Beauty'],
            'MID  WASH' => ['MID  WASH', 'MID WASH'],
            'MID INDIGO  WASH' => ['MID INDIGO  WASH', 'MID INDIGO WASH'],
        ];

        $normalizedInput = strtolower(trim($input));

        foreach ($rules as $category => $variations) {
            $normalizedVariations = array_map(fn($v) => strtolower(preg_replace('/\s+/', ' ', trim($v))), $variations);

            if (in_array($normalizedInput, $normalizedVariations)) {
                return $category;
            }
        }

        return $input;
    }
}
