<?php

namespace App\Tests\Reports;

use App\Reports\ValueObjects\AgingBreakdown;
use App\Tests\AppTestCase;

class AgingBreakdownTest extends AppTestCase
{
    public function testGetBucketsInvoiceDate(): void
    {
        $breakdown = new AgingBreakdown([0, 14, 30, 60, 90, 120], 'date');
        $expected = [
            [
                'lower' => 0,
                'upper' => 13,
                'color' => '#54BF83',
            ],
            [
                'lower' => 14,
                'upper' => 29,
                'color' => '#BFED1A',
            ],
            [
                'lower' => 30,
                'upper' => 59,
                'color' => '#E7ED1A',
            ],
            [
                'lower' => 60,
                'upper' => 89,
                'color' => '#e91c2b',
            ],
            [
                'lower' => 90,
                'upper' => 119,
                'color' => '#9E0510',
            ],
            [
                'lower' => 120,
                'upper' => null,
                'color' => '#9E0510',
            ],
        ];
        $this->assertEquals($expected, $breakdown->getBuckets());
    }

    public function testGetBucketsDueDate(): void
    {
        $breakdown = new AgingBreakdown([-1, 0, 30, 60, 90, 120], 'due_date');
        $expected = [
            [
                'lower' => -1,
                'upper' => null,
                'color' => '#54BF83',
            ],
            [
                'lower' => 0,
                'upper' => 29,
                'color' => '#BFED1A',
            ],
            [
                'lower' => 30,
                'upper' => 59,
                'color' => '#E7ED1A',
            ],
            [
                'lower' => 60,
                'upper' => 89,
                'color' => '#e91c2b',
            ],
            [
                'lower' => 90,
                'upper' => 119,
                'color' => '#9E0510',
            ],
            [
                'lower' => 120,
                'upper' => null,
                'color' => '#9E0510',
            ],
        ];
        $this->assertEquals($expected, $breakdown->getBuckets());
    }

    public function testGetDateColumn(): void
    {
        $breakdown = new AgingBreakdown([0, 14, 30, 60, 90, 120], 'date');
        $this->assertEquals('date', $breakdown->dateColumn);

        $breakdown = new AgingBreakdown([0, 14, 30, 60, 90, 120], 'due_date');
        $this->assertEquals('due_date', $breakdown->dateColumn);
    }

    public function testGetBucketNameInvoiceDate(): void
    {
        $translator = self::getService('translator');
        $breakdown = new AgingBreakdown([0, 1, 2, 3, 4, 5], 'date');
        $this->assertEquals('Current', $breakdown->getBucketName(['lower' => -1, 'upper' => 0], $translator, 'en_US'));
        $this->assertEquals('0 - 10 Days', $breakdown->getBucketName(['lower' => 0, 'upper' => 10], $translator, 'en_US'));
        $this->assertEquals('14 - 30 Days', $breakdown->getBucketName(['lower' => 14, 'upper' => 30], $translator, 'en_US'));
        $this->assertEquals('60+ Days', $breakdown->getBucketName(['lower' => 60, 'upper' => null], $translator, 'en_US'));
    }

    public function testGetBucketNameDueDate(): void
    {
        $translator = self::getService('translator');
        $breakdown = new AgingBreakdown([0, 1, 2, 3, 4, 5], 'date');
        $this->assertEquals('Current', $breakdown->getBucketName(['lower' => -1, 'upper' => 0], $translator, 'en_US'));
        $this->assertEquals('0 - 10 Days', $breakdown->getBucketName(['lower' => 0, 'upper' => 10], $translator, 'en_US'));
        $this->assertEquals('14 - 30 Days', $breakdown->getBucketName(['lower' => 14, 'upper' => 30], $translator, 'en_US'));
        $this->assertEquals('60+ Days', $breakdown->getBucketName(['lower' => 60, 'upper' => null], $translator, 'en_US'));
    }

    public function testGetBucketForAgeInvoiceDate(): void
    {
        $breakdown = new AgingBreakdown([0, 14, 30, 60, 90, 120], 'date');

        $expected = [
            'lower' => 90,
            'upper' => 119,
            'color' => '#9E0510',
        ];
        $this->assertEquals($expected, $breakdown->getBucketForAge(90));
        $this->assertEquals($expected, $breakdown->getBucketForAge(91));

        $expected = [
            'lower' => 0,
            'upper' => 13,
            'color' => '#54BF83',
        ];
        $this->assertEquals($expected, $breakdown->getBucketForAge(0));
        $this->assertEquals($expected, $breakdown->getBucketForAge(1));
        $this->assertEquals($expected, $breakdown->getBucketForAge(-30));

        $expected = [
            'lower' => 120,
            'upper' => null,
            'color' => '#9E0510',
        ];
        $this->assertEquals($expected, $breakdown->getBucketForAge(120));
        $this->assertEquals($expected, $breakdown->getBucketForAge(1000));
    }

