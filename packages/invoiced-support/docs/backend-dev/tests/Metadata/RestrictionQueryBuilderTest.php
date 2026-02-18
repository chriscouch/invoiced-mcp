<?php

namespace App\Tests\Metadata;

use App\Companies\Models\Company;
use App\Metadata\ValueObjects\CustomFieldRestriction;
use App\Metadata\Libs\RestrictionQueryBuilder;
use App\Tests\AppTestCase;
use App\Core\Orm\Query;
use App\Tests\Core\Orm\Models\Customer;

class RestrictionQueryBuilderTest extends AppTestCase
{
    public function testBuildSql(): void
    {
        $restrictions = [
            new CustomFieldRestriction('department', ['East', 'West']),
            new CustomFieldRestriction('entity', ['100']),
        ];
        $builder = new RestrictionQueryBuilder(new Company(['id' => 1234]), $restrictions);

        $this->assertEquals('(EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`=1234 AND `object_type`="customer" AND object_id=Customers.id AND `key`=\'department\' AND `value` IN (\'East\',\'West\')) OR EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`=1234 AND `object_type`="customer" AND object_id=Customers.id AND `key`=\'entity\' AND `value` = \'100\'))', $builder->buildSql('Customers.id'));
    }

    public function testBuildSqlNoRestrictions(): void
    {
        $builder = new RestrictionQueryBuilder(new Company(), []);
        $this->assertNull($builder->buildSql('customer_id'));
    }

    public function testAddToOrmQuery(): void
    {
        $restrictions = [
            new CustomFieldRestriction('department', ['East', 'West']),
            new CustomFieldRestriction('entity', ['100']),
        ];
        $builder = new RestrictionQueryBuilder(new Company(['id' => 1234]), $restrictions);
        $query = new Query(Customer::class);

        $builder->addToOrmQuery('customer_id', $query);

        $expected = [
            '(EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`=1234 AND `object_type`="customer" AND object_id=customer_id AND `key`=\'department\' AND `value` IN (\'East\',\'West\')) OR EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`=1234 AND `object_type`="customer" AND object_id=customer_id AND `key`=\'entity\' AND `value` = \'100\'))',
        ];
        $this->assertEquals($expected, $query->getWhere());
    }

    public function testAddToOrmQueryNoRestrictions(): void
    {
        $builder = new RestrictionQueryBuilder(new Company(), []);
        $query = new Query(Customer::class);

        $builder->addToOrmQuery('customer_id', $query);
        $this->assertEquals([], $query->getWhere());
    }
}
