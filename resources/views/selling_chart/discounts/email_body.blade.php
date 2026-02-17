<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Selling Chart Discount Notification</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f6f9; font-family: Arial, Helvetica, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9; padding:20px 0;">
        <tr>
            <td align="center">

                <!-- Main Container -->
                <table width="700" cellpadding="0" cellspacing="0"
                       style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.05);">

                    <!-- Header -->
                    <tr>
                        <td style="background:#2d3748; padding:20px; text-align:center;">
                            <h2 style="margin:0; color:#ffffff;">
                                Selling Chart Discount Notification
                            </h2>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:25px;">

                            @if ($type == 'approval')
                                <p style="font-size:15px; color:#333;">
                                    This discount requires <strong>approval</strong>.
                                </p>
                            @else
                                <p style="font-size:15px; color:#333;">
                                    This discount has been <strong>assigned to worker</strong>.
                                </p>
                            @endif

                            <br>

                            <!-- Table -->
                            <table width="100%" cellpadding="8" cellspacing="0"
                                   style="border-collapse:collapse; font-size:14px;">

                                <thead>
                                    <tr style="background-color:#edf2f7;">
                                        <th align="left" style="border:1px solid #ddd;">Design No</th>
                                        <th align="left" style="border:1px solid #ddd;">Color</th>
                                        <th align="left" style="border:1px solid #ddd;">Range</th>
                                        <th align="left" style="border:1px solid #ddd;">Platform</th>
                                        <th align="right" style="border:1px solid #ddd;">Confirm Selling Price (£)</th>
                                        <th align="right" style="border:1px solid #ddd;">Discount Price (£)</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($discounts as $discount)
                                        <tr>
                                            <td style="border:1px solid #ddd;">
                                                {{ $discount?->sellingChartPrice?->sellingChartBasicInfo?->design_no }}
                                            </td>
                                            <td style="border:1px solid #ddd;">
                                                {{ $discount?->sellingChartPrice?->color_name }} ({{ $discount?->sellingChartPrice?->color_code }})
                                            </td>
                                            <td style="border:1px solid #ddd;">
                                                {{ $discount?->sellingChartPrice?->range }}
                                            </td>
                                            <td style="border:1px solid #ddd;">
                                                {{ $discount?->platform?->name }}
                                            </td>
                                            <td align="right" style="border:1px solid #ddd;">
                                                @price($discount?->sellingChartPrice?->confirm_selling_price)
                                            </td>
                                            <td align="right" style="border:1px solid #ddd;">
                                                @price($discount->price)
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>

                            </table>

                            <br><br>

                            <p style="font-size:13px; color:#777;">
                                This is an automated system notification. Please do not reply to this email.
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f7fafc; padding:15px; text-align:center; font-size:12px; color:#999;">
                            © {{ date('Y') }} {{config('app.name')}}. All rights reserved.
                        </td>
                    </tr>

                </table>
                <!-- End Main Container -->

            </td>
        </tr>
    </table>

</body>
</html>
