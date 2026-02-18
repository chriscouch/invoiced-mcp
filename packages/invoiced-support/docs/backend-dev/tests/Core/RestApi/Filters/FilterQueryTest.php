<?php

namespace App\Tests\Core\RestApi\Filters;

use App\AccountsReceivable\Models\Customer;
use App\Core\Orm\Query;
use App\Core\RestApi\Enum\FilterOperator;
use App\Core\RestApi\Filters\FilterQuery;
use App\Core\RestApi\ValueObjects\FilterCondition;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Tests\AppTestCase;

class FilterQueryTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testAddToQuery(): void
    {
        $filters = [];
        foreach (FilterOperator::cases() as $operator) {
            $filters[] = new FilterCondition(
                operator: $operator,
                field: 'name',
                value: 'test',
            );
        }
        $filter = new ListFilter($filters);

        $query = new Query(Customer::class);
        FilterQuery::addToQuery($filter, $query);

        $this->assertEquals([
            ['name', 'test', '='],
            ['name', 'test', '<>'],
            ['name', 'test', '>='],
            ['name', 'test', '>'],
            ['name', 'test', '<'],
            ['name', 'test', '<='],
            ['name', 'test%', 'LIKE'],
            ['name', '%test', 'LIKE'],
            ['name', '%test%', 'LIKE'],
            ['name', '%test%', 'NOT LIKE'],
            ['name', null, '='],
            ['name', null, '<>'],
        ], $query->getWhere());
    }

    public function testAddToQueryMetadata(): void
    {
        $filters = [];
        foreach (FilterOperator::cases() as $operator) {
            $filters[] = new FilterCondition(
                operator: $operator,
                field: 'metadata.custom_field',
                value: 'test',
            );
        }
        $filter = new ListFilter($filters);

        $query = new Query(Customer::class);
        FilterQuery::addToQuery($filter, $query);

        $this->assertEquals([
            'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id.' AND `object_type`="customer" AND object_id=Customers.id AND `key`=\'custom_field\' AND `value` = \'test\')',
            'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id.' AND `object_type`="customer" AND object_id=Customers.id AND `key`=\'custom_field\' AND `value` <> \'test\')',
            'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id.' AND `object_type`="customer" AND object_id=Customers.id AND `key`=\'custom_field\' AND `value` >= \'test\')',
            'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id.' AND `object_type`="customer" AND object_id=Customers.id AND `key`=\'custom_field\' AND `value` > \'test\')',
            'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id.' AND `object_type`="customer" AND object_id=Customers.id AND `key`=\'custom_field\' AND `value` < \'test\')',
            'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id.' AND `object_type`="customer" AND object_id=Customers.id AND `key`=\'custom_field\' AND `value` <= \'test\')',
            'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id.' AND `object_type`="customer" AND object_id=Customers.id AND `key`=\'custom_field\' AND `value` LIKE \'test%\')',
            'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id.' AND `object_type`="customer" AND object_id=Customers.id AND `key`=\'custom_field\' AND `value` LIKE \'%test\')',
            'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id.' AND `object_type`="customer" AND object_id=Customers.id AND `key`=\'custom_field\' AND `value` LIKE \'%test%\')',
            'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id.' AND `object_type`="customer" AND object_id=Customers.id AND `key`=\'custom_field\' AND `value` NOT LIKE \'%test%\')',
            '(SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id.' AND `object_type`="customer" AND object_id=Customers.id AND `key`=\'custom_field\') IS NULL',
            '(SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id.' AND `object_type`="customer" AND object_id=Customers.id AND `key`=\'custom_field\') IS NOT NULL',
        ], $query->getWhere());
    }
}
