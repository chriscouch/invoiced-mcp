<?php

namespace App\Reports\ReportBuilder\Sql\Expressions;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\Sql\VirtualColumns\CardExpirationDateColumn;
use App\Reports\ReportBuilder\Sql\VirtualColumns\CustomerBalanceColumn;
use App\Reports\ReportBuilder\Sql\VirtualColumns\CustomerCreditBalanceColumn;
use App\Reports\ReportBuilder\Sql\VirtualColumns\MetadataColumn;
use App\Reports\ReportBuilder\Sql\VirtualColumns\UserNameColumn;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;

final class FieldReferenceHelper
{
    /**
     * @throws ReportException
     */
    public static function makeSql(FieldReferenceExpression $fieldReference, SqlContext $context): string
    {
        $table = $fieldReference->table;
        $object = $table->object;
        $id = $fieldReference->id;
        if ('metadata' == $object) {
            return MetadataColumn::makeSql($fieldReference, $context);
        } elseif ('customer' == $object && 'balance' == $id) {
            return CustomerBalanceColumn::makeSql($fieldReference, $context);
        } elseif ('customer' == $object && 'credit_balance' == $id) {
            return CustomerCreditBalanceColumn::makeSql($fieldReference, $context);
        } elseif ('card' == $object && 'exp_date' == $id) {
            return CardExpirationDateColumn::makeSql($fieldReference, $context);
        } elseif ('user' == $object && 'name' == $id) {
            return UserNameColumn::makeSql($fieldReference, $context);
        }

        return $context->getTableAlias($table).'.'.$id;
    }
}
