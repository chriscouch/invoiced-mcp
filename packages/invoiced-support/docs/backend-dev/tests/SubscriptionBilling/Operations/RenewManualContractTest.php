<?php

namespace App\Tests\SubscriptionBilling\Operations;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Operations\RenewManualContract;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class RenewManualContractTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasPlan();

        // enable manual contract renewals for testing
        self::$company->features->enable('subscription_manual_renewal');
    }

    public function testRenewBillInAdvance(): void
    {
        $yearFrom = ((int) date('Y')) - 4;
        // last day of the month
        [$hours, $minutes, $seconds] = [19, 44, 45];
        $time = (int) mktime($hours, $minutes, $seconds, 10, 31, $yearFrom);
        $expected = CarbonImmutable::createFromTimestamp($time);

        $subscription = $this->buildAdvanceSubscription($time);
        $renewContract = $this->getOperation();

        $expectedFirstStart = $expected->addMonthsNoOverflow(2)->lastOfMonth()->startOfDay();
        $expectedFirstEnd = $expectedFirstStart->addMonthsNoOverflow(2);
        $expectedFirstEnd = $expectedFirstEnd->setTime($hours, $minutes, $seconds)->subSeconds(2);

        $expectedStartSecond = $expectedFirstStart->addMonthsNoOverflow(2)->lastOfMonth()->startOfDay();
        $expectedEndSecond = $expectedFirstEnd->addMonthsNoOverflow(2)->lastOfMonth();
        $expectedEndSecond = $expectedEndSecond->setTime($hours, $minutes, $seconds)->subSeconds(2);

        $expectedStartThird = $expectedStartSecond->addMonthsNoOverflow(2)->lastOfMonth()->startOfDay();
        $expectedEndThird = $expectedEndSecond->addMonthsNoOverflow(2);

        $renewContract->renew($subscription, 1);

        $this->verifyLineItems($subscription);

        $this->assertEqualsDate($expectedFirstStart->toDateString(), (int) $subscription->period_start);
        $this->assertEqualsDate($expectedFirstEnd->toDateString(), (int) $subscription->period_end);
        $this->assertEquals(null, $subscription->renews_next);
        $this->assertEquals(1, $subscription->num_invoices);
        $this->assertEqualsDate($expectedFirstStart->toDateString(), (int) $subscription->contract_period_start);
        $this->assertEqualsDate($expectedFirstEnd->toDateString(), (int) $subscription->contract_period_end);

        $renewContract->renew($subscription, 1);
        $this->verifyLineItems($subscription);

        $this->assertEqualsDate($expectedStartSecond->toDateString(), (int) $subscription->period_start);
        $this->assertEqualsDate($expectedEndSecond->toDateString(), (int) $subscription->period_end);
        $this->assertEquals(null, $subscription->renews_next);
        $this->assertEquals(1, $subscription->num_invoices);
        $this->assertEqualsDate($expectedStartSecond->toDateString(), (int) $subscription->contract_period_start);
        $this->assertEqualsDate($expectedEndSecond->toDateString(), (int) $subscription->contract_period_end);

        $renewContract->renew($subscription, 1);
        $this->verifyLineItems($subscription);

        $subscription->refresh();
        $this->assertEqualsDate($expectedStartThird->toDateString(), (int) $subscription->period_start);
        $this->assertEqualsDate($expectedEndThird->toDateString(), (int) $subscription->period_end);
        $this->assertEquals(null, $subscription->renews_next);
        $this->assertEquals(1, $subscription->num_invoices);
        $this->assertEqualsDate($expectedStartThird->toDateString(), (int) $subscription->contract_period_start);
        $this->assertEqualsDate($expectedEndThird->toDateString(), (int) $subscription->contract_period_end);

        $invoices = Invoice::where('subscription_id', $subscription->id)->sort('id')->all();
        $this->assertCount(4, $invoices);

        $this->assertEqualsDate($expectedFirstStart->subMonthsNoOverflow(2)->toDateString(), $invoices[0]->date);
        $this->assertEqualsDate($expectedFirstStart->toDateString(), $invoices[1]->date);
        $this->assertEqualsDate($expectedStartSecond->toDateString(), $invoices[2]->date);
        $this->assertEqualsDate($expectedStartThird->toDateString(), $invoices[3]->date);
    }

    public function testRenewBillInArrears(): void
    {
        $yearFrom = ((int) date('Y')) - 4;

        //Last day of the month at 00:00:00
        $time = (int) mktime(0, 0, 0, 10, 31, $yearFrom);
        $subscription = $this->buildArrearsSubscription($time);
        $expected = CarbonImmutable::createFromTimestamp($time);
        $renewContract = $this->getOperation();
        $renewContract->renew($subscription, 1);

        $expectedFirstStart = $expected->addMonthsNoOverflow(2)->lastOfMonth()->startOfDay();
        $expectedFirstEnd = $expectedFirstStart->addMonthsNoOverflow(2)->lastOfMonth()->startOfDay()->subSecond();

        $expectedStartSecond = $expectedFirstStart->addMonthsNoOverflow(2);
        $expectedEndSecond = $expectedFirstEnd->addMonthsNoOverflow(2)->lastOfMonth()->startOfDay()->subSecond();

        $expectedStartThird = $expectedStartSecond->addMonthsNoOverflow(2)->lastOfMonth()->startOfDay();
        $expectedEndThird = $expectedEndSecond->addMonthsNoOverflow(2)->lastOfMonth()->startOfDay()->subSecond();

        $expectedStartForth = $expectedStartThird->addMonthsNoOverflow(2)->lastOfMonth()->startOfDay();
        $expectedEndForth = $expectedEndThird->addMonthsNoOverflow(2)->lastOfMonth()->startOfDay()->subSecond();

        $this->assertEqualsDate($expectedStartSecond->toDateString(), (int) $subscription->period_start);
        $this->assertEqualsDate($expectedEndSecond->toDateString(), (int) $subscription->period_end);
        $this->verifyLineItems(
            $subscription,
            $expectedFirstStart,
            $expectedFirstEnd
        );
        $this->assertEquals(null, $subscription->renews_next);
        $this->assertEquals(1, $subscription->num_invoices);
        $this->assertEqualsDate($expectedStartSecond->toDateString(), (int) $subscription->contract_period_start);
        $this->assertEqualsDate($expectedEndSecond->toDateString(), (int) $subscription->contract_period_end);

        $renewContract->renew($subscription, 1);

        $this->assertEqualsDate($expectedStartThird->toDateString(), (int) $subscription->period_start);
        $this->assertEqualsDate($expectedEndThird->toDateString(), (int) $subscription->period_end);
        $this->verifyLineItems(
            $subscription,
            $expectedStartSecond,
            $expectedEndSecond,
        );
        $this->assertEquals(null, $subscription->renews_next);
        $this->assertEquals(1, $subscription->num_invoices);
        $this->assertEqualsDate($expectedStartThird->toDateString(), (int) $subscription->contract_period_start);
        $this->assertEqualsDate($expectedEndThird->toDateString(), (int) $subscription->contract_period_end);

        $renewContract->renew($subscription, 1);
        $subscription->refresh();

        $this->verifyLineItems(
            $subscription,
            $expectedStartThird,
            $expectedEndThird,
        );

        $this->assertEqualsDate($expectedStartForth->toDateString(), (int) $subscription->period_start);
        $this->assertEqualsDate($expectedEndForth->toDateString(), (int) $subscription->period_end);
        $this->assertEquals(null, $subscription->renews_next);
        $this->assertEquals(1, $subscription->num_invoices);
        $this->assertEqualsDate($expectedStartForth->toDateString(), (int) $subscription->contract_period_start);
        $this->assertEqualsDate($expectedEndForth->toDateString(), (int) $subscription->contract_period_end);

        $invoices = Invoice::where('subscription_id', $subscription->id)->sort('id')->all();
        $this->assertCount(4, $invoices);
        $this->assertEqualsDate($expectedFirstStart->subSecond()->toDateString(), $invoices[0]->date);
        $this->assertEqualsDate($expectedStartSecond->subSecond()->toDateString(), $invoices[1]->date);
        $this->assertEqualsDate($expectedStartThird->subSecond()->toDateString(), $invoices[2]->date);
        $this->assertEqualsDate($expectedStartForth->subSecond()->toDateString(), $invoices[3]->date);
    }

    private function getOperation(): RenewManualContract
    {
        return self::getService('test.renew_contract');
    }

    private function verifyLineItems(
        Subscription $subscription,
        ?CarbonImmutable $periodStart = null,
        ?CarbonImmutable $periodEnd = null
    ): void {
        $periodStart = $periodStart ? $periodStart->getTimestamp() : $subscription->period_start;
        $periodEnd = $periodEnd ? $periodEnd->getTimestamp() : $subscription->period_end;

        $this->assertEquals(1, LineItem::where('subscription_id', $subscription->id)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->count());
    }

    private function buildAdvanceSubscription(int $date): Subscription
    {
        return self::getService('test.create_subscription')->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => $date,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_MANUAL,
            'cycles' => 1,
            'contract_renewal_cycles' => null,
            'bill_in' => Subscription::BILL_IN_ADVANCE,
        ]);
    }

    private function buildArrearsSubscription(int $date): Subscription
    {
        return self::getService('test.create_subscription')->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => $date,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_MANUAL,
            'cycles' => 1,
            'contract_renewal_cycles' => null,
            'bill_in' => Subscription::BILL_IN_ARREARS,
        ]);
    }
}
