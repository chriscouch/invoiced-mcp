<?php

namespace App\Reports\ReportBuilder\Interfaces;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

interface FunctionInterface
{
    /**
     * @throws ReportException
     */
    public static function makeSql(FunctionExpression $function, ?Table $table, SqlContext $context): string;
}
