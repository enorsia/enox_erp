<?php

namespace App\Services\Exports\Async;

final class SpreadsheetWriterProfile
{
    public function __construct(
        public readonly string $layoutKey,
        public readonly int $columnCount,
        public readonly int $mergeEndColumn,
        public readonly int $phoneColumn,
        public readonly int $orderNumberColumn,
        public readonly int $sizeColumn,
        public readonly int $quantityColumn,
        public readonly int $moneyColumnStart,
        public readonly int $moneyColumnEnd,
        public readonly int $couponPercentColumn,
        public readonly int $totalLabelColumn,
        public readonly string $reportTitle,
        public readonly string $overflowSheetPrefix,
        public readonly bool $autoSizeColumnsFromContent = false,
    ) {}

    public static function sales(): self
    {
        return new self(
            layoutKey: 'sales',
            columnCount: 28,
            mergeEndColumn: 11,
            phoneColumn: 5,
            orderNumberColumn: 2,
            sizeColumn: 10,
            quantityColumn: 11,
            moneyColumnStart: 12,
            moneyColumnEnd: 24,
            couponPercentColumn: 17,
            totalLabelColumn: 10,
            reportTitle: 'ENORSIA SALES REPORT',
            overflowSheetPrefix: 'Sales Data',
        );
    }

    public static function refund(): self
    {
        return new self(
            layoutKey: 'refund',
            columnCount: 31,
            mergeEndColumn: 11,
            phoneColumn: 7,
            orderNumberColumn: 4,
            sizeColumn: 11,
            quantityColumn: 13,
            moneyColumnStart: 14,
            moneyColumnEnd: 25,
            couponPercentColumn: 19,
            totalLabelColumn: 12,
            reportTitle: 'ENORSIA REFUND REPORT',
            overflowSheetPrefix: 'Refund Data',
        );
    }

    public static function dbzSales(): self
    {
        return new self(
            layoutKey: 'dbz_sales',
            columnCount: 23,
            mergeEndColumn: 11,
            phoneColumn: 5,
            orderNumberColumn: 2,
            sizeColumn: 12,
            quantityColumn: 13,
            moneyColumnStart: 14,
            moneyColumnEnd: 21,
            couponPercentColumn: 20,
            totalLabelColumn: 12,
            reportTitle: 'DABENHAMS SALES REPORTS',
            overflowSheetPrefix: 'DBZ Sales Data',
        );
    }

    public static function dbzRefund(): self
    {
        return new self(
            layoutKey: 'dbz_refund',
            columnCount: 28,
            mergeEndColumn: 11,
            phoneColumn: 5,
            orderNumberColumn: 2,
            sizeColumn: 12,
            quantityColumn: 13,
            moneyColumnStart: 14,
            moneyColumnEnd: 21,
            couponPercentColumn: 21,
            totalLabelColumn: 12,
            reportTitle: 'DABENHAMS REFUND REPORTS',
            overflowSheetPrefix: 'DBZ Refund Data',
        );
    }

    public static function amzSales(): self
    {
        return new self(
            layoutKey: 'amz_sales',
            columnCount: 25,
            mergeEndColumn: 11,
            phoneColumn: 5,
            orderNumberColumn: 2,
            sizeColumn: 12,
            quantityColumn: 13,
            moneyColumnStart: 14,
            moneyColumnEnd: 22,
            couponPercentColumn: 22,
            totalLabelColumn: 12,
            reportTitle: 'AMAZON SALES REPORTS',
            overflowSheetPrefix: 'Amazon Sales Data',
        );
    }

    public static function amzRefund(): self
    {
        return new self(
            layoutKey: 'amz_refund',
            columnCount: 25,
            mergeEndColumn: 11,
            phoneColumn: 5,
            orderNumberColumn: 2,
            sizeColumn: 12,
            quantityColumn: 13,
            moneyColumnStart: 14,
            moneyColumnEnd: 21,
            couponPercentColumn: 21,
            totalLabelColumn: 12,
            reportTitle: 'AMAZON REFUND REPORTS',
            overflowSheetPrefix: 'Amazon Refund Data',
        );
    }

    public static function amzReturnReason(): self
    {
        return new self(
            layoutKey: 'amz_return_reason',
            columnCount: 26,
            mergeEndColumn: 7,
            phoneColumn: 6,
            orderNumberColumn: 3,
            sizeColumn: 13,
            quantityColumn: 14,
            moneyColumnStart: 15,
            moneyColumnEnd: 16,
            couponPercentColumn: 16,
            totalLabelColumn: 12,
            reportTitle: 'AMAZON ORDER RETURN REASON REPORTS',
            overflowSheetPrefix: 'Amazon Return Reason Data',
        );
    }

    public static function wishlist(): self
    {
        return new self(
            layoutKey: 'wishlist',
            columnCount: 4,
            mergeEndColumn: 3,
            phoneColumn: 0,
            orderNumberColumn: 0,
            sizeColumn: 0,
            quantityColumn: 0,
            moneyColumnStart: 0,
            moneyColumnEnd: 0,
            couponPercentColumn: 0,
            totalLabelColumn: 0,
            reportTitle: 'Wishlist Report',
            overflowSheetPrefix: 'Wishlist Data',
        );
    }

    public static function customerLogin(): self
    {
        return new self(
            layoutKey: 'customer_login',
            columnCount: 13,
            mergeEndColumn: 12,
            phoneColumn: 0,
            orderNumberColumn: 0,
            sizeColumn: 0,
            quantityColumn: 0,
            moneyColumnStart: 0,
            moneyColumnEnd: 0,
            couponPercentColumn: 0,
            totalLabelColumn: 0,
            reportTitle: 'Online Store Customer Login Report',
            overflowSheetPrefix: 'Customer Login Data',
        );
    }

    public static function popularityRating(): self
    {
        return new self(
            layoutKey: 'popularity_rating',
            columnCount: 5,
            mergeEndColumn: 4,
            phoneColumn: 0,
            orderNumberColumn: 0,
            sizeColumn: 0,
            quantityColumn: 0,
            moneyColumnStart: 0,
            moneyColumnEnd: 0,
            couponPercentColumn: 0,
            totalLabelColumn: 0,
            reportTitle: 'Online Store Popularity and Rating Report',
            overflowSheetPrefix: 'Popularity Rating Data',
        );
    }

    public static function discountAlert(): self
    {
        return new self(
            layoutKey: 'discount_alert',
            columnCount: 10,
            mergeEndColumn: 9,
            phoneColumn: 0,
            orderNumberColumn: 0,
            sizeColumn: 0,
            quantityColumn: 0,
            moneyColumnStart: 0,
            moneyColumnEnd: 0,
            couponPercentColumn: 0,
            totalLabelColumn: 0,
            reportTitle: 'Discount Alert Report',
            overflowSheetPrefix: 'Discount Alert Data',
            autoSizeColumnsFromContent: true,
        );
    }

    public static function ecomActivity(int $columnCount): self
    {
        return new self(
            layoutKey: 'ecom_activity',
            columnCount: max(1, $columnCount),
            mergeEndColumn: min(max(1, $columnCount - 1), 6),
            phoneColumn: 0,
            orderNumberColumn: 0,
            sizeColumn: 0,
            quantityColumn: 0,
            moneyColumnStart: 0,
            moneyColumnEnd: 0,
            couponPercentColumn: 0,
            totalLabelColumn: 0,
            reportTitle: 'USER ACTIVITY REPORT',
            overflowSheetPrefix: 'Activity Data',
            autoSizeColumnsFromContent: true,
        );
    }
}
