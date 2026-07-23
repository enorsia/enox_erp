@php $inForm = $inForm ?? false; @endphp
<div class="w-full sm:w-52">
    <label class="f-label">Period</label>
    <select class="f-input custom-select" x-model="period"
            @if($inForm) name="period" @endif
            @if(!$inForm) @change="period !== 'custom' && submitPeriod()" @endif>
        <option value="this_month">This Month</option>
        <option value="last_month">Last Month</option>
        <option value="last_3_months">Last 3 Months</option>
        <option value="last_6_months">Last 6 Months</option>
        <option value="last_1_year">Last 1 Year</option>
        <option value="custom">Custom Range</option>
    </select>
</div>
<div class="w-full sm:w-auto">
    <label class="f-label">From (Year-Month)</label>
    <input type="month" x-model="fromYM" @change="markCustomPeriod()"
           @if($inForm) name="from_year_month" @endif
           class="f-input w-full sm:w-42" />
</div>
<div class="w-full sm:w-auto">
    <label class="f-label">To (Year-Month)</label>
    <input type="month" x-model="toYM" @change="markCustomPeriod()"
           @if($inForm) name="to_year_month" @endif
           class="f-input w-full sm:w-42" />
</div>
