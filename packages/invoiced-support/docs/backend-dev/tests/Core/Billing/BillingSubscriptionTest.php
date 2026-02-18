<?php

namespace App\Tests\Core\Billing;

use App\Companies\Models\Company;
use App\Core\Billing\Enums\BillingSubscriptionStatus;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\ValueObjects\BillingSubscriptionStatusGenerator;
use App\Tests\AppTestCase;

class BillingSubscriptionTest extends AppTestCase
{
    public function testCanceled(): void
    {
        $company = new Company();
        $company->canceled = true;
        $billingProfile = new BillingProfile();
        $company->billing_profile = $billingProfile;
        $status = BillingSubscriptionStatusGenerator::get($company);

        $this->assertEquals(BillingSubscriptionStatus::Canceled, $status);
    }

    public function testStatusActive(): void
    {
        $company = new Company();
        $billingProfile = new BillingProfile();
        $company->billing_profile = $billingProfile;
        $status = BillingSubscriptionStatusGenerator::get($company);

        $this->assertEquals(BillingSubscriptionStatus::Active, $status);
        $this->assertTrue($status->isActive());
    }

    public function testStatusActiveNoBillingSystem(): void
    {
        $company = new Company();
        $billingProfile = new BillingProfile();
        $company->billing_profile = $billingProfile;
        $status = BillingSubscriptionStatusGenerator::get($company);

        $this->assertEquals(BillingSubscriptionStatus::Active, $status);
        $this->assertTrue($status->isActive());
    }

    public function testStatusActiveNotRenewedYet(): void
    {
        $company = new Company();
        $billingProfile = new BillingProfile(['past_due' => false]);
        $company->billing_profile = $billingProfile;
        $status = BillingSubscriptionStatusGenerator::get($company);

        $this->assertEquals(BillingSubscriptionStatus::Active, $status);
        $this->assertTrue($status->isActive());
    }

    public function testStatusTrialing(): void
    {
        $company = new Company();
        $company->trial_ends = time() + 900;
        $billingProfile = new BillingProfile();
        $company->billing_profile = $billingProfile;
        $status = BillingSubscriptionStatusGenerator::get($company);

        $this->assertEquals(BillingSubscriptionStatus::Trialing, $status);
    }

    public function testPastDue(): void
    {
        $company = new Company();
        $billingProfile = new BillingProfile(['past_due' => true]);
        $company->billing_profile = $billingProfile;
        $status = BillingSubscriptionStatusGenerator::get($company);

        $this->assertEquals(BillingSubscriptionStatus::PastDue, $status);
        $this->assertTrue($status->isActive());
    }

    public function testStatusUnpaid(): void
    {
        $company = new Company();
        $company->trial_ends = strtotime('-1 day');
        $billingProfile = new BillingProfile();
        $company->billing_profile = $billingProfile;
        $status = BillingSubscriptionStatusGenerator::get($company);

        $this->assertEquals(BillingSubscriptionStatus::Unpaid, $status);
    }
}
