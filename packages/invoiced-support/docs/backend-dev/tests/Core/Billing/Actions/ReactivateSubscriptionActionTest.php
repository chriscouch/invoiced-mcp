<?php

namespace App\Tests\Core\Billing\Actions;

use App\Companies\Models\Company;
use App\Core\Billing\Action\ReactivateSubscriptionAction;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Interfaces\BillingSystemInterface;
use App\Core\Billing\Models\BillingProfile;
use App\Tests\AppTestCase;
use Mockery;

class ReactivateSubscriptionActionTest extends AppTestCase
{
    private function getAction(BillingSystemInterface $billingSystem): ReactivateSubscriptionAction
    {
        $factory = Mockery::mock(BillingSystemFactory::class);
        $factory->shouldReceive('getForBillingProfile')->andReturn($billingSystem);

        return new ReactivateSubscriptionAction($factory);
    }

    public function testCannotReactivate(): void
    {
        $this->expectException(BillingException::class);

        $billingSystem = Mockery::mock(BillingSystemInterface::class);

        $action = $this->getAction($billingSystem);
        $company = new Company();
        $company->canceled = true;
        $company->billing_profile = new BillingProfile();

        $action->reactivate($company);
    }

    public function testReactivate(): void
    {
        $company = Mockery::mock(Company::class.'[saveOrFail]');
        $company->shouldReceive('saveOrFail')->once();
        $company->billing_profile = new BillingProfile();

        $billingSystem = Mockery::mock(BillingSystemInterface::class);
        $billingSystem->shouldReceive('reactivate')
            ->withArgs([$company->billing_profile])
            ->once();

        $action = $this->getAction($billingSystem);

        $action->reactivate($company);
    }

    public function testReactivateFail(): void
    {
        $this->expectException(BillingException::class);

        $company = new Company();
        $company->billing_profile = new BillingProfile();

        $billingSystem = Mockery::mock(BillingSystemInterface::class);
        $billingSystem->shouldReceive('reactivate')
            ->andThrow(new BillingException('could not reactivate'));

        $action = $this->getAction($billingSystem);

        $action->reactivate($company);
    }
}
