<?php

namespace App\Tests\Reports;

use App\Reports\ValueObjects\NestedTableGroup;
use App\Tests\AppTestCase;

class NestedTableGroupTest extends AppTestCase
{
    public function testGetType(): void
    {
        $group = new NestedTableGroup(['name', 'value']);
        $this->assertEquals('nested_table', $group->getType());
    }

    public function testGetHeader(): void
    {
        $group = new NestedTableGroup(['name', 'value']);
        $this->assertNull($group->getHeader());
        $group->setHeader(['header1', 'header2']);
        $this->assertEquals(['header1', 'header2'], $group->getHeader());
    }

    public function testAddRow(): void
    {
        $group = new NestedTableGroup(['name', 'value']);
        $group->addRow(['line1', 'value1']);
        $subTable = new NestedTableGroup(['nested', 'value']);
        $subTable->addRow(['line2', 'value2']);
        $group->addRow($subTable);
        $group->addRow(['line3', 'value3']);
        $group->addRows([['line4', 'value4'], ['line5', 'value5']]);

        $expected = [
            [
                'line1',
                'value1',
            ],
            $subTable,
            [
                'line3',
                'value3',
            ],
            [
                'line4',
                'value4',
            ],
            [
                'line5',
                'value5',
            ],
        ];
        $this->assertEquals($expected, $group->getRows());
    }

    public function testGetFooter(): void
    {
        $group = new NestedTableGroup(['name', 'value']);
        $this->assertNull($group->getFooter());
        $group->setFooter(['footer1', 'footer2']);
        $this->assertEquals(['footer1', 'footer2'], $group->getFooter());
    }
}
