<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\AutoPaySucceeded;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Models\Charge;

class AutoPaySucceededTest extends AbstractNotificationEmailTest
{
    private array $charges;

    private function addEvent(): void
    {
        self::hasInvoice();
        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = self::$invoice->balance;
        $charge->status = Charge::SUCCEEDED;
        $charge->gateway = TestGateway::ID;
        $charge->gateway_id = 'ch_test'.microtime(true);
        $charge->setPaymentSource(self::$card);
        $charge->saveOrFail();

        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::AutoPaySucceeded);
        $event->object_id = $charge->id;
        self::$events[] = $event;
        $result = $charge->toArray();
        $result['customer'] = self::$customer->toArray();
        $this->charges[] = $result;
    }

    private function getEmail(): AutoPaySucceeded
    {
        return new AutoPaySucceeded(self::getService('test.database'));
    }

    public function testProcess(): void
    {
        self::hasCustomer();
        self::hasCard();
        $this->addEvent();

        $email = $this->getEmail();

        $this->assertEquals(
            [
                'subject' => 'New AutoPay payment received',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/autopay-succeeded', $email->getTemplate(self::$events));
        $this->assertEquals($this->charges, $email->getVariables(self::$events)['charges']);
    }

    public function testProcessBulk(): void
    {
        $email = $this->getEmail();
        self::hasCustomer();
        $this->addEvent();
        $this->addEvent();
        $this->addEvent();
        $this->assertEquals(
            [
                'subject' => 'New AutoPay payment received',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/autopay-succeeded-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'charges' => [
                    [
                        'cnt' => 4,
                        'amount' => 400,
                        'currency' => 'usd',
                    ],
                ],
            ],
            $email->getVariables(self::$events)
        );
    }
}
