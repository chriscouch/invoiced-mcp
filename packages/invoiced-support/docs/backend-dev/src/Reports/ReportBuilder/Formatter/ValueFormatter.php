<?php

namespace App\Reports\ReportBuilder\Formatter;

use App\Companies\Models\Company;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\Interfaces\FormattableFieldInterface;
use App\Reports\ReportBuilder\ReportConfiguration;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\ObjectReferenceValue;
use App\Reports\ReportBuilder\ValueObjects\SelectColumn;
use Carbon\CarbonImmutable;
use Exception;

class ValueFormatter
{
    private string $dateTimeFormat;

    public function __construct(private array $moneyFormat, private string $dateFormat)
    {
        $this->dateTimeFormat = $dateFormat.' g:i a';
    }

    public static function forCompany(Company $company): self
    {
        return new self($company->moneyFormat(), $company->date_format);
    }

    /**
     * Formats a value for display in a report.
     */
    public function format(Company $company, FormattableFieldInterface $field, mixed $value, array $parameters): mixed
    {
        // un-wrap values that reference an object
        $reference = null;
        if ($value instanceof ObjectReferenceValue) {
            $reference = $value;
            $value = $value->getValue();
        }

        // format the value
        $value = $this->_format($company, $field, $value, $parameters);

        // re-wrap the value in a reference
        if ($reference && $value) {
            $value = $this->formatReference($reference, $value);
        }

        return $value;
    }

    private function _format(Company $company, FormattableFieldInterface $field, mixed $value, array $parameters): mixed
    {
        $unit = $field->getUnit();
        if (ColumnType::Boolean == $field->getType()) {
            return '1' == $value ? 'Y' : 'N';
        }

        if (in_array($field->getType(), [ColumnType::Date, ColumnType::DateTime])) {
            try {
                // Dates can be in a UNIX timestamp or the MySQL Date or DateTime format
                if (is_numeric($value) && $value > 0) {
                    $value = CarbonImmutable::createFromTimestamp($value);
                } elseif ($value) {
                    if (str_contains($value, ' ')) {
                        // Parse DateTime format
                        $value = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $value);
                    } else {
                        // Parse Date format
                        $value = CarbonImmutable::createFromFormat('Y-m-d', $value);
                    }
                }

                if ($value instanceof CarbonImmutable) {
                    if (ColumnType::DateTime == $field->getType()) {
                        return $this->formatDateTime($value);
                    }

                    return $this->formatDate($value);
                }
            } catch (Exception) {
                return null;
            }
        }

