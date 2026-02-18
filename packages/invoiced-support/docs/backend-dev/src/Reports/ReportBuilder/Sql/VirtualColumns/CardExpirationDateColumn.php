<?php

namespace App\Reports\ReportBuilder\Sql\VirtualColumns;

use App\Reports\ReportBuilder\Interfaces\VirtualColumnInterface;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;

final class CardExpirationDateColumn implements VirtualColumnInterface
{
    /**
     * Generates the card expiration date which
     * does not have a column in the database.
     */
    public static function makeSql(FieldReferenceExpression $fieldReference, SqlContext $context): string
    {
        $tableAlias = $context->getTableAlias($fieldReference->table);

        return 'LAST_DAY(CONCAT('.$tableAlias.'.exp_year, "-", LPAD('.$tableAlias.'.exp_month, 2, "0"), "-01"))';
    }
}
