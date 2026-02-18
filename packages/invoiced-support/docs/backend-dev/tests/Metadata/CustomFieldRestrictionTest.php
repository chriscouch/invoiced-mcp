<?php

namespace App\Tests\Metadata;

use App\Metadata\ValueObjects\CustomFieldRestriction;
use App\Tests\AppTestCase;

class CustomFieldRestrictionTest extends AppTestCase
{
    public function testGetters(): void
    {
        $restriction = new CustomFieldRestriction('test', ['west', 'east', 'north', 'south']);
        $this->assertEquals('test', $restriction->getKey());
        $this->assertEquals(['west', 'east', 'north', 'south'], $restriction->getValues());
    }

    public function testValidateRestrictionsInvalidType(): void
    {
        $this->expectExceptionMessage('Restrictions input is invalid');

        CustomFieldRestriction::validateRestrictions(new \stdClass());
    }

    public function testValidateRestrictionsInvalidType2(): void
    {
        $this->expectExceptionMessage('Restrictions input is invalid');

        CustomFieldRestriction::validateRestrictions('');
    }

    public function testValidateRestrictionsTooManyKeys(): void
    {
        $this->expectExceptionMessage('There can only be restrictions on up to 3 custom fields');

        $val = array_fill(0, 4, 'test');
        CustomFieldRestriction::validateRestrictions($val);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testValidateRestrictions(): void
    {
        $val = ['department' => ['east', 'west'], 'test' => ['1']];
        CustomFieldRestriction::validateRestrictions($val);
    }
}
