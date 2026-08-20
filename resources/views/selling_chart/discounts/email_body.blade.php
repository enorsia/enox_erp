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
            max-width: 720px;
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
            padding: 8px;
            border: 1px solid #e5e7eb;
            text-align: left;
        }

        .data-table td {
            padding: 8px;
            border: 1px solid #e5e7eb;
            color: #374151;
            vertical-align: middle;
            font-size: 12px;
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
                font-size: 11px;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="container">

            <!-- Header -->
            <div class="header">
                <h2>Discount Assigned: {{ $item['platform'] }}</h2>
            </div>

            <!-- Sub-header -->
            <div class="subheader">
                <p>{{ config('app.name') }} &nbsp;·&nbsp; {{ now()->format('d M Y') }}</p>
            </div>

            <!-- Body -->
            <div class="body">
                <p>
                    The following discount(s) have been <strong>assigned to {{ $item['platform'] }}</strong> and are
                    ready for execution.
                </p>

                <!-- Data Table -->
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Design No</th>
                            <th>Ecom SKU</th>
                            <th>Color</th>
                            <th>Range</th>
                            <th>Platform</th>
                            <th style="text-align:right">Discount Price (£)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $discounts = $item['discounts']
                        @endphp
                        @foreach ($discounts as $discount)
                            <tr>
                                <td><strong>{{ $item['style'] }}</strong></td>
                                <td><strong>{{ $item['product_code'] }}</strong></td>
                                <td>
                                    {{ $discount['color'] }}
                                </td>
                                <td>{{ $discount['range'] ?: '—' }}</td>
                                <td>{{ $item['platform'] }}</td>
                                <td class="num discount-price">{{$discount['discount']}}</td>
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
