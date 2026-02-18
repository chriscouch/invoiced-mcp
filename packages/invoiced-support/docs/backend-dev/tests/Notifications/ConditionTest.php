<?php

namespace App\Tests\Notifications;

use App\Notifications\ValueObjects\Condition;
use App\Tests\AppTestCase;

class ConditionTest extends AppTestCase
{
    public function testToString(): void
    {
        $condition = new Condition('object.test', 'equal', 'previous.test');
        $string = '{"property":"object.test","operator":"equal","comparison":"previous.test","comparison_object":true}';
        $this->assertEquals($string, (string) $condition);
        $this->assertEquals($string, json_encode($condition));
    }

    public function testFromString(): void
    {
        $string = '{"property":"object.test","operator":"equal","comparison":"previous.test","comparison_object":true}';
        $comparison = new Condition('object.test', 'equal', 'previous.test');
        $condition = Condition::fromString($string);
        $this->assertEquals($comparison, $condition);
    }
}
