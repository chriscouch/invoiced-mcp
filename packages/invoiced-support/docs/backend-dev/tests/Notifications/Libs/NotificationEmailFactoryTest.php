<?php

namespace App\Tests\Notifications\Libs;

use App\Companies\Models\Member;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationEmailFactory;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\Models\NotificationRecipient;
use App\Notifications\NotificationEmails\AutoPayFailed;
use App\Notifications\NotificationEmails\AutoPaySucceeded;
use App\Notifications\NotificationEmails\EmailThreadAssigned;
use App\Notifications\NotificationEmails\EstimateApproved;
use App\Notifications\NotificationEmails\EstimateViewed;
use App\Notifications\NotificationEmails\InvoiceViewed;
use App\Notifications\NotificationEmails\LockboxCheckReceived;
use App\Notifications\NotificationEmails\NetworkDocumentReceived;
use App\Notifications\NotificationEmails\NetworkDocumentStatusChanged;
use App\Notifications\NotificationEmails\NetworkInvitationAccepted;
use App\Notifications\NotificationEmails\NetworkInvitationDeclined;
use App\Notifications\NotificationEmails\EmailReceived;
use App\Notifications\NotificationEmails\PaymentDone;
use App\Notifications\NotificationEmails\PaymentPlanApproved;
use App\Notifications\NotificationEmails\PromiseCreated;
use App\Notifications\NotificationEmails\SignUpPageCompleted;
use App\Notifications\NotificationEmails\SubscriptionCanceled;
use App\Notifications\NotificationEmails\SubscriptionExpired;
use App\Notifications\NotificationEmails\TaskAssigned;
use App\Tests\AppTestCase;

class NotificationEmailFactoryTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getFactory(): NotificationEmailFactory
    {
        return self::getService('test.notification_event_processor_factory');
    }

    public function testGetEvents(): void
    {
        $member = Member::query()->one();
        $factory = $this->getFactory();

        foreach (NotificationEventType::cases() as $type) {
            $notif = new NotificationEvent();
            $notif->setType($type);
            $notif->object_id = 1;
            $notif->save();

            $nr = new NotificationRecipient();
            $nr->notification_event = $notif;
            $nr->member = $member;
            $nr->save();
        }

        $this->assertCount(1, $factory->getEvents(NotificationEventType::InvoiceViewed->toInteger(), $member));
    }

    public function testBuild(): void
    {
        $factory = $this->getFactory();

        $this->assertInstanceOf(AutoPaySucceeded::class, $factory->build(NotificationEventType::AutoPaySucceeded->toInteger()));
        $this->assertInstanceOf(AutoPayFailed::class, $factory->build(NotificationEventType::AutoPayFailed->toInteger()));
        $this->assertInstanceOf(EmailThreadAssigned::class, $factory->build(NotificationEventType::ThreadAssigned->toInteger()));
        $this->assertInstanceOf(EstimateApproved::class, $factory->build(NotificationEventType::EstimateApproved->toInteger()));
        $this->assertInstanceOf(EstimateViewed::class, $factory->build(NotificationEventType::EstimateViewed->toInteger()));
        $this->assertInstanceOf(InvoiceViewed::class, $factory->build(NotificationEventType::InvoiceViewed->toInteger()));
        $this->assertInstanceOf(EmailReceived::class, $factory->build(NotificationEventType::EmailReceived->toInteger()));
        $this->assertInstanceOf(PaymentDone::class, $factory->build(NotificationEventType::PaymentDone->toInteger()));
        $this->assertInstanceOf(PaymentPlanApproved::class, $factory->build(NotificationEventType::PaymentPlanApproved->toInteger()));
        $this->assertInstanceOf(PromiseCreated::class, $factory->build(NotificationEventType::PromiseCreated->toInteger()));
        $this->assertInstanceOf(SignUpPageCompleted::class, $factory->build(NotificationEventType::SignUpPageCompleted->toInteger()));
        $this->assertInstanceOf(SubscriptionCanceled::class, $factory->build(NotificationEventType::SubscriptionCanceled->toInteger()));
        $this->assertInstanceOf(SubscriptionExpired::class, $factory->build(NotificationEventType::SubscriptionExpired->toInteger()));
        $this->assertInstanceOf(TaskAssigned::class, $factory->build(NotificationEventType::TaskAssigned->toInteger()));
        $this->assertInstanceOf(LockboxCheckReceived::class, $factory->build(NotificationEventType::LockboxCheckReceived->toInteger()));
        $this->assertInstanceOf(NetworkInvitationDeclined::class, $factory->build(NotificationEventType::NetworkInvitationDeclined->toInteger()));
        $this->assertInstanceOf(NetworkInvitationAccepted::class, $factory->build(NotificationEventType::NetworkInvitationAccepted->toInteger()));
        $this->assertInstanceOf(NetworkDocumentReceived::class, $factory->build(NotificationEventType::NetworkDocumentReceived->toInteger()));
        $this->assertInstanceOf(NetworkDocumentStatusChanged::class, $factory->build(NotificationEventType::NetworkDocumentStatusChange->toInteger()));

        $this->expectException(\InvalidArgumentException::class);
        $factory->build(0);
    }
}
