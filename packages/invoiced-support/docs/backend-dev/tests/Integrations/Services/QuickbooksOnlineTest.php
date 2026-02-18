<?php

namespace App\Tests\Integrations\Services;

use App\Integrations\Enums\IntegrationType;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\Services\QuickbooksOnline;
use App\Tests\AppTestCase;

class QuickbooksOnlineTest extends AppTestCase
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

        self::hasQuickBooksAccount();
        $integration = $this->getIntegration();
        $this->assertTrue($integration->isConnected());

        /** @var QuickBooksAccount $account */
        $account = $integration->getAccount();
        $account->refresh_token_expires = time() - 1;
        $this->assertTrue($integration->isConnected());
    }

    /**
     * @depends testIsConnected
     */
    public function testGetConnectedName(): void
    {
        $integration = $this->getIntegration();
        $this->assertEquals('Test QuickBooks Account', $integration->getConnectedName());
    }

    /**
     * @depends testIsConnected
     */
    public function testGetExtra(): void
    {
        $integration = $this->getIntegration();
        $this->assertEquals((object) ['uses_payments' => false, 'sync_profile' => null], $integration->getExtra());
    }

    /**
     * @depends testIsConnected
     */
    public function testDisconnect(): void
    {
        $integration = $this->getIntegration();
        $integration->disconnect();
        $this->assertFalse($integration->isConnected());
        $account = QuickBooksAccount::queryWithTenant(self::$company)->oneOrNull();
        $this->assertNull($account);
    }

    private function getIntegration(): QuickbooksOnline
    {
        return self::getService('test.integration_factory')->get(IntegrationType::QuickBooksOnline, self::$company);
    }
}
