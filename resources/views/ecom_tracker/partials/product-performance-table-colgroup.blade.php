@php
    $showSerial = (bool) ($showSerial ?? false);
@endphp
<colgroup>
    @if ($showSerial)
        <col class="etd-col-sl">
    @endif
    <col class="etd-col-product">
    <col class="etd-col-metric">
    <col class="etd-col-metric">
    <col class="etd-col-metric">
    <col class="etd-col-metric">
    <col class="etd-col-metric">
    <col class="etd-col-metric">
</colgroup>
