<?php

namespace App\Services\Exports\Async;

final class SpreadsheetColumnWidthEstimator
{
    /**
     * Shared defaults used when a report does not define a column explicitly.
     *
     * @var array<string, float>
     */
    private const BASE_WIDTH_HINTS = [
        'Customer Name' => 24.0,
        'Customer Email' => 30.0,
        'Email' => 30.0,
        'Phone' => 16.0,
        'Product Title' => 50.0,
        'Product Name' => 38.0,
        'Order Date' => 20.0,
        'Order Number' => 16.0,
        'Order/Inv Number' => 18.0,
        'Item Code' => 16.0,
        'Style Number' => 20.0,
        'Style/ Design' => 20.0,
        'Color' => 16.0,
        'PO Color' => 16.0,
        'Size' => 10.0,
        'Quantity' => 10.0,
        'SKU' => 16.0,
        'EAN Number' => 18.0,
        'Product Code' => 16.0,
        'DPD Parcel' => 30.0,
        'Parcel Number' => 22.0,
        'Return Parcel Number' => 24.0,
        'DPD Return Label' => 24.0,
        'Shipment Create Time' => 20.0,
        'Delivered On' => 20.0,
        'Working Date' => 18.0,
        'Refund Date' => 18.0,
        'Return Request Date' => 20.0,
        'Refund Release Date' => 20.0,
        'Parcel Dropped Date' => 20.0,
        'Parcel Received Date' => 20.0,
        'Dropped Date' => 18.0,
        'Received Date' => 18.0,
        'Credit Note Number' => 20.0,
        'Return Label Number' => 22.0,
        'Return Reason' => 26.0,
        'Return Action Plan Comment' => 40.0,
        'Remarks' => 26.0,
        'Location' => 24.0,
        'Region Name' => 18.0,
        'City Name' => 18.0,
        'IP Address' => 18.0,
        'Browser' => 22.0,
        'Platform' => 14.0,
        'Stock Yes/NO' => 14.0,
        'Wishlist Count' => 14.0,
        'Rating' => 12.0,
        'Invoice Amount' => 16.0,
        'Refund Amount' => 16.0,
        'Sales Price' => 14.0,
        'Shipping Price' => 14.0,
        'Sub Total' => 14.0,
        'Sub Total(GBP)' => 16.0,
        'Sale Price (GBP)' => 16.0,
        'Discount (if any)' => 16.0,
        'Net Sale Price (GBP)' => 18.0,
        'VAT 20%' => 12.0,
        'Vat 20%' => 12.0,
        'Coupon Percent(%)' => 14.0,
        'Smart Cart Saver (GBP)' => 18.0,
        'DPD Label Charges (GBP)' => 20.0,
        'DPD Label Charges +VAT 20%' => 24.0,
        'Refund Charges (GBP)' => 18.0,
        'Delivery Charges (GBP)' => 20.0,
        'Extra Delivery Charges (GBP)' => 22.0,
        'Delivery Charges + Extra (GBP)' => 24.0,
        'VAT 20% on Delivery Charges' => 24.0,
        'Total Delivery Charges (GBP)' => 24.0,
        'DBZ Commission 20%' => 18.0,
        'DBZ VAT On Commission 20%' => 22.0,
        'DBZ Commission (Incl. VAT)' => 24.0,
        'AMZ Commission 15.3%' => 18.0,
        'AMZ VAT On Commission 20%' => 22.0,
        'AMZ Commission (Incl. VAT)' => 24.0,
        'Total Sale (Excl. Commission)' => 24.0,
        'Country Code' => 12.0,
        'Region Code' => 12.0,
        'Zip Code' => 12.0,
        'Latitude' => 14.0,
        'Longitude' => 14.0,
        '#SL' => 8.0,
        'SL' => 8.0,
        'SL.' => 8.0,
    ];

