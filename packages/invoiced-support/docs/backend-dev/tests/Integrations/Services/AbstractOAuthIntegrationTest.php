<?php

namespace App\Tests\Integrations\Services;

use App\Integrations\Enums\IntegrationType;
use App\Integrations\Interfaces\IntegrationInterface;
use App\Integrations\OAuth\Models\OAuthAccount;
use App\Tests\AppTestCase;

abstract class AbstractOAuthIntegrationTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    abstract protected function getIntegrationType(): IntegrationType;

    protected function getIntegration(): IntegrationInterface
    {
        return self::getService('test.integration_factory')->get($this->getIntegrationType(), self::$company);
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

        self::hasOAuthAccount($this->getIntegrationType());
        $integration = $this->getIntegration();
        $this->assertTrue($integration->isConnected());
    }

    /**
     * @depends testIsConnected
     */
    public function testGetConnectedName(): void
    {
        $integration = $this->getIntegration();
        $this->assertEquals('Test OAuth Account', $integration->getConnectedName());
    }

    /**
     * @depends testIsConnected
     */
    public function testGetExtra(): void
    {
        $integration = $this->getIntegration();
        $this->assertEquals((object) [], $integration->getExtra());
    }

    /**
     * @depends testIsConnected
     */
    public function testDisconnect(): void
    {
        $integration = $this->getIntegration();
        $integration->disconnect();
        $this->assertFalse($integration->isConnected());
        $account = OAuthAccount::queryWithTenant(self::$company)->oneOrNull();
        $this->assertNull($account);
    }
}
