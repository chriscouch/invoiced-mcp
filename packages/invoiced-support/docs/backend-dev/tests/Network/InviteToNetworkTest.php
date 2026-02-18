<?php

namespace App\Tests\Network;

use App\Companies\Models\Company;
use App\Network\Command\InviteToNetwork;
use App\Network\Exception\NetworkInviteException;
use App\Tests\AppTestCase;

class InviteToNetworkTest extends AppTestCase
{
    private static Company $company2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company2 = self::getTestDataFactory()->createCompany();
        self::hasCompany();
        self::hasCustomer();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (isset(self::$company2)) {
            self::$company2->delete();
        }
    }

    private function getCommand(): InviteToNetwork
    {
        return self::getService('test.invite_to_network');
    }

    public function testInviteByUsername(): void
    {
        $action = $this->getCommand();
        $invitation = $action->inviteCustomer(self::$company, null, self::$company2->username, self::$customer);
        $this->assertEquals(self::$company, $invitation->from_company);
        $this->assertTrue($invitation->is_customer);
        $this->assertNull($invitation->email);
        $this->assertEquals(self::$company2->id, $invitation->to_company?->id);
    }

    public function testInviteSameCompany(): void
    {
        $this->expectException(NetworkInviteException::class);
        $action = $this->getCommand();
        $action->inviteCustomer(self::$company, null, self::$company, self::$customer);
    }

    public function testInviteByUsernameNotFound(): void
    {
        $this->expectException(NetworkInviteException::class);
        $action = $this->getCommand();
        $action->inviteCustomer(self::$company, null, 'doesnotexit', self::$customer);
    }

    public function testInviteByEmail(): void
    {
        $action = $this->getCommand();
        $invitation = $action->inviteCustomer(self::$company, null, 'test@example.com', self::$customer);
        $this->assertEquals(self::$company, $invitation->from_company);
        $this->assertTrue($invitation->is_customer);
        $this->assertEquals('test@example.com', $invitation->email);
    }
}
