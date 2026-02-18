<?php

namespace App\Tests\Reports\ReportBuilder;

use App\Reports\Enums\ColumnType;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\SqlGenerator;
use App\Reports\ReportBuilder\ValueObjects\ConstantExpression;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\ExpressionList;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\Fields;
use App\Reports\ReportBuilder\ValueObjects\Filter;
use App\Reports\ReportBuilder\ValueObjects\FilterCondition;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\Group;
use App\Reports\ReportBuilder\ValueObjects\GroupField;
use App\Reports\ReportBuilder\ValueObjects\JoinCondition;
use App\Reports\ReportBuilder\ValueObjects\Joins;
use App\Reports\ReportBuilder\ValueObjects\SelectColumn;
use App\Reports\ReportBuilder\ValueObjects\Sort;
use App\Reports\ReportBuilder\ValueObjects\SortField;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;
use App\Tests\AppTestCase;

class SqlGeneratorTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SelectColumn::resetCounter();
    }

    public function testGenerate(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $date = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'date'));
        $total = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'total'));
        $balance = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'balance'));
        $fields = new Fields([$number, $date, $total, $balance]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'balance'), '>', 0),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sortField = new SortField(new FieldReferenceExpression(new Table('invoice'), 'balance'), false);
        $sort = new Sort([$sortField]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,invoice_1.date AS date_2,invoice_1.total AS total_3,invoice_1.balance AS balance_4,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.balance>? ORDER BY invoice_1.balance DESC LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([0], $context->getParams());
    }

    public function testGenerateJoin(): void
    {
        $context = new SqlContext();

        $joins = new Joins([new JoinCondition(new Table('invoice'), new Table('customer'), ['parent_column' => 'customer'])]);
        $customer = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'));
        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $date = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'date'));
        $total = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'total'));
        $balance = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'balance'));
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'balance'), '>', 0),
        ];
        $fields = new Fields([$customer, $number, $date, $total, $balance]);
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sortField = new SortField(new FieldReferenceExpression(new Table('invoice'), 'balance'), false);
        $sort = new Sort([$sortField]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_1.name AS name_1,invoice_2.number AS number_2,invoice_2.date AS date_3,invoice_2.total AS total_4,invoice_2.balance AS balance_5,invoice_2.id AS invoice_reference,customer_1.id AS customer_reference FROM Invoices invoice_2 LEFT JOIN Customers customer_1 ON invoice_2.customer=customer_1.id WHERE invoice_2.balance>? ORDER BY invoice_2.balance DESC LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([0], $context->getParams());
    }

    public function testGenerateJoinThrough(): void
    {
        $context = new SqlContext();

        $joins = new Joins([new JoinCondition(new Table('vendor'), new Table('network_connection'), ['parent_column' => 'network_connection_id', 'join_through_tablename' => 'NetworkConnections', 'join_through_column' => 'vendor_id'])]);
        $customer = new SelectColumn(new FieldReferenceExpression(new Table('vendor'), 'name'));
        $number = new SelectColumn(new FieldReferenceExpression(new Table('network_connection'), 'username'));
        $date = new SelectColumn(new FieldReferenceExpression(new Table('network_connection'), 'address1'));
        $total = new SelectColumn(new FieldReferenceExpression(new Table('network_connection'), 'city'));
        $balance = new SelectColumn(new FieldReferenceExpression(new Table('network_connection'), 'state'));
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('network_connection'), 'state'), '=', 'TX'),
        ];
        $fields = new Fields([$customer, $number, $date, $total, $balance]);
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sortField = new SortField(new FieldReferenceExpression(new Table('vendor'), 'name'), false);
        $sort = new Sort([$sortField]);
        $query = new DataQuery(new Table('vendor'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT vendor_1.name AS name_1,network_connection_2.username AS username_2,network_connection_2.address1 AS address1_3,network_connection_2.city AS city_4,network_connection_2.state AS state_5,vendor_1.id AS vendor_reference FROM Vendors vendor_1 LEFT JOIN NetworkConnections NetworkConnections_3 ON vendor_1.network_connection_id=NetworkConnections_3.id LEFT JOIN Companies network_connection_2 ON NetworkConnections_3.vendor_id=network_connection_2.id WHERE network_connection_2.state=? ORDER BY vendor_1.name DESC LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals(['TX'], $context->getParams());
    }

    public function testGenerateFunction(): void
    {
        $context = new SqlContext();

        $arguments = new ExpressionList([new FieldReferenceExpression(new Table('customer'), 'id')]);
        $count = new SelectColumn(new FunctionExpression('count', $arguments));
        $fields = new Fields([$count]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('customer'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT COUNT(customer_1.id) AS function_1,customer_1.id AS customer_reference FROM Customers customer_1 WHERE customer_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionFirstValue(): void
    {
        $context = new SqlContext();

        $name = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'));
        $number = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'number'));
        $joins = new Joins([new JoinCondition(new Table('customer'), new Table('note'), ['parent_column' => 'id', 'join_column' => 'customer_id'])]);
        $arguments = new ExpressionList([
            new FieldReferenceExpression(new Table('note'), 'notes'),
            new FieldReferenceExpression(new Table('customer'), 'id'),
            new FieldReferenceExpression(new Table('note'), 'customer_id'),
            new FieldReferenceExpression(new Table('note'), 'updated_at'),
        ]);
        $firstNote = new SelectColumn(new FunctionExpression('first_value', $arguments));
        $fields = new Fields([$name, $number, $firstNote]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('customer'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_1.name AS name_1,customer_1.number AS number_2,(SELECT subselect_2.notes AS notes_4 FROM Notes subselect_2 WHERE subselect_2.customer_id=customer_1.id AND subselect_2.tenant_id=customer_1.tenant_id ORDER BY subselect_2.updated_at LIMIT 1) AS function_3,customer_1.id AS customer_reference,note_3.id AS note_reference FROM Customers customer_1 LEFT JOIN Notes note_3 ON customer_1.id=note_3.customer_id WHERE customer_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionFirstValueInvd3412(): void
    {
        $context = new SqlContext();

        $function1 = new SelectColumn(new FunctionExpression('last_value', new ExpressionList([
            new FieldReferenceExpression(new Table('note'), 'notes'),
            new FieldReferenceExpression(new Table('note'), 'id'),
            new FieldReferenceExpression(new Table('note'), 'id'),
            new FieldReferenceExpression(new Table('note'), 'created_at'),
        ])));
        $function2 = new SelectColumn(new FunctionExpression('last_value', new ExpressionList([
            new FieldReferenceExpression(new Table('note'), 'id'),
            new FieldReferenceExpression(new Table('note'), 'id'),
            new FieldReferenceExpression(new Table('note'), 'id'),
            new FieldReferenceExpression(new Table('note'), 'created_at'),
        ])));
        $function3 = new SelectColumn(new FunctionExpression('last_value', new ExpressionList([
            new FieldReferenceExpression(new Table('note'), 'created_at'),
            new FieldReferenceExpression(new Table('note'), 'id'),
            new FieldReferenceExpression(new Table('note'), 'id'),
            new FieldReferenceExpression(new Table('note'), 'created_at'),
        ])));
        $fields = new Fields([$function1, $function2, $function3]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('note'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('note'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT (SELECT subselect_1.notes AS notes_4 FROM Notes subselect_1 WHERE subselect_1.id=note_2.id AND subselect_1.tenant_id=note_2.tenant_id ORDER BY subselect_1.created_at DESC LIMIT 1) AS function_1,(SELECT subselect_1.id AS id_5 FROM Notes subselect_1 WHERE subselect_1.id=note_2.id AND subselect_1.tenant_id=note_2.tenant_id ORDER BY subselect_1.created_at DESC LIMIT 1) AS function_2,(SELECT subselect_1.created_at AS created_at_6 FROM Notes subselect_1 WHERE subselect_1.id=note_2.id AND subselect_1.tenant_id=note_2.tenant_id ORDER BY subselect_1.created_at DESC LIMIT 1) AS function_3,note_2.id AS note_reference FROM Notes note_2 WHERE note_2.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionLastValue(): void
    {
        $context = new SqlContext();

        $name = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'));
        $number = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'number'));
        $joins = new Joins([new JoinCondition(new Table('customer'), new Table('note'), ['parent_column' => 'id', 'join_column' => 'customer_id'])]);
        $arguments = new ExpressionList([
            new FieldReferenceExpression(new Table('note'), 'notes'),
            new FieldReferenceExpression(new Table('customer'), 'id'),
            new FieldReferenceExpression(new Table('note'), 'customer_id'),
            new FieldReferenceExpression(new Table('note'), 'updated_at'),
        ]);
        $lastNote = new SelectColumn(new FunctionExpression('last_value', $arguments));
        $fields = new Fields([$name, $number, $lastNote]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('customer'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_1.name AS name_1,customer_1.number AS number_2,(SELECT subselect_2.notes AS notes_4 FROM Notes subselect_2 WHERE subselect_2.customer_id=customer_1.id AND subselect_2.tenant_id=customer_1.tenant_id ORDER BY subselect_2.updated_at DESC LIMIT 1) AS function_3,customer_1.id AS customer_reference,note_3.id AS note_reference FROM Customers customer_1 LEFT JOIN Notes note_3 ON customer_1.id=note_3.customer_id WHERE customer_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionLastValueUnion(): void
    {
        $context = new SqlContext();

        $name = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'));
        $number = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'number'));
        $joins = new Joins([]);
        $arguments = new ExpressionList([
            new FieldReferenceExpression(new Table('note'), 'notes'),
            new FieldReferenceExpression(new Table('customer'), 'id'),
            new FieldReferenceExpression(new Table('note'), 'customer_id'),
            new FieldReferenceExpression(new Table('note'), 'updated_at'),
        ]);
        $lastNote = new SelectColumn(new FunctionExpression('last_value', $arguments));
        $fields = new Fields([$name, $number, $lastNote]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('sale'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_1.name AS name_1,customer_1.number AS number_2,(SELECT subselect_2.notes AS notes_4 FROM Notes subselect_2 WHERE subselect_2.customer_id=customer_1.id AND subselect_2.tenant_id=sale_3.tenant_id ORDER BY subselect_2.updated_at DESC LIMIT 1) AS function_3,CONCAT(sale_3.type,"-",sale_3.id) AS sale_reference FROM (SELECT "invoice" AS `type`,invoice_4.tenant_id AS tenant_id,invoice_4.customer AS customer,invoice_4.balance AS balance,invoice_4.closed AS closed,invoice_4.created_at AS created_at,invoice_4.currency AS currency,invoice_4.date AS date,invoice_4.due_date AS due_date,invoice_4.date_voided AS date_voided,invoice_4.draft AS draft,invoice_4.id AS id,invoice_4.name AS name,invoice_4.notes AS notes,invoice_4.number AS number,invoice_4.paid AS paid,invoice_4.purchase_order AS purchase_order,invoice_4.sent AS sent,invoice_4.status AS status,invoice_4.subtotal AS subtotal,invoice_4.total AS total,invoice_4.updated_at AS updated_at,invoice_4.viewed AS viewed,invoice_4.voided AS voided FROM Invoices invoice_4 WHERE customer_1.tenant_id=? UNION ALL SELECT "credit_note" AS `type`,credit_note_5.tenant_id AS tenant_id,credit_note_5.customer AS customer,(- credit_note_5.balance) AS balance,credit_note_5.closed AS closed,credit_note_5.created_at AS created_at,credit_note_5.currency AS currency,credit_note_5.date AS date,NULL AS due_date,credit_note_5.date_voided AS date_voided,credit_note_5.draft AS draft,credit_note_5.id AS id,credit_note_5.name AS name,credit_note_5.notes AS notes,credit_note_5.number AS number,credit_note_5.paid AS paid,credit_note_5.purchase_order AS purchase_order,credit_note_5.sent AS sent,credit_note_5.status AS status,(- credit_note_5.subtotal) AS subtotal,(- credit_note_5.total) AS total,credit_note_5.updated_at AS updated_at,credit_note_5.viewed AS viewed,credit_note_5.voided AS voided FROM CreditNotes credit_note_5 WHERE customer_1.tenant_id=?) sale_3 LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1, -1], $context->getParams());
    }

    public function testGenerateFunctionDay(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $arguments = new ExpressionList([
            new FieldReferenceExpression(new Table('invoice'), 'date', ColumnType::Date),
        ]);
        $date = new SelectColumn(new FunctionExpression('day', $arguments));
        $fields = new Fields([$number, $date]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,DATE_FORMAT(FROM_UNIXTIME(invoice_1.date), "%Y-%m-%d") AS function_2,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionWeek(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $arguments = new ExpressionList([
            new FieldReferenceExpression(new Table('invoice'), 'date', ColumnType::Date),
        ]);
        $date = new SelectColumn(new FunctionExpression('week', $arguments));
        $fields = new Fields([$number, $date]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,DATE_FORMAT(FROM_UNIXTIME(invoice_1.date), "%X-%V") AS function_2,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionMonth(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $arguments = new ExpressionList([
            new FieldReferenceExpression(new Table('invoice'), 'date', ColumnType::Date),
        ]);
        $date = new SelectColumn(new FunctionExpression('month', $arguments));
        $fields = new Fields([$number, $date]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,DATE_FORMAT(FROM_UNIXTIME(invoice_1.date), "%Y%m") AS function_2,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionQuarter(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $arguments = new ExpressionList([
            new FieldReferenceExpression(new Table('invoice'), 'date', ColumnType::Date),
        ]);
        $date = new SelectColumn(new FunctionExpression('quarter', $arguments));
        $fields = new Fields([$number, $date]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,CONCAT(DATE_FORMAT(FROM_UNIXTIME(invoice_1.date), "%yQ"), QUARTER(FROM_UNIXTIME(invoice_1.date))) AS function_2,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionYear(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $arguments = new ExpressionList([
            new FieldReferenceExpression(new Table('invoice'), 'date', ColumnType::Date),
        ]);
        $date = new SelectColumn(new FunctionExpression('year', $arguments));
        $fields = new Fields([$number, $date]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,DATE_FORMAT(FROM_UNIXTIME(invoice_1.date), "%Y") AS function_2,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionDateAdd(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $date = new SelectColumn(
            new FieldReferenceExpression(new Table('invoice'), 'date'),
            type: ColumnType::Date,
        );
        $arguments = new ExpressionList([
            new FieldReferenceExpression(new Table('invoice'), 'date'),
            new ConstantExpression('30', false),
            new ConstantExpression('day', false),
        ]);
        $dueDate = new SelectColumn(
            new FunctionExpression('date_add', $arguments),
            type: ColumnType::Date,
        );
        $fields = new Fields([$number, $date, $dueDate]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,invoice_1.date AS date_2,DATE_ADD(invoice_1.date, INTERVAL 30 DAY) AS function_3,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionCount(): void
    {
        $context = new SqlContext();

        $name = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'));
        $number = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'number'));
        $joins = new Joins([new JoinCondition(new Table('customer'), new Table('invoice'), ['parent_column' => 'id', 'join_column' => 'customer'])]);
        $arguments = new ExpressionList([
            new FieldReferenceExpression(new Table('invoice'), 'id'),
        ]);
        $numInvoices = new SelectColumn(new FunctionExpression('count', $arguments));
        $fields = new Fields([$name, $number, $numInvoices]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('customer'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_1.name AS name_1,customer_1.number AS number_2,COUNT(invoice_2.id) AS function_3,customer_1.id AS customer_reference,invoice_2.id AS invoice_reference FROM Customers customer_1 LEFT JOIN Invoices invoice_2 ON customer_1.id=invoice_2.customer WHERE customer_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionCountDistinct(): void
    {
        $context = new SqlContext();

        $joins = new Joins([new JoinCondition(new Table('invoice'), new Table('customer'), ['parent_column' => 'customer'])]);
        $arguments = new ExpressionList([
            new FieldReferenceExpression(new Table('customer'), 'id'),
        ]);
        $numCustomers = new SelectColumn(new FunctionExpression('count_distinct', $arguments));
        $fields = new Fields([$numCustomers]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT COUNT(DISTINCT customer_1.id) AS function_1,invoice_2.id AS invoice_reference,customer_1.id AS customer_reference FROM Invoices invoice_2 LEFT JOIN Customers customer_1 ON invoice_2.customer=customer_1.id WHERE invoice_2.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionCaseWithElse(): void
    {
        $context = new SqlContext();

        $function = new SelectColumn(
            new FunctionExpression('case', new ExpressionList([
                new FieldReferenceExpression(new Table('invoice'), 'status'),
                new ConstantExpression('past_due', true),
                new ConstantExpression('1', false),
                new ConstantExpression('0', false),
            ]))
        );
        $fields = new Fields([$function]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT CASE invoice_1.status WHEN "past_due" THEN 1 ELSE 0 END AS function_1,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionCaseWithoutElse(): void
    {
        $context = new SqlContext();

        $function = new SelectColumn(
            new FunctionExpression('case', new ExpressionList([
                new FieldReferenceExpression(new Table('invoice'), 'status'),
                new ConstantExpression('past_due', true),
                new ConstantExpression('1', false),
                new ConstantExpression('paid', true),
                new ConstantExpression('0', false),
                new ConstantExpression('voided', true),
                new ConstantExpression('0', false),
            ]))
        );
        $fields = new Fields([$function]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT CASE invoice_1.status WHEN "past_due" THEN 1 WHEN "paid" THEN 0 WHEN "voided" THEN 0 END AS function_1,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionIfNull(): void
    {
        $context = new SqlContext();

        $arguments = new ExpressionList([new FieldReferenceExpression(new Table('customer'), 'email'), new ConstantExpression('UNKNOWN', true)]);
        $function = new SelectColumn(new FunctionExpression('ifnull', $arguments));
        $fields = new Fields([$function]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('customer'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT IFNULL(customer_1.email, "UNKNOWN") AS function_1,customer_1.id AS customer_reference FROM Customers customer_1 WHERE customer_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateFunctionIf(): void
    {
        $context = new SqlContext();

        $function = new SelectColumn(
            new FunctionExpression('if', new ExpressionList([
                new FilterCondition(
                    new FieldReferenceExpression(new Table('invoice'), 'status'),
                    '=',
                    'past_due',
                ),
                new ConstantExpression('1', false),
                new ConstantExpression('0', false),
            ]))
        );
        $fields = new Fields([$function]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT IF(invoice_1.status=?, 1, 0) AS function_1,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals(['past_due', -1], $context->getParams());
    }

    public function testGenerateFormula(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $date = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'date'));
        $total = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'total'));
        $balance = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'balance'));
        $formula = new ExpressionList([
            new FieldReferenceExpression(new Table('invoice'), 'total'),
            new ConstantExpression('-', false),
            new FieldReferenceExpression(new Table('invoice'), 'balance'),
        ]);
        $amountPaid = new SelectColumn($formula);
        $fields = new Fields([$number, $date, $total, $balance, $amountPaid]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'balance'), '>', 0),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sortField = new SortField(new FieldReferenceExpression(new Table('invoice'), 'balance'), false);
        $sort = new Sort([$sortField]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,invoice_1.date AS date_2,invoice_1.total AS total_3,invoice_1.balance AS balance_4,(invoice_1.total - invoice_1.balance) AS formula_5,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.balance>? ORDER BY invoice_1.balance DESC LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([0], $context->getParams());
    }

    public function testGenerateGrouping(): void
    {
        $context = new SqlContext();

        $joins = new Joins([new JoinCondition(new Table('invoice'), new Table('customer'), ['parent_column' => 'customer'])]);
        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $date = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'date'));
        $total = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'total'));
        $balance = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'balance'));
        $fields = new Fields([$number, $date, $total, $balance]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'balance'), '>', 0),
        ];
        $filter = new Filter($conditions);
        $group = new Group([
            new GroupField(new FieldReferenceExpression(new Table('customer'), 'name'), true, false),
        ]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,invoice_1.date AS date_2,invoice_1.total AS total_3,invoice_1.balance AS balance_4,invoice_1.id AS invoice_reference,customer_2.id AS customer_reference,customer_2.name AS group_name FROM Invoices invoice_1 LEFT JOIN Customers customer_2 ON invoice_1.customer=customer_2.id WHERE invoice_1.balance>? GROUP BY customer_2.name LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([0], $context->getParams());
    }

    public function testGenerateGroupingExpanded(): void
    {
        $context = new SqlContext();

        $joins = new Joins([new JoinCondition(new Table('invoice'), new Table('customer'), ['parent_column' => 'customer'])]);
        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $date = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'date'));
        $total = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'total'));
        $balance = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'balance'));
        $fields = new Fields([$number, $date, $total, $balance]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'balance'), '>', 0),
        ];
        $filter = new Filter($conditions);
        $group = new Group([
            new GroupField(new FieldReferenceExpression(new Table('customer'), 'name'), true, true),
        ]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,invoice_1.date AS date_2,invoice_1.total AS total_3,invoice_1.balance AS balance_4,invoice_1.id AS invoice_reference,customer_2.id AS customer_reference,customer_2.name AS group_name FROM Invoices invoice_1 LEFT JOIN Customers customer_2 ON invoice_1.customer=customer_2.id WHERE invoice_1.balance>? ORDER BY customer_2.name LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([0], $context->getParams());
    }

    public function testGenerateCustomerBalance(): void
    {
        $context = new SqlContext();

        $name = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'));
        $balance = new SelectColumn(
            new FieldReferenceExpression(new Table('customer'), 'balance'),
            type: ColumnType::Money,
        );
        $fields = new Fields([$name, $balance]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('customer'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_1.name AS name_1,IFNULL((SELECT SUM(balance) FROM Invoices WHERE customer=customer_1.id AND closed=0 AND draft=0 AND date <= UNIX_TIMESTAMP()), 0) - IFNULL((SELECT SUM(balance) FROM CreditNotes WHERE customer=customer_1.id AND closed=0 AND draft=0 AND date <= UNIX_TIMESTAMP()), 0) AS balance_2,customer_1.id AS customer_reference FROM Customers customer_1 WHERE customer_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateCustomerCreditBalance(): void
    {
        $context = new SqlContext(['$currency' => 'usd']);

        $name = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'));
        $balance = new SelectColumn(
            new FieldReferenceExpression(new Table('customer'), 'credit_balance'),
            type: ColumnType::Money,
        );
        $fields = new Fields([$name, $balance]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', -1),
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'credit_balance'), '>', 0),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('customer'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_1.name AS name_1,IFNULL((SELECT balance FROM CreditBalances WHERE customer_id=customer_1.id AND `timestamp` <= UNIX_TIMESTAMP() AND currency=? ORDER BY `timestamp` DESC,transaction_id DESC LIMIT 1), 0) AS credit_balance_2,customer_1.id AS customer_reference FROM Customers customer_1 WHERE customer_1.tenant_id=? AND IFNULL((SELECT balance FROM CreditBalances WHERE customer_id=customer_1.id AND `timestamp` <= UNIX_TIMESTAMP() AND currency=? ORDER BY `timestamp` DESC,transaction_id DESC LIMIT 1), 0)>? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals(['usd', -1, 'usd', 0], $context->getParams());
    }

    public function testGenerateMetadata(): void
    {
        $context = new SqlContext();

        $name = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'));
        $number = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'number'));
        $joins = new Joins([]);
        $salesRep = new SelectColumn(new FieldReferenceExpression(new Table('metadata', 'customer'), 'sales_rep', metadataObject: 'customer'));
        $fields = new Fields([$name, $number, $salesRep]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('customer'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_1.name AS name_1,customer_1.number AS number_2,(SELECT `value` FROM Metadata WHERE tenant_id=customer_1.tenant_id AND `key`="sales_rep" AND object_type="customer" AND object_id=customer_1.id) AS sales_rep_3,customer_1.id AS customer_reference FROM Customers customer_1 WHERE customer_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateSaleMetadataFilter(): void
    {
        $context = new SqlContext();

        $name = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'));
        $number = new SelectColumn(new FieldReferenceExpression(new Table('sale'), 'number'));
        $joins = new Joins([
            new JoinCondition(new Table('sale'), new Table('customer'), ['parent_column' => 'customer']),
        ]);
        $fields = new Fields([$name, $number]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', -1),
            new FilterCondition(new FieldReferenceExpression(new Table('metadata', 'sale'), 'tenant_id', metadataObject: 'sale'), '=', 'Jared King'),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('sale'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_1.name AS name_1,sale_2.number AS number_2,CONCAT(sale_2.type,"-",sale_2.id) AS sale_reference,customer_1.id AS customer_reference FROM (SELECT "invoice" AS `type`,invoice_3.tenant_id AS tenant_id,invoice_3.customer AS customer,invoice_3.balance AS balance,invoice_3.closed AS closed,invoice_3.created_at AS created_at,invoice_3.currency AS currency,invoice_3.date AS date,invoice_3.due_date AS due_date,invoice_3.date_voided AS date_voided,invoice_3.draft AS draft,invoice_3.id AS id,invoice_3.name AS name,invoice_3.notes AS notes,invoice_3.number AS number,invoice_3.paid AS paid,invoice_3.purchase_order AS purchase_order,invoice_3.sent AS sent,invoice_3.status AS status,invoice_3.subtotal AS subtotal,invoice_3.total AS total,invoice_3.updated_at AS updated_at,invoice_3.viewed AS viewed,invoice_3.voided AS voided FROM Invoices invoice_3 LEFT JOIN Customers customer_1 ON invoice_3.customer=customer_1.id WHERE customer_1.tenant_id=? AND (SELECT `value` FROM Metadata WHERE tenant_id=invoice_3.tenant_id AND `key`="tenant_id" AND object_type IN ("credit_note", "invoice") AND object_id=invoice_3.id)=? UNION ALL SELECT "credit_note" AS `type`,credit_note_4.tenant_id AS tenant_id,credit_note_4.customer AS customer,(- credit_note_4.balance) AS balance,credit_note_4.closed AS closed,credit_note_4.created_at AS created_at,credit_note_4.currency AS currency,credit_note_4.date AS date,NULL AS due_date,credit_note_4.date_voided AS date_voided,credit_note_4.draft AS draft,credit_note_4.id AS id,credit_note_4.name AS name,credit_note_4.notes AS notes,credit_note_4.number AS number,credit_note_4.paid AS paid,credit_note_4.purchase_order AS purchase_order,credit_note_4.sent AS sent,credit_note_4.status AS status,(- credit_note_4.subtotal) AS subtotal,(- credit_note_4.total) AS total,credit_note_4.updated_at AS updated_at,credit_note_4.viewed AS viewed,credit_note_4.voided AS voided FROM CreditNotes credit_note_4 LEFT JOIN Customers customer_1 ON credit_note_4.customer=customer_1.id WHERE customer_1.tenant_id=? AND (SELECT `value` FROM Metadata WHERE tenant_id=credit_note_4.tenant_id AND `key`="tenant_id" AND object_type IN ("credit_note", "invoice") AND object_id=credit_note_4.id)=?) sale_2 LEFT JOIN Customers customer_1 ON sale_2.customer=customer_1.id LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1, 'Jared King', -1, 'Jared King'], $context->getParams());
    }

    public function testGenerateCustomerPortalEventTable(): void
    {
        $context = new SqlContext();

        $fields = new Fields([
            new SelectColumn(new FieldReferenceExpression(new Table('customer_portal_event'), 'event')),
            new SelectColumn(new FieldReferenceExpression(new Table('customer_portal_event'), 'timestamp')),
        ]);
        $joins = new Joins([]);
        $filter = new Filter([
            new FilterCondition(new FieldReferenceExpression(new Table('customer_portal_event'), 'tenant_id'), '=', -1),
        ]);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('customer_portal_event'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_portal_event_1.event AS event_1,customer_portal_event_1.timestamp AS timestamp_2 FROM CustomerPortalEvents customer_portal_event_1 WHERE customer_portal_event_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateSaleLineItemTable(): void
    {
        $context = new SqlContext();

        $fields = new Fields([
            new SelectColumn(new FieldReferenceExpression(new Table('sale'), 'number')),
            new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name')),
            new SelectColumn(new FieldReferenceExpression(new Table('sale_line_item'), 'name')),
            new SelectColumn(
                new FieldReferenceExpression(new Table('sale_line_item'), 'quantity'),
                type: ColumnType::Float,
            ),
            new SelectColumn(
                new FieldReferenceExpression(new Table('sale_line_item'), 'unit_cost'),
                type: ColumnType::Money,
            ),
            new SelectColumn(
                new FieldReferenceExpression(new Table('sale_line_item'), 'amount'),
                type: ColumnType::Money,
            ),
        ]);
        $joins = new Joins([
            new JoinCondition(new Table('sale_line_item'), new Table('sale')),
            new JoinCondition(new Table('sale'), new Table('customer'), ['parent_column' => 'customer']),
        ]);
        $filter = new Filter([
            new FilterCondition(new FieldReferenceExpression(new Table('sale_line_item'), 'tenant_id'), '=', -1),
            new FilterCondition(new FieldReferenceExpression(new Table('sale'), 'voided'), '=', false),
        ]);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('sale_line_item'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT sale_1.number AS number_1,customer_2.name AS name_2,sale_line_item_3.name AS name_3,sale_line_item_3.quantity AS quantity_4,sale_line_item_3.unit_cost AS unit_cost_5,sale_line_item_3.amount AS amount_6,CONCAT(sale_line_item_3.object_type,"-",sale_line_item_3.id) AS sale_line_item_reference,CONCAT(sale_1.type,"-",sale_1.id) AS sale_reference,customer_2.id AS customer_reference FROM (SELECT "invoice_line_item" AS object_type,"invoice" AS sale_type,invoice_line_item_4.invoice_id AS sale_id,invoice_line_item_4.tenant_id AS tenant_id,invoice_line_item_4.amount AS amount,invoice_line_item_4.catalog_item_id AS catalog_item_id,invoice_line_item_4.created_at AS created_at,invoice_line_item_4.description AS description,invoice_line_item_4.discountable AS discountable,invoice_line_item_4.id AS id,invoice_line_item_4.name AS name,invoice_line_item_4.period_end AS period_end,invoice_line_item_4.period_start AS period_start,invoice_line_item_4.plan AS plan,invoice_line_item_4.plan_id AS plan_id,invoice_line_item_4.prorated AS prorated,invoice_line_item_4.quantity AS quantity,invoice_line_item_4.subscription_id AS subscription_id,invoice_line_item_4.taxable AS taxable,invoice_line_item_4.type AS type,invoice_line_item_4.unit_cost AS unit_cost,invoice_line_item_4.updated_at AS updated_at FROM LineItems invoice_line_item_4 LEFT JOIN Invoices invoice_5 ON invoice_line_item_4.invoice_id=invoice_5.id LEFT JOIN Customers customer_2 ON invoice_5.customer=customer_2.id WHERE invoice_line_item_4.tenant_id=? AND invoice_5.voided=? AND invoice_line_item_4.invoice_id IS NOT NULL UNION ALL SELECT "credit_note_line_item" AS object_type,"credit_note" AS sale_type,credit_note_line_item_6.credit_note_id AS sale_id,credit_note_line_item_6.tenant_id AS tenant_id,(- credit_note_line_item_6.amount) AS amount,credit_note_line_item_6.catalog_item_id AS catalog_item_id,credit_note_line_item_6.created_at AS created_at,credit_note_line_item_6.description AS description,credit_note_line_item_6.discountable AS discountable,credit_note_line_item_6.id AS id,credit_note_line_item_6.name AS name,credit_note_line_item_6.period_end AS period_end,credit_note_line_item_6.period_start AS period_start,credit_note_line_item_6.plan AS plan,credit_note_line_item_6.plan_id AS plan_id,credit_note_line_item_6.prorated AS prorated,(- credit_note_line_item_6.quantity) AS quantity,credit_note_line_item_6.subscription_id AS subscription_id,credit_note_line_item_6.taxable AS taxable,credit_note_line_item_6.type AS type,(- credit_note_line_item_6.unit_cost) AS unit_cost,credit_note_line_item_6.updated_at AS updated_at FROM LineItems credit_note_line_item_6 LEFT JOIN CreditNotes credit_note_7 ON credit_note_line_item_6.credit_note_id=credit_note_7.id LEFT JOIN Customers customer_2 ON credit_note_7.customer=customer_2.id WHERE credit_note_line_item_6.tenant_id=? AND credit_note_7.voided=? AND credit_note_line_item_6.credit_note_id IS NOT NULL) sale_line_item_3 LEFT JOIN (SELECT "invoice" AS `type`,invoice_5.tenant_id AS tenant_id,invoice_5.customer AS customer,invoice_5.balance AS balance,invoice_5.closed AS closed,invoice_5.created_at AS created_at,invoice_5.currency AS currency,invoice_5.date AS date,invoice_5.due_date AS due_date,invoice_5.date_voided AS date_voided,invoice_5.draft AS draft,invoice_5.id AS id,invoice_5.name AS name,invoice_5.notes AS notes,invoice_5.number AS number,invoice_5.paid AS paid,invoice_5.purchase_order AS purchase_order,invoice_5.sent AS sent,invoice_5.status AS status,invoice_5.subtotal AS subtotal,invoice_5.total AS total,invoice_5.updated_at AS updated_at,invoice_5.viewed AS viewed,invoice_5.voided AS voided FROM Invoices invoice_5 WHERE invoice_5.tenant_id=? UNION ALL SELECT "credit_note" AS `type`,credit_note_7.tenant_id AS tenant_id,credit_note_7.customer AS customer,(- credit_note_7.balance) AS balance,credit_note_7.closed AS closed,credit_note_7.created_at AS created_at,credit_note_7.currency AS currency,credit_note_7.date AS date,NULL AS due_date,credit_note_7.date_voided AS date_voided,credit_note_7.draft AS draft,credit_note_7.id AS id,credit_note_7.name AS name,credit_note_7.notes AS notes,credit_note_7.number AS number,credit_note_7.paid AS paid,credit_note_7.purchase_order AS purchase_order,credit_note_7.sent AS sent,credit_note_7.status AS status,(- credit_note_7.subtotal) AS subtotal,(- credit_note_7.total) AS total,credit_note_7.updated_at AS updated_at,credit_note_7.viewed AS viewed,credit_note_7.voided AS voided FROM CreditNotes credit_note_7 WHERE credit_note_7.tenant_id=?) sale_1 ON sale_line_item_3.sale_id=sale_1.id LEFT JOIN Customers customer_2 ON sale_1.customer=customer_2.id LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1, false, -1, false, -1, -1], $context->getParams());
    }

    public function testGenerateAndOr(): void
    {
        $context = new SqlContext();

        $name = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'));
        $number = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'number'));
        $country = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'country'));
        $fields = new Fields([$name, $number, $country]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', -1),
            new FilterCondition(null, 'and', [
                new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'autopay_delay_days'), '>', 0),
                new FilterCondition(null, 'or', [
                    new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'country'), '=', 'CA'),
                    new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'country'), '=', 'US'),
                ]),
            ]),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('customer'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_1.name AS name_1,customer_1.number AS number_2,customer_1.country AS country_3,customer_1.id AS customer_reference FROM Customers customer_1 WHERE customer_1.tenant_id=? AND (customer_1.autopay_delay_days>? AND (customer_1.country=? OR customer_1.country=?)) LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1, 0, 'CA', 'US'], $context->getParams());
    }

    public function testGenerateMissingParameter(): void
    {
        $this->expectException(ReportException::class);

        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $date = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'date'));
        $total = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'total'));
        $balance = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'balance'));
        $fields = new Fields([$number, $date, $total, $balance]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'balance'), '>', 0),
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'date'), '>=', '$date'),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sortField = new SortField(new FieldReferenceExpression(new Table('invoice'), 'balance'), false);
        $sort = new Sort([$sortField]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT Invoices.number AS number_1,Invoices.date AS date_2,Invoices.total AS total_3,Invoices.balance AS balance_4,Invoices.id AS invoice_reference FROM Invoices WHERE Invoices.balance>? ORDER BY Invoices.balance DESC LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([0], $context->getParams());
    }

    public function testGenerateParameters(): void
    {
        $currentTime = time();
        $context = new SqlContext(['$date' => $currentTime]);

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $date = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'date'));
        $total = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'total'));
        $balance = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'balance'));
        $fields = new Fields([$number, $date, $total, $balance]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'balance'), '>', 0),
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'date'), '>=', '$date'),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sortField = new SortField(new FieldReferenceExpression(new Table('invoice'), 'balance'), false);
        $sort = new Sort([$sortField]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,invoice_1.date AS date_2,invoice_1.total AS total_3,invoice_1.balance AS balance_4,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.balance>? AND invoice_1.date>=? ORDER BY invoice_1.balance DESC LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([0, $currentTime], $context->getParams());
    }

    public function testGenerateLineItem(): void
    {
        $date = time();
        $context = new SqlContext(['$date' => $date]);

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $name = new SelectColumn(new FieldReferenceExpression(new Table('invoice_line_item'), 'name'));
        $quantity = new SelectColumn(
            new FieldReferenceExpression(new Table('invoice_line_item'), 'quantity'),
            type: ColumnType::Float,
        );
        $total = new SelectColumn(
            new FieldReferenceExpression(new Table('invoice_line_item'), 'amount'),
            type: ColumnType::Money,
        );
        $joins = new Joins([new JoinCondition(new Table('invoice_line_item'), new Table('invoice'))]);
        $fields = new Fields([$number, $name, $quantity, $total]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice_line_item'), 'quantity'), '>', 1),
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'date'), '>=', '$date'),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sortField = new SortField(new FieldReferenceExpression(new Table('invoice_line_item'), 'amount'), false);
        $sort = new Sort([$sortField]);
        $query = new DataQuery(new Table('invoice_line_item'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,invoice_line_item_2.name AS name_2,invoice_line_item_2.quantity AS quantity_3,invoice_line_item_2.amount AS amount_4,invoice_line_item_2.id AS invoice_line_item_reference,invoice_1.id AS invoice_reference FROM LineItems invoice_line_item_2 LEFT JOIN Invoices invoice_1 ON invoice_line_item_2.invoice_id=invoice_1.id WHERE invoice_line_item_2.quantity>? AND invoice_1.date>=? AND invoice_line_item_2.invoice_id IS NOT NULL ORDER BY invoice_line_item_2.amount DESC LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([1, $date], $context->getParams());
    }

    public function testGenerateAging(): void
    {
        $currentTime = 1622410329;
        $context = new SqlContext([
            '$currency' => 'usd',
            '$date' => $currentTime,
        ]);

        $number = new SelectColumn(new FieldReferenceExpression(new Table('sale'), 'number'));
        $date = new SelectColumn(
            new FieldReferenceExpression(new Table('sale'), 'date'),
            type: ColumnType::Date,
        );
        $subtotal = new SelectColumn(
            new FieldReferenceExpression(new Table('sale'), 'subtotal'),
            type: ColumnType::Money,
        );
        $total = new SelectColumn(
            new FieldReferenceExpression(new Table('sale'), 'total'),
            type: ColumnType::Money,
        );
        $age1 = new SelectColumn(
            new FunctionExpression('age_range', new ExpressionList([
                new FieldReferenceExpression(new Table('sale'), 'date'),
                new FieldReferenceExpression(new Table('sale'), 'balance'),
                new ConstantExpression('0', false),
                new ConstantExpression('30', false),
                new ConstantExpression((string) $currentTime, false),
            ])),
            type: ColumnType::Money,
        );
        $age2 = new SelectColumn(
            new FunctionExpression('age_range', new ExpressionList([
                new FieldReferenceExpression(new Table('sale'), 'date'),
                new FieldReferenceExpression(new Table('sale'), 'balance'),
                new ConstantExpression('31', false),
                new ConstantExpression('60', false),
                new ConstantExpression((string) $currentTime, false),
            ])),
            type: ColumnType::Money,
        );
        $age3 = new SelectColumn(
            new FunctionExpression('age_range', new ExpressionList([
            new FieldReferenceExpression(new Table('sale'), 'date'),
            new FieldReferenceExpression(new Table('sale'), 'balance'),
            new ConstantExpression('61', false),
            new ConstantExpression('90', false),
            new ConstantExpression((string) $currentTime, false), ])),
            type: ColumnType::Money, );
        $age4 = new SelectColumn(
            new FunctionExpression('age_range', new ExpressionList([
            new FieldReferenceExpression(new Table('sale'), 'date'),
            new FieldReferenceExpression(new Table('sale'), 'balance'),
            new ConstantExpression('91', false),
            new ConstantExpression('120', false),
            new ConstantExpression((string) $currentTime, false), ])),
            type: ColumnType::Money, );
        $age5 = new SelectColumn(
            new FunctionExpression('age_range', new ExpressionList([
            new FieldReferenceExpression(new Table('sale'), 'date'),
            new FieldReferenceExpression(new Table('sale'), 'balance'),
            new ConstantExpression('120', false),
            new ConstantExpression('*1', false),
            new ConstantExpression((string) $currentTime, false), ])),
            type: ColumnType::Money, );
        $balance = new SelectColumn(
            new FieldReferenceExpression(new Table('sale'), 'balance'),
            type: ColumnType::Money,
        );
        $joins = new Joins([new JoinCondition(new Table('sale'), new Table('customer'), ['parent_column' => 'customer'])]);
        $fields = new Fields([$number, $date, $subtotal, $total, $age1, $age2, $age3, $age4, $age5, $balance]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('sale'), 'tenant_id'), '=', -1),
            new FilterCondition(new FieldReferenceExpression(new Table('sale'), 'paid'), '=', false),
            new FilterCondition(new FieldReferenceExpression(new Table('sale'), 'closed'), '=', false),
            new FilterCondition(new FieldReferenceExpression(new Table('sale'), 'voided'), '=', false),
            new FilterCondition(new FieldReferenceExpression(new Table('sale'), 'draft'), '=', false),
            new FilterCondition(new FieldReferenceExpression(new Table('sale'), 'currency'), '=', '$currency'),
            new FilterCondition(new FieldReferenceExpression(new Table('sale'), 'date'), '<=', '$date'),
        ];
        $filter = new Filter($conditions);
        $group = new Group([new GroupField(new FieldReferenceExpression(new Table('customer'), 'name'), true, true)]);
        $sort = new Sort([new SortField(new FieldReferenceExpression(new Table('sale'), 'date'), true)]);
        $query = new DataQuery(new Table('sale'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT sale_1.number AS number_1,sale_1.date AS date_2,sale_1.subtotal AS subtotal_3,sale_1.total AS total_4,CASE WHEN FLOOR((1622410329 - sale_1.date) / 86400) BETWEEN 0 AND 30 THEN sale_1.balance ELSE 0 END AS function_5,CASE WHEN FLOOR((1622410329 - sale_1.date) / 86400) BETWEEN 31 AND 60 THEN sale_1.balance ELSE 0 END AS function_6,CASE WHEN FLOOR((1622410329 - sale_1.date) / 86400) BETWEEN 61 AND 90 THEN sale_1.balance ELSE 0 END AS function_7,CASE WHEN FLOOR((1622410329 - sale_1.date) / 86400) BETWEEN 91 AND 120 THEN sale_1.balance ELSE 0 END AS function_8,CASE WHEN FLOOR((1622410329 - sale_1.date) / 86400) >= 120 THEN sale_1.balance ELSE 0 END AS function_9,sale_1.balance AS balance_10,CONCAT(sale_1.type,"-",sale_1.id) AS sale_reference,customer_2.id AS customer_reference,customer_2.name AS group_name FROM (SELECT "invoice" AS `type`,invoice_3.tenant_id AS tenant_id,invoice_3.customer AS customer,invoice_3.balance AS balance,invoice_3.closed AS closed,invoice_3.created_at AS created_at,invoice_3.currency AS currency,invoice_3.date AS date,invoice_3.due_date AS due_date,invoice_3.date_voided AS date_voided,invoice_3.draft AS draft,invoice_3.id AS id,invoice_3.name AS name,invoice_3.notes AS notes,invoice_3.number AS number,invoice_3.paid AS paid,invoice_3.purchase_order AS purchase_order,invoice_3.sent AS sent,invoice_3.status AS status,invoice_3.subtotal AS subtotal,invoice_3.total AS total,invoice_3.updated_at AS updated_at,invoice_3.viewed AS viewed,invoice_3.voided AS voided FROM Invoices invoice_3 LEFT JOIN Customers customer_2 ON invoice_3.customer=customer_2.id WHERE invoice_3.tenant_id=? AND invoice_3.paid=? AND invoice_3.closed=? AND invoice_3.voided=? AND invoice_3.draft=? AND invoice_3.currency=? AND invoice_3.date<=? UNION ALL SELECT "credit_note" AS `type`,credit_note_4.tenant_id AS tenant_id,credit_note_4.customer AS customer,(- credit_note_4.balance) AS balance,credit_note_4.closed AS closed,credit_note_4.created_at AS created_at,credit_note_4.currency AS currency,credit_note_4.date AS date,NULL AS due_date,credit_note_4.date_voided AS date_voided,credit_note_4.draft AS draft,credit_note_4.id AS id,credit_note_4.name AS name,credit_note_4.notes AS notes,credit_note_4.number AS number,credit_note_4.paid AS paid,credit_note_4.purchase_order AS purchase_order,credit_note_4.sent AS sent,credit_note_4.status AS status,(- credit_note_4.subtotal) AS subtotal,(- credit_note_4.total) AS total,credit_note_4.updated_at AS updated_at,credit_note_4.viewed AS viewed,credit_note_4.voided AS voided FROM CreditNotes credit_note_4 LEFT JOIN Customers customer_2 ON credit_note_4.customer=customer_2.id WHERE credit_note_4.tenant_id=? AND credit_note_4.paid=? AND credit_note_4.closed=? AND credit_note_4.voided=? AND credit_note_4.draft=? AND credit_note_4.currency=? AND credit_note_4.date<=?) sale_1 LEFT JOIN Customers customer_2 ON sale_1.customer=customer_2.id ORDER BY customer_2.name,sale_1.date LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1, false, false, false, false, 'usd', $currentTime, -1, false, false, false, false, 'usd', $currentTime], $context->getParams());
    }

    public function testGenerateAge(): void
    {
        $currentTime = 1622410329;
        $context = new SqlContext([
            '$currency' => 'usd',
            '$date' => $currentTime,
        ]);

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $age = new SelectColumn(
            new FunctionExpression('age', new ExpressionList([
            new FieldReferenceExpression(new Table('invoice'), 'date'),
            new ConstantExpression((string) $currentTime, false), ])),
            type: ColumnType::Integer, );
        $subtotal = new SelectColumn(
            new FieldReferenceExpression(new Table('invoice'), 'subtotal'),
            type: ColumnType::Money,
        );
        $total = new SelectColumn(
            new FieldReferenceExpression(new Table('invoice'), 'total'),
            type: ColumnType::Money,
        );
        $balance = new SelectColumn(
            new FieldReferenceExpression(new Table('invoice'), 'balance'),
            type: ColumnType::Money,
        );
        $joins = new Joins([]);
        $fields = new Fields([$number, $age, $subtotal, $total, $balance]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'tenant_id'), '=', -1),
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'currency'), '=', '$currency'),
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'date'), '<=', '$date'),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([new SortField(new FieldReferenceExpression(new Table('invoice'), 'date'), true)]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,FLOOR((1622410329 - invoice_1.date) / 86400) AS function_2,invoice_1.subtotal AS subtotal_3,invoice_1.total AS total_4,invoice_1.balance AS balance_5,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.tenant_id=? AND invoice_1.currency=? AND invoice_1.date<=? ORDER BY invoice_1.date LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1, 'usd', $currentTime], $context->getParams());
    }

    public function testGenerateDateEqual(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $fields = new Fields([$number]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'date', ColumnType::Date), '=', '2021-05-31'),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.date BETWEEN ? AND ? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([1622419200, 1622505599], $context->getParams());
    }

    public function testGenerateDateNotEqual(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $fields = new Fields([$number]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'date', ColumnType::Date), '<>', '2021-05-31'),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.date NOT BETWEEN ? AND ? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([1622419200, 1622505599], $context->getParams());
    }

    public function testGenerateDateBetween(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $fields = new Fields([$number]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'date', ColumnType::Date), 'between', ['start' => '2020-05-31', 'end' => '2021-05-31']),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.date BETWEEN ? AND ? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([1590883200, 1622505599], $context->getParams());
    }

    public function testGenerateDateTimeEqual(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('invoice'), 'number'));
        $fields = new Fields([$number]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('invoice'), 'created_at', ColumnType::DateTime, dateFormat: 'Y-m-d H:i:s'), '=', '2021-05-31 12:00:00'),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('invoice'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT invoice_1.number AS number_1,invoice_1.id AS invoice_reference FROM Invoices invoice_1 WHERE invoice_1.created_at=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals(['2021-05-31 12:00:00'], $context->getParams());
    }

    public function testGenerateDateTimeBetween(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('payment'), 'reference'));
        $fields = new Fields([$number]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('payment'), 'date', ColumnType::DateTime), 'between', ['start' => '2020-05-31 12:00:00', 'end' => '2021-05-31 01:02:03']),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('payment'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT payment_1.reference AS reference_1,payment_1.id AS payment_reference FROM Payments payment_1 WHERE payment_1.date BETWEEN ? AND ? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([1590926400, 1622422923], $context->getParams());
    }

    public function testGenerateDateFilterComparison(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('payment'), 'reference'));
        $fields = new Fields([$number]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('payment'), 'date', ColumnType::DateTime), '<', new FieldReferenceExpression(new Table('payment'), 'created_at', ColumnType::DateTime)),
            new FilterCondition(new FieldReferenceExpression(new Table('payment'), 'date', ColumnType::Date), '<', new FieldReferenceExpression(new Table('payment'), 'created_at', ColumnType::Date)),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('payment'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT payment_1.reference AS reference_1,payment_1.id AS payment_reference FROM Payments payment_1 WHERE payment_1.date<payment_1.created_at AND payment_1.date<payment_1.created_at LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([], $context->getParams());
    }

    public function testGenerateContains(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'number'));
        $fields = new Fields([$number]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'name', ColumnType::String), 'contains', 'test'),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('customer'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_1.number AS number_1,customer_1.id AS customer_reference FROM Customers customer_1 WHERE customer_1.name LIKE ? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals(['%test%'], $context->getParams());
    }

    public function testGenerateNotContains(): void
    {
        $context = new SqlContext();

        $number = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'number'));
        $fields = new Fields([$number]);
        $joins = new Joins([]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'name', ColumnType::String), 'not_contains', 'test'),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('customer'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_1.number AS number_1,customer_1.id AS customer_reference FROM Customers customer_1 WHERE customer_1.name NOT LIKE ? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals(['%test%'], $context->getParams());
    }

    public function testGenerateSameObjectJoin(): void
    {
        $context = new SqlContext();

        $name = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'));
        $number = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'number'));
        $parentName = new SelectColumn(new FieldReferenceExpression(new Table('customer', 'parent_customer'), 'name'));
        $joins = new Joins([new JoinCondition(new Table('customer'), new Table('customer', 'parent_customer'), ['parent_column' => 'parent_customer'])]);
        $fields = new Fields([$name, $number, $parentName]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('customer'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_1.name AS name_1,customer_1.number AS number_2,parent_customer_2.name AS name_3,customer_1.id AS customer_reference,parent_customer_2.id AS parent_customer_reference FROM Customers customer_1 LEFT JOIN Customers parent_customer_2 ON customer_1.parent_customer=parent_customer_2.id WHERE customer_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }

    public function testGenerateMemberUserJoin(): void
    {
        $context = new SqlContext();

        $name = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'));
        $number = new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'number'));
        $userName = new SelectColumn(new FieldReferenceExpression(new Table('user'), 'name'));
        $joins = new Joins([
            new JoinCondition(new Table('customer'), new Table('member', 'owner'), ['parent_column' => 'user_id', 'join_column' => 'user_id']),
            new JoinCondition(new Table('member', 'owner'), new Table('user')),
        ]);
        $fields = new Fields([$name, $number, $userName]);
        $conditions = [
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', -1),
        ];
        $filter = new Filter($conditions);
        $group = new Group([]);
        $sort = new Sort([]);
        $query = new DataQuery(new Table('customer'), $joins, $fields, $filter, $group, $sort, 10000);

        $expected = 'SELECT customer_1.name AS name_1,customer_1.number AS number_2,CONCAT(user_2.first_name, " ", user_2.last_name) AS name_3,customer_1.id AS customer_reference,owner_3.id AS owner_reference,user_2.id AS user_reference FROM Customers customer_1 LEFT JOIN Members owner_3 ON customer_1.user_id=owner_3.user_id AND owner_3.tenant_id=customer_1.tenant_id LEFT JOIN Users user_2 ON owner_3.user_id=user_2.id WHERE customer_1.tenant_id=? LIMIT 10000';
        $this->assertEquals($expected, SqlGenerator::generate($query, $context));
        $this->assertEquals([-1], $context->getParams());
    }
}
