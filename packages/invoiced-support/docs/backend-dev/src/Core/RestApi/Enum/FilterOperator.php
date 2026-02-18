<?php

namespace App\Core\RestApi\Enum;

enum FilterOperator: string
{
    case Equal = '=';
    case NotEqual = '<>';
    case GreaterThanOrEqual = '>=';
    case GreaterThan = '>';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case Empty = 'empty';
    case NotEmpty = 'not_empty';
}
