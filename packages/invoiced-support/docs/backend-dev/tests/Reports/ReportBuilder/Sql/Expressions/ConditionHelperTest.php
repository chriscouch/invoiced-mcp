<?php

namespace App\Tests\Reports\ReportBuilder\Sql\Expressions;

use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\Sql\Expressions\ConditionHelper;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\FilterCondition;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;
use App\Tests\AppTestCase;

class ConditionHelperTest extends AppTestCase
{
    /**
     * @dataProvider provideFields
     */
    public function testMakeMetadataNotEqualSQL(FilterCondition $field, array $expected): void
    {
        $context = new SqlContext([]);
        $sql = ConditionHelper::makeSql($field, $context);
        $this->assertEquals($expected[0], $sql);
        $this->assertEquals($expected[1], $context->getParams());
    }

    public function provideFields(): array
    {
        $table = new Table('metadata');

        return [
            [
                new FilterCondition(new FieldReferenceExpression(
                    $table,
                    'customer_custom_field',
                    ColumnType::String,
                    'customer_custom_field',
                    'customer'
                ), '<>', 'A'),
                [
                    '(SELECT `value` FROM Metadata WHERE tenant_id=metadata_1.tenant_id AND `key`="customer_custom_field" AND object_type="customer" AND object_id=metadata_1.id AND value=? ) IS NULL ',
                    ['A'],
                ],
            ],
            [
                new FilterCondition(new FieldReferenceExpression(
                    $table,
                    'customer_custom_field',
                    ColumnType::String,
                    'customer_custom_field',
                    'customer'
                ), '=', 'A'),
                [
                    '(SELECT `value` FROM Metadata WHERE tenant_id=metadata_1.tenant_id AND `key`="customer_custom_field" AND object_type="customer" AND object_id=metadata_1.id)=?',
                    ['A'],
                ],
            ],
            [
                new FilterCondition(new FieldReferenceExpression(
                    $table,
                    'customer_custom_field',
                    ColumnType::String,
                    'customer_custom_field',
                    'customer'
                ), '=', null),
                [
                    '(SELECT `value` FROM Metadata WHERE tenant_id=metadata_1.tenant_id AND `key`="customer_custom_field" AND object_type="customer" AND object_id=metadata_1.id) IS NULL',
                    [],
                ],
            ],
            [
                new FilterCondition(new FieldReferenceExpression(
                    $table,
                    'customer_custom_field',
                    ColumnType::String,
                    'customer_custom_field',
                    'customer'
                ), '<>', null),
                [
                    '(SELECT `value` FROM Metadata WHERE tenant_id=metadata_1.tenant_id AND `key`="customer_custom_field" AND object_type="customer" AND object_id=metadata_1.id) IS NOT NULL',
                    [],
                ],
            ],
        ];
    }
}
