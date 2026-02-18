<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Companies\Models\Member;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\EmailThreadAssigned;
use App\Sending\Email\Models\EmailThread;

class EmailThreadAssignedTest extends AbstractNotificationEmailTest
{
    /** @var EmailThread[] */
    private array $threads;

    private function addEvent(): void
    {
        self::hasEmailThread();
        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::EmailReceived);
        $event->object_id = self::$thread->id;
        self::$events[] = $event;
        $this->threads[] = self::$thread;
    }

    private function getEmail(): EmailThreadAssigned
    {
        return new EmailThreadAssigned(self::getService('test.database'));
    }

    public function testProcess(): void
    {
        self::hasInbox();
        for ($i = 0; $i < EmailThreadAssigned::THRESHOLD; ++$i) {
            $this->addEvent();
        }

        $email = $this->getEmail();

        $this->assertEquals(
            [
                'subject' => 'Conversation was assigned to you',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/email-thread', $email->getTemplate(self::$events));
        $this->assertEquals(array_map(fn ($item) => $item->toArray(), $this->threads), $email->getVariables(self::$events)['threads']);

        self::hasCustomer();
        $member = Member::one();
        foreach ($this->threads as $thread) {
            $thread->assignee_id = $member->user_id;
            $thread->saveOrFail();
        }
    }

    public function testProcessBulk(): void
    {
        $email = $this->getEmail();
        $this->addEvent();
        $this->assertEquals(
            [
                'subject' => 'Conversation was assigned to you',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/email-thread-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'count' => 4,
            ],
            $email->getVariables(self::$events)
        );
    }
}
