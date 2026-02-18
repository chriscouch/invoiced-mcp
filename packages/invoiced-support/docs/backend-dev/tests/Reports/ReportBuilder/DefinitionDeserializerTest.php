<?php

namespace App\Tests\Reports\ReportBuilder;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\DefinitionDeserializer;
use App\Reports\ReportBuilder\ValueObjects\Definition;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Tests\AppTestCase;

class DefinitionDeserializerTest extends AppTestCase
{
    private static Member $member;
    private static Company $company2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::getService('test.database')->executeStatement('DELETE FROM Members WHERE NOT EXISTS (SELECT 1 FROM Companies WHERE id=tenant_id)');
        self::hasCompany();
        self::$member = Member::where('user_id', self::$company->creator_id)->one();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (isset(self::$company2)) {
            self::$company2->delete();
        }
    }

    public function testDeserializeInvalidJson(): void
    {
        $this->expectException(ReportException::class);
        DefinitionDeserializer::deserialize('not json', self::$company, self::$member);
    }

    public function testDeserializeInvalidConfig(): void
    {
        $this->expectException(ReportException::class);
        DefinitionDeserializer::deserialize('{}', self::$company, self::$member);
    }

    public function testDeserializeInvalidConfig2(): void
    {
        $this->expectException(ReportException::class);
        DefinitionDeserializer::deserialize('{"sections":["test"]}', self::$company, self::$member);
    }

    public function testDeserializeNoSections(): void
    {
        $this->expectException(ReportException::class);
        $json = '{"sections":[]}';
        DefinitionDeserializer::deserialize($json, self::$company, self::$member);
    }

    public function testDeserializeTooManySections(): void
    {
        $this->expectException(ReportException::class);
        $json = (string) file_get_contents(__DIR__.'/data/too_many_sections.json');
        DefinitionDeserializer::deserialize($json, self::$company, self::$member);
    }

    public function testDeserializeNoColumns(): void
    {
        $this->expectException(ReportException::class);
        $json = '{"sections":[{"object":"customer","type":"table","fields":[]}]}';
        DefinitionDeserializer::deserialize($json, self::$company, self::$member);
    }

    public function testDeserializeTooManyColumns(): void
    {
        $this->expectException(ReportException::class);
        $json = (string) file_get_contents(__DIR__.'/data/too_many_columns.json');
        DefinitionDeserializer::deserialize($json, self::$company, self::$member);
    }

    public function testDeserializeTooManyFilters(): void
    {
        $this->expectException(ReportException::class);
        $json = (string) file_get_contents(__DIR__.'/data/too_many_filters.json');
        DefinitionDeserializer::deserialize($json, self::$company, self::$member);
    }

    public function testDeserializeTooManyGroups(): void
    {
        $this->expectException(ReportException::class);
        $json = (string) file_get_contents(__DIR__.'/data/too_many_groups.json');
        DefinitionDeserializer::deserialize($json, self::$company, self::$member);
    }

    public function testDeserializeTooManySort(): void
    {
        $this->expectException(ReportException::class);
        $json = (string) file_get_contents(__DIR__.'/data/too_many_sorts.json');
        DefinitionDeserializer::deserialize($json, self::$company, self::$member);
    }

    public function testDeserializeTooManyJoins(): void
    {
        $this->expectException(ReportException::class);
        $json = (string) file_get_contents(__DIR__.'/data/too_many_joins.json');
        DefinitionDeserializer::deserialize($json, self::$company, self::$member);
    }

    public function testDeserializeCharts(): void
    {
        $json = (string) file_get_contents(__DIR__.'/data/charts.json');
        $config = DefinitionDeserializer::deserialize($json, self::$company, self::$member);

        $this->assertEquals('Chart Test', $config->getTitle());
        $this->assertCount(8, $config->getSections());
        $this->assertHasTenantCondition($config, [self::$company->id()]);
    }

    public function testDeserializeCollectionActivity(): void
    {
        $json = (string) file_get_contents(__DIR__.'/data/collection_activity.json');
        $config = DefinitionDeserializer::deserialize($json, self::$company, self::$member);

        $this->assertEquals('Collection Activity', $config->getTitle());
        $this->assertCount(1, $config->getSections());
        $this->assertHasTenantCondition($config, [self::$company->id()]);

        $dataQuery = $config->getSections()[0]->getDataQuery();
        $fields = $dataQuery->fields->columns;
        $this->assertCount(13, $fields);
        $invoiceCount = $fields[9];
        $this->assertEquals('count', $invoiceCount->expression->getName());
        $table = $dataQuery->table;
        $this->assertEquals('customer', $table->object);
        $joins = $dataQuery->joins->conditions;
        $this->assertCount(3, $joins);
        $this->assertEquals('customer', $joins[0]->parentTable->object);
        $this->assertEquals('parent_customer', $joins[0]->parentColumn);
        $this->assertEquals('LEFT JOIN', $joins[0]->joinType);
        $this->assertEquals('customer', $joins[0]->joinTable->object);
        $this->assertEquals('id', $joins[0]->joinColumn);
        $this->assertEquals('customer', $joins[1]->parentTable->object);
        $this->assertEquals('id', $joins[1]->parentColumn);
        $this->assertEquals('LEFT JOIN', $joins[1]->joinType);
        $this->assertEquals('invoice', $joins[1]->joinTable->object);
        $this->assertEquals('customer', $joins[1]->joinColumn);
        $this->assertEquals('customer', $joins[2]->parentTable->object);
        $this->assertEquals('id', $joins[2]->parentColumn);
        $this->assertEquals('LEFT JOIN', $joins[2]->joinType);
        $this->assertEquals('note', $joins[2]->joinTable->object);
        $this->assertEquals('customer_id', $joins[2]->joinColumn);
        /** @var FieldReferenceExpression $idField */
        $idField = $invoiceCount->expression->arguments[0]; /* @phpstan-ignore-line */
        $this->assertEquals('invoice', $idField->table->object);
        $this->assertEquals('id', $idField->id);
    }

    public function testDeserializeExpiredCards(): void
    {
        $json = (string) file_get_contents(__DIR__.'/data/expired_cards.json');
        $config = DefinitionDeserializer::deserialize($json, self::$company, self::$member);

        $this->assertEquals('Expired Cards', $config->getTitle());
        $this->assertCount(2, $config->getSections());
        $this->assertHasTenantCondition($config, [self::$company->id()]);
    }

    public function testDeserializeFailedCharges(): void
    {
        $json = (string) file_get_contents(__DIR__.'/data/failed_charges.json');
        $config = DefinitionDeserializer::deserialize($json, self::$company, self::$member);

        $this->assertEquals('', $config->getTitle());
        $this->assertCount(2, $config->getSections());
        $this->assertHasTenantCondition($config, [self::$company->id()]);
    }

    public function testDeserializeMetrics(): void
    {
        $json = (string) file_get_contents(__DIR__.'/data/metrics.json');
        $config = DefinitionDeserializer::deserialize($json, self::$company, self::$member);

        $this->assertEquals('Metric Testing', $config->getTitle());
        $this->assertCount(9, $config->getSections());
        $this->assertHasTenantCondition($config, [self::$company->id()]);
    }

    public function testDeserializeMultiEntity(): void
    {
        $json = (string) file_get_contents(__DIR__.'/data/multi_entity_invoice.json');
        $config = DefinitionDeserializer::deserialize($json, self::$company, self::$member);

        $this->assertEquals('Multi-Entity Invoice', $config->getTitle());
        $this->assertCount(1, $config->getSections());
        $this->assertHasTenantCondition($config, [self::$company->id()]);

        // create a second company
        self::getService('test.tenant')->clear();
        self::$company2 = new Company();
        self::$company2->name = 'Second Company';
        self::$company2->username = 'SecondCompany';
        self::$company2->creator_id = self::getService('test.user_context')->get()->id();
        self::$company2->saveOrFail();

        $config = DefinitionDeserializer::deserialize($json, self::$company, self::$member);

        $this->assertEquals('Multi-Entity Invoice', $config->getTitle());
        $this->assertCount(1, $config->getSections());
        $this->assertHasTenantCondition($config, [self::$company->id(), self::$company2->id()]);
    }

    public function testDeserializePaymentStatistics(): void
    {
        $json = (string) file_get_contents(__DIR__.'/data/payment_statistics.json');
        $config = DefinitionDeserializer::deserialize($json, self::$company, self::$member);

        $this->assertEquals('Payment Statistics', $config->getTitle());
        $this->assertCount(2, $config->getSections());
        $this->assertHasTenantCondition($config, [self::$company->id()]);
    }

    public function testDeserializeSalesByRep(): void
    {
        $json = (string) file_get_contents(__DIR__.'/data/sales_by_rep.json');
        $config = DefinitionDeserializer::deserialize($json, self::$company, self::$member);

        $this->assertEquals('Sales by Rep', $config->getTitle());
        $this->assertCount(1, $config->getSections());
        $this->assertHasTenantCondition($config, [self::$company->id()]);
    }

    public function testDeserializeMultiLevelJoin(): void
    {
        $json = (string) file_get_contents(__DIR__.'/data/multi_level_join.json');
        $config = DefinitionDeserializer::deserialize($json, self::$company, self::$member);

        $this->assertEquals('Multi Level Join', $config->getTitle());
        $this->assertCount(1, $config->getSections());
        $this->assertHasTenantCondition($config, [self::$company->id()]);

        $dataQuery = $config->getSections()[0]->getDataQuery();
        $fields = $dataQuery->fields->columns;
        $this->assertCount(3, $fields);
        $joins = $dataQuery->joins->conditions;
        $this->assertCount(2, $joins);
        $this->assertEquals('customer', $joins[0]->parentTable->object);
        $this->assertEquals('customer', $joins[0]->parentTable->alias);
        $this->assertEquals('owner_id', $joins[0]->parentColumn);
        $this->assertEquals('LEFT JOIN', $joins[0]->joinType);
        $this->assertEquals('member', $joins[0]->joinTable->object);
        $this->assertEquals('owner', $joins[0]->joinTable->alias);
        $this->assertEquals('user_id', $joins[0]->joinColumn);
        $this->assertEquals('member', $joins[1]->parentTable->object);
        $this->assertEquals('owner', $joins[1]->parentTable->alias);
        $this->assertEquals('user_id', $joins[1]->parentColumn);
        $this->assertEquals('LEFT JOIN', $joins[1]->joinType);
        $this->assertEquals('user', $joins[1]->joinTable->object);
        $this->assertEquals('owner.user', $joins[1]->joinTable->alias);
        $this->assertEquals('id', $joins[1]->joinColumn);
    }

    private function assertHasTenantCondition(Definition $config, array $companyIds): void
    {
        foreach ($config->getSections() as $section) {
            $tenantCondition = $section->getDataQuery()->filter->conditions[0];
            $this->assertEquals('tenant_id', $tenantCondition->field->id); /* @phpstan-ignore-line */
            $this->assertEquals('=', $tenantCondition->operator);
            $this->assertEquals($companyIds, $tenantCondition->value);
        }
    }
}
