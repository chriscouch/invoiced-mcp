<?php

namespace App\Tests\Core\Billing\Usage;

use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Usage\UsageFactory;
use App\Core\Billing\Usage\CustomersPerMonth;
use App\Core\Billing\Usage\InvoicesPerMonth;
use App\Tests\AppTestCase;

class UsageFactoryTest extends AppTestCase
{
    private function getFactory(): UsageFactory
    {
        return self::getService('test.billing_usage_factory');
    }

    public function testFactory(): void
    {
        $factory = $this->getFactory();
        $this->assertInstanceOf(InvoicesPerMonth::class, $factory->get(UsageType::InvoicesPerMonth));
        $this->assertInstanceOf(CustomersPerMonth::class, $factory->get(UsageType::CustomersPerMonth));
    }
}
