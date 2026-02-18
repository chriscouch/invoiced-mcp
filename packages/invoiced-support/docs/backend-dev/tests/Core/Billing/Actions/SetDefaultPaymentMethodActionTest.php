<?php

namespace App\Tests\Core\Billing\Actions;

use App\Core\Billing\Action\SetDefaultPaymentMethodAction;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Interfaces\BillingSystemInterface;
use App\Core\Billing\Models\BillingProfile;
use App\Tests\AppTestCase;
use Mockery;

class SetDefaultPaymentMethodActionTest extends AppTestCase
{
    private function getAction(BillingSystemInterface $billingSystem): SetDefaultPaymentMethodAction
    {
        $factory = Mockery::mock(BillingSystemFactory::class);
        $factory->shouldReceive('getForBillingProfile')->andReturn($billingSystem);

        return new SetDefaultPaymentMethodAction($factory);
    }

    public function testSetNoToken(): void
    {
        $this->expectException(BillingException::class);

        $billingSystem = Mockery::mock(BillingSystemInterface::class);
        $action = $this->getAction($billingSystem);
        $action->set(new BillingProfile(), '');
    }

    public function testSet(): void
    {
        $billingProfile = new BillingProfile();
        $billingSystem = Mockery::mock(BillingSystemInterface::class);
        $billingSystem->shouldReceive('setDefaultPaymentMethod')
            ->withArgs([$billingProfile, 'test'])
            ->once();

        $action = $this->getAction($billingSystem);
        $action->set($billingProfile, 'test');
    }
}
