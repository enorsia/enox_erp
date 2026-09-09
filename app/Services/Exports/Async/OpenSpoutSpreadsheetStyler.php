<?php

namespace App\Services\Exports\Async;

use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;

final class OpenSpoutSpreadsheetStyler
{
    private const HEADER_PINK = 'FFB6C1';

    private const NUMBER_FORMAT = '0.00';

    private const DISCOUNT_FORMAT = '0.00;-0.00;0.00';

    private Style $companyHeaderStyle;

    private Style $columnHeaderStyle;

    private Style $dataRowStyle;

    private Style $dataRowCenterStyle;

    private Style $orderSeparatorStyle;

    private Style $totalsRowStyle;

    /** @var array<string, Style> */
    private array $cellStyleCache = [];

    public function __construct(private readonly SpreadsheetExportLayout $layout)
    {
        $this->companyHeaderStyle = (new Style)
            ->setFontBold()
            ->setFontSize(16)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);

        $headerAlignment = $layout->headerCenterAligned ? CellAlignment::CENTER : CellAlignment::LEFT;

        $this->columnHeaderStyle = (new Style)
            ->setFontBold()
            ->setFontSize(12)
            ->setBackgroundColor(Color::toARGB(self::HEADER_PINK))
            ->setCellAlignment($headerAlignment)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder($this->outlineBorder());

        $dataAlignment = $layout->dataCenterAligned ? CellAlignment::CENTER : CellAlignment::LEFT;

        $this->dataRowStyle = (new Style)
            ->setCellAlignment($dataAlignment)
            ->setShouldWrapText(false);

        $this->dataRowCenterStyle = (new Style)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText(false);

        $this->orderSeparatorStyle = (new Style)
            ->setCellAlignment($dataAlignment)
            ->setShouldWrapText(false)
            ->setBorder(new Border(
                new BorderPart(Border::BOTTOM, Color::BLACK, Border::WIDTH_THICK, Border::STYLE_SOLID),
            ));

        $this->totalsRowStyle = (new Style)
            ->setFontBold()
            ->setFontSize(14)
            ->setCellAlignment($dataAlignment);
    }

    public function companyHeaderStyle(): Style
    {
        return $this->companyHeaderStyle;
    }

    public function columnHeaderStyle(): Style
    {
        return $this->columnHeaderStyle;
    }

    public function totalsRowStyle(): Style
    {
        return $this->totalsRowStyle;
    }

    public function cellStyleForColumn(int $columnIndex, bool $isOrderEnd): Style
    {
        $cacheKey = ($isOrderEnd ? 'order' : 'row').':'.$columnIndex.':'.$this->numberFormatForColumn($columnIndex);

        if (isset($this->cellStyleCache[$cacheKey])) {
            return $this->cellStyleCache[$cacheKey];
        }

        $style = new Style;
        $baseStyle = $this->baseDataStyle($isOrderEnd);

        $style->setCellAlignment($baseStyle->getCellAlignment());
        $style->setShouldWrapText(false);

        if ($baseStyle->getBorder() !== null) {
            $style->setBorder($baseStyle->getBorder());
        }

        $format = $this->numberFormatForColumn($columnIndex);

        if ($format !== null) {
            $style->setFormat($format);
        }

        return $this->cellStyleCache[$cacheKey] = $style;
    }

    private function baseDataStyle(bool $isOrderEnd): Style
    {
        if ($isOrderEnd && $this->layout->hasOrderSeparators) {
            return $this->orderSeparatorStyle;
        }

        return $this->layout->dataCenterAligned
          ? $this->dataRowCenterStyle
          : $this->dataRowStyle;
    }

    private function numberFormatForColumn(int $columnIndex): ?string
    {
        if ($this->layout->discountColumn === $columnIndex) {
            return self::DISCOUNT_FORMAT;
        }

        if (in_array($columnIndex, $this->layout->extraMoneyColumns, true)) {
            return self::NUMBER_FORMAT;
        }

        if (
            $this->layout->moneyFormatStart !== null
            && $this->layout->moneyFormatEnd !== null
            && $columnIndex >= $this->layout->moneyFormatStart
            && $columnIndex <= $this->layout->moneyFormatEnd
        ) {
            return self::NUMBER_FORMAT;
        }

        return null;
    }

    private function outlineBorder(): Border
    {
        return new Border(
            new BorderPart(Border::TOP, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::LEFT, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
        );
    }
}
