<?php

namespace App\Tests\Core\Orm;

use PHPUnit\Framework\TestCase;
use App\Core\Orm\Definition;
use App\Core\Orm\Property;

class DefinitionTest extends TestCase
{
    public function testArrayAccess(): void
    {
        $property = new Property();
        $definition = new Definition(['id'], ['test' => $property]);
        $this->assertFalse(isset($definition['does_not_exist']));
        $this->assertNull($definition['does_not_exist']);

        $this->assertTrue(isset($definition['test']));
        $this->assertEquals($property, $definition['test']);

        $e = null;
        try {
            $definition['test'] = new Property();
        } catch (\Exception $ex) {
            $e = $ex;
        }
        $this->assertNotNull($e);

        $e = null;
        try {
            unset($definition['test']);
        } catch (\Exception $ex) { /* @phpstan-ignore-line */
            $e = $ex;
        }
        $this->assertNotNull($e);
    }
}
