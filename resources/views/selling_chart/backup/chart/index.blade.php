@extends('master.app')
@push('css')
    @include('selling_chart.css')
@endpush

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Chart',
            'icon' => 'bi bi-graph-up-arrow',
            'sub_title' => [
                'Manage Selling Chart ' => '',
                'Manage Selling Chart' => route('admin.selling_chart.index'),
            ],
        ])
        <div class="text-end">
            @can('general.chart.import')
                <a href="{{ route('admin.selling_chart.upload.sheet') }}" class="btn btn-info rounded-pill me-2">
                    <i class="bi bi-upload me-2"></i> Import Excel</span>
                </a>
            @endcan
            @can('general.chart.create')
                <a href="{{ route('admin.selling_chart.create') }}" class="btn btn-outline-secondary rounded-pill px-3">
                    Create <span><i class="bi bi-plus-lg me-0"></i></span>
                </a>
            @endcan
        </div>
    </div>

    @include('selling_chart.filter')

    <div class="platform-divider">
        <div class="divider-content">
            <div class="divider-line"></div>
            <button title="Show Count" class="btn border" type="button" data-bs-toggle="collapse"
                data-bs-target="#collapseCards" aria-expanded="false" aria-controls="collapseCards">
                <iconify-icon icon="solar:double-alt-arrow-down-linear" class="fs-18"></iconify-icon>
            </button>
            <div class="divider-line"></div>
        </div>
    </div>
    <div class="collapse" id="collapseCards">
        <div class="bottom_cards pt-3">

            @foreach ($deparment_total_colors as $dtc)
                <div class="bottom_item card">
                    <div class="bottom_icon">
                        <i>
                            <img width="32" src="{{ cloudflareImage('5cc020c8-2510-444c-6060-edd319510600') }}"
                                alt="color" />
                        </i>
                    </div>
                    <div class="bottom_text w-100">
                        <h6 class="text-uppercase">{{ $dtc['department_name'] }}</h6>
                        <div class="d-flex justify-content-between flex-wrap" style="gap: 5px;">
                            @foreach ($dtc['mini_categories'] as $mini_tc)
                                <p>{{ $mini_tc['mini_category_name'] }}: <br> {{ $mini_tc['count'] }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="bottom_item card">
                <div class="bottom_icon">
                    <i>
                        <img width="30" src="{{ cloudflareImage('734000ef-3e9d-47f3-82ff-662f55b84100') }}"
                            alt="color" />
                    </i>
                </div>
                <div class="bottom_text w-100">
                    <h6 class="text-uppercase">Style Count</h6>
                    <div class="d-flex justify-content-between flex-wrap" style="gap: 5px;">
                        @if (!$mini_total_styles->isEmpty())
                            @foreach ($mini_total_styles as $mini_tc)
                                <p>{{ $mini_tc?->miniCategory?->name }}: <br> {{ $mini_tc->total_count }}</p>
                            @endforeach
                        @else
                            <strong class="fs-5">0</strong>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bottom_item card">
                <div class="bottom_icon">
                    <i>
                        <img width="30" src="{{ cloudflareImage('c802ca32-d61c-4aca-92a6-6bd518e65c00') }}"
                            alt="color" />
                    </i>
                </div>
                <div class="bottom_text w-100">
                    <h6 class="text-uppercase">Total Colors</h6>
                    <div class="d-flex justify-content-between flex-wrap" style="gap: 5px;">
                        {{ $totalColors }}
                    </div>
                </div>
            </div>

            <div class="bottom_item card">
                <div class="bottom_icon">
                    <i>
                        <img width="35" src="{{ cloudflareImage('e54e8867-dd21-45fd-7713-4d1dcbcb5500') }}"
                            alt="color" />
                    </i>
                </div>
                <div class="bottom_text w-100">
                    <h6 class="text-uppercase">Total Quantity</h6>
                    <div class="d-flex justify-content-between flex-wrap" style="gap: 5px;">
                        {{ $totalQuantity }}
                    </div>
                </div>
            </div>

        </div>
    </div>

    @include('selling_chart.index-table')
    <div class="setViewSellingChartItemModal"></div>
@endsection
@push('js')
    @include('selling_chart.script')
@endpush
