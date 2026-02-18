<?php

namespace App\Tests\PaymentPlans;

use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class PaymentPlanInstallmentTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasPlan();
    }

    public function testINVD1349(): void
    {
        $now = new CarbonImmutable();
        $plan = $this->buildPaymentPlanInstallments(new CarbonImmutable());
        $data = $plan->calculateBalance();
        $this->assertEquals(0, $data['age']);
        $this->assertEquals(0, $data['pastDueAge']);
        $this->assertEquals(250, $data['balance']);
        $this->assertEquals(0, $data['pastDueBalance']);

        $plan = $this->buildPaymentPlanInstallments(new CarbonImmutable('-14 days'));
        $data = $plan->calculateBalance();
        $this->assertEquals(14, $data['age']);
        $this->assertEquals(0, $data['pastDueAge']);
        $this->assertEquals(250, $data['balance']);
        $this->assertEquals(0, $data['pastDueBalance']);

        $expectedPastDueAge = 1;
        /** @var CarbonImmutable $previosMonth */
        $previosMonth = $now->modify('-1 month');
        if ($previosMonth->format('m') === $now->format('m')) {
            /** @var CarbonImmutable $previosMonth */
            $previosMonth = $now->modify('last day of previous month');
            $expectedPastDueAge = $now->diff($previosMonth->modify('next month')->modify('-1 days'))->days;
        }
        /** @var CarbonImmutable $date */
        $date = $previosMonth->modify('-1 days');

        $now2 = clone $now;
        if ($now2->modify('first day of this month')->format('j') === $now->format('j')) {
            $date2 = clone $date;
            $jn = $now2->modify('-1 days')->format('j');
            $j2 = $date2->format('j');
            if ($j2 < $jn) {
                $diff = ((int) $now2->modify('-1 days')->format('j')) - ((int) $date2->format('j'));
                $expectedPastDueAge = $diff + 1;
            }
        }

        $plan = $this->buildPaymentPlanInstallments($date);
        $data = $plan->calculateBalance();
        $days = $date->diff($now)->days;
        $this->assertEquals($days, $data['age']);
        $this->assertEquals($expectedPastDueAge, $data['pastDueAge']);
        $this->assertEquals(500, $data['balance']);
        $this->assertEquals(250, $data['pastDueBalance']);

        /** @var CarbonImmutable $date */
        $date = $now->modify('-1 month')->modify('-6 days');
        $plan = $this->buildPaymentPlanInstallments($date, 250);
        $data = $plan->calculateBalance();
        $date = (new CarbonImmutable())->setTimestamp($plan->installments[0]->date);
        $days = $date->diff($now)->days;
        $this->assertEquals($days, $data['age']);
        $this->assertEquals(0, $data['pastDueAge']);
        $this->assertEquals(250, $data['balance']);
        $this->assertEquals(0, $data['pastDueBalance']);

        /** @var CarbonImmutable $minusThreeMonths */
        $minusThreeMonths = $now->modify('-3 month');
        /** @var CarbonImmutable $date */
        $date = $minusThreeMonths->modify('-14 days');
        $plan = $this->buildPaymentPlanInstallments($date, 250);
        $data = $plan->calculateBalance();

        $date = (new CarbonImmutable())->setTimestamp($plan->installments[0]->date);
        $days = $date->diff($now)->days;
        $this->assertEquals($days, $data['age']);

        $date = (new CarbonImmutable())->setTimestamp($plan->installments[1]->date);
        $days = $date->diff($now)->days;
        $this->assertEquals($days, $data['pastDueAge']);
        $this->assertEquals(750, $data['balance']);
        $this->assertEquals(500, $data['pastDueBalance']);
    }

    private function buildPaymentPlanInstallments(\DateTimeInterface $date, int $pay = 0): PaymentPlan
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->date = $date->getTimestamp();
        $invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 1000,
            ],
        ];
        $invoice->saveOrFail();

        $paymentPlan = new PaymentPlan();
        $paymentPlan->invoice_id = $invoice->id;
        $installments = [];
        for ($i = 0; $i < 4; ++$i) {
            $newDate = $date->modify('next month');
            $date = $newDate->diff($date)->m && 0 == $newDate->diff($date)->d
                ? $newDate
                : $date->modify('last day of next month');

            $installment = new PaymentPlanInstallment();
            $installment->date = $date->getTimestamp();
            $installment->amount = 250;
            $installments[] = $installment;
        }
        $paymentPlan->installments = $installments;
        $paymentPlan->saveOrFail();

        if ($pay) {
            $payment = new Money('usd', $pay * 100);
            $paymentPlan->applyPayment($payment);
        }

        return $paymentPlan;
    }
}
