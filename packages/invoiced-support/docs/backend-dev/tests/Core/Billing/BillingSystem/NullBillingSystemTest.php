<?php

namespace App\Tests\Core\Billing\BillingSystem;

use App\Companies\Models\Company;
use App\Core\Billing\BillingSystem\NullBillingSystem;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\ValueObjects\BillingOneTimeItem;
use App\Core\Billing\ValueObjects\BillingSubscriptionItem;
use App\Core\Entitlements\Models\Product;
use App\Core\I18n\ValueObjects\Money;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class NullBillingSystemTest extends AppTestCase
{
    public function getBillingSystem(): NullBillingSystem
    {
        return new NullBillingSystem();
    }

    private function getBillingProfile(): BillingProfile
    {
        return new BillingProfile();
    }

    public function testCreateSubscription(): void
    {
        $this->expectException(BillingException::class);
        $billingProfile = $this->getBillingProfile();
        $company = new Company();
        $product = Product::where('name', 'Advanced Accounts Receivable')->one();
        $subscriptionItems = [
            new BillingSubscriptionItem(
                price: new Money('usd', 100000),
                billingInterval: BillingInterval::Monthly,
                product: $product,
            ),
        ];
        $this->getBillingSystem()->createSubscription($billingProfile, $subscriptionItems, CarbonImmutable::now());
    }

    public function testUpdateSubscription(): void
    {
        $this->expectException(BillingException::class);
        $billingProfile = $this->getBillingProfile();
        $subscriptionItems = [
            new BillingSubscriptionItem(
                price: new Money('usd', 100000),
                billingInterval: BillingInterval::Monthly,
                product: Product::where('name', 'Advanced Accounts Receivable')->one(),
            ),
        ];
        $prorationDate = CarbonImmutable::now();
        $this->getBillingSystem()->updateSubscription($billingProfile, $subscriptionItems, true, $prorationDate);
    }

    public function testSetDefaultPaymentMethod(): void
    {
        $this->expectException(BillingException::class);
        $billingProfile = $this->getBillingProfile();
        $this->getBillingSystem()->setDefaultPaymentMethod($billingProfile, 'tok_test');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testBillLineItem(): void
    {
        $billingProfile = $this->getBillingProfile();
        $charge = new BillingOneTimeItem(
            price: new Money('usd', 100),
            usageType: UsageType::CustomersPerMonth,
            billingInterval: BillingInterval::Monthly,
            periodStart: new CarbonImmutable('2020-01'),
            periodEnd: new CarbonImmutable('2020-02'),
        );
        $this->getBillingSystem()->billLineItem($billingProfile, $charge, false);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCancel(): void
    {
        $billingSystem = $this->getBillingSystem();
        $billingProfile = $this->getBillingProfile();

        $billingSystem->cancel($billingProfile, false);
    }

    public function testCancelAtPeriodEnd(): void
    {
        $this->expectException(BillingException::class);
        $billingSystem = $this->getBillingSystem();
        $billingProfile = $this->getBillingProfile();

        $billingSystem->cancel($billingProfile, true);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testReactivate(): void
    {
        $billingProfile = $this->getBillingProfile();
        $this->getBillingSystem()->reactivate($billingProfile);
    }

    public function testGetBillingState(): void
    {
        $billingProfile = $this->getBillingProfile();

        $billingState = $this->getBillingSystem()->getBillingState($billingProfile);
        $this->assertEquals([], $billingState->paymentSource);
        $this->assertNull($billingState->discount);
        $this->assertNull($billingState->nextBillDate);
        $this->assertFalse($billingState->cancelAtPeriodEnd);
        $this->assertEquals(0, $billingState->nextChargeAmount);
        $this->assertFalse($billingState->autopay);
    }

    public function testGetBillingHistory(): void
    {
        $billingProfile = $this->getBillingProfile();

        $this->assertEquals([], $this->getBillingSystem()->getBillingHistory($billingProfile));
    }

    public function testUpdatePaymentInfoUrl(): void
    {
        $billingProfile = $this->getBillingProfile();
        $this->assertNull($this->getBillingSystem()->getUpdatePaymentInfoUrl($billingProfile));
    }
}
