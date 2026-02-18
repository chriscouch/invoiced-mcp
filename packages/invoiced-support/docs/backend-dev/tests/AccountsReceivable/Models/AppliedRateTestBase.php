<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\AbstractRate;
use App\AccountsReceivable\Models\AppliedRate;
use App\Core\Utils\Enums\ObjectType;
use App\Tests\AppTestCase;

class AppliedRateTestBase extends AppTestCase
{
    private static AbstractRate $rate;
    private static AbstractRate $deletedRate;
    private static AppliedRate $appliedRate;
    private static AppliedRate $appliedRate2;
    protected static string $type = '';
    protected static string $model = '';
    protected static array $extraProperties = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        // create the rate
        /** @var AppliedRate $model */
        $model = static::$model;
        /** @var AbstractRate $rateModel */
        $rateModel = $model::RATE_MODEL;
        self::$rate = new $rateModel();
        self::$rate->name = 'Test Rate';
        self::$rate->id = 'test_rate';
        self::$rate->value = 10;
        self::$rate->saveOrFail();

        // create the rate
        self::$deletedRate = new $rateModel();
        self::$deletedRate->name = 'Test Deleted Rate';
        self::$deletedRate->id = 'deleted_rate';
        self::$deletedRate->value = 10;
        self::$deletedRate->saveOrFail();
        self::$deletedRate->delete();
    }

    public function testCompareScope(): void
    {
        /** @var AppliedRate $model */
        $model = static::$model;

        $a = [
            'in_items' => true,
            'in_subtotal' => false, ];
        $b = [
            'in_items' => true,
            'in_subtotal' => true, ];
        $this->assertEquals(-1, $model::compare($a, $b));

        $a = [
            'in_items' => true,
            'in_subtotal' => true, ];
        $b = [
            'in_items' => true,
            'in_subtotal' => true, ];
        $this->assertEquals(0, $model::compare($a, $b));

        $a = [
            'in_items' => false,
            'in_subtotal' => true, ];
        $b = [
            'in_items' => true,
            'in_subtotal' => false, ];
        $this->assertEquals(1, $model::compare($a, $b));
    }

    public function testCompareOrder(): void
    {
        /** @var AppliedRate $model */
        $model = static::$model;

        $a = ['order' => 1];
        $b = ['order' => 2];
        $this->assertEquals(-1, $model::compare($a, $b));
    }

    public function testCalculateAmount(): void
    {
        /** @var AppliedRate $model */
        $model = static::$model;
        /** @var AbstractRate $rateModel */
        $rateModel = $model::RATE_MODEL;
        $rateObjectName = ObjectType::fromModelClass($rateModel)->typeName();

        $ar = [
            $rateObjectName => [
                'id' => null,
                'value' => 10,
                'is_percent' => true,
            ],
        ];

        // test with a rate
        $amount = $model::calculateAmount('usd', 20000, $ar);
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(2000, $amount->amount);

        // test custom amount
        $ar[$rateObjectName] = null;
        $ar['amount'] = 25;
        $amount = $model::calculateAmount('usd', 20000, $ar);
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(2500, $amount->amount);

        // test with no rate or custom amount
        $amount = $model::calculateAmount('usd', 20000, []);
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(0, $amount->amount);
    }

    public function testExpandList(): void
    {
        /** @var AppliedRate $model */
        $model = static::$model;
        /** @var AbstractRate $rateModel */
        $rateModel = $model::RATE_MODEL;
        $rateObjectName = ObjectType::fromModelClass($rateModel)->typeName();

        $list = [
            self::$rate->id,
            [
                'amount' => 10,
            ],
            [],
            // duplicates should be prevented
            self::$rate->id,
            [
                $rateObjectName => self::$rate->id,
            ],
            [
                $rateObjectName => self::$rate->toArray(),
            ],
            // add in a deleted rate
            [
                $rateObjectName => self::$deletedRate->toArray(),
            ],
        ];
        // trigger a small difference so arrays cannot
        // be considered equal even though the reference the
        // same rate ID
        $list[5][$rateObjectName]['test'] = true;

        $expected = [
            [
                $rateObjectName => self::$rate->toArray(),
            ],
            [
                $rateObjectName => null,
                'amount' => 10,
            ],
            [
                $rateObjectName => null,
            ],
            [
                $rateObjectName => self::$deletedRate->toArray(),
            ],
        ];

        $this->assertEquals($expected, $model::expandList($list));
    }

    public function testCreate(): void
    {
        /** @var AppliedRate $model */
        $model = static::$model;

        self::$appliedRate = new $model();
        self::$appliedRate->setParent(self::$invoice);
        self::$appliedRate->amount = 10;
        self::$appliedRate->rate = 'test_rate';
        $this->assertTrue(self::$appliedRate->save());
        $this->assertEquals(static::$type, self::$appliedRate->type);
        $this->assertEquals(self::$rate->internal_id, self::$appliedRate->rate_id);

        self::$appliedRate2 = new $model();
        self::$appliedRate2->setParent(self::$invoice);
        self::$appliedRate2->amount = 10;
        $this->assertTrue(self::$appliedRate2->save());
        $this->assertEquals(static::$type, self::$appliedRate2->type);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        /** @var AppliedRate $model */
        $model = static::$model;
        $rateModel = $model::RATE_MODEL;
        $rateObjectName = ObjectType::fromModelClass($rateModel)->typeName();

        $expected = [
            'id' => self::$appliedRate->id(),
            'object' => static::$type,
            'amount' => 10,
            $rateObjectName => self::$rate->toArray(),
        ];

        if (property_exists(get_called_class(), 'extraProperties')) {
            foreach (static::$extraProperties as $property) {
                $expected[$property] = self::$appliedRate->$property;
            }
        }

        $rate = self::$appliedRate->toArray();
        unset($rate['updated_at']);
        $this->assertEquals($expected, $rate);

        $expected = [
            'id' => self::$appliedRate2->id(),
            'object' => static::$type,
            'amount' => 10,
            $rateObjectName => null,
        ];

        if (property_exists(get_called_class(), 'extraProperties')) {
            foreach (static::$extraProperties as $property) {
                $expected[$property] = self::$appliedRate2->$property;
            }
        }

        $rate = self::$appliedRate2->toArray();
        unset($rate['updated_at']);
        $this->assertEquals($expected, $rate);
    }
}
