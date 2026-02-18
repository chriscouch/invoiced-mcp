<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\AbstractRate;
use App\AccountsReceivable\Models\PricingObject;
use App\Tests\AppTestCase;

class RateTestBase extends AppTestCase
{
    protected static AbstractRate $rate;
    protected static AbstractRate $rate2;
    protected static string $model = '';
    protected static string $objectName = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testCurrency(): void
    {
        $model = static::$model;
        $rate = new $model(['id' => -1]);
        $rate->is_percent = false;
        $rate->currency = 'usd';
        $this->assertEquals('usd', $rate->currency);

        $rate->is_percent = true;
        $this->assertNull($rate->currency);
    }

    public function testApplyRateToAmount(): void
    {
        $model = static::$model;
        $rate = [
            'is_percent' => true,
            'value' => 10.234,
        ];

        $amount = $model::applyRateToAmount('usd', 10000, $rate);
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(1023, $amount->amount);

        $amount = $model::applyRateToAmount('usd', 5000, $rate);
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(512, $amount->amount);

        $rate['is_percent'] = false;
        $amount = $model::applyRateToAmount('usd', 12300, $rate);
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(1023, $amount->amount);

        $amount = $model::applyRateToAmount('usd', 12203843, $rate);
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(1023, $amount->amount);
    }

    public function testCreateInvalidID(): void
    {
        $model = static::$model;
        /** @var AbstractRate $rate */
        $rate = new $model();
        $rate->name = 'Test';
        $rate->value = 10;
        $rate->id = '*#$&)#&%)*#)(%*';

        $this->assertFalse($rate->save());
    }

    public function testCreate(): void
    {
        $model = static::$model;
        /** @var AbstractRate $rate */
        $rate = new $model();
        self::$rate = $rate;
        self::$rate->id = 'test-rate';
        self::$rate->name = 'Test';
        self::$rate->value = 10;
        $this->assertTrue(self::$rate->save());

        $this->assertEquals(self::$company->id(), self::$rate->tenant_id);

        /** @var AbstractRate $rate2 */
        $rate2 = new $model();
        self::$rate2 = $rate2;
        $this->assertTrue(self::$rate2->create([
            'name' => 'Test 2',
            'id' => 'test-rate-2',
            'value' => 15,
        ]));
    }

    /**
     * @depends testCreate
     */
    public function testCreateNonUnique(): void
    {
        $model = static::$model;
        /** @var AbstractRate $rate */
        $rate = new $model();
        $errors = $rate->getErrors();

        $rate->name = 'Test';
        $rate->value = 10;
        $rate->id = 'test-rate';
        $this->assertFalse($rate->save());

        $this->assertCount(1, $errors);
        $this->assertEquals('An item already exists with ID: test-rate', $errors->all()[0]);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $model = static::$model;
        $rates = $model::all();

        $this->assertCount(2, $rates);
    }

    /**
     * @depends testCreate
     */
    public function testGetCurrent(): void
    {
        $model = static::$model;
        $this->assertEquals(self::$rate, $model::getCurrent('test-rate'));
        $this->assertNull($model::getCurrent('does-not-exist'));
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $this->assertEquals($this->getExpectedArrayRepresentation(), self::$rate->toArray());
    }

    protected function getExpectedArrayRepresentation(): array
    {
        return [
            'id' => 'test-rate',
            'object' => static::$objectName,
            'name' => 'Test',
            'is_percent' => true,
            'currency' => null,
            'value' => 10,
            'metadata' => new \stdClass(),
            'created_at' => self::$rate->created_at,
            'updated_at' => self::$rate->updated_at,
        ];
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$rate->name = 'Test';
        $this->assertTrue(self::$rate->save());
    }

    /**
     * @depends testCreate
     */
    public function testCannotChangeID(): void
    {
        self::$rate2->id = 'test-rate';
        $this->assertTrue(self::$rate2->save());
        $this->assertNotEquals('test-rate', self::$rate2->id);
    }

