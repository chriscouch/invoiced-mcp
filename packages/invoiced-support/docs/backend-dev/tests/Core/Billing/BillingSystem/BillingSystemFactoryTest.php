<?php

namespace App\Tests\Core\Billing\BillingSystem;

use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\BillingSystem\InvoicedBillingSystem;
use App\Core\Billing\BillingSystem\NullBillingSystem;
use App\Core\Billing\BillingSystem\ResellerBillingSystem;
use App\Core\Billing\BillingSystem\StripeBillingSystem;
use App\Core\Billing\Models\BillingProfile;
use App\Tests\AppTestCase;

class BillingSystemFactoryTest extends AppTestCase
{
    private function getFactory(): BillingSystemFactory
    {
        return self::getService('test.billing_system_factory');
    }

    public function testGetForAccountInvoiced(): void
    {
        $factory = $this->getFactory();

        $billingProfile = new BillingProfile(['billing_system' => 'invoiced']);
        $this->assertInstanceOf(InvoicedBillingSystem::class, $factory->getForBillingProfile($billingProfile));
    }

    public function testGetForAccountNone(): void
    {
        $factory = $this->getFactory();

        $billingProfile = new BillingProfile(['billing_system' => null]);
        $this->assertInstanceOf(NullBillingSystem::class, $factory->getForBillingProfile($billingProfile));
    }

    public function testGetForAccountReseller(): void
    {
        $factory = $this->getFactory();

        $billingProfile = new BillingProfile(['billing_system' => 'reseller']);
        $this->assertInstanceOf(ResellerBillingSystem::class, $factory->getForBillingProfile($billingProfile));
    }

    public function testGetForAccountStripe(): void
    {
        $factory = $this->getFactory();

        $billingProfile = new BillingProfile(['billing_system' => 'stripe']);
        $this->assertInstanceOf(StripeBillingSystem::class, $factory->getForBillingProfile($billingProfile));
    }
}
