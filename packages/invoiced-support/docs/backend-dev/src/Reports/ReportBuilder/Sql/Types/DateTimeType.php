<?php

namespace App\Reports\ReportBuilder\Sql\Types;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use Carbon\CarbonImmutable;
use Exception;

final class DateTimeType
{
    /**
     * Converts a value in the format YYYY-MM-DD HH:MM:SS to the
     * format accepted by the database.
     *
     * @param string $operator one of >, >=, <, <=
     *
     * @throws ReportException
     */
    public static function formatInput(ExpressionInterface $field, string $operator, string $date): string
    {
        try {
            $dt = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $date);
        } catch (Exception) {
            // Intentionally not throwing an exception here
            $dt = null;
        }

        if (!$dt) {
            // Attempt to fallback to date notation
            try {
                $dt = CarbonImmutable::createFromFormat('Y-m-d', $date);
            } catch (Exception) {
                // Intentionally not throwing an exception here
                $dt = null;
            }

            if (!$dt) {
                throw new ReportException('Invalid date time (must be in YYYY-MM-DD HH:MM:SS format): '.$date);
            }

            $dt = DateType::setTimeOfDay($dt, $operator);
        }

        // The default datetime format is UNIX timestamp.
        $dateFormat = 'U';

        // If this is a field reference then it specifies its own date format.
        if ($field instanceof FieldReferenceExpression) {
            $dateFormat = $field->dateFormat;
        }

        // Use MySQL DateTime format for functions
        if ($field instanceof FunctionExpression) {
            $dateFormat = 'Y-m-d H:i:s';
        }

        return $dt->format($dateFormat);
    }
}
