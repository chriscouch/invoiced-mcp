<?php

namespace App\Reports\ReportBuilder\Sql\Types;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use Carbon\CarbonImmutable;
use Exception;

final class DateType
{
    /**
     * Converts a value in the format YYYY-MM-DD to the
     * format accepted by the database.
     *
     * @param string $operator one of >, >=, <, <=
     *
     * @throws ReportException
     */
    public static function formatInput(ExpressionInterface $field, string $operator, string $date): string
    {
        try {
            $dt = CarbonImmutable::createFromFormat('Y-m-d', $date);
        } catch (Exception $e) {
            throw new ReportException($e->getMessage());
        }

        if (!$dt) {
            throw new ReportException('Invalid date (must be in YYYY-MM-DD format): '.$date);
        }

        // The default date format is UNIX timestamp.
        $dateFormat = 'U';

        // If this is a field reference then it specifies its own date format.
        if ($field instanceof FieldReferenceExpression) {
            $dateFormat = $field->dateFormat;
        }

        // Use MySQL Date format for functions
        if ($field instanceof FunctionExpression) {
            $dateFormat = 'Y-m-d';
        }

        if ('U' == $dateFormat) {
            $dt = self::setTimeOfDay($dt, $operator);
        }

        return $dt->format($dateFormat);
    }

    /**
     * Set the time of day based on the operator.
     */
    public static function setTimeOfDay(CarbonImmutable $date, string $operator): CarbonImmutable
    {
        // < should be 00:00:00
        // <= should be 23:59:59
        // > should be 23:59:59
        // >= should be 00:00:00
        if (in_array($operator, ['<=', '>'])) {
            return $date->setTime(23, 59, 59);
        }

        return $date->setTime(0, 0);
    }
}
