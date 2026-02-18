<?php

namespace App\Reports\ReportBuilder\Sql\VirtualColumns;

use App\Reports\ReportBuilder\Interfaces\VirtualColumnInterface;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;

final class CustomerBalanceColumn implements VirtualColumnInterface
{
    /**
     * Generates the customer balance which
     * does not have a column in the database.
     */
    public static function makeSql(FieldReferenceExpression $fieldReference, SqlContext $context): string
    {
        // WARNING: This does not respect report currency and does not work with multi-currency
        $customerTableAlias = $context->getTableAlias($fieldReference->table);
        $invoices = 'SELECT SUM(balance) FROM Invoices WHERE customer='.$customerTableAlias.'.id AND closed=0 AND draft=0 AND date <= UNIX_TIMESTAMP()';
        $creditNotes = 'SELECT SUM(balance) FROM CreditNotes WHERE customer='.$customerTableAlias.'.id AND closed=0 AND draft=0 AND date <= UNIX_TIMESTAMP()';

        return 'IFNULL(('.$invoices.'), 0) - IFNULL(('.$creditNotes.'), 0)';
    }
}
