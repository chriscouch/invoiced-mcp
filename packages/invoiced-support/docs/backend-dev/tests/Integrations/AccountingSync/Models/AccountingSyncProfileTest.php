<?php

namespace App\Tests\Integrations\AccountingSync\Models;

use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class AccountingSyncProfileTest extends AppTestCase
{
    private static AccountingSyncProfile $profile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$profile = new AccountingSyncProfile();
        self::$profile->integration = IntegrationType::BusinessCentral;
        $this->assertTrue(self::$profile->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'created_at' => self::$profile->created_at,
            'id' => self::$profile->id,
            'invoice_start_date' => CarbonImmutable::now()->getTimestamp(),
            'last_synced' => null,
            'parameters' => (object) [],
            'payment_accounts' => [],
            'read_credit_notes' => false,
            'read_customers' => false,
            'read_invoices' => false,
            'read_invoices_as_drafts' => false,
            'read_payments' => false,
            'read_pdfs' => true,
            'updated_at' => self::$profile->updated_at,
            'write_convenience_fees' => false,
            'write_credit_notes' => false,
            'write_customers' => false,
            'write_invoices' => false,
            'write_payments' => false,
        ];

        $this->assertEquals($expected, self::$profile->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$profile->read_customers = true;
        $this->assertTrue(self::$profile->save());
        $this->assertGreaterThan(0, self::$profile->read_cursor);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$profile->delete());
    }
}
