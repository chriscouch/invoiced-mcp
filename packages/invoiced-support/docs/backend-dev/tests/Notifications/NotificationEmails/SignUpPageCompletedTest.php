<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\AccountsReceivable\Models\Customer;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\SignUpPageCompleted;

class SignUpPageCompletedTest extends AbstractNotificationEmailTest
{
    private array $customers;

    private function addEvent(): void
    {
        $customer = new Customer();
        $customer->name = 'Sign Up Page Notification';
        $customer->saveOrFail();

        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::SignUpPageCompleted);
        $event->object_id = $customer->id;
        self::$events[] = $event;
        $result = $customer->toArray();
        $this->customers[] = $result;
    }

    public function testProcess(): void
    {
        $this->addEvent();

        $email = new SignUpPageCompleted(self::getService('test.database'));

        $this->assertEquals(
            [
                'subject' => 'Sign up page completed',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/sign-up-page-completed', $email->getTemplate(self::$events));
        $this->assertEquals($this->customers, $email->getVariables(self::$events)['customers']);

        $this->addEvent();
    }

    public function testProcessBulk(): void
    {
        $email = new SignUpPageCompleted(self::getService('test.database'));

        $this->addEvent();
        $this->addEvent();

        $this->assertEquals(
            [
                'subject' => 'Sign up page completed',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/sign-up-page-completed-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'customers' => [
                    [
                        'cnt' => 4,
                    ],
                ],
            ],
            $email->getVariables(self::$events)
        );
    }
}
