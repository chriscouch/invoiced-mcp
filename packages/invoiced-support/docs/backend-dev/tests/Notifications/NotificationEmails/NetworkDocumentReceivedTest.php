<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Companies\Models\Company;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\NetworkDocumentReceived;

class NetworkDocumentReceivedTest extends AbstractNotificationEmailTest
{
    private static Company $company2;
    private array $documents;

    public static function setUpBeforeClass(): void
    {
        self::$company2 = self::getTestDataFactory()->createCompany();
        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (isset(self::$company2)) {
            self::$company2->delete();
        }
    }

    private function addEvent(): void
    {
        $document = self::getTestDataFactory()->createNetworkDocument(self::$company, self::$company2);

        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::NetworkDocumentReceived);
        $event->object_id = $document->id;
        self::$events[] = $event;

        $this->documents[] = [
            'id' => $document->id,
            'from' => $document->from_company->name,
            'type' => 'Invoice',
            'reference' => $document->reference,
        ];
    }

    private function getEmail(): NetworkDocumentReceived
    {
        return new NetworkDocumentReceived(self::getService('test.database'));
    }

    public function testProcess(): void
    {
        $this->addEvent();
        $this->addEvent();
        $email = $this->getEmail();

        $this->assertEquals(
            [
                'subject' => 'New document sent to you',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/network-document-received', $email->getTemplate(self::$events));
        $this->assertEquals($this->documents, $email->getVariables(self::$events)['documents']);
    }

    public function testProcessBulk(): void
    {
        $this->addEvent();
        $this->addEvent();
        $email = $this->getEmail();
        $this->assertEquals(
            [
                'subject' => 'New document sent to you',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/network-document-received-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'count' => 4,
            ],
            $email->getVariables(self::$events)
        );
    }
}
