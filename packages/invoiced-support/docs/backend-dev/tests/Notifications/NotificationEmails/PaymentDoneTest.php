<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\PaymentDone;

class PaymentDoneTest extends AbstractNotificationEmailTest
{
    private array $payments;

    private function addEvent(): void
    {
        self::hasPayment(self::$customer);
        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::PaymentDone);
        $event->object_id = self::$payment->id;
        self::$events[] = $event;
        $payment = self::$payment->toArray();
        $payment['customer'] = self::$customer->toArray();
        $this->payments[] = $payment;
    }

    public function testProcess(): void
    {
        self::hasCustomer();
        $this->addEvent();

        $email = new PaymentDone(self::getService('test.database'));

        $this->assertEquals(
            [
                'subject' => 'New payment received',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/payment', $email->getTemplate(self::$events));
        $this->assertEquals($this->payments, $email->getVariables(self::$events)['payments']);
    }

    public function testProcessBulk(): void
    {
        self::hasCustomer();

        $email = new PaymentDone(self::getService('test.database'));

        $this->addEvent();
        $this->addEvent();
        $this->addEvent();

        $this->assertEquals(
            [
                'subject' => 'New payment received',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/payment-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'payments' => [
                    [
                        'cnt' => '4',
                        'amount' => 800,
                        'currency' => 'usd',
                    ],
                ],
            ],
            $email->getVariables(self::$events)
        );
    }
}
