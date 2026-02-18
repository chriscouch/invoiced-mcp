<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Chasing\Models\PromiseToPay;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\PromiseCreated;

class PromiseCreatedTest extends AbstractNotificationEmailTest
{
    private array $promises;

    private function addEvent(): void
    {
        self::hasInvoice();
        $promiseToPay = new PromiseToPay();
        $promiseToPay->invoice = self::$invoice;
        $promiseToPay->customer = self::$customer;
        $promiseToPay->currency = self::$invoice->currency;
        $promiseToPay->amount = self::$invoice->balance;
        $promiseToPay->saveOrFail();

        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::PromiseCreated);
        $event->object_id = $promiseToPay->id;
        self::$events[] = $event;
        $promiseToPay = $promiseToPay->toArray();
        $promiseToPay['customer'] = self::$customer->toArray();
        $promiseToPay['invoice'] = self::$invoice->toArray();
        $this->promises[] = $promiseToPay;
    }

    public function testProcess(): void
    {
        self::hasCustomer();
        $this->addEvent();

        $email = new PromiseCreated(self::getService('test.database'));

        $this->assertEquals(
            [
                'subject' => 'New promise-to-pay',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/promise', $email->getTemplate(self::$events));
        $this->assertEquals($this->promises, $email->getVariables(self::$events)['promises']);
    }

    public function testProcessBulk(): void
    {
        self::hasCustomer();

        $email = new PromiseCreated(self::getService('test.database'));

        $this->addEvent();
        $this->addEvent();
        $this->addEvent();

        $this->assertEquals(
            [
                'subject' => 'New promise-to-pay',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/promise-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'promises' => [
                    [
                        'cnt' => '4',
                        'amount' => 400,
                        'currency' => 'usd',
                    ],
                ],
            ],
            $email->getVariables(self::$events)
        );
    }
}
