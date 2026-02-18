<?php

namespace App\Tests\Integrations\QuickBooksOnline;

use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Tests\AppTestCase;

class QuickBooksOnlineSyncProfileTest extends AppTestCase
{
    private static QuickBooksOnlineSyncProfile $profile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$profile = new QuickBooksOnlineSyncProfile();
        $this->assertTrue(self::$profile->save());
        $this->assertNotNull(self::$profile->read_cursor);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$profile->delete());
    }
}
