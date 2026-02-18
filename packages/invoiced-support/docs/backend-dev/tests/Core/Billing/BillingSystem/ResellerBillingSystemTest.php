<?php

namespace App\Tests\Core\Billing\BillingSystem;

use App\Companies\Models\Company;
use App\Core\Billing\BillingSystem\ResellerBillingSystem;
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
use Invoiced\Client;
use Invoiced\Customer;
use Invoiced\Item;
use Invoiced\Plan;
use Invoiced\Subscription;
use Mockery;

class ResellerBillingSystemTest extends AppTestCase
{
    private function getBillingSystem(Client $mockClient = null): ResellerBillingSystem
    {
        $billingSystem = new ResellerBillingSystem('invd_test', 'test');
        $billingSystem->setLogger(self::$logger);
        $mockClient = $mockClient ?? Mockery::mock(Client::class);
        $billingSystem->setClient($mockClient);

        return $billingSystem;
    }

    private function getBillingProfile(): BillingProfile
    {
        return new BillingProfile([
            'billing_system' => 'reseller',
            'invoiced_customer' => '1234',
            'billing_interval' => BillingInterval::Monthly,
        ]);
    }

    public function testCreateSubscription(): void
    {
        $this->expectException(BillingException::class);
        $company = new Company();
        $billingProfile = $this->getBillingProfile();
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
        $client = Mockery::mock(Client::class);

        $customerResult = Mockery::mock(Customer::class, [$client, 123]);
        $mockCustomer = Mockery::mock();
        $mockCustomer->shouldReceive('retrieve')->andReturn($customerResult);
        $client->Customer = $mockCustomer;

        $subscriptionResult = Mockery::mock(Subscription::class, [$client, 123]);
        $subscriptionResult->customer = 123;
        $subscriptionResult->status = 'active';
        $subscriptionResult->plan = 'whatever';
        $subscriptionResult->quantity = 1;
        $subscriptionResult->amount = null;
        $subscriptionResult->addons = [
            [
                'id' => 'eh',
                'plan' => 'sure',
                'amount' => null,
                'quantity' => 1,
            ],
        ];
        $subscriptionResult->shouldReceive('save')
            ->andReturn(true);

        $mockSubscription = Mockery::mock(Subscription::class);
        $mockSubscription->shouldReceive('all')
            ->andReturn([[$subscriptionResult]]);
        $client->Subscription = $mockSubscription;

        $billingSystem = $this->getBillingSystem($client);

        $product = Product::where('name', 'Advanced Accounts Receivable')->one();
        $plan = new Plan($client, 'invoiced-product-'.$product->id.'-monthly', [
            'interval' => 'month',
            'interval_count' => 1,
        ]);
        $billingSystem->setPlan($plan);

        $item = new Item($client, 'invoiced-product-'.$product->id, []);
        $billingSystem->setItem($item);

        $subscriptionItems = [
            new BillingSubscriptionItem(
                price: new Money('usd', 100000),
                billingInterval: BillingInterval::Monthly,
                product: $product,
            ),
        ];
        $prorationDate = CarbonImmutable::now();
        $billingSystem->updateSubscription($this->getBillingProfile(), $subscriptionItems, true, $prorationDate);

        $this->assertTrue($subscriptionResult->prorate); /* @phpstan-ignore-line */
        $this->assertEquals(1, $subscriptionResult->quantity);
        $this->assertEquals('invoiced-product-'.$product->id.'-monthly', $subscriptionResult->plan);
        $this->assertEquals(1000.0, $subscriptionResult->amount);
        $this->assertCount(0, $subscriptionResult->addons);
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

        // The reseller billing system should do nothing with an immediate cancellation
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
