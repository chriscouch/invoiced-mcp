<?php

namespace App\Integrations\AccountingSync\Enums;

enum TransformFieldType: string
{
    case Array = 'array';
    case Boolean = 'boolean';
    case Country = 'country';
    case Currency = 'currency';
    case DateUnix = 'date_unix';
    case EmailList = 'email_list';
    case Float = 'float';
    case String = 'string';
}
