<?php

namespace App\Tests\Core\Billing\Actions;

use App\Companies\Models\Company;
use App\Core\Billing\Action\ChangeExtraUserCountAction;
use App\Core\Billing\Audit\BillingAudit;
use App\Core\Billing\Audit\BillingItemFactory;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\BillingSystem\NullBillingSystem;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Interfaces\BillingSystemInterface;
use App\Tests\AppTestCase;
use Mockery;

class ChangeExtraUserCountActionTest extends AppTestCase
{
    private function getAction(BillingSystemInterface $billingSystem): ChangeExtraUserCountAction
    {
        $factory = Mockery::mock(BillingSystemFactory::class);
        $factory->shouldReceive('getForBillingProfile')->andReturn($billingSystem);
        $billingItemFactory = new BillingItemFactory();
        $billingAudit = Mockery::mock(BillingAudit::class);

        return new ChangeExtraUserCountAction($factory, $billingItemFactory, $billingAudit);
    }

    public function testChangeUserCountBelowMin(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage('Additional user count cannot be negative');

        $action = $this->getAction(new NullBillingSystem());
        $action->change(new Company(), -1);
    }
}
