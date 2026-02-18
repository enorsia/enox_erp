@extends('master.app')
@push('css')
    @include('selling_chart.css')
@endpush

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Discounts',
            'icon' => 'bi bi-graph-up-arrow',
            'sub_title' => [
                'Manage Selling Chart ' => '',
                'Manage Selling Chart' => route('admin.selling_chart.forecasting'),
            ],
        ])
    </div>

    <form method="get" action="{{ route('admin.selling_chart.discounts') }}">
        <div class="card" id="filterSection">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <div class="filter_close_sec border-bottom d-flex align-items-center justify-content-between">
                            <h4 class="mb-0"><i class="bi bi-sliders"></i>Filter</h4>
                            <button type="button" class="btn btn-outline-secondary advance-btn d-flex"
                                title="Advance Search" data-bs-toggle="collapse" href="#collapseAdvance" role="button"
                                aria-expanded="false" aria-controls="collapseAdvance"><iconify-icon
                                    icon="solar:card-search-broken" class="fs-25"></iconify-icon></button>
                        </div>
                    </div>
                    <div class="col-12">
                        <input type="hidden" name="advance_search" id="advance_search"
                            value="{{ request('advance_search', 0) }}">
                        <div class="advance-search collapse {{ request('advance_search') ? 'show' : '' }}"
                            id="collapseAdvance">
                            <div class="row">
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="form-group mb-2 new_select_field new_same_item">
                                        <select id="department_select" name="department_id" class=" form-control"
                                            data-choices>
                                            <option value="">Select Department</option>
                                            @foreach ($departments as $department)
                                                <option value="{{ $department->id }}"
                                                    {{ request('department_id') == $department->id ? 'selected' : '' }}>
                                                    {{ $department->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="form-group mb-2 new_select_field new_same_item">
                                        <select id="product_category" name="product_category_id" class=" form-control"
                                            data-choices>
                                            <option value="">Select a Product Category</option>
                                            @if (request('department_id'))
                                                @foreach ($selling_chart_cats->where('lookup_id', request('department_id')) as $selling_chart_cat)
                                                    <option value="{{ $selling_chart_cat->id }}"
                                                        {{ request('product_category_id') == $selling_chart_cat->id ? 'selected' : '' }}>
                                                        {{ $selling_chart_cat->name }}
                                                    </option>
                                                @endforeach
                                            @endif

                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="position-relative form-group mb-2 new_search">
                                        <div class="new_select_field new_same_item d-flex flex-wrap">
                                            <select id="product_mini_category" name="mini_category" class=" form-control"
                                                data-choices>
                                                <option value="">Select Mini Category</option>
                                                @foreach ($selling_chart_types as $selling_chart_type)
                                                    <option value="{{ $selling_chart_type->id }}"
                                                        {{ request('mini_category') == $selling_chart_type->id ? 'selected' : '' }}>
                                                        {{ $selling_chart_type->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-5 col-md-4">
                        <div class="form-group new_search new_same_item ">
                            <input class="form-control" id="search_id" type="text" name="name"
                                placeholder="Search here.. " value="{{ request('name') }}">
                        </div>
                    </div>
                    <div class="col-6 col-sm-7 col-md-8 text-end mt-2 mt-sm-0">
                        <div class="flex-center">
                            <a href="{{ route('admin.selling_chart.discounts') }}"
                                class="btn btn-outline-danger flex-center mx-1 mb-1 mb-md-0"><i
                                    class="bi bi-arrow-clockwise ms-0"></i>
                                Reset</a>
                            <button type="submit" class="btn btn-primary mx-1 mb-1 mb-md-0"><i class="fa fa-filter ms-0"
                                    aria-hidden="true"></i>
                                Search</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if ($errors->any())
        <div class="alert alert-danger my-3">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card-dark main-card my-3 card p-0" id="selling_chart_view_table">
        <div class="card-body">
            <div class="new_search" id="selling_chart_table">
                <div class="selling_table_body new_table m-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-2" style="width: max-content !important; min-width: 100%;">
                            <thead>
                                <tr>
                                    <th class="text-nowrap" scope="col" style="width: 40px;">#SL</th>
                                    <th class="text-nowrap" scope="col" style="width: 280px;">Product Info</th>

                                    <th class="text-nowrap" scope="col">Color / Range</th>
                                    @foreach ($platform_ncs as $p_code => $p_name)
                                        <th class="text-nowrap text-start" scope="col">{{ $p_name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @if (!$chartInfos->isEmpty())
                                    @foreach ($chartInfos as $chartInfo)
                                        @php
                                            $ecommerceProduct = $ecommerceMap[$chartInfo->design_no] ?? null;
                                        @endphp
                                        <tr>
                                            <td style="width: 40px !important;" class="text-nowrap"
                                                @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                                {{ $start + $loop->index }}</td>

                                            <td class="text-nowrap text-start info-td"
                                                @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                                <div class="d-flex align-items-center">
                                                    @if ($chartInfo->design_image)
                                                        <img class="img-fluid mb-1"
                                                            src="{{ $chartInfo->design_image ? cloudflareImage($chartInfo->design_image, 120) : cloudflareImage('099de045-63a0-407d-75ca-8e22f95b8700', 50) }}"
                                                            alt="Design Image" width="120">
                                                    @endif
                                                    <div class="ps-2">
                                                        <p class="mb-0">Design No:</p>
                                                        <h6>
                                                            @can('general.discounts.show')
                                                                <a href="javascript:void(0)"
                                                                    onclick="viewChart({{ $chartInfo->id }}, 3)">
                                                                    {{ $chartInfo->design_no }}
                                                                </a>
                                                            @else
                                                                {{ $chartInfo->design_no }}
                                                            @endcan
                                                        </h6>
                                                        <p class="mb-0">Ecom Sku:</p>
                                                        <h6>{{ $ecommerceProduct['sku'] ?? '' }}</h6>
                                                        <p class="mb-0">Department:</p>
                                                        <h6>{{ $chartInfo->department_name }}</h6>
                                                        <p class="mb-0">Category:</p>
                                                        <h6>{{ $chartInfo->category_name }}</h6>
                                                        <p class="mb-0">Mini Category:</p>
                                                        <h6>{{ $chartInfo->mini_category_name }}</h6>
                                                    </div>
                                                </div>
                                            </td>


                                            @if ($chartInfo->selling_chart_prices_count)
                                                @foreach ($chartInfo->sellingChartPrices as $ch_price)
                                                    {{-- @dd(calculatePlatformProfit($ch_price, 'enox')); --}}
                                                    @if ($loop->index == 1)
                                                        @break
                                                    @endif
                                                    <td class="text-nowrap">
                                                        {{ $ch_price->color_name }}
                                                        @if ($ch_price->range)
                                                            <br>
                                                            {{ $ch_price->range }}
                                                        @endif
                                                    </td>
                                                    @foreach ($platform_ncs as $p_code => $p_name)
                                                        @php
                                                            $platform = $platforms->get($p_code);
                                                            $d_price = $ch_price?->discounts
                                                                ->where('status', 1)
                                                                ->where('platform_id', $platform->id)
                                                                ->first();
                                                            $cal_val = calculatePlatformProfit($ch_price, $platform);
                                                            if ($d_price) {
                                                                $dch_price = clone $ch_price;
                                                                $dch_price->confirm_selling_price = $d_price->price;
                                                                $dis_val = calculatePlatformProfit(
                                                                    $dch_price,
                                                                    $platform,
                                                                );
                                                            }
                                                        @endphp
                                                        <td class="text-nowrap text-start">
                                                            <b class="text-info">Orginal Price:</b><br>
                                                            <span title="Selling Price"><b>SP:</b> @price($cal_val['selling_price'])</span>
                                                            <br>
                                                            <span title="Profit Margin"><b>PM:</b> @pricews($cal_val['profit_margin'])%
                                                            </span> <br>
                                                            <span title="Net Profit"><b>NP:</b> @price($cal_val['net_profit']) </span>
                                                            <br>
                                                            @if ($d_price)
                                                                <b class="text-primary">Discount Price:</b><br>
                                                                <span title="Selling Price"><b>SP:</b>
                                                                    @price($dis_val['selling_price'])</span> <br>
                                                                <span title="Profit Margin"><b>PM:</b> @pricews($dis_val['profit_margin'])%
                                                                </span> <br>
                                                                <span title="Net Profit"><b>NP:</b> @price($dis_val['net_profit'])
                                                                </span> <br>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                @endforeach
                                            @else
                                                <td class="text-nowrap">0</td>
                                                <td class="text-nowrap">0</td>
                                            @endif
                                        </tr>

                                        @if ($chartInfo->selling_chart_prices_count > 1)
                                            @foreach ($chartInfo->sellingChartPrices as $ch_price)
                                                @if ($loop->index == 0)
                                                    @continue
                                                @endif
                                                <tr>
                                                    <td class="text-nowrap">{{ $ch_price->color_name }}
                                                        @if ($ch_price->range)
                                                            <br>
                                                            {{ $ch_price->range }}
                                                        @endif
                                                    </td>
                                                    @foreach ($platform_ncs as $p_code => $p_name)
                                                        @php
                                                            $platform = $platforms->get($p_code);
                                                            $d_price = $ch_price?->discounts
                                                                ->where('status', 1)
                                                                ->where('platform_id', $platform->id)
                                                                ->first();
                                                            $cal_val = calculatePlatformProfit($ch_price, $platform);

                                                            if ($d_price) {
                                                                $dch_price = clone $ch_price;
                                                                $dch_price->confirm_selling_price = $d_price->price;
                                                                $dis_val = calculatePlatformProfit(
                                                                    $dch_price,
                                                                    $platform,
                                                                );
                                                            }
                                                        @endphp
                                                        <td class="text-nowrap text-start">
                                                            <b class="text-info">Orginal Price:</b><br>
                                                            <span title="Selling Price"><b>SP:</b> @price($cal_val['selling_price'])</span>
                                                            <br>
                                                            <span title="Profit Margin"><b>PM:</b> @pricews($cal_val['profit_margin'])%
                                                            </span> <br>
                                                            <span title="Net Profit"><b>NP:</b> @price($cal_val['net_profit']) </span>
                                                            <br>
                                                            @if ($d_price)
                                                                <b class="text-primary">Discount Price:</b><br>
                                                                <span title="Selling Price"><b>SP:</b>
                                                                    @price($dis_val['selling_price'])</span> <br>
                                                                <span title="Profit Margin"><b>PM:</b> @pricews($dis_val['profit_margin'])%
                                                                </span> <br>
                                                                <span title="Net Profit"><b>NP:</b> @price($dis_val['net_profit'])
                                                                </span> <br>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        @endif
                                    @endforeach
                                @else
                                    <tr>
                                        <td class="text-nowrap" colspan="14">
                                            <h5 style="width: 100vw;"
                                                class="text-danger text-center text-uppercase py-2 mb-0">No Result found.
                                            </h5>
                                        </td>
                                    </tr>
                                @endif

                            </tbody>
                        </table>
                    </div>
                    {!! $chartInfos->links('master.custom-paginator') !!}
                </div>
            </div>
        </div>
    </div>
    <div class="setViewSellingChartItemModal"></div>
@endsection
@push('js')
    @include('selling_chart.script')
@endpush
