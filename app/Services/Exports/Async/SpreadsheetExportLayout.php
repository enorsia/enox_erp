<?php

namespace App\Services\Exports\Async;

use Carbon\Carbon;

final class SpreadsheetExportLayout
{
    public const HEADER_ROW = 6;

    public const FIRST_DATA_ROW = 7;

    /**
     * @param  array<int, float>  $columnWidths
     */
    public function __construct(
        public readonly SpreadsheetWriterProfile $profile,
        public readonly array $columnWidths,
        public readonly int $companyHeaderLines,
        public readonly bool $includeDateRangeLine,
        public readonly bool $headerCenterAligned,
        public readonly bool $dataCenterAligned,
        public readonly ?int $customerColumnStart,
        public readonly ?int $customerColumnEnd,
        public readonly ?int $moneyFormatStart,
        public readonly ?int $moneyFormatEnd,
        public readonly ?int $discountColumn,
        public readonly ?SpreadsheetTotalsRowSpec $totalsRow,
        public readonly int $companyStyleEndColumn,
        public readonly bool $hasOrderSeparators,
        public readonly array $extraMoneyColumns = [],
        public readonly array $verticallyCenteredColumns = [],
        public readonly array $rightAlignedColumns = [],
    ) {}

    public function shouldVerticallyCenterColumn(int $columnIndex): bool
    {
        return in_array($columnIndex, $this->verticallyCenteredColumns, true);
    }

    public function shouldRightAlignColumn(int $columnIndex): bool
    {
        return in_array($columnIndex, $this->rightAlignedColumns, true);
    }

