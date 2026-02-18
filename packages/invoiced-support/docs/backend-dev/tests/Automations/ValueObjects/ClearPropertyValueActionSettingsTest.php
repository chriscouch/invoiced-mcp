<?php

namespace App\Tests\Automations\ValueObjects;

use App\Automations\ValueObjects\ClearPropertyValueActionSettings;
use App\Core\Utils\Enums\ObjectType;
use App\Tests\AppTestCase;

class ClearPropertyValueActionSettingsTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testValidate(): void
    {
        $settings = new ClearPropertyValueActionSettings('customer', 'chase');
        $object = ObjectType::Payment;

        $settings->validate($object);
        $this->assertTrue(true);
    }
}
