<?php

namespace App\Reports\Traits;

use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\Reports\ValueObjects\NestedTableGroup;
use App\Reports\ValueObjects\Section;
use Money\Money as PhpMoney;

/**
 * Used in reports not built by the report builder to handle formatting.
 */
trait ReportFormattingTrait
{
    protected array $moneyFormat;

    /**
     * Formats a number in the currency format.
     */
    protected function formatMoney(Money $money): array
    {
        return [
            'formatted' => MoneyFormatter::get()->format($money, $this->moneyFormat),
            'value' => $money->toDecimal(),
        ];
    }

    /**
     * Formats a number in the currency format and rounds to the
     * nearest dollar amount.
     */
    protected function formatMoneyRound(Money $money): array
    {
        $money = Money::fromDecimal($money->currency, round($money->toDecimal()));

        return [
            'formatted' => MoneyFormatter::get()->format($money, $this->moneyFormat),
            'value' => $money->toDecimal(),
        ];
    }

    /**
     * Formats a monetary value in the currency format.
     */
    protected function formatPhpMoney(PhpMoney $money): array
    {
        return $this->formatMoney(Money::fromMoneyPhp($money));
    }

    /**
     * Formats an integer number, i.e. 3000 -> 3,000.
     * NOTE: this function will chop off decimals.
     */
    protected function formatNumber(int $n, string $decimal = '.', string $thousands = ','): array
    {
        return [
            'formatted' => number_format($n, 0, $decimal, $thousands),
            'value' => $n,
        ];
    }

    /**
     * Formats a number, i.e. 3000 -> 3,000, without chopping
     * off any decimals.
     */
    protected function formatNumberDecimal(float $n, string $decimal = '.', string $thousands = ','): array
    {
        $broken = explode('.', (string) $n);

        $result = number_format((float) $broken[0], 0, $decimal, $thousands);

        if (2 === count($broken)) {
            $result .= $decimal.$broken[1];
        }

        return [
            'formatted' => $result,
            'value' => $n,
        ];
    }

    protected function buildTableSection(string $title, array $header, array $rows): Section
    {
        $section = new Section($title);
        $section->addGroup($this->buildTable($header, $rows));

        return $section;
    }

    /**
     * Shortcut to make a table group. This will
     * build a footer row as well for any numeric
     * or money columns.
     */
    protected function buildTable(array $header, array $rows): NestedTableGroup
    {
        $table = new NestedTableGroup($header);

        $footer = array_fill(0, count($header), '');

        // Format the rows and build the footer
        $isFirstRow = true;
        foreach ($rows as $row) {
            foreach ($row as $i => &$value) {
                if ($value instanceof Money) {
                    if ($isFirstRow || !$footer[$i]) {
                        $footer[$i] = $value;
                    } else {
                        $footer[$i] = $footer[$i]->add($value);
                    }

                    $value = $this->formatMoney($value);
                } elseif (is_float($value)) {
                    if ($isFirstRow || !$footer[$i]) {
                        $footer[$i] = $value;
                    } else {
                        $footer[$i] += $value;
                    }

                    $value = $this->formatNumberDecimal($value);
                } elseif (is_int($value)) {
                    if ($isFirstRow || !$footer[$i]) {
                        $footer[$i] = $value;
                    } else {
                        $footer[$i] += $value;
                    }

                    $value = $this->formatNumber($value);
                }
            }

            $table->addRow($row);
            $isFirstRow = false;
        }

        // Format the footer
        foreach ($footer as &$value) {
            if ($value instanceof Money) {
                $value = $this->formatMoney($value);
            } elseif (is_float($value)) {
                $value = $this->formatNumberDecimal($value);
            } elseif (is_int($value)) {
                $value = $this->formatNumber($value);
            }
        }

        $table->setFooter($footer);

        return $table;
    }
}
