@extends('master.app')
@push('css')
    <style>
        .modal-dialog {
            box-shadow: none
        }

        .new_search textarea,
        .new_search textarea:focus {
            background: #374151;
            border: 1px solid #4f5154;
            color: #fff;
            min-height: 100px;
        }

        .table .new_select_field .form-control {
            padding: 8px 8px !important;
            font-size: 16px !important;
        }

        #selling_chart_table .new_table table tr td {
            text-align: center;
        }



        .bottom_cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-gap: 20px;
            /* margin: 50px 0px 50px 0px; */
        }

        .bottom_item {
            padding: 20px;
            border-radius: 10px;
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
            align-items: center;
            transition: .4s;
            margin-bottom: 0;
        }

        .bottom_item:hover {
            transform: translateX(4px);
        }

        .bottom_item h6 {
            color: #9CA3AF;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .bottom_item p {
            font-size: 13px;
            margin-bottom: 0;
        }

        .bottom_icon {
            margin-right: 13px;
        }

        .bottom_icon i {
            background: #059669;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 25px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .filter_button.new_same_item {
            margin: 0.5rem !important;
        }

        .selling_chart_view_p p {
            text-transform: uppercase;
            font-size: 13px;
        }

        .selling_chart_view_p p span {
            text-transform: capitalize !important;
            font-size: 13px;
            color: #7ba7e5;
        }

        .last_col {
            display: flex;
            flex-direction: row;
            justify-content: space-evenly;
        }

        .last_col p {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
        }

        /* .last_col p:nth-child(3), .last_col p:nth-child(4){
                                                                    width: 175px;
                                                                } */
        /* .selling_chart_view_p p span{

                                                                    width: 100px;
                                                                }*/
        .selling_chart_view_p p span {
            margin-top: 7px;
            width: 95%;
        }

        .selling_chart_view_p p span img {
            height: 168px;
            width: 177px;
        }


        @media (max-width: 991px) {
            .bottom_cards {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 767px) {
            .bottom_cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 575px) {
            .bottom_cards {
                grid-template-columns: repeat(1, 1fr);
            }

            form .card-dark {
                padding: 20px;
            }

            .top_title .tlt-btn,
            .filter_button button {
                height: 40px;
                font-size: 12px;
                padding: 0px 12px;
            }

            .selling_chart_view_p p span {
                margin-top: 7px;
                width: 95%;
            }

            .selling_chart_view_p p span img {
                height: 168px;
                width: 100%;
            }
        }

        .platform-divider {
            position: relative;
        }

        .divider-content {
            display: flex;
            align-items: center;
            gap: 30px;
            position: relative;
        }

        .divider-content .btn {
            padding: 0;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .divider-line {
            flex: 1;
            height: 2px;
            position: relative;
        }

        /* 🌞 Light theme */
        html[data-bs-theme="light"] .divider-line {
            background: linear-gradient(90deg,
                    transparent,
                    rgba(0, 0, 0, 0.12),
                    transparent);
        }

        /* 🌙 Dark theme */
        html[data-bs-theme="dark"] .divider-line {
            background: linear-gradient(90deg,
                    transparent,
                    rgba(255, 255, 255, 0.25),
                    transparent);
        }
    </style>
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
            <a href="{{ route('admin.selling_chart.upload.sheet') }}" class="btn btn-info rounded-pill me-2">
                <i class="bi bi-upload me-2"></i> Import Excel</span>
            </a>

            <a href="{{ route('admin.selling_chart.create') }}" class="btn btn-outline-secondary rounded-pill px-3">
                Create <span><i class="bi bi-plus-lg me-0"></i></span>
            </a>
        </div>
    </div>

    @include('selling_chart.filter')

    <div class="platform-divider">
        <div class="divider-content">
            <div class="divider-line"></div>
            <button title="Show Count" class="btn border" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCards"
                aria-expanded="false" aria-controls="collapseCards">
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const advanceInput = document.getElementById('advance_search');
            const collapseEl = document.getElementById('collapseAdvance');

            collapseEl.addEventListener('shown.bs.collapse', function() {
                advanceInput.value = 1;
            });

            collapseEl.addEventListener('hidden.bs.collapse', function() {
                advanceInput.value = 0;
            });
        });
    </script>
@endpush
