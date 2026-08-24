@extends('mail.layout.layout')

@section('title', 'Discount Assigned')

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">

        <!-- Title -->
        <tr>
            <td style="padding-bottom: 20px;">
                <h1 style="margin: 0; color: #000000; font-size: 36px; font-weight: 700; line-height: 42px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; letter-spacing: -0.5px;"
                    class="socus_title">
                    Discount Assigned: {{ $item['platform'] }}
                </h1>
                <table role="presentation" width="80" cellpadding="0" cellspacing="0" border="0" style="margin-top: 12px;">
                    <tr>
                        <td style="height: 4px; background-color: #000000;"></td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Subtitle -->
        <tr>
            <td style="padding-bottom: 20px;">
                <p style="margin: 0; color: #4a4a4a; font-size: 16px; line-height: 26px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;"
                   class="socus_subtitle">
                    The following discount(s) have been <strong>assigned to {{ $item['platform'] }}</strong> and are ready for execution.
                </p>
            </td>
        </tr>

        <!-- Data Table -->
        <tr>
            <td style="padding-bottom: 20px;">
                @php
                    $discounts = $item['discounts'];
                    $discountCount = count($discounts);
                    $hasRange = collect($discounts)->contains(function ($discount) {
                        return !empty($discount['range']);
                    });
                @endphp
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr>
                            <th style="background: #f8fafc; color: #6b7280; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; padding: 8px; border: 1px solid #e5e7eb; text-align: left; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">Design No</th>
                            <th style="background: #f8fafc; color: #6b7280; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; padding: 8px; border: 1px solid #e5e7eb; text-align: left; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">Ecom SKU</th>
                            <th style="background: #f8fafc; color: #6b7280; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; padding: 8px; border: 1px solid #e5e7eb; text-align: left; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">Platform</th>
                            <th style="background: #f8fafc; color: #6b7280; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; padding: 8px; border: 1px solid #e5e7eb; text-align: left; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">Color</th>
                            <th style="background: #f8fafc; color: #6b7280; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; padding: 8px; border: 1px solid #e5e7eb; text-align: left; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">Range</th>
                            <th style="background: #f8fafc; color: #6b7280; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; padding: 8px; border: 1px solid #e5e7eb; text-align: right; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">Discount Price (&pound;)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($discounts as $index => $discount)
                            @if ($index === 0)
                                <tr>
                                    <td rowspan="{{ $discountCount }}" style="padding: 8px; border: 1px solid #e5e7eb; color: #374151; vertical-align: middle; font-size: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                                        <strong>{{ $item['style'] }}</strong>
                                    </td>
                                    <td rowspan="{{ $discountCount }}" style="padding: 8px; border: 1px solid #e5e7eb; color: #374151; vertical-align: middle; font-size: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                                        <strong>{{ $item['product_code'] }}</strong>
                                    </td>
                                    <td rowspan="{{ $discountCount }}" style="padding: 8px; border: 1px solid #e5e7eb; color: #374151; vertical-align: middle; font-size: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                                        {{ $item['platform'] }}
                                    </td>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb; color: #374151; vertical-align: middle; font-size: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                                        {{ $discount['color'] }}
                                    </td>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb; color: #374151; vertical-align: middle; font-size: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                                        {{ $discount['range'] ?: '—' }}
                                    </td>
                                    @if (!$hasRange)
                                        <td rowspan="{{ $discountCount }}" style="padding: 8px; border: 1px solid #e5e7eb; color: #dc2626; vertical-align: middle; font-size: 12px; font-weight: 700; text-align: right; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                                            {{ number_format($discount['discount'], 2) }}
                                        </td>
                                    @else
                                        <td style="padding: 8px; border: 1px solid #e5e7eb; color: #dc2626; vertical-align: middle; font-size: 12px; font-weight: 700; text-align: right; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                                            {{ number_format($discount['discount'], 2) }}
                                        </td>
                                    @endif
                                </tr>
                            @else
                                <tr>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb; color: #374151; vertical-align: middle; font-size: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                                        {{ $discount['color'] }}
                                    </td>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb; color: #374151; vertical-align: middle; font-size: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                                        {{ $discount['range'] ?: '—' }}
                                    </td>
                                    @if ($hasRange)
                                        <td style="padding: 8px; border: 1px solid #e5e7eb; color: #dc2626; vertical-align: middle; font-size: 12px; font-weight: 700; text-align: right; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                                            {{ number_format($discount['discount'], 2) }}
                                        </td>
                                    @endif
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>

        <!-- Timestamp -->
        <tr>
            <td align="center" style="padding: 15px 0 0 0;">
                <p style="margin: 0; color: #6a6a6a; font-size: 13px; line-height: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-style: italic;">
                    Sent on {{ now()->format('F j, Y \a\t g:i A') }}
                </p>
            </td>
        </tr>

    </table>
@endsection
