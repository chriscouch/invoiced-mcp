<?php

namespace App\Tests\Integrations\Services;

use App\Integrations\Enums\IntegrationType;
use App\Integrations\NetSuite\Models\NetSuiteAccount;
use App\Integrations\Services\Netsuite;
use App\Tests\AppTestCase;

class NetSuiteTest extends AppTestCase
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

        self::hasNetSuiteAccount();
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
        $this->assertEquals((object) [
            'sync_profile' => null,
        ], $integration->getExtra());

        self::hasAccountingSyncProfile(IntegrationType::NetSuite);

        $integration = $this->getIntegration();
        $this->assertEquals((object) [
            'sync_profile' => [
                'created_at' => self::$accountingSyncProfile->created_at,
                'id' => self::$accountingSyncProfile->id,
                'invoice_start_date' => self::$accountingSyncProfile->invoice_start_date,
                'last_synced' => null,
                'parameters' => (object) [],
                'payment_accounts' => [],
                'read_credit_notes' => false,
                'read_customers' => false,
                'read_invoices' => false,
                'read_invoices_as_drafts' => false,
                'read_payments' => false,
                'read_pdfs' => true,
                'updated_at' => self::$accountingSyncProfile->updated_at,
                'write_convenience_fees' => false,
                'write_credit_notes' => false,
                'write_customers' => false,
                'write_invoices' => false,
                'write_payments' => false,
            ],
        ], $integration->getExtra());
    }

    /**
     * @depends testIsConnected
     */
    public function testDisconnect(): void
    {
        $integration = $this->getIntegration();
        $integration->disconnect();

        $this->assertFalse($integration->isConnected());
        $account = NetSuiteAccount::queryWithTenant(self::$company)->oneOrNull();
        $this->assertNull($account);
    }

    private function getIntegration(): Netsuite
    {
        return self::getService('test.integration_factory')->get(IntegrationType::NetSuite, self::$company);
    }
}
