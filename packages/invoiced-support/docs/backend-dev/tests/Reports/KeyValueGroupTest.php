<?php

namespace App\Tests\Reports;

use App\Reports\ValueObjects\KeyValueGroup;
use App\Tests\AppTestCase;

class KeyValueGroupTest extends AppTestCase
{
    public function testGetType(): void
    {
        $group = new KeyValueGroup();
        $this->assertEquals('keyvalue', $group->getType());
    }

    public function testAddLine(): void
    {
        $group = new KeyValueGroup();
        $group->addLine('line1', 'value1');
        $group->addLine('line2', 'value2');
        $group->addLine('line3', 'value3');
        $group->addLine('line4', 'value4');
        $group->addLine('line5', 'value5');

        $expected = [
            [
                'name' => 'line1',
                'value' => 'value1',
            ],
            [
                'name' => 'line2',
                'value' => 'value2',
            ],
            [
                'name' => 'line3',
                'value' => 'value3',
            ],
            [
                'name' => 'line4',
                'value' => 'value4',
            ],
            [
                'name' => 'line5',
                'value' => 'value5',
            ],
        ];
        $this->assertEquals($expected, $group->getLines());
    }

    public function testGetValues(): void
    {
        $group = new KeyValueGroup();
        $group->addLine('Title', 'test');
        $this->assertNull($group->getValue('does not exist'));
        $this->assertEquals('test', $group->getValue('Title'));
    }
}