    /**
     * Report-specific overrides. These take priority over BASE_WIDTH_HINTS.
     *
     * @var array<string, array<string, float>>
     */
    private const LAYOUT_WIDTH_OVERRIDES = [
        'sales' => [
            'Order Date' => 20.0,
            'Product Title' => 52.0,
            'Style Number' => 20.0,
            'Color' => 16.0,
            'DPD Parcel' => 30.0,
            'Delivered On' => 20.0,
            'Shipment Create Time' => 20.0,
            'Customer Name' => 24.0,
            'Email' => 32.0,
        ],
        'refund' => [
            'Refund Date' => 20.0,
            'Order Date' => 20.0,
            'Product Title' => 52.0,
            'Style Number' => 20.0,
            'Color' => 16.0,
            'Customer Name' => 24.0,
            'Email' => 32.0,
            'Return Reason' => 28.0,
            'Return Request Date' => 20.0,
            'Parcel Dropped Date' => 20.0,
            'Parcel Received Date' => 20.0,
        ],
        'dbz_sales' => [
            'Order Date' => 20.0,
            'Product Title' => 50.0,
            'Style Number' => 20.0,
            'Color' => 16.0,
            'Parcel Number' => 24.0,
            'Customer Name' => 24.0,
            'Email' => 32.0,
        ],
        'dbz_refund' => [
            'Order Date' => 20.0,
            'Product Title' => 50.0,
            'Style Number' => 20.0,
            'Color' => 16.0,
            'Refund Date' => 20.0,
            'Return Request Date' => 20.0,
            'Return Reason' => 28.0,
            'DPD Return Label' => 26.0,
            'Dropped Date' => 20.0,
            'Received Date' => 20.0,
            'Customer Name' => 24.0,
            'Email' => 32.0,
        ],
        'amz_sales' => [
            'Order Date' => 20.0,
            'Product Title' => 50.0,
            'Style Number' => 20.0,
            'Color' => 16.0,
            'Parcel Number' => 24.0,
            'Customer Name' => 24.0,
            'Email' => 32.0,
        ],
        'amz_refund' => [
            'Order Date' => 20.0,
            'Product Title' => 50.0,
            'Style Number' => 20.0,
            'Color' => 16.0,
            'Refund Date' => 20.0,
            'Return Reason' => 28.0,
            'Customer Name' => 24.0,
            'Email' => 32.0,
        ],
        'amz_return_reason' => [
            'Working Date' => 20.0,
            'Order Date' => 20.0,
            'Product Title' => 50.0,
            'Style Number' => 20.0,
            'PO Color' => 16.0,
            'Return Parcel Number' => 26.0,
            'Return Reason' => 28.0,
            'Return Action Plan Comment' => 42.0,
            'Remarks' => 28.0,
            'Refund Release Date' => 20.0,
            'Customer Name' => 24.0,
            'Email' => 32.0,
        ],
        'wishlist' => [
            'Style/ Design' => 22.0,
            'Product Name' => 42.0,
            'Wishlist Count' => 16.0,
        ],
        'customer_login' => [
            'Customer Name' => 26.0,
            'Customer Email' => 32.0,
            'Location' => 26.0,
            'Browser' => 24.0,
            'City Name' => 20.0,
            'Region Name' => 20.0,
        ],
        'popularity_rating' => [
            'Style/ Design' => 22.0,
            'Product Name' => 42.0,
            'Product Code' => 18.0,
            'Rating' => 12.0,
        ],
        'ecom_activity' => [
            'Session started' => 20.0,
            'User type' => 14.0,
            'Name' => 24.0,
            'Email' => 30.0,
            'Phone' => 16.0,
            'UTM source' => 14.0,
            'Traffic type' => 12.0,
            'Paid click ID' => 28.0,
            'Commerce stage' => 14.0,
            'Order ID' => 14.0,
            'Order total' => 14.0,
            'Commerce detail' => 18.0,
            'Product title' => 38.0,
            'Size' => 10.0,
            'Color' => 14.0,
            'Product qty' => 10.0,
            'Unit price' => 12.0,
            'Line total' => 12.0,
            'Orders' => 12.0,
            'Order qty' => 10.0,
            'Sum qty' => 10.0,
            'Order value' => 14.0,
            'Cart qty' => 12.0,
            'Cart value' => 14.0,
            'Abandoned' => 18.0,
            'Source' => 18.0,
            'Medium' => 16.0,
            'Duration' => 14.0,
        ],
    ];

    /**
     * @param  array<int, string>  $headings
     * @return array<int, float>
     */
    public static function forProfile(SpreadsheetWriterProfile $profile, array $headings): array
    {
        $layoutOverrides = self::LAYOUT_WIDTH_OVERRIDES[$profile->layoutKey] ?? [];
        $widths = [];

        foreach ($headings as $index => $heading) {
            $widths[$index] = $layoutOverrides[$heading]
                ?? self::BASE_WIDTH_HINTS[$heading]
                ?? self::estimateFromHeading($heading);
        }

        return $widths;
    }

    private static function estimateFromHeading(string $heading): float
    {
        $length = mb_strlen($heading);

        if (str_contains($heading, '(GBP)') || str_contains($heading, 'Charges') || str_contains($heading, 'Commission')) {
            return round(max(14.0, min(26.0, $length * 0.95 + 4.0)), 2);
        }

        if (str_contains($heading, 'VAT') || str_contains($heading, 'Percent')) {
            return round(max(12.0, min(22.0, $length * 0.9 + 3.5)), 2);
        }

        return round(max(10.0, min(30.0, $length * 1.05 + 3.0)), 2);
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int|string, mixed>>  $rows
     * @return array<int, int>
     */
    public static function maxLengthsFromRows(array $headings, array $rows): array
    {
        $lengths = [];

        foreach ($headings as $index => $heading) {
            $lengths[$index] = mb_strlen($heading);
        }

        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $lengths[$index] = max($lengths[$index] ?? 0, mb_strlen((string) $value));
            }
        }

        return $lengths;
    }

    public static function widthFromCharacterCount(int $charCount): float
    {
        return round(max(8.0, min(60.0, $charCount * 1.15 + 2.5)), 2);
    }
}
