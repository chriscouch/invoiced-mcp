<?php

namespace App\Tests\Integrations\Xero;

use App\Integrations\Xero\Models\XeroSyncProfile;
use App\Tests\AppTestCase;

class XeroSyncProfileTest extends AppTestCase
{
    private static XeroSyncProfile $profile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$profile = new XeroSyncProfile();
        $this->assertTrue(self::$profile->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$profile->delete());
    }
}
