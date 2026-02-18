<?php

namespace App\Tests\Reports;

use App\Reports\ValueObjects\MetricGroup;
use App\Tests\AppTestCase;

class MetricGroupTest extends AppTestCase
{
    public function testGetType(): void
    {
        $group = new MetricGroup();
        $this->assertEquals('metric', $group->getType());
    }

    public function testAddMetric(): void
    {
        $group = new MetricGroup();
        $group->addMetric('line1', 'value1');
        $group->addMetric('line2', 'value2');
        $group->addMetric('line3', 'value3');
        $group->addMetric('line4', 'value4');
        $group->addMetric('line5', 'value5');

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
        $this->assertEquals($expected, $group->getMetrics());
    }

    public function testGetValues(): void
    {
        $group = new MetricGroup();
        $group->addMetric('Title', 'test');
        $this->assertNull($group->getValue('does not exist'));
        $this->assertEquals('test', $group->getValue('Title'));
    }
}
