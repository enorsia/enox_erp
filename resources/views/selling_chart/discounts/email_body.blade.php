<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discount Notification</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f0f4f8;
            font-family: Arial, Helvetica, sans-serif;
        }

        .wrapper {
            width: 100%;
            background: #f0f4f8;
            padding: 30px 16px;
            box-sizing: border-box;
        }

        .container {
            max-width: 680px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.07);
        }

        .header {
            background: #0c1521;
            padding: 24px 32px;
            text-align: center;
        }

        .header h2 {
            margin: 0;
            color: #ffffff;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .subheader {
            background: #1a2840;
            padding: 10px 32px;
            text-align: center;
        }

        .subheader p {
            margin: 0;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.55);
        }

        .body {
            padding: 28px 32px;
        }

        .body p {
            font-size: 14px;
            color: #374151;
            line-height: 1.6;
            margin: 0 0 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .data-table th {
            background: #f8fafc;
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            text-align: left;
        }

        .data-table td {
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            color: #374151;
            vertical-align: middle;
        }

        .data-table tr:nth-child(even) td {
            background: #f9fafb;
        }

        .data-table .num {
            text-align: right;
            font-weight: 600;
            color: #111827;
        }

        .data-table .discount-price {
            color: #dc2626;
            font-weight: 700;
        }

        .notice {
            margin-top: 24px;
            font-size: 12px;
            color: #9ca3af;
            border-top: 1px solid #f0f0f0;
            padding-top: 16px;
        }

        .footer {
            background: #f8fafc;
            padding: 16px 32px;
            text-align: center;
            font-size: 11px;
            color: #9ca3af;
            border-top: 1px solid #f0f0f0;
        }

        @media only screen and (max-width: 600px) {
            .body {
                padding: 20px 16px;
            }

            .data-table th,
            .data-table td {
                padding: 8px 8px;
                font-size: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="container">

            <!-- Header -->
            <div class="header">
                @php $platform = $discounts->first()?->platform; @endphp
                @if ($type == 'approval')
                <h2>Selling Chart Discount — Approval Required</h2>
                @else
                <h2>Discount Assigned: {{ $platform?->name }}</h2>
                @endif
            </div>

            <!-- Sub-header -->
            <div class="subheader">
                <p>{{ config('app.name') }} &nbsp;·&nbsp; {{ now()->format('d M Y') }}</p>
            </div>

            <!-- Body -->
            <div class="body">
                @if ($type == 'approval')
                <p>The following discount(s) have been submitted and require <strong>approval for {{ $platform?->name
                    }}</strong>. Please review and take action.</p>
                @else
                <p>The following discount(s) have been <strong>assigned for {{ $platform?->name }}</strong> and are ready
                    for the executor.</p>
                @endif

                <!-- Data Table -->
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Design No</th>
                            <th>Color</th>
                            <th>Range</th>
                            <th>Platform</th>
                            <th style="text-align:right">Orig. Price (£)</th>
                            <th style="text-align:right">Discount Price (£)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($discounts as $discount)
                        <tr>
                            <td><strong>{{ $discount?->sellingChartPrice?->sellingChartBasicInfo?->design_no }}</strong></td>
                            <td>
                                {{ $discount?->sellingChartPrice?->color_name }}
                                @if ($discount?->sellingChartPrice?->color_code)
                                <span style="color:#9ca3af;">({{ $discount->sellingChartPrice->color_code }})</span>
                                @endif
                            </td>
                            <td>{{ $discount?->sellingChartPrice?->range ?: '—' }}</td>
                            <td>{{ $discount?->platform?->name }}</td>
                            <td class="num">@price($discount?->sellingChartPrice?->confirm_selling_price)</td>
                            <td class="num discount-price">@price($discount->price)</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <p class="notice">This is an automated system notification. Please do not reply to this email.</p>
            </div>

            <!-- Footer -->
            <div class="footer">
                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
            </div>

        </div>
    </div>
</body>

</html>
