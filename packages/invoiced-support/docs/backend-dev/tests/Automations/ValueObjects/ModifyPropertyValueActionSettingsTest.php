<?php

namespace App\Tests\Automations\ValueObjects;

use App\Automations\ValueObjects\ModifyPropertyValueActionSettings;
use App\Core\Utils\Enums\ObjectType;
use App\Tests\AppTestCase;

class ModifyPropertyValueActionSettingsTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testValidate(): void
    {
        $settings = new ModifyPropertyValueActionSettings('customer', 'chase', '44');
        $object = ObjectType::Payment;
        $settings->validate($object);
        $this->assertTrue(true);
    }
}
