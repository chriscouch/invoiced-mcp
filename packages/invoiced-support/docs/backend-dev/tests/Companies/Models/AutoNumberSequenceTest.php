<?php

namespace App\Tests\Companies\Models;

use App\Companies\Models\AutoNumberSequence;
use App\Tests\AppTestCase;

class AutoNumberSequenceTest extends AppTestCase
{
    private static AutoNumberSequence $sequence;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();

        self::getService('test.database')->delete('AutoNumberSequences', ['tenant_id' => self::$company->id()]);
    }

    public function testCreate(): void
    {
        self::$sequence = new AutoNumberSequence();
        self::$sequence->type = 'customer';
        self::$sequence->template = 'CUST-%05d';

        $this->assertTrue(self::$sequence->save());

        $this->assertEquals(1, self::$sequence->next);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'type' => 'customer',
            'template' => 'CUST-%05d',
            'next' => 1,
        ];

        $this->assertEquals($expected, self::$sequence->toArray());
    }
}
