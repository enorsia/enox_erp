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

                        <div class="col-lg-12">
                            <div class="p-2 mb-2 bg-light mt-3" style="background: #F8F9FB; border-radius: 7px;">
                                <p class="mb-0 d-inline-block me-2 text-uppercase fw-bold">show / hide columns:</p>
                                <h6 class="form-check form-check-inline mb-0">
                                    <input type="checkbox" class="form-check-input toggle-column" value="vat"
                                        id="customCheck4">
                                    <label class="form-check-label" for="customCheck4">Vat details</label>
                                </h6>
                                <h6 class="form-check form-check-inline mb-0">
                                    <input type="checkbox" class="form-check-input toggle-column" value="discount"
                                        id="customCheck3">
                                    <label class="form-check-label" for="customCheck3">Discount details</label>
                                </h6>
                            </div>
                            <div class="mt-2 new_table table-responsive">
                                <table class="table table-bordered" id="ordered_products_table">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Color Name (code)</th>
                                            @if ($chartInfo->department_id == 1928 || $chartInfo->department_id == 1929)
                                                <th>Size Range</th>
                                            @endif
                                            <th>PO Qty</th>
                                            <th class="text-center">price $(FOB)</th>
                                            <th class="text-center">Unit Price</th>
                                            <th class="text-center">Confirm Selling Price</th>
                                            <th class="text-center toogle-item vat">20% Selling VAT</th>
                                            <th class="text-center toogle-item vat">Vat Value £</th>
                                            <th class="text-center">Profit Margin %</th>

                                            <th class="text-center">Net Profit</th>
                                            <th class="text-center toogle-item discount">Discount %</th>
                                            <th class="text-center toogle-item discount">Discount Selling Price</th>
                                            <th class="text-center toogle-item discount">20% Selling Vat Dedact Price
                                            </th>
                                            <th class="text-center toogle-item discount">Discount Vat Value £</th>
                                            <th class="text-center">Discount Profit Margin %</th>
                                            <th class="text-center">Discount Net Profit</th>
                                        </tr>
                                    <tbody id="ordered_products">
                                        @foreach ($chartInfo->sellingChartPrices as $ch_price)
                                            <tr>
                                                <td>{{ $ch_price->color_name }} ({{ $ch_price->color_code }})</td>
                                                @if ($chartInfo->department_id == 1928 || $chartInfo->department_id == 1929)
                                                    <td class="text-center">{{ $ch_price->range }}</td>
                                                @endif
                                                <td class="text-center">{{ $ch_price->po_order_qty }}</td>
                                                <td class="text-center">${{ $ch_price->price_fob }}</td>
                                                <td class="text-center">£{{ $ch_price->unit_price }}</td>
                                                <td class="text-center">
                                                    £{{ $ch_price->confirm_selling_price ?? '0.00' }}</td>
                                                <td class="text-center toogle-item vat">
                                                    £{{ $ch_price->vat_price ?? '0.00' }}</td>
                                                <td class="text-center toogle-item vat">
                                                    £{{ $ch_price->vat_value ?? '0.00' }}</td>
                                                <td class="text-center">{{ $ch_price->profit_margin ?? '0.00' }}%</td>
                                                <td class="text-center">£{{ $ch_price->net_profit ?? '0.00' }}</td>
                                                <td class=" toogle-item discount">{{ $ch_price->discount ?? '0.00' }}%
                                                </td>
                                                <td class=" toogle-item discount">
                                                    £{{ $ch_price->discount_selling_price ?? '0.00' }}</td>
                                                <td class=" toogle-item discount">
                                                    £{{ $ch_price->discount_vat_price ?? '0.00' }}
                                                </td>
                                                <td class=" toogle-item discount">
                                                    £{{ $ch_price->discount_vat_value ?? '0.00' }}
                                                </td>
                                                <td class="text-center">
                                                    {{ $ch_price->discount_profit_margin ?? '0.00' }}%</td>
                                                <td class="text-center">£{{ $ch_price->discount_net_profit ?? '0.00' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    </thead>
                                </table>
                            </div>
                            @can('general.chart.approve')
                                @if ($chartInfo->status == 0 || $chartInfo->status == 2)
                                    <button type="button" onclick="approveData({{ $chartInfo->id }})"
                                        class="btn btn-primary mx-2 btn-sm float-end" title="Approve">
                                        <i class="bi bi-check"></i>
                                        <span>Approve</span>
                                    </button>
                                    @if ($chartInfo->status != 2)
                                        <button type="button" onclick='approveData("{{ $chartInfo->id }}", "reject")'
                                            class="btn btn-danger btn-sm float-end mr-2" title="Reject">
                                            <i class="bi bi-trash"></i>
                                            <span>Reject</span>
                                        </button>
                                    @endif
                                    <form id="approve-form-{{ $chartInfo->id }}" method="POST"
                                        action="{{ route('admin.selling_chart.approve', $chartInfo->id) }}"
                                        style="display: none;">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                                    </form>
                                @endif
                            @endcan
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
