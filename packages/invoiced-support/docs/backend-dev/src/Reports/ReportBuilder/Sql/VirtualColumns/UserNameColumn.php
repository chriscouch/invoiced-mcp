<?php

namespace App\Reports\ReportBuilder\Sql\VirtualColumns;

use App\Reports\ReportBuilder\Interfaces\VirtualColumnInterface;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;

final class UserNameColumn implements VirtualColumnInterface
{
    /**
     * Generates the user name which does
     * not have a column in the database.
     */
    public static function makeSql(FieldReferenceExpression $fieldReference, SqlContext $context): string
    {
        $tableAlias = $context->getTableAlias($fieldReference->table);

        return 'CONCAT('.$tableAlias.'.first_name, " ", '.$tableAlias.'.last_name)';
    }
}
