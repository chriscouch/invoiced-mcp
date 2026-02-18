<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\EmailReceived;

class EmailReceivedTest extends AbstractNotificationEmailTest
{
    private array $emails;

    private function addEvent(): void
    {
        self::hasInboxEmail();
        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::EmailReceived);
        $event->object_id = self::$inboxEmail->id;
        self::$events[] = $event;
        $email = self::$inboxEmail->toArray();
        $email['customer'] = self::$inboxEmail->thread->customer ? self::$inboxEmail->thread->customer->toArray() : null;
        $email['inbox_id'] = self::$inbox->id;
        $email['subject'] = '';
        $email['from'] = '';
        $email['link'] = 'http://app.invoiced.localhost:1236/inboxes/thread/'.self::$inboxEmail->thread_id.'?account='.self::$company->id.'&id='.self::$inboxEmail->thread->inbox_id.'&emailId='.self::$inboxEmail->id;
        $this->emails[] = $email;
    }

    public function testProcess(): void
    {
        self::hasInbox();
        self::hasEmailThread();
        for ($i = 0; $i < EmailReceived::THRESHOLD; ++$i) {
            $this->addEvent();
        }

        $email = new EmailReceived(self::getService('test.database'));

        $this->assertEquals(
            [
                'subject' => 'New message on Invoiced',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/email-received', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'emails' => $this->emails,
            ],
            $email->getVariables(self::$events)
        );

        self::hasCustomer();
        self::$thread->customer = self::$customer;
        self::$thread->saveOrFail();

        $this->emails = array_map(function (array $email) {
            $email['customer'] = self::$customer->toArray();
            $email['inbox_id'] = self::$inbox->id;

            return $email;
        }, $this->emails);
    }

    public function testProcessBulk(): void
    {
        $email = new EmailReceived(self::getService('test.database'));

        $this->addEvent();
        $this->assertEquals(
            [
                'subject' => 'New message on Invoiced',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/email-received-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'count' => 4,
            ],
            $email->getVariables(self::$events)
        );
    }
}
