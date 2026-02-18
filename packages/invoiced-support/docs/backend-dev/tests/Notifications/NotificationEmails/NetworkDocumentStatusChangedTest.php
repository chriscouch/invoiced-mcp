<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Companies\Models\Company;
use App\Network\Enums\DocumentStatus;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\NetworkDocumentStatusChanged;

class NetworkDocumentStatusChangedTest extends AbstractNotificationEmailTest
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
        $transition = self::getTestDataFactory()->createNetworkDocumentStatusTransition(self::$company, $document, DocumentStatus::Approved);

        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::NetworkDocumentStatusChange);
        $event->object_id = $transition->id;
        self::$events[] = $event;

        $this->documents[] = [
            'id' => $document->id,
            'from' => $document->from_company->name,
            'type' => 'Invoice',
            'reference' => $document->reference,
            'status' => 'Approved',
            'description' => null,
        ];
    }

    private function getEmail(): NetworkDocumentStatusChanged
    {
        return new NetworkDocumentStatusChanged(self::getService('test.database'));
    }

    public function testProcess(): void
    {
        $this->addEvent();
        $this->addEvent();
        $email = $this->getEmail();

        $this->assertEquals(
            [
                'subject' => 'Document status has been updated',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/network-document-status-changed', $email->getTemplate(self::$events));
        $this->assertEquals($this->documents, $email->getVariables(self::$events)['documents']);
    }

    public function testProcessBulk(): void
    {
        $this->addEvent();
        $this->addEvent();
        $email = $this->getEmail();
        $this->assertEquals(
            [
                'subject' => 'Document status has been updated',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/network-document-status-changed-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'count' => 4,
            ],
            $email->getVariables(self::$events)
        );
    }
}
