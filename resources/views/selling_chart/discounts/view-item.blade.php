<div class="modal fade" class="viewSellingChartItemModal" id="viewSellingChartItemModal" tabindex="-1" role="dialog"
    aria-labelledby="viewSellingChartItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down" role="document"
        style="max-width: 1400px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-18" id="exampleModalLabel">DETAILS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="Orders_order_id">
                <div id="order_details_section">
                    <div class="row selling_chart_view_p">
                        <div class="col-lg-4 col-12">
                            <p>Department:</p>
                            <h6>{{ $chartInfo->department_name }}</h6>

                            <p>Season:</p>
                            <h6>{{ $chartInfo->season_name }}</h6>

                            <p>Season Phase:</p>
                            <h6>{{ $chartInfo->phase_name }}</h6>

                            <p>Initial/ Repeat Order:</p>
                            <h6>{{ $chartInfo->initial_repeated_status }}</h6>

                            <p>Product Launch Month:</p>
                            <h6>{{ $chartInfo->product_launch_month }}</h6>

                            <p>Product Description:</p>
                            <h6>{{ $chartInfo->product_description }}</h6>

                            <p>Status:</p>
                            @if ($chartInfo->status == 1)
                                <span class="badge bg-success text-capitalize px-1">Approved</span>
                            @elseif($chartInfo->status == 2)
                                <span class="badge bg-danger text-capitalize px-1">Rejected</span>
                            @else
                                <span class="badge bg-warning text-capitalize px-1 ">Not Approved</span>
                            @endif
                        </div>
                        <div class="col-lg-4 col-12">
                            <p>Product Category:</p>
                            <h6>{{ $chartInfo->category_name }}</h6>

                            <p>Mini Category:</p>
                            <h6>{{ $chartInfo->mini_category_name }}</h6>

                            <p>Product Code:</p>
                            <h6>{{ $chartInfo->product_code }}</h6>

                            <p>Ecom Sku:</p>
                            <h6>{{ $skus['sku'] ?? '' }}</h6>

                            <p>Design No:</p>
                            <h6>{{ $chartInfo->design_no }}</h6>

                            <p>Febrication:</p>
                            <h6>{{ $chartInfo->fabrication }}</h6>
                        </div>
                        @if ($chartInfo->design_image || $chartInfo->inspiration_image)
                            <div class="col-lg-4 col-12 text-center">
                                @if ($chartInfo->design_image)
                                    <p>Design Image: </p>
                                    <h6>
                                        <img class="img-fluid border rounded p-1"
                                            src="{{ $chartInfo->design_image ? cloudflareImage($chartInfo->design_image, 130) : cloudflareImage('099de045-63a0-407d-75ca-8e22f95b8700', 130) }}"
                                            alt="img">
                                    </h6>
                                @endif
                                @if ($chartInfo->inspiration_image)
                                    <p>Inspiration Image:</p>
                                    <h6>
                                        <img class="img-fluid border rounded p-1"
                                            src="{{ $chartInfo->inspiration_image ? cloudflareImage($chartInfo->inspiration_image, 130) : cloudflareImage('099de045-63a0-407d-75ca-8e22f95b8700', 150) }}"
                                            alt="img">
                                    </h6>
                                @endif
                            </div>
                        @endif

                        <div class="col-lg-12 mt-3">
                            <div class="p-2 mb-2 bg-light" style="background: #F8F9FB; border-radius: 7px;">
                                <p class="mb-0 d-inline-block me-2 text-uppercase fw-bold">show / hide columns:</p>
                                <h6 class="form-check form-check-inline mb-0">
                                    <input type="checkbox" class="form-check-input toggle-column" value="commission"
                                        id="customCheck3">
                                    <label class="form-check-label" for="customCheck3">Price & Commission
                                        details</label>
                                </h6>
                                <h6 class="form-check form-check-inline mb-0">
                                    <input type="checkbox" class="form-check-input toggle-column" value="vat"
                                        id="customCheck4">
                                    <label class="form-check-label" for="customCheck4">Vat details</label>
                                </h6>
                            </div>

                            <ul class="nav nav-tabs">
                                @foreach ($platform_ncs as $p_code => $p_name)
                                    <li class="nav-item">
                                        <a href="#{{ $p_code }}" data-bs-toggle="tab"
                                            aria-expanded="{{ $p_code == 'enox' ? 'true' : 'false' }}"
                                            class="nav-link {{ $p_code == 'enox' ? 'active' : '' }}">
                                            <span class="d-block d-sm-none"><i class="bx bx-home"></i></span>
                                            <span class="d-none d-sm-block">{{ $p_name }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="tab-content text-muted">
                                @foreach ($platform_ncs as $p_code => $p_name)
                                    @php
                                        $platform = $platforms->get($p_code);
                                    @endphp
                                    <div class="tab-pane {{ $p_code == 'enox' ? 'show active' : '' }}"
                                        id="{{ $p_code }}">
                                        <form class="pp-form"
                                            action="{{ route('admin.selling_chart.save.platform.discount.price') }}"
                                            method="POST" enctype="multipart/form-data">
                                            @csrf
                                            <input type="hidden" name="platform_id" class="platform_id"
                                                value="{{ $platform->id }}" />
                                            <input type="hidden" name="department_id" class="department_id"
                                                value="{{ $chartInfo->department_id }}" />

                                            <div class="new_table table-responsive">
                                                <table class="table table-bordered mb-2" id="ordered_products_table">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center" style="width: 50px;">Check</th>
                                                            <th class="text-center">Color Name (code)</th>
                                                            @if ($chartInfo->department_id == 1928 || $chartInfo->department_id == 1929)
                                                                <th class="text-center">Size Range</th>
                                                            @endif
                                                            <th class="text-center" style="width: 120px;">Discount</th>
                                                            <th class="text-center" style="width: 60px;">Status</th>
                                                            <th class="text-center toogle-item commission">price $(FOB)
                                                            </th>
                                                            <th class="text-center toogle-item commission">Unit Price
                                                            </th>
                                                            <th class="text-center">Confirm Selling Price</th>

                                                            <th class="text-center toogle-item commission">Commission
                                                            </th>
                                                            <th class="text-center toogle-item commission">Commission
                                                                Vat
                                                            </th>
                                                            <th class="text-center toogle-item commission">Selling
                                                                Price
                                                            </th>
                                                            <th class="text-center toogle-item vat">20% Selling VAT
                                                            </th>
                                                            <th class="text-center toogle-item vat">Vat Value £</th>
                                                            <th class="text-center toogle-item vat">Selling Price + Vat
                                                            </th>
                                                            <th class="text-center">Profit Margin %</th>
                                                            <th class="text-center">Net Profit</th>
                                                            <th class="text-center" style="width: 200px;">Save Type</th>
                                                        </tr>

                                                    </thead>
                                                    <tbody id="ordered_products">
                                                        @foreach ($chartInfo->sellingChartPrices as $ch_price)
                                                            @php
                                                                $d_price = $ch_price?->discounts
                                                                    ->where('platform_id', $platform->id)
                                                                    ->first();
                                                                $h_ch_price = clone $ch_price;
                                                                if ($d_price) {
                                                                    $h_ch_price->confirm_selling_price =
                                                                        $d_price->price;
                                                                }
                                                                $profit_cal = calculatePlatformProfit(
                                                                    $h_ch_price,
                                                                    $platform,
                                                                );
                                                            @endphp
                                                            <tr>
                                                                <input type="hidden"
                                                                    name="ch_price_id[{{ $ch_price->id }}]"
                                                                    class="ch_price_id"
                                                                    value="{{ $ch_price->id }}" />

                                                                @if ($chartInfo->department_id == 1928 || $chartInfo->department_id == 1929)
                                                                    <td class="text-center">
                                                                        <input
                                                                            style="height: 15px !important; width: 15px !important;"
                                                                            type="checkbox" name="sl_price_id[]"
                                                                            value="{{ $ch_price->id }}">
                                                                    </td>
                                                                    <td>{{ $ch_price->color_name }}
                                                                        ({{ $ch_price->color_code }})
                                                                    </td>
                                                                    <td class="text-center">{{ $ch_price->range }}
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <input type="text"
                                                                            name="discount_price[{{ $ch_price->id }}]"
                                                                            data-price-id="{{ $ch_price->id }}"
                                                                            data-csp="{{ $ch_price->confirm_selling_price }}"
                                                                            class="form-control p-1 text-center discount_price rounded-0 text-danger"
                                                                            style="font-size: 13px;"
                                                                            value="{{ $d_price?->price ?? '' }}">
                                                                    </td>
                                                                    <td class="text-center">
                                                                        @can('general.discounts.approve')
                                                                            @if ($d_price)
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input"
                                                                                        type="checkbox" role="switch"
                                                                                        name="statuses[{{ $ch_price->id }}]"
                                                                                        {{ $d_price?->status ? 'checked' : '' }}>
                                                                                </div>
                                                                            @endif
                                                                        @else
                                                                            @if ($d_price)
                                                                                @if ($d_price->status == 1)
                                                                                    <span
                                                                                        class="badge bg-success-subtle text-success py-1 px-2">Approved</span>
                                                                                @else
                                                                                    <span
                                                                                        class="badge bg-danger-subtle text-danger py-1 px-2">Not
                                                                                        Approve</span>
                                                                                @endif
                                                                            @endif
                                                                        @endcan
                                                                    </td>
                                                                @else
                                                                    @if ($loop->index == 0)
                                                                        <td class="text-center"
                                                                            rowspan="{{ count($chartInfo->sellingChartPrices) }}">
                                                                            <input
                                                                                style="height: 15px !important; width: 15px !important;"
                                                                                type="checkbox" name="sl_price_id[]"
                                                                                value="{{ $ch_price->id }}">
                                                                        </td>
                                                                    @endif
                                                                    <td>{{ $ch_price->color_name }}
                                                                        ({{ $ch_price->color_code }})
                                                                    </td>
                                                                    @if ($loop->index == 0)
                                                                        <td class="text-center"
                                                                            rowspan="{{ count($chartInfo->sellingChartPrices) }}">
                                                                            <input type="text"
                                                                                name="discount_price[{{ $ch_price->id }}]"
                                                                                data-price-id="{{ $ch_price->id }}"
                                                                                data-csp="{{ $ch_price->confirm_selling_price }}"
                                                                                class="form-control p-1 text-center discount_price rounded-0 text-danger"
                                                                                style="font-size: 13px;"
                                                                                value="{{ $d_price?->price ?? '' }}">
                                                                        </td>
                                                                        <td class="text-center"
                                                                            rowspan="{{ count($chartInfo->sellingChartPrices) }}">
                                                                            @can('general.discounts.approve')
                                                                                @if ($d_price)
                                                                                    <div class="form-check form-switch">
                                                                                        <input class="form-check-input"
                                                                                            type="checkbox" role="switch"
                                                                                            name="statuses[{{ $ch_price->id }}]"
                                                                                            {{ $d_price?->status ? 'checked' : '' }}>
                                                                                    </div>
                                                                                @endif
                                                                            @else
                                                                                @if ($d_price)
                                                                                    @if ($d_price->status == 1)
                                                                                        <span
                                                                                            class="badge bg-success-subtle text-success py-1 px-2">Approved</span>
                                                                                    @else
                                                                                        <span
                                                                                            class="badge bg-danger-subtle text-danger py-1 px-2">Not
                                                                                            Approve</span>
                                                                                    @endif
                                                                                @endif
                                                                            @endcan
                                                                        </td>
                                                                    @endif
                                                                @endif

                                                                <td class="text-center toogle-item commission">$
                                                                    @pricews($ch_price->price_fob)</td>
                                                                <td class="text-center toogle-item commission">
                                                                    @price($ch_price->unit_price)</td>
                                                                <td class="text-center">@price($ch_price->confirm_selling_price)</td>
                                                                <td class="text-center toogle-item commission com">
                                                                    @price($profit_cal['commission'])</td>
                                                                <td class="text-center toogle-item commission com-vat">
                                                                    @price($profit_cal['commission_vat'])</td>
                                                                <td class="text-center toogle-item commission sp">
                                                                    @price($profit_cal['selling_price'])</td>
                                                                <td class="text-center toogle-item vat sl-vat">
                                                                    @price($profit_cal['selling_vat'])
                                                                </td>
                                                                <td class="text-center toogle-item vat vat-val">
                                                                    @price($profit_cal['vat_value'])
                                                                </td>
                                                                <td class="text-center toogle-item vat sp-vat">
                                                                    @price($profit_cal['selling_price_and_vat'])
                                                                </td>
                                                                <td class="text-center pm">
                                                                    @pricews($profit_cal['profit_margin']) %
                                                                </td>
                                                                <td class="text-center np">@price($profit_cal['net_profit'])</td>
                                                                @if ($loop->index == 0)
                                                                    @php
                                                                        $mappedPrices = $chartInfo->sellingChartPrices->map(
                                                                            function ($price) use ($platform) {
                                                                                return [
                                                                                    'price_id' => $price->id,
                                                                                    'approval_price' => $price->discounts
                                                                                        ->where(
                                                                                            'platform_id',
                                                                                            $platform->id,
                                                                                        )
                                                                                        ->where('status', 1)
                                                                                        ->first(),

                                                                                    'executor_price' => $price->discounts
                                                                                        ->where(
                                                                                            'platform_id',
                                                                                            $platform->id,
                                                                                        )
                                                                                        ->where('status', 0)
                                                                                        ->first(),
                                                                                ];
                                                                            },
                                                                        );

                                                                        $hasApproval = $mappedPrices->contains(
                                                                            fn($item) => $item['approval_price'] !=
                                                                                null,
                                                                        );
                                                                        $hasExecutor = $mappedPrices->contains(
                                                                            fn($item) => $item['executor_price'] != null,
                                                                        );
                                                                    @endphp
                                                                    <td class="text-center"
                                                                        rowspan="{{ count($chartInfo->sellingChartPrices) }}">
                                                                        <select name="save_type" class="form-control">
                                                                            <option value="1">Save</option>
                                                                            @can('general.discounts.sent_mail')
                                                                                @if ($hasExecutor)
                                                                                    <option value="2">Save & Sent for
                                                                                        Approval</option>
                                                                                @endif
                                                                                @if ($hasApproval)
                                                                                    <option value="3">Save & Sent to Executor
                                                                                    </option>
                                                                                @endif
                                                                            @endcan
                                                                        </select>
                                                                    </td>
                                                                @endif
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>

                                            @can('general.discounts.update')
                                                <div class="text-end">
                                                    <button type="submit"
                                                        class="btn btn-lg btn-primary fs-6 px-3 submit-btn rounded">Save
                                                    </button>
                                                </div>
                                            @endcan

                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
