<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Network\Models\NetworkInvitation;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\NetworkInvitationDeclined;

class NetworkInvitationDeclinedTest extends AbstractNotificationEmailTest
{
    private array $invitations;

    private function addEvent(): void
    {
        $invitation = new NetworkInvitation();
        $invitation->from_company = self::$company;
        $invitation->email = uniqid().'@example.com';
        $invitation->declined = false;
        $invitation->saveOrFail();

        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::NetworkInvitationDeclined);
        $event->object_id = $invitation->id;
        self::$events[] = $event;

        $this->invitations[] = ['name' => $invitation->email];
    }

    private function getEmail(): NetworkInvitationDeclined
    {
        return new NetworkInvitationDeclined(self::getService('test.database'));
    }

    public function testProcess(): void
    {
        $this->addEvent();
        $this->addEvent();
        $email = $this->getEmail();

        $this->assertEquals(
            [
                'subject' => 'Invitation to join your business network was declined',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/network-invitation-declined', $email->getTemplate(self::$events));
        $this->assertEquals($this->invitations, $email->getVariables(self::$events)['invitations']);
    }

    public function testProcessBulk(): void
    {
        $this->addEvent();
        $this->addEvent();
        $email = $this->getEmail();

        $this->assertEquals(
            [
                'subject' => 'Invitation to join your business network was declined',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/network-invitation-declined-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'count' => 4,
            ],
            $email->getVariables(self::$events)
        );
    }
}