    /**
     * @param  array<int, string>  $headings
     */
    public static function fromProfile(SpreadsheetWriterProfile $profile, array $headings): self
    {
        return match ($profile->layoutKey) {
            'sales' => new self(
                profile: $profile,
                columnWidths: SpreadsheetColumnWidthEstimator::forProfile($profile, $headings),
                companyHeaderLines: 3,
                includeDateRangeLine: true,
                headerCenterAligned: false,
                dataCenterAligned: false,
                customerColumnStart: 3,
                customerColumnEnd: 5,
                moneyFormatStart: 12,
                moneyFormatEnd: 24,
                discountColumn: 13,
                totalsRow: new SpreadsheetTotalsRowSpec(
                    labelColumn: 10,
                    integerSumColumn: 11,
                    poundSumColumns: [12, 13, 14, 15, 16, 18, 19, 20, 21, 22, 23, 24],
                    plainSumColumns: [17],
                    styleStartColumn: 10,
                    styleEndColumn: 24,
                ),
                companyStyleEndColumn: 27,
                hasOrderSeparators: true,
            ),
            'refund' => new self(
                profile: $profile,
                columnWidths: SpreadsheetColumnWidthEstimator::forProfile($profile, $headings),
                companyHeaderLines: 3,
                includeDateRangeLine: true,
                headerCenterAligned: true,
                dataCenterAligned: false,
                customerColumnStart: 5,
                customerColumnEnd: 7,
                moneyFormatStart: 14,
                moneyFormatEnd: 25,
                discountColumn: 15,
                totalsRow: new SpreadsheetTotalsRowSpec(
                    labelColumn: 12,
                    integerSumColumn: 13,
                    poundSumColumns: [14, 15, 16, 17, 18, 20, 21, 22, 23, 24, 25],
                    plainSumColumns: [19],
                    styleStartColumn: 12,
                    styleEndColumn: 25,
                ),
                companyStyleEndColumn: 30,
                hasOrderSeparators: true,
            ),
            'dbz_sales', 'dbz_refund', 'amz_sales', 'amz_refund' => new self(
                profile: $profile,
                columnWidths: SpreadsheetColumnWidthEstimator::forProfile($profile, $headings),
                companyHeaderLines: 3,
                includeDateRangeLine: true,
                headerCenterAligned: true,
                dataCenterAligned: false,
                customerColumnStart: 3,
                customerColumnEnd: 5,
                moneyFormatStart: 14,
                moneyFormatEnd: self::marketplaceMoneyEndColumn($profile->layoutKey),
                discountColumn: null,
                totalsRow: new SpreadsheetTotalsRowSpec(
                    labelColumn: 12,
                    integerSumColumn: 13,
                    poundSumColumns: self::marketplacePoundColumns($profile->layoutKey),
                    plainSumColumns: [],
                    styleStartColumn: 8,
                    styleEndColumn: $profile->columnCount - 1,
                ),
                companyStyleEndColumn: $profile->columnCount - 1,
                hasOrderSeparators: true,
            ),
            'amz_return_reason' => new self(
                profile: $profile,
                columnWidths: SpreadsheetColumnWidthEstimator::forProfile($profile, $headings),
                companyHeaderLines: 3,
                includeDateRangeLine: true,
                headerCenterAligned: true,
                dataCenterAligned: false,
                customerColumnStart: 3,
                customerColumnEnd: 5,
                moneyFormatStart: 15,
                moneyFormatEnd: 16,
                discountColumn: null,
                totalsRow: null,
                companyStyleEndColumn: 25,
                hasOrderSeparators: true,
                extraMoneyColumns: [21],
            ),
            'discount_alert' => new self(
                profile: $profile,
                columnWidths: SpreadsheetColumnWidthEstimator::forProfile($profile, $headings),
                companyHeaderLines: 2,
                includeDateRangeLine: false,
                headerCenterAligned: true,
                dataCenterAligned: true,
                customerColumnStart: null,
                customerColumnEnd: null,
                moneyFormatStart: 7,
                moneyFormatEnd: 7,
                discountColumn: null,
                totalsRow: null,
                companyStyleEndColumn: $profile->columnCount - 1,
                hasOrderSeparators: true,
            ),
            'ecom_activity' => new self(
                profile: $profile,
                columnWidths: SpreadsheetColumnWidthEstimator::forProfile($profile, $headings),
                companyHeaderLines: 3,
                includeDateRangeLine: true,
                headerCenterAligned: true,
                dataCenterAligned: false,
                customerColumnStart: null,
                customerColumnEnd: null,
                moneyFormatStart: $profile->moneyColumnEnd > 0 ? $profile->moneyColumnStart : null,
                moneyFormatEnd: $profile->moneyColumnEnd > 0 ? $profile->moneyColumnEnd : null,
                discountColumn: null,
                totalsRow: null,
                companyStyleEndColumn: $profile->columnCount - 1,
                hasOrderSeparators: false,
                verticallyCenteredColumns: EcomActivityExportSchema::mergeColumnIndices($headings)['all'],
                rightAlignedColumns: EcomActivityExportSchema::trafficColumnIndices($headings),
            ),
            default => new self(
                profile: $profile,
                columnWidths: SpreadsheetColumnWidthEstimator::forProfile($profile, $headings),
                companyHeaderLines: 2,
                includeDateRangeLine: false,
                headerCenterAligned: true,
                dataCenterAligned: true,
                customerColumnStart: null,
                customerColumnEnd: null,
                moneyFormatStart: null,
                moneyFormatEnd: null,
                discountColumn: null,
                totalsRow: null,
                companyStyleEndColumn: $profile->columnCount - 1,
                hasOrderSeparators: false,
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    public function companyHeaderTexts(array $context): array
    {
        if ($this->profile->layoutKey === 'ecom_activity') {
            $lines = [$this->profile->reportTitle];

            if (! empty($context['start_date']) && ! empty($context['end_date'])) {
                $lines[] = 'FROM '.Carbon::parse($context['start_date'])->format('m/d/Y')
                    .' TO '.Carbon::parse($context['end_date'])->format('m/d/Y');
            } elseif (! empty($context['range_label'])) {
                $lines[] = strtoupper((string) $context['range_label']);
            } else {
                $lines[] = '';
            }

            $lines[] = filled($context['filter_summary'] ?? null)
                ? strtoupper((string) $context['filter_summary'])
                : 'ALL SESSIONS';

            return $lines;
        }

        $lines = ['PFD ENORSIA UK LTD', $this->profile->reportTitle];

        if ($this->companyHeaderLines === 3 && $this->includeDateRangeLine) {
            $dateLine = '';

            if (! empty($context['start_date']) && ! empty($context['end_date'])) {
                $dateLine = 'FROM '.Carbon::parse($context['start_date'])->format('m/d/Y')
                  .' TO '.Carbon::parse($context['end_date'])->format('m/d/Y');
            } elseif (! empty($context['range_label'])) {
                $dateLine = strtoupper((string) $context['range_label']);
            }

            $lines[] = $dateLine;
        }

        return $lines;
    }

    private static function marketplaceMoneyEndColumn(string $layoutKey): int
    {
        return match ($layoutKey) {
            'amz_sales' => 22,
            'amz_refund', 'dbz_sales', 'dbz_refund' => 21,
            default => 21,
        };
    }

    /**
     * @return array<int, int>
     */
    private static function marketplacePoundColumns(string $layoutKey): array
    {
        $end = match ($layoutKey) {
            'amz_sales' => 22,
            'amz_refund', 'dbz_sales', 'dbz_refund' => 21,
            default => 21,
        };

        return range(14, $end);
    }
}
