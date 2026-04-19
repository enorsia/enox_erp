{{-- Page identifier for selling chart pages.
     Pass $pageId from the parent blade: @include('selling_chart.page-id', ['pageId' => 'enox_selling_chart_index'])
--}}
<div id="{{ $pageId }}"
     class="enox-selling-chart-page"
     hidden
     data-calculate-profit="{{ route('admin.selling_chart.calculate.platform.profit') }}"
     data-size-range="{{ url('/admin/selling-chart/get-size-range') }}"
     data-dep-wise-cats="{{ url('admin/selling-chart/get-dep-wise-cats') }}"
     data-color-search="{{ url('/admin/selling-chart/get-color-by-search') }}"
     data-view-chart="{{ route('admin.selling_chart.view.single.chart', ':id') }}"
></div>