        if (ColumnType::DateTime == $field->getType()) {
            try {
                // Dates can be in a UNIX timestamp or the MySQL DateTime format
                if (is_numeric($value) && $value > 0) {
                    $value = CarbonImmutable::createFromTimestamp($value);
                } elseif ($value) {
                    $value = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $value);
                }

                if ($value instanceof CarbonImmutable) {
                    return $this->formatDate($value);
                }
            } catch (Exception) {
                return null;
            }
        }

        if (ColumnType::Day == $field->getType()) {
            return $this->formatDay((string) $value);
        }

        if (ColumnType::Week == $field->getType()) {
            return $this->formatWeek((string) $value);
        }

        if (ColumnType::Month == $field->getType()) {
            return $this->formatMonth((string) $value);
        }

        if (ColumnType::Enum == $field->getType()) {
            return $this->formatEnum($field, $company, $value);
        }

        if (ColumnType::Float == $field->getType()) {
            return $this->formatDecimal((float) $value, $unit);
        }

        if (ColumnType::Integer == $field->getType()) {
            return $this->formatInteger((int) $value, $unit);
        }

        if (ColumnType::Money == $field->getType()) {
            if (!$value instanceof Money) {
                $value = Money::fromDecimal($parameters['$currency'] ?? $company->currency, (float) $value);
            }

            $hideZero = $field instanceof SelectColumn && $field->hideEmptyValues;

            return $this->formatMoney($value, $hideZero);
        }

        return $value;
    }

    /**
     * Formats a number in the currency format.
     */
    private function formatMoney(Money $money, bool $hideZero = false): array
    {
        if ($hideZero && $money->isZero()) {
            return [
                'formatted' => '',
                'value' => $money->toDecimal(),
            ];
        }

        return [
            'formatted' => MoneyFormatter::get()->format($money, $this->moneyFormat),
            'value' => $money->toDecimal(),
        ];
    }

    /**
     * Formats a date value.
     */
    private function formatDate(CarbonImmutable $date): string
    {
        return $date->format($this->dateFormat);
    }

    /**
     * Formats a datetime value.
     */
    private function formatDateTime(CarbonImmutable $date): string
    {
        return $date->format($this->dateTimeFormat);
    }

    /**
     * Formats a day value. Input: 2021-07-25.
     */
    private function formatDay(string $date): string
    {
        $parsed = CarbonImmutable::createFromFormat('Y-m-d', $date);
        if (!$parsed) {
            return $date;
        }

        return $parsed->format('M d, Y');
    }

    /**
     * Formats a week value. Input: 2021-05.
     */
    private function formatWeek(string $date): string
    {
        [$year, $week] = explode('-', $date);
        /** @var CarbonImmutable $start */
        $start = (new CarbonImmutable("$year-01-01"))->week((int) $week);
        $end = $start->addDays(6);

        return $start->format('M d, Y').' - '.$end->format('M d, Y');
    }

    /**
     * Formats a month value.
     */
    private function formatMonth(string $date): string
    {
        // Can be in Y-m or Ym format.
        // Add a day to this in order to prevent overflow issues.
        $dateFormat = (!str_contains($date, '-')) ? 'Ymd' : 'Y-m-d';
        if (6 == strlen($date)) {
            $date .= '01';
        } elseif (7 == strlen($date)) {
            $date .= '-01';
        }

        $parsed = CarbonImmutable::createFromFormat($dateFormat, $date);
        if (!$parsed) {
            return $date;
        }

        return $parsed->format('M Y');
    }

    private function formatEnum(FormattableFieldInterface $field, Company $company, mixed $input): mixed
    {
        $expression = $field->getExpression();
        if ($expression instanceof FieldReferenceExpression) {
            $configuration = ReportConfiguration::get();
            if ($configuration->hasField($expression->table->object, $expression->id)) {
                $fieldConfig = $configuration->getField($expression->table->object, $expression->id, $expression->metadataObject, $company);
                $values = $fieldConfig['values'] ?? [];
                $input = (string) $input;
                if (isset($values[$input])) {
                    return $values[$input];
                }
            }
        }

        return $input;
    }

    /**
     * Formats an integer number, i.e. 3000 -> 3,000.
     * NOTE: this function will chop off decimals.
     */
    private function formatInteger(int $n, ?string $unit): array
    {
        $decimal = '.';
        $thousands = ',';

        return [
            'formatted' => number_format($n, 0, $decimal, $thousands).$unit,
            'value' => $n,
        ];
    }

    /**
     * Formats a number, i.e. 3000 -> 3,000, without chopping
     * off any decimals.
     */
    private function formatDecimal(float $n, ?string $unit): array
    {
        $broken = explode('.', (string) $n);

        $decimal = '.';
        $thousands = ',';
        $result = number_format((float) $broken[0], 0, $decimal, $thousands);

        if (2 === count($broken)) {
            $result .= $decimal.$broken[1];
        }

        $unitSpace = $unit && '%' != $unit ? ' ' : '';

        return [
            'formatted' => $result.$unitSpace.$unit,
            'value' => $n,
        ];
    }

    /**
     * Wraps a value in a reference.
     */
    private function formatReference(ObjectReferenceValue $reference, mixed $value): array
    {
        $object = $reference->getObject();
        $id = $reference->getId();

        // Special case for sales derived table to properly reference invoice or credit note
        if ('sale' == $object) {
            [$object, $id] = explode('-', $id);
        }

        if (is_array($value)) {
            $value[$object] = $id;

            return $value;
        }

        return [
            $object => $id,
            'formatted' => $value,
            'value' => $value,
        ];
    }
}
