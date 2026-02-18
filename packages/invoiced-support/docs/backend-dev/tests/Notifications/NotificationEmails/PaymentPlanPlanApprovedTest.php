<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\PaymentPlanApproved;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;

class PaymentPlanPlanApprovedTest extends AbstractNotificationEmailTest
{
    private array $plans;

    private function createPaymentPlan(): PaymentPlan
    {
        self::hasInvoice();
        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('-1 month');
        $installment1->amount = 50;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = time() - 1;
        $installment2->amount = 50;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [$installment1, $installment2];
        self::$invoice->attachPaymentPlan($paymentPlan, false, true);

        return $paymentPlan;
    }

    private function addEvent(): void
    {
        $paymentPlan = $this->createPaymentPlan();
        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::PaymentPlanApproved);
        $event->object_id = $paymentPlan->id;
        self::$events[] = $event;
        $paymentPlan = $paymentPlan->toArray();
        $paymentPlan['invoice'] = self::$invoice->toArray();
        $this->plans[] = $paymentPlan;
    }

    public function testProcess(): void
    {
        self::hasCustomer();
        $this->addEvent();

        $email = new PaymentPlanApproved(self::getService('test.database'));

        $this->assertEquals(
            [
                'subject' => 'Payment plan was approved',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/plan-approved', $email->getTemplate(self::$events));
        $this->assertEquals($this->plans, $email->getVariables(self::$events)['plans']);
    }

    public function testProcessBulk(): void
    {
        self::hasCustomer();

        $email = new PaymentPlanApproved(self::getService('test.database'));

        $this->addEvent();
        $this->addEvent();
        $this->addEvent();
        $this->assertEquals(
            [
                'subject' => 'Payment plan was approved',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/plan-approved-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'count' => 4,
            ],
            $email->getVariables(self::$events)
        );
    }
}
