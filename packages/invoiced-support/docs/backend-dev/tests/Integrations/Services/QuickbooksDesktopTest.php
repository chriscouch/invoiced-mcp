<?php

namespace App\Tests\Integrations\Services;

use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Services\QuickbooksDesktop;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class QuickbooksDesktopTest extends AppTestCase
{
    private static AccountingSyncProfile $syncProfile;

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

        self::$syncProfile = new AccountingSyncProfile();
        self::$syncProfile->integration = IntegrationType::QuickBooksDesktop;
        self::$syncProfile->saveOrFail();

        $integration = $this->getIntegration();
        $this->assertTrue($integration->isConnected());
    }

    /**
     * @depends testIsConnected
     */
    public function testGetConnectedName(): void
    {
        $integration = $this->getIntegration();
        $this->assertNull($integration->getConnectedName());
    }

    /**
     * @depends testIsConnected
     */
    public function testGetExtra(): void
    {
        $integration = $this->getIntegration();
        $this->assertEquals((object) [
            'sync_profile' => [
                'all_invoices' => true,
                'created_at' => self::$syncProfile->created_at,
                'debug' => false,
                'id' => self::$syncProfile->id,
                'invoice_start_date' => CarbonImmutable::now()->toDateString(),
                'last_synced' => null,
                'parameters' => (object) [],
                'parent_child_enabled' => false,
                'payment_accounts' => [],
                'read_credit_notes' => false,
                'read_customers' => false,
                'read_invoices' => false,
                'read_invoices_as_drafts' => false,
                'read_payments' => false,
                'read_pdfs' => true,
                'time_zone' => '',
                'updated_at' => self::$syncProfile->updated_at,
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
     *
     * @doesNotPerformAssertions
     */
    public function testDisconnect(): void
    {
        $integration = $this->getIntegration();
        $integration->disconnect();

        // should do nothing
    }

    private function getIntegration(): QuickbooksDesktop
    {
        return self::getService('test.integration_factory')->get(IntegrationType::QuickBooksDesktop, self::$company);
    }
}
