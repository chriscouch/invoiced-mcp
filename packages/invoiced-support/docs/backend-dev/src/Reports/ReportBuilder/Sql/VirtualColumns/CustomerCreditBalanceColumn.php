<?php

namespace App\Reports\ReportBuilder\Sql\VirtualColumns;

use App\Reports\ReportBuilder\Interfaces\VirtualColumnInterface;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;

final class CustomerCreditBalanceColumn implements VirtualColumnInterface
{
    /**
     * Generates the customer credit balance using the CreditBalances table
     * instead of using the cached credit_balance column, which can in some
     * cases be inaccurate.
     */
    public static function makeSql(FieldReferenceExpression $fieldReference, SqlContext $context): string
    {
        $customerTableAlias = $context->getTableAlias($fieldReference->table);
        $sql = 'SELECT balance FROM CreditBalances WHERE customer_id='.$customerTableAlias.'.id AND `timestamp` <= UNIX_TIMESTAMP()';
        if ($currency = $context->getParameter('$currency')) {
            $sql .= ' AND currency=?';
            $context->addParam($currency);
        }
        $sql .= ' ORDER BY `timestamp` DESC,transaction_id DESC LIMIT 1';

        return 'IFNULL(('.$sql.'), 0)';
    }
}
