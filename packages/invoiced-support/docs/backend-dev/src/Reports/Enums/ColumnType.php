<?php

namespace App\Reports\Enums;

enum ColumnType: string
{
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';
    case Day = 'date_day';
    case Enum = 'enum';
    case Float = 'float';
    case Integer = 'integer';
    case Money = 'money';
    case Month = 'date_month';
    case Quarter = 'date_quarter';
    case String = 'string';
    case Week = 'date_week';
    case Year = 'date_year';
}
