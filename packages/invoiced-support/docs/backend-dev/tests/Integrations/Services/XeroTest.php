<?php

namespace App\Tests\Integrations\Services;

use App\Integrations\Enums\IntegrationType;
use App\Integrations\Services\Xero;
use App\Integrations\Xero\Models\XeroAccount;
use App\Tests\AppTestCase;

class XeroTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testIsAccountingIntegration(): void
    {
        $integration = $this->getIntegration();
        $this->assertTrue($integration->isAccountingIntegration());
    }

    public function testIsConnected(): void
    {
        $integration = $this->getIntegration();
        $this->assertFalse($integration->isConnected());

        self::hasXeroAccount();
        $integration = $this->getIntegration();
        $this->assertTrue($integration->isConnected());
    }

    /**
     * @depends testIsConnected
     */
    public function testGetConnectedName(): void
    {
        $integration = $this->getIntegration();
        $this->assertEquals('Test Xero Account', $integration->getConnectedName());
    }

    /**
     * @depends testIsConnected
     */
    public function testGetExtra(): void
    {
        $integration = $this->getIntegration();
        $this->assertEquals((object) ['sync_profile' => null], $integration->getExtra());
    }

    /**
     * @depends testIsConnected
     */
    public function testDisconnect(): void
    {
        $integration = $this->getIntegration();
        $integration->disconnect();

        $this->assertFalse($integration->isConnected());
        $account = XeroAccount::queryWithTenant(self::$company)->oneOrNull();
        $this->assertNull($account);
    }

    private function getIntegration(): Xero
    {
        return self::getService('test.integration_factory')->get(IntegrationType::Xero, self::$company);
    }
}
