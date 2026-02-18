<?php

namespace App\Tests\Core\Billing\Actions;

use App\Companies\Models\Company;
use App\Companies\ValueObjects\EntitlementsChangeset;
use App\Core\Billing\Action\CreateSubscriptionAction;
use App\Core\Billing\Audit\BillingItemFactory;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\BillingSystem\NullBillingSystem;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Interfaces\BillingSystemInterface;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\ValueObjects\BillingSubscriptionItem;
use App\Core\I18n\ValueObjects\Money;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class CreateSubscriptionActionTest extends AppTestCase
{
    private function getAction(BillingSystemInterface $billingSystem): CreateSubscriptionAction
    {
        $factory = Mockery::mock(BillingSystemFactory::class);
        $factory->shouldReceive('getForBillingProfile')->andReturn($billingSystem);
        $billingItemFactory = new BillingItemFactory();
        $entitlementsManager = self::getService('test.company_entitlements_manager');

        return new CreateSubscriptionAction($factory, $billingItemFactory, $entitlementsManager);
    }

    public function testCannotCreateExistingSubscription(): void
    {
        $this->expectException(BillingException::class);

        $billingSystem = new NullBillingSystem();

        $company = Mockery::mock(Company::class.'[saveOrFail]');
        $company->shouldReceive('saveOrFail')->once();
        $company->canceled = false;
        $billingProfile = Mockery::mock(BillingProfile::class.'[saveOrFail]');
        $billingProfile->shouldReceive('saveOrFail')->once();
        $company->billing_profile = $billingProfile;

        $changeset = new EntitlementsChangeset();

        $action = $this->getAction($billingSystem);
        $action->create($company, BillingInterval::Monthly, $changeset);
    }

    public function testCannotCreateNoPlan(): void
    {
        $this->expectException(BillingException::class);

        $billingSystem = new NullBillingSystem();

        $company = Mockery::mock(Company::class.'[saveOrFail]');
        $company->shouldReceive('saveOrFail')->once();
        $company->canceled = false;
        $billingProfile = Mockery::mock(BillingProfile::class.'[saveOrFail]');
        $billingProfile->shouldReceive('saveOrFail')->once();
        $company->billing_profile = $billingProfile;

        $changeset = new EntitlementsChangeset();

        $action = $this->getAction($billingSystem);
        $action->create($company, BillingInterval::Monthly, $changeset);
    }

    public function testCreate(): void
    {
        $company = Mockery::mock(Company::class.'[saveOrFail]');
        $company->shouldReceive('saveOrFail')->once();
        $company->canceled = false;
        $company->trial_ends = time() + 1000;
        $billingProfile = Mockery::mock(BillingProfile::class.'[saveOrFail]');
        $billingProfile->shouldReceive('saveOrFail')->once();
        $company->billing_profile = $billingProfile;

        $startDate = CarbonImmutable::now();
        $billingSystem = Mockery::mock(BillingSystemInterface::class);
        $billingSystem->shouldReceive('createSubscription')
            ->withArgs([$billingProfile, [
                new BillingSubscriptionItem(new Money('usd', 0), BillingInterval::Monthly),
            ], $startDate])
            ->once();

        $changeset = new EntitlementsChangeset();

        $action = $this->getAction($billingSystem);
        $action->create($company, BillingInterval::Monthly, $changeset, $startDate);

        $this->assertFalse($company->billing_profile?->past_due);
        $this->assertFalse($company->canceled);
        $this->assertNull($company->canceled_at);
        $this->assertEquals(0, $company->trial_ends);
    }

    public function testCreateFail(): void
    {
        $this->expectException(BillingException::class);

        $company = Mockery::mock(Company::class.'[saveOrFail]');
        $company->shouldReceive('saveOrFail')->once();
        $company->canceled = false;
        $billingProfile = Mockery::mock(BillingProfile::class.'[saveOrFail]');
        $billingProfile->shouldReceive('saveOrFail')->once();
        $company->billing_profile = $billingProfile;

        $startDate = CarbonImmutable::now();
        $billingSystem = Mockery::mock(BillingSystemInterface::class);
        $e = new BillingException('error');
        $billingSystem->shouldReceive('createSubscription')
            ->withArgs([$billingProfile, [
                new BillingSubscriptionItem(new Money('usd', 0), BillingInterval::Monthly),
            ], $startDate])
            ->andThrow($e);

        $changeset = new EntitlementsChangeset();

        $action = $this->getAction($billingSystem);
        $action->create($company, BillingInterval::Monthly, $changeset, $startDate);
    }
}
