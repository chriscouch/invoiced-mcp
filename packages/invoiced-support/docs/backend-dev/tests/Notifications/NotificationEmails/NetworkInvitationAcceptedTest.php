<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Companies\Models\Company;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\NetworkInvitationAccepted;

class NetworkInvitationAcceptedTest extends AbstractNotificationEmailTest
{
    private static Company $company2;
    private static Company $company3;
    private static Company $company4;
    private static Company $company5;
    private array $connections;

    public static function setUpBeforeClass(): void
    {
        self::$company2 = self::getTestDataFactory()->createCompany();
        self::$company3 = self::getTestDataFactory()->createCompany();
        self::$company4 = self::getTestDataFactory()->createCompany();
        self::$company5 = self::getTestDataFactory()->createCompany();
        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (isset(self::$company2)) {
            self::$company2->delete();
        }
        if (isset(self::$company3)) {
            self::$company3->delete();
        }
        if (isset(self::$company4)) {
            self::$company4->delete();
        }
        if (isset(self::$company5)) {
            self::$company5->delete();
        }
    }

    private function addEvent(Company $customer): void
    {
        $connection = self::getTestDataFactory()->connectCompanies(self::$company, $customer);

        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::NetworkInvitationAccepted);
        $event->object_id = $connection->id;
        self::$events[] = $event;

        $this->connections[] = ['name' => $connection->customer->name];
    }

    private function getEmail(): NetworkInvitationAccepted
    {
        return new NetworkInvitationAccepted(self::getService('test.database'));
    }

    public function testProcess(): void
    {
        $this->addEvent(self::$company2);
        $this->addEvent(self::$company3);
        $email = $this->getEmail();

        $this->assertEquals(
            [
                'subject' => 'Your business network is growing!',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/network-invitation-accepted', $email->getTemplate(self::$events));
        $this->assertEquals($this->connections, $email->getVariables(self::$events)['connections']);
    }

    public function testProcessBulk(): void
    {
        $this->addEvent(self::$company4);
        $this->addEvent(self::$company5);
        $email = $this->getEmail();
        $this->assertEquals(
            [
                'subject' => 'Your business network is growing!',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/network-invitation-accepted-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'count' => 4,
            ],
            $email->getVariables(self::$events)
        );
    }
}
