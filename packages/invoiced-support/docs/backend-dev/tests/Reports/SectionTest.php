<?php

namespace App\Tests\Reports;

use App\Reports\ValueObjects\KeyValueGroup;
use App\Reports\ValueObjects\NestedTableGroup;
use App\Reports\ValueObjects\Section;
use App\Tests\AppTestCase;

class SectionTest extends AppTestCase
{
    public function testGetTitle(): void
    {
        $section = new Section('Test');
        $this->assertEquals('Test', $section->getTitle());
    }

    public function testGetClass(): void
    {
        $section = new Section('Test', 'test');
        $this->assertEquals('test', $section->getClass());
    }

    public function testGetGroups(): void
    {
        $section = new Section('Test');
        $group1 = new KeyValueGroup();
        $group2 = new NestedTableGroup(['test']);
        $section->addGroup($group1)->addGroup($group2);

        $this->assertEquals([$group1, $group2], $section->getGroups());
    }
}
