<?php

namespace App\Tests\Network;

use App\Companies\Models\Company;
use App\Network\Command\DeclineNetworkInvitation;
use App\Network\Models\NetworkInvitation;
use App\Tests\AppTestCase;
use App\Core\Utils\InfuseUtility as Utility;

class DeclineNetworkInvitationTest extends AppTestCase
{
    private static Company $company2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company2 = self::getTestDataFactory()->createCompany();
        self::hasCompany();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (isset(self::$company2)) {
            self::$company2->delete();
        }
    }

    private function getCommand(): DeclineNetworkInvitation
    {
        return self::getService('test.decline_network_invitation');
    }

    public function testDecline(): void
    {
        $action = $this->getCommand();

        $invitation = new NetworkInvitation();
        $invitation->uuid = Utility::guid();
        $invitation->from_company = self::$company;
        $invitation->to_company = self::$company2;
        $invitation->is_customer = true;
        $invitation->saveOrFail();

        $action->decline($invitation);

        $this->assertTrue($invitation->declined);
        $this->assertNotNull($invitation->declined_at);

        // It should not be possible to delete a declined invitation
        $this->assertFalse($invitation->delete());
    }
}
