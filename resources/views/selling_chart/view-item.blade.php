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
                            <p>Department: <span>{{ $chartInfo->department_name }}</span> </p>
                            <p>Season: <span>{{ $chartInfo->season_name }}</span></p>
                            <p>Season Phase: <span>{{ $chartInfo->phase_name }}</span></p>
                            <p>Initial/ Repeat Order: <span>{{ $chartInfo->initial_repeated_status }}</span></p>
                            <p>Product Launch Month: <span>{{ $chartInfo->product_launch_month }}</span></p>
                            <p>Product Description: <span>{{ $chartInfo->product_description }}</span></p>
                            @if ($chartInfo->status == 1)
                                <span class="badge badge-success text-capitalize px-1">Approved</span>
                            @elseif($chartInfo->status == 2)
                                <span class="badge badge-danger text-capitalize px-1">Rejected</span>
                            @else
                                <span class="badge badge-warning text-capitalize px-1 ">Not Approved</span>
                            @endif
                        </div>
                        <div class="col-lg-4 col-12">
                            <p>Product Category: <span>{{ $chartInfo->category_name }}</span></p>
                            <p>Mini Category: <span>{{ $chartInfo->mini_category_name }}</span></p>
                            <p>Product Code: <span>{{ $chartInfo->product_code }}</span></p>
                            <p>Ecom Sku: <span>{{ $skus['sku'] ?? '' }}</span></p>
                            <p>Design No: <span>{{ $chartInfo->design_no }}</span></p>
                            <p>Febrication: <span>{{ $chartInfo->fabrication }}</span></p>
                        </div>
                        @if ($chartInfo->design_image || $chartInfo->inspiration_image)
                            <div class="col-lg-4 col-12 last_col">
                                @if ($chartInfo->design_image)
                                    <p>Design Image: <span>
                                            <img class="img-fluid"
                                                src="{{ $chartInfo->design_image ? cloudflareImage($chartInfo->design_image, 150) : cloudflareImage('099de045-63a0-407d-75ca-8e22f95b8700', 150) }}"
                                                alt="img"></span>
                                    </p>
                                @endif
                                @if ($chartInfo->inspiration_image)
                                    <p>Inspiration Image: <span>
                                            <img class="img-fluid"
                                                src="{{ $chartInfo->inspiration_image ? cloudflareImage($chartInfo->inspiration_image, 150) : cloudflareImage('099de045-63a0-407d-75ca-8e22f95b8700', 150) }}"
                                                alt="img"></span>
                                    </p>
                                @endif
                            </div>
                        @endif

                        <div class="col-lg-12">
                            <div class="mt-3 new_table table-responsive">
                                <table class="table " id="ordered_products_table">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Color Name(code)</th>
                                            <th>Size Range</th>
                                            <th>PO Qty</th>
                                            <th class="text-center">price $(FOB)</th>
                                            <th class="text-center">Unit Price</th>
                                            <th class="text-center">Confirm Selling Price</th>
                                            <th class="text-center">20% Selling VAT</th>
                                            <th class="text-center">Vat Value £</th>
                                            <th class="text-center">Profit Margin %</th>

                                            <th class="text-center">Net Profit</th>
                                            <th class="text-center">Discount %</th>
                                            <th class="text-center">Discount Selling Price</th>
                                            <th class="text-center">20% Selling Vat Dedact Price</th>
                                            <th class="text-center">Discount Vat Value £</th>
                                            <th class="text-center">Discount Profit Margin %</th>
                                            <th class="text-center">Discount Net Profit</th>
                                        </tr>
                                    <tbody id="ordered_products">
                                        @foreach ($chartInfo->sellingChartPrices as $ch_price)
                                            <tr>
                                                <td>{{ $ch_price->color_name }} ({{ $ch_price->color_code }})</td>
                                                <td class="text-center">{{ $ch_price->range }}</td>
                                                <td class="text-center">{{ $ch_price->po_order_qty }}</td>
                                                <td class="text-center">${{ $ch_price->price_fob }}</td>
                                                <td class="text-center">£{{ $ch_price->unit_price }}</td>
                                                <td class="text-center">
                                                    £{{ $ch_price->confirm_selling_price ?? '0.00' }}</td>
                                                <td class="text-center">£{{ $ch_price->vat_price ?? '0.00' }}</td>
                                                <td class="text-center">£{{ $ch_price->vat_value ?? '0.00' }}</td>
                                                <td class="text-center">{{ $ch_price->profit_margin ?? '0.00' }}%</td>
                                                <td class="text-center">£{{ $ch_price->net_profit ?? '0.00' }}</td>
                                                <td class="text-center">{{ $ch_price->discount ?? '0.00' }}%</td>
                                                <td class="text-center">
                                                    £{{ $ch_price->discount_selling_price ?? '0.00' }}</td>
                                                <td class="text-center">£{{ $ch_price->discount_vat_price ?? '0.00' }}
                                                </td>
                                                <td class="text-center">£{{ $ch_price->discount_vat_value ?? '0.00' }}
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
