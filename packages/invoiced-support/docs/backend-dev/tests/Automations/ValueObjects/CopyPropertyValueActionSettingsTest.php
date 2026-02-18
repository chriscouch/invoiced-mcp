<?php

namespace App\Tests\Automations\ValueObjects;

use App\Automations\ValueObjects\CopyPropertyValueActionSettings;
use App\Core\Utils\Enums\ObjectType;
use App\Tests\AppTestCase;

class CopyPropertyValueActionSettingsTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testValidate(): void
    {
        $settings = new CopyPropertyValueActionSettings('customer', 'chase', 'matched');
        $object = ObjectType::Payment;

        $settings->validate($object);
        $this->assertTrue(true);
    }
}
