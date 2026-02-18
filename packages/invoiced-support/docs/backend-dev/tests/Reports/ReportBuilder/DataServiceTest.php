<?php

namespace App\Tests\Reports\ReportBuilder;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\DataService;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\Fields;
use App\Reports\ReportBuilder\ValueObjects\Filter;
use App\Reports\ReportBuilder\ValueObjects\FilterCondition;
use App\Reports\ReportBuilder\ValueObjects\Group;
use App\Reports\ReportBuilder\ValueObjects\Joins;
use App\Reports\ReportBuilder\ValueObjects\SelectColumn;
use App\Reports\ReportBuilder\ValueObjects\Sort;
use App\Reports\ReportBuilder\ValueObjects\SortField;
use App\Reports\ReportBuilder\ValueObjects\Table;
use App\Tests\AppTestCase;

class DataServiceTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    protected function setUp(): void
    {
        parent::setUp();
        SelectColumn::resetCounter();
    }

    private function getDataService(): DataService
    {
        return self::getService('test.report_data_service');
    }

    public function testFetchData(): void
    {
        $dataService = $this->getDataService();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $date = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'date'));
        $total = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'total'));
        $balance = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'balance'));
        $fields = new Fields([$number, $date, $total, $balance]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'tenant_id'), '=', self::$company->id()),
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'balance'), '>', 0),
        ];
        $filter = new Filter($conditions);
        $sortField = new SortField(new FieldReferenceExpression(new Table('invoice'), 'balance'), false);
        $sort = new Sort([$sortField]);
        $query = new DataQuery(new Table('invoice'), new Joins([]), $fields, $filter, new Group([]), $sort);

        $data = $dataService->fetchData($query, []);

        $this->assertEquals([
            [
                'number_1' => 'INV-00001',
                'date_2' => self::$invoice->date,
                'total_3' => 100.0,
                'balance_4' => 100.0,
                'invoice_reference' => self::$invoice->id(),
            ],
        ], $data);
    }

    public function testFetchDataFail(): void
    {
        $this->expectException(ReportException::class);

        $dataService = $this->getDataService();

        $field1 = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'not_a_real_field'));
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'tenant_id'), '=', self::$company->id()),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), new Joins([]), new Fields([$field1]), $filter, $group, $sort);

        $dataService->fetchData($query, []);
    }
}
