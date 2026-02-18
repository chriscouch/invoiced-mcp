<?php

namespace App\Reports\ReportBuilder\Interfaces;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;

interface ClauseInterface
{
    /**
     * @throws ReportException
     */
    public static function makeSql(DataQuery $query, SqlContext $context): string;
}
