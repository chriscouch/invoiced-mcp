<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\LockboxCheckReceived;

class LockboxCheckReceivedTest extends AbstractNotificationEmailTest
{
    private array $payments;

    private function addEvent(): void
    {
        self::hasPayment();
        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::LockboxCheckReceived);
        $event->object_id = self::$payment->id;
        self::$events[] = $event;
        $this->payments[] = self::$payment->toArray();
    }

    private function getEmail(): LockboxCheckReceived
    {
        return new LockboxCheckReceived(self::getService('test.database'));
    }

    public function testProcess(): void
    {
        $this->addEvent();

        $email = $this->getEmail();

        $this->assertEquals(
            [
                'subject' => 'New check received in lockbox',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/lockbox-received', $email->getTemplate(self::$events));
        $this->assertEquals($this->payments, $email->getVariables(self::$events)['payments']);
    }

    public function testProcessBulk(): void
    {
        $email = $this->getEmail();

        $this->addEvent();
        $this->addEvent();
        $this->addEvent();
        $this->assertEquals(
            [
                'subject' => 'New check received in lockbox',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/lockbox-received-bulk', $email->getTemplate(self::$events));
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
