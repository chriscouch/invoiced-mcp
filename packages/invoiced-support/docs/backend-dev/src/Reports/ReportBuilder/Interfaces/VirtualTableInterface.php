<?php

namespace App\Reports\ReportBuilder\Interfaces;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;

interface VirtualTableInterface
{
    /**
     * @throws ReportException
     */
    public static function makeSql(DataQuery $query, SqlContext $context): string;

    public static function makeReference(SqlContext $context, Table $table): string;
}
