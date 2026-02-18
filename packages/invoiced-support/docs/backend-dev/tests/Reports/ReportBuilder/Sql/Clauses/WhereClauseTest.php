<?php

namespace App\Tests\Reports\ReportBuilder\Sql\Clauses;

use App\Reports\ReportBuilder\Sql\Clauses\WhereClause;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\Fields;
use App\Reports\ReportBuilder\ValueObjects\Filter;
use App\Reports\ReportBuilder\ValueObjects\FilterCondition;
use App\Reports\ReportBuilder\ValueObjects\Group;
use App\Reports\ReportBuilder\ValueObjects\Joins;
use App\Reports\ReportBuilder\ValueObjects\Sort;
use App\Reports\ReportBuilder\ValueObjects\SortField;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;
use App\Tests\AppTestCase;

class WhereClauseTest extends AppTestCase
{
    public function testMakeSql(): void
    {
        $this->assertEquals('WHERE customer_1.currency=?', $this->make('test'));
        $this->assertEquals('WHERE customer_1.currency=?', $this->make('$currency', ['$currency' => 'usd']));
        $this->assertEquals('WHERE (customer_1.currency=? OR customer_1.currency IS NULL)', $this->make('$currency', ['$currency' => 'usd', '$currencyNullable' => true]));
        $this->assertEquals('WHERE (customer_1.currency=? OR customer_1.currency IS NULL)', $this->make('$currency', ['$currency' => 'usd',  '$currencyNullable' => true]));
        $this->assertEquals('WHERE customer_1.currency IN (?,?)', $this->make(['$currency', '$currency2'], ['$currency' => 'usd', '$currency2' => 'eur']));
        $this->assertEquals('WHERE customer_1.currency=?', $this->make('$currency', ['$currency' => 'usd',  '$randomNullable' => true]));
    }

    private function make(mixed $value, array $parameters = []): string
    {
        $table = new Table('customer');
        $sortField = new SortField(new FieldReferenceExpression($table, 'balance'), false);
        $sort = new Sort([$sortField]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression($table, 'currency'), '=', $value),
        ];
        $filter = new Filter($conditions);
        $query = new DataQuery($table, new Joins([]), new Fields([]), $filter, new Group([]), $sort);

        return WhereClause::makeSql($query, new SqlContext($parameters));
    }
}
