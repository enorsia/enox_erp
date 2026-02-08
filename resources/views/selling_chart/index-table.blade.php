<div class="card-dark main-card my-3 card p-0" id="selling_chart_view_table">
    <div class="card-body">
        <div class="new_search" id="selling_chart_table">
            <div class="selling_table_body new_table m-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-2" style="width: max-content !important;">
                        <thead>
                            <tr>
                                <th class="text-nowrap" scope="col" style="width: 50px !important;">#SL</th>
                                <th class="text-nowrap" scope="col" style="width: 90px !important;">Action</th>
                                <th class="text-nowrap" scope="col">Status</th>
                                <th class="text-nowrap" scope="col">Department</th>
                                <th class="text-nowrap" scope="col">Season</th>
                                <th class="text-nowrap" scope="col">Season Phase</th>
                                <th class="text-nowrap" scope="col">Initial/ Repeat Order</th>
                                <th class="text-nowrap" scope="col">Product Launch Month</th>
                                <th class="text-nowrap" scope="col">Product Category</th>
                                <th class="text-nowrap" scope="col">Mini Category</th>
                                <th class="text-nowrap" scope="col">Product Code</th>
                                <th class="text-nowrap" scope="col">Ecom Sku</th>
                                <th class="text-nowrap" scope="col">Design No</th>
                                <th class="text-nowrap" scope="col">Design Image</th>
                                <th class="text-nowrap" scope="col">Inspiration Image</th>
                                <th scope="col">Product Description</th>
                                <th>Fabrication</th>

                                <th class="text-nowrap" scope="col">Color Code</th>
                                <th class="text-nowrap" scope="col">Color Name</th>
                                {{-- <th class="text-nowrap" scope="col">Size (Age)</th> --}}
                                <th class="text-nowrap" scope="col">Range</th>
                                <th class="text-nowrap" scope="col">PO Order Qty</th>
                                <th class="text-nowrap" scope="col">Price $ (FOB)</th>
                                <th class="text-nowrap" scope="col">Unit Price</th>
                                <th class="text-nowrap" scope="col">Confirm Selling Price</th>
                                <th class="text-nowrap" scope="col">20% Selling VAT</th>
                                <th class="text-nowrap" scope="col">Vat Value £</th>
                                <th class="text-nowrap" scope="col">Profit Margin %</th>
                                <th class="text-nowrap" scope="col">Net Profit </th>
                                <th class="text-nowrap" scope="col">Discount %</th>

                                <th class="text-nowrap" scope="col">Discount Selling Price</th>
                                <th class="text-nowrap" scope="col">20% Selling Vat Dedact Price</th>
                                <th class="text-nowrap" scope="col">Discount Vat Value £</th>
                                <th class="text-nowrap" scope="col">Discount Profit Margin %</th>
                                <th class="text-nowrap" scope="col">Discount Net Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if (!$chartInfos->isEmpty())
                                @foreach ($chartInfos as $chartInfo)
                                    @php
                                        $ecommerceProduct = $ecommerceMap[$chartInfo->design_no] ?? null;
                                    @endphp
                                    <tr>
                                        <td style="width: 50px !important;" class="text-nowrap"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            {{ $start + $loop->index }}</td>

                                        <td class="text-nowrap text-left"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>

                                            @can('general.chart.show')
                                                <a class="btn btn-light btn-sm" href="javascript:void(0)"
                                                    onclick="viewChart({{ $chartInfo->id }})">
                                                    <iconify-icon icon="solar:eye-broken"
                                                        class="align-middle fs-18"></iconify-icon>
                                                </a>
                                            @endcan
                                            @if ($chartInfo->status == 0)
                                                @can('general.chart.edit')
                                                    <a class="btn btn-soft-primary btn-sm mx-1" title="Edit"
                                                        href="{{ route('admin.selling_chart.edit', $chartInfo->id) }}">
                                                        <iconify-icon icon="solar:pen-2-broken"
                                                            class="fs-18"></iconify-icon>
                                                    </a>
                                                @endcan
                                                @can('general.chart.delete')
                                                    <button type="button" onclick="deleteData({{ $chartInfo->id }})"
                                                        class="btn btn-soft-danger btn-sm delete-btn mx-1">
                                                        <iconify-icon icon="solar:trash-bin-minimalistic-2-broken"
                                                            class="fs-18 delete-icon"></iconify-icon>
                                                    </button>

                                                    <form id="delete-form-{{ $chartInfo->id }}" method="POST"
                                                        action="{{ route('admin.selling_chart.destroy', $chartInfo->id) }}"
                                                        style="display: none;">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                @endcan
                                            @endif
                                        </td>

                                        <td class="text-nowrap"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            @if ($chartInfo->status == 1)
                                                <span
                                                    class="badge bg-success-subtle text-success py-1 px-2">Approved</span>
                                            @elseif($chartInfo->status == 2)
                                                <span
                                                    class="badge bg-danger-subtle text-danger px-2 py-1">Rejected</span>
                                            @else
                                                <span class="badge bg-primary-subtle text-primary py-1 px-2">Not
                                                    Approved</span>
                                            @endif

                                        </td>
                                        <td class="text-nowrap"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            {{ $chartInfo->department_name }}</td>
                                        <td class="text-nowrap"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            {{ $chartInfo->season_name }}</td>
                                        <td class="text-nowrap"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            {{ $chartInfo->phase_name }}</td>
                                        <td class="text-nowrap"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            {{ $chartInfo->initial_repeated_status }}
                                        </td>
                                        <td class="text-nowrap"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            {{ $chartInfo->product_launch_month }}</td>
                                        <td class="text-nowrap"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            {{ $chartInfo->category_name }}</td>
                                        <td class="text-nowrap"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            {{ $chartInfo->mini_category_name }}</td>
                                        <td class="text-nowrap"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            {{ $chartInfo->product_code }}</td>
                                        <td class="text-nowrap"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            {{ $ecommerceProduct['sku'] ?? '' }}</td>
                                        <td class="text-nowrap"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            {{ $chartInfo->design_no }}</td>
                                        <td class="text-nowrap"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            @if ($chartInfo->design_image)
                                                <img class="img-fluid"
                                                    src="{{ $chartInfo->design_image ? cloudflareImage($chartInfo->design_image, 50) : cloudflareImage('099de045-63a0-407d-75ca-8e22f95b8700', 50) }}"
                                                    alt="Design Image" width="50" height="50">
                                            @endif
                                        </td>
                                        <td class="text-nowrap"
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            @if ($chartInfo->inspiration_image)
                                                <img style="width: 80px;" class="img-fluid"
                                                    src="{{ $chartInfo->inspiration_image ? cloudflareImage($chartInfo->inspiration_image, 50) : cloudflareImage('099de045-63a0-407d-75ca-8e22f95b8700', 50) }}"
                                                    alt="Inspiration Image" width="50" height="50">
                                            @endif
                                        </td>
                                        <td class=""
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>
                                            <p class="mb-0" style="max-width: 300px;">
                                                {{ $chartInfo->product_description }}</p>

                                        </td>
                                        <td class=""
                                            @if ($chartInfo->selling_chart_prices_count) rowspan="{{ $chartInfo->selling_chart_prices_count }}" @endif>

                                            <p class="mb-0" style="max-width: 300px;">
                                                {{ $chartInfo->fabrication }}</p>
                                        </td>


                                        @if ($chartInfo->selling_chart_prices_count)
                                            @foreach ($chartInfo->sellingChartPrices as $ch_price)
                                                @if ($loop->index == 1)
                                                    @break
                                                @endif
                                                <td class="text-nowrap">{{ $ch_price->color_code }}</td>
                                                <td class="text-nowrap">{{ $ch_price->color_name }}</td>
                                                {{-- <td class="text-nowrap">
                                                {{ $ch_price->size }}
                                                {{ \Illuminate\Support\Str::productSize($ch_price->size, $chartInfo->department_id, 'uk') }}
                                            </td> --}}
                                                <td class="text-nowrap">{{ $ch_price->range }}</td>
                                                <td class="text-nowrap">{{ $ch_price->po_order_qty }}</td>
                                                <td class="text-nowrap">$ {{ $ch_price->price_fob }}</td>
                                                <td class="text-nowrap">£ {{ $ch_price->unit_price }}</td>

                                                <td class="text-nowrap">£ {{ $ch_price->confirm_selling_price ?? 0 }}
                                                </td>
                                                <td class="text-nowrap">£ {{ $ch_price->vat_price ?? 0 }}</td>
                                                <td class="text-nowrap">£ {{ $ch_price->vat_value ?? 0 }}</td>
                                                <td class="text-nowrap">{{ $ch_price->profit_margin ?? 0 }}%</td>
                                                <td class="text-nowrap">£ {{ $ch_price->net_profit ?? 0 }}</td>
                                                <td class="text-nowrap">{{ $ch_price->discount ?? 0 }}%</td>

                                                <td class="text-nowrap">£
                                                    {{ $ch_price->discount_selling_price ?? 0 }}
                                                </td>
                                                <td class="text-nowrap">£ {{ $ch_price->discount_vat_price ?? 0 }}
                                                </td>
                                                <td class="text-nowrap">£ {{ $ch_price->discount_vat_value ?? 0 }}
                                                </td>
                                                <td class="text-nowrap">
                                                    {{ $ch_price->discount_profit_margin ?? 0 }}%
                                                </td>
                                                <td class="text-nowrap">£
                                                    {{ $ch_price->discount_net_profit ?? 0 }}
                                                </td>
                                            @endforeach
                                        @else
                                            <td class="text-nowrap">0</td>
                                            <td class="text-nowrap">0</td>
                                            {{-- <td class="text-nowrap">-</td> --}}
                                            <td class="text-nowrap">0</td>
                                            <td class="text-nowrap">0</td>
                                            <td class="text-nowrap">0</td>
                                            <td class="text-nowrap">0</td>
                                            <td class="text-nowrap">0</td>
                                            <td class="text-nowrap">0</td>
                                            <td class="text-nowrap">0</td>
                                            <td class="text-nowrap">0</td>
                                            <td class="text-nowrap">0</td>
                                            <td class="text-nowrap">0</td>
                                            <td class="text-nowrap">0</td>
                                            <td class="text-nowrap">0</td>
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
                                                <td class="text-nowrap">{{ $ch_price->color_code }}</td>
                                                <td class="text-nowrap">{{ $ch_price->color_name }}</td>
                                                {{-- <td class="text-nowrap">
                                                {{ $ch_price->size }}
                                                {{ \Illuminate\Support\Str::productSize($ch_price->size, $chartInfo->department_id, 'uk') }}
                                            </td> --}}
                                                <td class="text-nowrap">{{ $ch_price->range }}</td>
                                                <td class="text-nowrap">{{ $ch_price->po_order_qty }}</td>
                                                <td class="text-nowrap">$ {{ $ch_price->price_fob }}</td>
                                                <td class="text-nowrap">£ {{ $ch_price->unit_price }}</td>

                                                <td class="text-nowrap">£ {{ $ch_price->confirm_selling_price ?? 0 }}
                                                </td>
                                                <td class="text-nowrap">£ {{ $ch_price->vat_price ?? 0 }}</td>
                                                <td class="text-nowrap">£ {{ $ch_price->vat_value ?? 0 }}</td>
                                                <td class="text-nowrap">{{ $ch_price->profit_margin ?? 0 }}%</td>
                                                <td class="text-nowrap">£ {{ $ch_price->net_profit ?? 0 }}</td>
                                                <td class="text-nowrap">{{ $ch_price->discount ?? 0 }}%</td>

                                                <td class="text-nowrap">£
                                                    {{ $ch_price->discount_selling_price ?? 0 }}
                                                </td>
                                                <td class="text-nowrap">£ {{ $ch_price->discount_vat_price ?? 0 }}
                                                </td>
                                                <td class="text-nowrap">£ {{ $ch_price->discount_vat_value ?? 0 }}
                                                </td>
                                                <td class="text-nowrap">
                                                    {{ $ch_price->discount_profit_margin ?? 0 }}%
                                                </td>
                                                <td class="text-nowrap">£
                                                    {{ $ch_price->discount_net_profit ?? 0 }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @endforeach
                            @else
                                <tr>
                                    <td class="text-nowrap" colspan="34">
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
