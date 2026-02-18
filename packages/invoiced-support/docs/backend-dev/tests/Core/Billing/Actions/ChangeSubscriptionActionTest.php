<?php

namespace App\Tests\Core\Billing\Actions;

use App\Companies\Models\Company;
use App\Companies\ValueObjects\EntitlementsChangeset;
use App\Core\Billing\Action\ChangeSubscriptionAction;
use App\Core\Billing\Audit\BillingAudit;
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

class ChangeSubscriptionActionTest extends AppTestCase
{
    private function getAction(BillingSystemInterface $billingSystem): ChangeSubscriptionAction
    {
        $factory = Mockery::mock(BillingSystemFactory::class);
        $factory->shouldReceive('getForBillingProfile')->andReturn($billingSystem);
        $billingItemFactory = new BillingItemFactory();
        $billingAudit = Mockery::mock(BillingAudit::class);
        $billingAudit->shouldReceive('audit')->once()->andReturn(true);
        $entitlementsManager = self::getService('test.company_entitlements_manager');

        return new ChangeSubscriptionAction($factory, $billingItemFactory, $billingAudit, $entitlementsManager);
    }

    public function testCannotChangeMissingSubscription(): void
    {
        $this->expectException(BillingException::class);

        $company = Mockery::mock(Company::class.'[saveOrFail]');
        $company->shouldReceive('saveOrFail')->once();
        $company->canceled = false;
        $billingProfile = Mockery::mock(BillingProfile::class.'[saveOrFail]');
        $billingProfile->shouldReceive('saveOrFail')->once();
        $company->billing_profile = $billingProfile;
        $changeset = new EntitlementsChangeset();

        $action = $this->getAction(new NullBillingSystem());
        $action->change($company, $changeset);
    }

    public function testChange(): void
    {
        $company = Mockery::mock(Company::class.'[saveOrFail]');
        $company->shouldReceive('saveOrFail')->once();
        $company->canceled = false;
        $billingProfile = Mockery::mock(BillingProfile::class.'[saveOrFail]');
        $billingProfile->billing_interval = BillingInterval::Monthly;
        $billingProfile->shouldReceive('saveOrFail')->once();
        $company->billing_profile = $billingProfile;

        $prorationDate = CarbonImmutable::now();
        $billingSystem = Mockery::mock(BillingSystemInterface::class);
        $billingSystem->shouldReceive('updateSubscription')
            ->withArgs([$billingProfile, [
                new BillingSubscriptionItem(new Money('usd', 0), BillingInterval::Monthly),
            ], false, $prorationDate])
            ->once();

        $changeset = new EntitlementsChangeset();

        $action = $this->getAction($billingSystem);
        $action->change($company, $changeset, false, $prorationDate);

        $this->assertFalse($billingProfile->past_due);
        $this->assertFalse($company->canceled);
        $this->assertNull($company->canceled_at);
        $this->assertNull($company->trial_ends);
    }

    public function testChangeFail(): void
    {
        $this->expectException(BillingException::class);

        $company = Mockery::mock(Company::class.'[saveOrFail]');
        $company->shouldReceive('saveOrFail')->once();
        $company->canceled = false;
        $billingProfile = Mockery::mock(BillingProfile::class.'[saveOrFail]');
        $billingProfile->shouldReceive('saveOrFail')->once();
        $company->billing_profile = $billingProfile;

        $billingSystem = Mockery::mock(BillingSystemInterface::class);

        $prorationDate = CarbonImmutable::now();
        $e = new BillingException('error');
        $billingSystem->shouldReceive('updateSubscription')
            ->withArgs([$billingProfile, [
                new BillingSubscriptionItem(new Money('usd', 0), BillingInterval::Monthly),
            ], true, $prorationDate])
            ->andThrow($e);

        $changeset = new EntitlementsChangeset();

        $action = $this->getAction($billingSystem);
        $action->change($company, $changeset, true, $prorationDate);
    }
}
