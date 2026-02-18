<?php

namespace App\Tests\Core\Entitlements;

use App\Core\Entitlements\Enums\QuotaType;
use App\Tests\AppTestCase;

class QuotaTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testAll(): void
    {
        $expected = [
            QuotaType::MaxDocumentVersions->value => 10,
            QuotaType::MaxOpenNetworkInvitations->value => 5,
            QuotaType::NewCompanyLimit->value => 3,
            QuotaType::TransactionsPerDay->value => null,
            QuotaType::Users->value => null,
            QuotaType::VendorPayDailyLimit->value => 100000,
            QuotaType::CustomerEmailDailyLimit->value => null,
        ];
        $this->assertEquals($expected, self::$company->quota->all());
    }

    public function testGetAndSetQuota(): void
    {
        $this->assertNull(self::$company->quota->get(QuotaType::Users));

        self::$company->quota->set(QuotaType::Users, 1);
        $this->assertEquals(1, self::$company->quota->get(QuotaType::Users));

        self::$company->quota->set(QuotaType::Users, 5);
        $this->assertEquals(5, self::$company->quota->get(QuotaType::Users));

        self::$company->quota->remove(QuotaType::Users);
        $this->assertNull(self::$company->quota->get(QuotaType::Users));
    }
}