    public function testGetBucketForAgeDueDate(): void
    {
        $breakdown = new AgingBreakdown([-1, 0, 14, 30, 60, 90, 120], 'due_date');

        $expected = [
            'lower' => -1,
            'upper' => null,
            'color' => '#54BF83',
        ];
        $this->assertEquals($expected, $breakdown->getBucketForAge(-1));

        $expected = [
            'lower' => 90,
            'upper' => 119,
            'color' => '#9E0510',
        ];
        $this->assertEquals($expected, $breakdown->getBucketForAge(90));
        $this->assertEquals($expected, $breakdown->getBucketForAge(91));

        $expected = [
            'lower' => 0,
            'upper' => 13,
            'color' => '#BFED1A',
        ];
        $this->assertEquals($expected, $breakdown->getBucketForAge(0));
        $this->assertEquals($expected, $breakdown->getBucketForAge(1));

        $expected = [
            'lower' => 120,
            'upper' => null,
            'color' => '#9E0510',
        ];
        $this->assertEquals($expected, $breakdown->getBucketForAge(120));
        $this->assertEquals($expected, $breakdown->getBucketForAge(1000));
    }

    public function testGetAgeForTimestampInvoiceDate(): void
    {
        $breakdown = new AgingBreakdown([0, 14, 30, 60, 90, 120], 'date');
        $this->assertEquals(1, $breakdown->getAgeForTimestamp(strtotime('-1 day')));
        $this->assertEquals(2, $breakdown->getAgeForTimestamp(strtotime('-2 days')));
        $this->assertEquals(10, $breakdown->getAgeForTimestamp(strtotime('-10 days')));
        $this->assertEquals(0, $breakdown->getAgeForTimestamp(null));
    }

    public function testGetAgeForTimestampDueDate(): void
    {
        $breakdown = new AgingBreakdown([-1, 0, 30, 60, 90], 'due_date');

        // invoices not past due
        $this->assertEquals(-1, $breakdown->getAgeForTimestamp(null));
        $this->assertEquals(-1, $breakdown->getAgeForTimestamp(null));
        $this->assertEquals(-1, $breakdown->getAgeForTimestamp(strtotime('+20 days')));
        $this->assertEquals(-1, $breakdown->getAgeForTimestamp(null));

        // invoices past due
        $this->assertEquals(1, $breakdown->getAgeForTimestamp(strtotime('-1 day')));
        $this->assertEquals(2, $breakdown->getAgeForTimestamp(strtotime('-2 days')));
        $this->assertEquals(10, $breakdown->getAgeForTimestamp(strtotime('-10 days')));
        $this->assertEquals(0, $breakdown->getAgeForTimestamp(time()));
    }

    public function testIsInBucketInvoiceDate(): void
    {
        $breakdown = new AgingBreakdown([0, 14, 30, 60, 90, 120], 'date');
        $this->assertTrue($breakdown->isInBucket(5, ['lower' => 5, 'upper' => 10]));
        $this->assertTrue($breakdown->isInBucket(6, ['lower' => 5, 'upper' => 10]));
        $this->assertFalse($breakdown->isInBucket(1, ['lower' => 5, 'upper' => 10]));
        $this->assertTrue($breakdown->isInBucket(10, ['lower' => 5, 'upper' => 10]));
        $this->assertFalse($breakdown->isInBucket(11, ['lower' => 5, 'upper' => 10]));
        $this->assertFalse($breakdown->isInBucket(3, ['lower' => 5, 'upper' => null]));
        $this->assertTrue($breakdown->isInBucket(5, ['lower' => 5, 'upper' => null]));
        $this->assertTrue($breakdown->isInBucket(100, ['lower' => 5, 'upper' => null]));
    }

    public function testIsInBucketDueDate(): void
    {
        $breakdown = new AgingBreakdown([-1, 0, 14, 30, 60, 90, 120], 'due_date');
        $this->assertTrue($breakdown->isInBucket(-1, ['lower' => -1, 'upper' => null]));
        $this->assertFalse($breakdown->isInBucket(-1, ['lower' => 0, 'upper' => 30]));
        $this->assertFalse($breakdown->isInBucket(19, ['lower' => -1, 'upper' => null]));
        $this->assertTrue($breakdown->isInBucket(0, ['lower' => 0, 'upper' => 30]));
        $this->assertTrue($breakdown->isInBucket(5, ['lower' => 5, 'upper' => 10]));
        $this->assertTrue($breakdown->isInBucket(6, ['lower' => 5, 'upper' => 10]));
        $this->assertFalse($breakdown->isInBucket(1, ['lower' => 5, 'upper' => 10]));
        $this->assertTrue($breakdown->isInBucket(10, ['lower' => 5, 'upper' => 10]));
        $this->assertFalse($breakdown->isInBucket(11, ['lower' => 5, 'upper' => 10]));
        $this->assertFalse($breakdown->isInBucket(3, ['lower' => 5, 'upper' => null]));
        $this->assertTrue($breakdown->isInBucket(5, ['lower' => 5, 'upper' => null]));
        $this->assertTrue($breakdown->isInBucket(100, ['lower' => 5, 'upper' => null]));
    }

    public function testGetColor(): void
    {
        $breakdown = new AgingBreakdown([0, 14, 30, 60, 90, 120], 'date');
        $this->assertEquals('#54BF83', $breakdown->getColor(['color' => '#54BF83']));
    }
}
