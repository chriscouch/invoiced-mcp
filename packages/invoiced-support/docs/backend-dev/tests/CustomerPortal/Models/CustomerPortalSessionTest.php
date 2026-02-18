<?php

namespace App\Tests\CustomerPortal\Models;

use App\CustomerPortal\Models\CustomerPortalSession;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class CustomerPortalSessionTest extends AppTestCase
{
    private static CustomerPortalSession $session;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$session = new CustomerPortalSession();
        self::$session->user = self::getService('test.user_context')->get();
        self::$session->expires = CarbonImmutable::now()->addDay();
        $this->assertTrue(self::$session->save());
        $this->assertEquals(self::$company->id(), self::$session->tenant_id);
        $this->assertEquals(32, strlen(self::$session->identifier));
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$session->email = 'test@example.com';
        $this->assertTrue(self::$session->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$session->delete());
    }
}
