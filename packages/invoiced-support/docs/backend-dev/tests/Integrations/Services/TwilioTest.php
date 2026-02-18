<?php

namespace App\Tests\Integrations\Services;

use App\Integrations\Enums\IntegrationType;
use App\Integrations\Services\Twilio;
use App\Integrations\Twilio\TwilioAccount;
use App\Tests\AppTestCase;

class TwilioTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testIsAccountingIntegration(): void
    {
        $integration = $this->getIntegration();
        $this->assertFalse($integration->isAccountingIntegration());
    }

    public function testIsConnected(): void
    {
        $integration = $this->getIntegration();
        $this->assertFalse($integration->isConnected());

        self::hasTwilioAccount();
        $integration = $this->getIntegration();
        $this->assertTrue($integration->isConnected());
    }

    /**
     * @depends testIsConnected
     */
    public function testGetConnectedName(): void
    {
        $integration = $this->getIntegration();
        $this->assertEquals('1234', $integration->getConnectedName());
    }

    /**
     * @depends testIsConnected
     */
    public function testGetExtra(): void
    {
        $integration = $this->getIntegration();
        $expected = [
            'from_number' => '+1123456789',
        ];
        $this->assertEquals((object) $expected, $integration->getExtra());
    }

    /**
     * @depends testIsConnected
     */
    public function testDisconnect(): void
    {
        $integration = $this->getIntegration();
        $integration->disconnect();

        $this->assertFalse($integration->isConnected());
        $account = TwilioAccount::queryWithTenant(self::$company)->oneOrNull();
        $this->assertNull($account);
    }

    private function getIntegration(): Twilio
    {
        return self::getService('test.integration_factory')->get(IntegrationType::Twilio, self::$company);
    }
}
