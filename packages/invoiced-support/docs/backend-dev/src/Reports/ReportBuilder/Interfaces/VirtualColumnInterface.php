<?php

namespace App\Reports\ReportBuilder\Interfaces;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;

interface VirtualColumnInterface
{
    /**
     * @throws ReportException
     */
    public static function makeSql(FieldReferenceExpression $fieldReference, SqlContext $context): string;
}
