<?php

namespace App\Tests\Metadata\Libs;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\Enums\ObjectType;
use App\Metadata\Models\CustomField;
use App\Metadata\Storage\AttributeStorage;
use App\Metadata\ValueObjects\MetadataQueryCondition;
use App\Tests\AppTestCase;
use Doctrine\DBAL\Connection;

class AttributeStorageTest extends AppTestCase
{
    private static AttributeStorage $storage;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasPayment();
        self::$storage = self::getService('test.attribute_storage');
    }

    /**
     * @covers \AttributeHelper::buildWhereCondition
     */
    public function testBuildSqlConditions(): void
    {
        $conditions = [];
        $conditions[] = new MetadataQueryCondition('test', 'test', '=');
        $conditions[] = new MetadataQueryCondition('test2', 'test2', '>');
        $query = self::$storage->buildSqlConditions($conditions, self::$payment, self::$company->id);
        $this->assertEquals([
            '1 = 2',
        ], $query);

        self::$payment->metadata = (object) [
            'test' => 'test',
            'test2' => 'test2',
        ];
        self::$payment->saveOrFail();
        $query = self::$storage->buildSqlConditions($conditions, self::$payment, self::$company->id);
        $this->assertEquals(1, preg_match("/EXISTS \(SELECT 1 FROM PaymentStringValues WHERE object_id=Payments.id AND attribute_id=\d+ AND value = 'test'\)/", $query[0]), $query[0]);
        $this->assertEquals(1, preg_match("/EXISTS \(SELECT 1 FROM PaymentStringValues WHERE object_id=Payments.id AND attribute_id=\d+ AND value > 'test2'\)/", $query[1]), $query[1]);
    }

    public function testGetMetadataQuery(): void
    {
        $table = 'payment_1';
        $customFields = [
            'string' => CustomField::FIELD_TYPE_STRING,
            'boolean' => CustomField::FIELD_TYPE_BOOLEAN,
            'integer' => CustomField::FIELD_TYPE_DOUBLE,
            'decimal' => CustomField::FIELD_TYPE_DOUBLE,
            'money' => CustomField::FIELD_TYPE_MONEY,
            'timestamp' => CustomField::FIELD_TYPE_DATE,
            'datetime' => CustomField::FIELD_TYPE_DATE,
            'jsonMoney' => CustomField::FIELD_TYPE_MONEY,
        ];
        $customFieldKeys = array_keys($customFields);

        // custom fields doesn't exist
        foreach ($customFieldKeys as $expression) {
            $query = self::$storage->getMetadataQuery(self::$payment, $expression, $table);
            $this->assertEquals('(1=2)', $query);
        }

        // custom fields not set
        foreach ($customFields as $name => $type) {
            $field = new CustomField();
            $field->type = $type;
            $field->name = $name;
            $field->id = $name;
            $field->object = ObjectType::Payment->typeName();
            $field->saveOrFail();
        }

        foreach ($customFieldKeys as $expression) {
            $query = self::$storage->getMetadataQuery(self::$payment, $expression, $table);
            $this->assertEquals('(1=2)', $query);
        }

        // custom fields set
        self::$payment->metadata = (object) [
            'string' => 'string',
            'boolean' => true,
            'integer' => 2,
            'decimal' => 1.01,
            'money' => Money::fromDecimal('usd', 1.01),
            'timestamp' => 123456789,
            'datetime' => '2022-01-02 00:00:00',
            'jsonMoney' => ['currency' => 'usd', 'amount' => 0.01],
        ];
        self::$payment->saveOrFail();

        $expressionsQueries = array_map(fn ($expression) => self::$storage->getMetadataQuery(self::$payment, $expression, $table), $customFieldKeys);

        /** @var Connection $connection */
        $connection = self::getService('test.database');

        $qb = $connection->createQueryBuilder();
        $ids = $qb->select('id')
            ->from('PaymentAttributes')
            ->andWhere('tenant_id = :tid')
            ->andWhere($qb->expr()->in('name', ':names'))
            ->setParameter('tid', self::$company->id)
            ->setParameter('names', $customFieldKeys, Connection::PARAM_STR_ARRAY)
            ->orderBy('id')
            ->fetchFirstColumn();

        $this->assertEquals([
            "(SELECT `value` FROM PaymentStringValues WHERE `attribute_id`=\"$ids[0]\" AND object_id=payment_1.id)",
            "(SELECT `value` FROM PaymentIntegerValues WHERE `attribute_id`=\"$ids[1]\" AND object_id=payment_1.id)",
            "(SELECT `value` FROM PaymentDecimalValues WHERE `attribute_id`=\"$ids[2]\" AND object_id=payment_1.id)",
            "(SELECT `value` FROM PaymentDecimalValues WHERE `attribute_id`=\"$ids[3]\" AND object_id=payment_1.id)",
            "(SELECT `value` FROM PaymentMoneyValues WHERE `attribute_id`=\"$ids[4]\" AND object_id=payment_1.id)",
            "(SELECT `value` FROM PaymentStringValues WHERE `attribute_id`=\"$ids[5]\" AND object_id=payment_1.id)",
            "(SELECT `value` FROM PaymentStringValues WHERE `attribute_id`=\"$ids[6]\" AND object_id=payment_1.id)",
            "(SELECT `value` FROM PaymentMoneyValues WHERE `attribute_id`=\"$ids[7]\" AND object_id=payment_1.id)",
        ], $expressionsQueries);
    }
}