    /**
     * @depends testCreate
     */
    public function testCannotChangePrice(): void
    {
        self::$rate2->value = 100000;
        $this->assertTrue(self::$rate2->save());
        $this->assertNotEquals(100000, self::$rate2->value);
    }

    /**
     * @depends testCreate
     */
    public function testExpandList(): void
    {
        $list = [
            'test-rate',
            [
                'id' => 'already-expanded',
                'test' => 'already expanded',
            ],
            // duplicates should be prevented
            [
                'id' => 'already-expanded',
                'test' => 'already expanded',
            ],
            'test-rate',
        ];

        $model = static::$model;
        $expected = [
            self::$rate->toArray(),
            [
                'id' => 'already-expanded',
                'test' => 'already expanded',
            ],
        ];

        $this->assertEquals($expected, $model::expandList($list));
    }

    /**
     * @depends testEdit
     */
    public function testArchivedNotHidden(): void
    {
        $this->assertTrue(self::$rate->archive());

        $model = static::$model;
        $rates = $model::all();

        $this->assertCount(2, $rates);

        $rates = $model::where('archived', true)->all();
        $this->assertCount(1, $rates);

        $rates = $model::where('archived', false)->all();
        $this->assertCount(1, $rates);
    }

    /**
     * @depends testCreate
     */
    public function testMetadata(): void
    {
        $metadata = self::$rate->metadata;
        $metadata->test = true;
        self::$rate->metadata = $metadata;
        $this->assertTrue(self::$rate->save());
        $this->assertEquals((object) ['test' => true], self::$rate->metadata);

        self::$rate->metadata = (object) ['internal.id' => '12345'];
        $this->assertTrue(self::$rate->save());
        $this->assertEquals((object) ['internal.id' => '12345'], self::$rate->metadata);

        self::$rate->metadata = (object) ['array' => [], 'object' => new \stdClass()];
        $this->assertTrue(self::$rate->save());
        $this->assertEquals((object) ['array' => [], 'object' => new \stdClass()], self::$rate->metadata);
    }

    /**
     * @depends testCreate
     */
    public function testBadMetadata(): void
    {
        self::$rate->metadata = (object) [str_pad('', 41) => 'fail'];
        $this->assertFalse(self::$rate->save());

        self::$rate->metadata = (object) ['fail' => str_pad('', 256)];
        $this->assertFalse(self::$rate->save());

        self::$rate->metadata = (object) array_fill(0, 11, 'fail');
        $this->assertFalse(self::$rate->save());

        self::$rate->metadata = (object) [];
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        // deleting is the same as archiving
        $this->assertTrue(self::$rate->delete());
        $this->assertTrue(self::$rate->persisted());
        $this->assertTrue(self::$rate->archived);
    }

    /**
     * @depends testDelete
     */
    public function testCanCreateIdAfterDelete(): void
    {
        $model = static::$model;
        /** @var AbstractRate $rate */
        $rate = new $model();
        $rate->name = 'Test';
        $rate->value = 20;
        $rate->id = 'test-rate';
        $this->assertTrue($rate->save());
    }

    public function testArchiving(): void
    {
        $model = static::$model;

        /** @var AbstractRate $rate */
        $rate = new $model();
        $rate->id = 'archived';
        $rate->name = 'Test';
        $rate->value = 10;
        $this->assertTrue($rate->save());

        // archive it
        $this->assertTrue($rate->archive());

        // try to look it up
        $rate2 = $model::where('id', 'archived')->oneOrNull();
        $this->assertInstanceOf($model, $rate2); /* @phpstan-ignore-line */
        $this->assertNull($model::getCurrent('archived'));

        // unarchive it
        $rate->archived = false;
        $this->assertTrue($rate->save());

        // archive it again
        $this->assertTrue($rate->save());
        $this->assertTrue($rate->save());
    }

    public function testCreateMissingID(): void
    {
        $model = static::$model;
        /** @var AbstractRate $rate */
        $rate = new $model();
        $rate->name = 'Test';
        $rate->value = 10;

        $this->assertTrue($rate->save());
        $this->assertEquals(PricingObject::ID_LENGTH, strlen($rate->id));
    }
}
