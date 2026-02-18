<?php

namespace App\Tests\Integrations\Services;

use App\Integrations\Enums\IntegrationType;
use App\Integrations\Services\Avalara;
use App\Integrations\Avalara\AvalaraAccount;
use App\Tests\AppTestCase;

class AvalaraTest extends AppTestCase
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

        self::hasAvalaraAccount();
        self::$company->accounts_receivable_settings->tax_calculator = 'avalara';
        self::$company->accounts_receivable_settings->saveOrFail();

        $integration = $this->getIntegration();
        $this->assertTrue($integration->isConnected());
    }

    /**
     * @depends testIsConnected
     */
    public function testGetConnectedName(): void
    {
        $integration = $this->getIntegration();
        $this->assertEquals('Test Avalara Account', $integration->getConnectedName());
    }

    /**
     * @depends testIsConnected
     */
    public function testGetExtra(): void
    {
        $integration = $this->getIntegration();
        $expected = [
            'company_code' => 'company_code',
            'commit_mode' => AvalaraAccount::COMMIT_MODE_COMMITTED,
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
        $account = AvalaraAccount::queryWithTenant(self::$company)->oneOrNull();
        $this->assertNull($account);

        $this->assertEquals('invoiced', self::$company->accounts_receivable_settings->refresh()->tax_calculator);
    }

    private function getIntegration(): Avalara
    {
        return self::getService('test.integration_factory')->get(IntegrationType::Avalara, self::$company);
    }
}
