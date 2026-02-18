<?php

namespace App\Tests\Integrations\Services;

use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Services\Intacct;
use App\Tests\AppTestCase;

class IntacctTest extends AppTestCase
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

        self::hasIntacctAccount();
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
        $this->assertEquals((object) ['sync_profile' => null, 'sync_all_entities' => false], $integration->getExtra());
    }

    /**
     * @depends testIsConnected
     */
    public function testDisconnect(): void
    {
        $integration = $this->getIntegration();
        $integration->disconnect();

        $this->assertFalse($integration->isConnected());
        $account = IntacctAccount::queryWithTenant(self::$company)->oneOrNull();
        $this->assertNull($account);
    }

    private function getIntegration(): Intacct
    {
        return self::getService('test.integration_factory')->get(IntegrationType::Intacct, self::$company);
    }
}
