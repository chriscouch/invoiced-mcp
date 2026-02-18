<?php

namespace App\Tests\CustomerPortal;

use App\CustomerPortal\ValueObjects\PrefilledValues;
use App\Tests\AppTestCase;

class PrefilledValuesTest extends AppTestCase
{
    public function testGet(): void
    {
        $values = new PrefilledValues(['test' => true]);
        $this->assertTrue($values->get('test'));
        $this->assertNull($values->get('does_not_exist'));
    }

    public function testAll(): void
    {
        $in = ['test' => true];
        $values = new PrefilledValues($in);
        $this->assertEquals($in, $values->all());
    }
}
