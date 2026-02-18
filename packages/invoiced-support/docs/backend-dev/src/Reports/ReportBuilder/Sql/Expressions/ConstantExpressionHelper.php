<?php

namespace App\Reports\ReportBuilder\Sql\Expressions;

use App\Reports\ReportBuilder\ValueObjects\ConstantExpression;

final class ConstantExpressionHelper
{
    public static function makeSql(ConstantExpression $item): string
    {
        // When a string constant that is not an exact match for an
        // allowed value then we wrap it in quotes. The use for these
        // is as an argument to a function, for example, the format string
        // in DATE_FORMAT. The `\` and `"` characters are also stripped from
        // the string to prevent SQL injection.
        if ($item->unsafe) {
            return '"'.str_replace(['"', '\\'], ['', ''], $item->value).'"';
        }

        return $item->value;
    }
}
