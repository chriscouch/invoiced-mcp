<?php

namespace App\Tests\Reports\ReportBuilder\Formatter;

use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\Formatter\MissingValueFiller;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\Fields;
use App\Reports\ReportBuilder\ValueObjects\Filter;
use App\Reports\ReportBuilder\ValueObjects\Group;
use App\Reports\ReportBuilder\ValueObjects\GroupField;
use App\Reports\ReportBuilder\ValueObjects\Joins;
use App\Reports\ReportBuilder\ValueObjects\SelectColumn;
use App\Reports\ReportBuilder\ValueObjects\Sort;
use App\Reports\ReportBuilder\ValueObjects\Table;
use App\Tests\AppTestCase;

class MissingValueFilterTest extends AppTestCase
{
    public function testFillMissingDataFromEmpty(): void
    {
        SelectColumn::resetCounter();
        $number = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'number'));
        $date = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'date'));
        $total = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'total'));
        $balance = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'balance'));
        $fields = new Fields([$number, $date, $total, $balance]);
        $joins = new Joins([]);
        $filter = new Filter([]);
        $groupField = new GroupField(
            new FieldReferenceExpression(new Table('mrr_item'), 'month', ColumnType::Month), true, false, '', true
        );
        $group = new Group([$groupField]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('mrr_item'), $joins, $fields, $filter, $group, $sort, 10000);
        $data = [];
        $parameters = [
            '$dateRange' => [
                'start' => '2023-07-01',
                'end' => '2024-07-01',
            ],
        ];

        $expected = [
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202307',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202308',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202309',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202310',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202311',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202312',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202401',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202402',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202403',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202404',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202405',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202406',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202407',
            ],
        ];
        $this->assertEquals($expected, MissingValueFiller::fillMissingValues($query, $data, $parameters));
    }

    public function testFillMissingDataSomeMissing(): void
    {
        SelectColumn::resetCounter();
        $number = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'number'));
        $date = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'date'));
        $total = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'total'));
        $balance = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'balance'));
        $fields = new Fields([$number, $date, $total, $balance]);
        $joins = new Joins([]);
        $filter = new Filter([]);
        $groupField = new GroupField(
            new FieldReferenceExpression(new Table('mrr_item'), 'month', ColumnType::Month), true, false, '', true
        );
        $group = new Group([$groupField]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('mrr_item'), $joins, $fields, $filter, $group, $sort, 10000);
        $data = [
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202309',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202405',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202406',
            ],
        ];
        $parameters = [
            '$dateRange' => [
                'start' => '2023-07-01',
                'end' => '2024-07-01',
            ],
        ];

        $expected = [
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202307',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202308',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202309',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202310',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202311',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202312',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202401',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202402',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202403',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202404',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202405',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202406',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202407',
            ],
        ];
        $this->assertEquals($expected, MissingValueFiller::fillMissingValues($query, $data, $parameters));
    }

    public function testFillMissingDataNoneMissing(): void
    {
        SelectColumn::resetCounter();
        $number = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'number'));
        $date = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'date'));
        $total = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'total'));
        $balance = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'balance'));
        $fields = new Fields([$number, $date, $total, $balance]);
        $joins = new Joins([]);
        $filter = new Filter([]);
        $groupField = new GroupField(
            new FieldReferenceExpression(new Table('mrr_item'), 'month', ColumnType::Month), true, false, '', true
        );
        $group = new Group([$groupField]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('mrr_item'), $joins, $fields, $filter, $group, $sort, 10000);
        $data = [
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202307',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202308',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202309',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202310',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202311',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202312',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202401',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202402',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202403',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202404',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202405',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202406',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202407',
            ],
        ];
        $parameters = [
            '$dateRange' => [
                'start' => '2023-07-01',
                'end' => '2024-07-01',
            ],
        ];

        $this->assertEquals($data, MissingValueFiller::fillMissingValues($query, $data, $parameters));
    }

    public function testFillMissingDataDescending(): void
    {
        SelectColumn::resetCounter();
        $number = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'number'));
        $date = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'date'));
        $total = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'total'));
        $balance = new SelectColumn(new FieldReferenceExpression(new Table('mrr_item'), 'balance'));
        $fields = new Fields([$number, $date, $total, $balance]);
        $joins = new Joins([]);
        $filter = new Filter([]);
        $groupField = new GroupField(
            new FieldReferenceExpression(new Table('mrr_item'), 'month', ColumnType::Month), false, false, '', true
        );
        $group = new Group([$groupField]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('mrr_item'), $joins, $fields, $filter, $group, $sort, 10000);
        $data = [
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202406',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202405',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202309',
            ],
        ];
        $parameters = [
            '$dateRange' => [
                'start' => '2023-07-01',
                'end' => '2024-07-01',
            ],
        ];

        $expected = [
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202407',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202406',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202405',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202404',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202403',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202402',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202401',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202312',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202311',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202310',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202309',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202308',
            ],
            [
                'number_1' => null,
                'date_2' => null,
                'total_3' => null,
                'balance_4' => null,
                'group_month' => '202307',
            ],
        ];
        $this->assertEquals($expected, MissingValueFiller::fillMissingValues($query, $data, $parameters));
    }
}
