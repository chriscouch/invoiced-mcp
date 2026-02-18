<?php

namespace App\Tests\Core\Billing\Actions;

use App\Companies\Models\Company;
use App\Core\Billing\Action\CancelSubscriptionAction;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Interfaces\BillingSystemInterface;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Mailer\Mailer;
use App\Tests\AppTestCase;
use Mockery;

class CancelSubscriptionActionTest extends AppTestCase
{
    private function getAction(BillingSystemInterface $billingSystem, ?Mailer $mailer = null): CancelSubscriptionAction
    {
        $factory = Mockery::mock(BillingSystemFactory::class);
        $factory->shouldReceive('getForBillingProfile')->andReturn($billingSystem);
        $mailer ??= self::getService('test.mailer');

        return new CancelSubscriptionAction($factory, $mailer);
    }

    public function testCancel(): void
    {
        $billingSystem = Mockery::mock(BillingSystemInterface::class);

        $company = Mockery::mock(Company::class.'[saveOrFail]');
        $company->shouldReceive('saveOrFail')->once();
        $company->canceled = false;
        $company->trial_ends = 0;
        $company->billing_profile = new BillingProfile();
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('sendToAdministrators')->once();

        $action = $this->getAction($billingSystem, $mailer);

        $billingSystem->shouldReceive('cancel')
            ->withArgs([$company->billing_profile, false])
            ->once();

        $action->cancel($company, 'reason');

        $this->assertTrue($company->canceled);
    }

    public function testCancelAtPeriodEnd(): void
    {
        $company = Mockery::mock(Company::class.'[saveOrFail]');
        $company->shouldReceive('saveOrFail')->once();
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('sendToAdministrators')->once();
        $company->canceled = false;
        $company->trial_ends = 0;
        $company->billing_profile = new BillingProfile();

        $billingSystem = Mockery::mock(BillingSystemInterface::class);
        $billingSystem->shouldReceive('cancel')
            ->withArgs([$company->billing_profile, true])
            ->once();
        $company->billing_profile->billing_system = 'test';

        $action = $this->getAction($billingSystem, $mailer);

        $action->cancel($company, 'reason', true);

        $this->assertFalse($company->canceled);
        $this->assertNull($company->canceled_at);
    }

    public function testCancelAtPeriodEndNoBillingSystem(): void
    {
        $company = Mockery::mock(Company::class.'[saveOrFail]');
        $company->shouldReceive('saveOrFail')->once();
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('sendToAdministrators')->once();
        $company->canceled = false;
        $company->trial_ends = 0;
        $company->billing_profile = new BillingProfile();

        $billingSystem = Mockery::mock(BillingSystemInterface::class);
        $billingSystem->shouldReceive('cancel')
            ->withArgs([$company->billing_profile, false])
            ->once();
        $action = $this->getAction($billingSystem, $mailer);
        $action->cancel($company, 'reason', true);

        $this->assertTrue($company->canceled);
        $this->assertBetween(time() - $company->canceled_at, 0, 3);
    }

    public function testCancelFail(): void
    {
        $this->expectException(BillingException::class);

        $billingSystem = Mockery::mock(BillingSystemInterface::class);

        $action = $this->getAction($billingSystem);

        $e = new BillingException('error');
        $billingSystem->shouldReceive('cancel')
            ->andThrow($e);

        $company = new Company();
        $company->canceled = false;
        $company->trial_ends = 0;
        $company->billing_profile = new BillingProfile();

        $action->cancel($company, 'reason');
    }
}
