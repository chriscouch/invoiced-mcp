<?php

namespace App\Tests\Network\NetworkDocumentSubscriber;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Core\Statsd\StatsdClient;
use App\ActivityLog\Libs\EventSpool;
use App\Network\Enums\DocumentStatus;
use App\Network\Event\DocumentTransitionEvent;
use App\Network\EventSubscriber\NetworkDocumentSubscriber;
use App\Network\Models\NetworkConnection;
use App\Network\Models\NetworkDocument;
use App\Network\Models\NetworkDocumentStatusTransition;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\Tests\AppTestCase;
use Mockery;

class NetworkDocumentSubscriberTest extends AppTestCase
{
    private static Company $company2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company2 = self::getTestDataFactory()->createCompany();
        self::hasCompany();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (isset(self::$company2)) {
            self::$company2->delete();
        }
    }

    public function testDocumentTransition(): void
    {
        $spool = Mockery::mock(NotificationSpool::class);
        $eventSpool = Mockery::mock(EventSpool::class);
        $eventSpool->shouldReceive('enqueue');
        $tenantContext = new TenantContext(self::getService('test.event_spool'), self::getService('test.email_spool'));
        $subscriber = new NetworkDocumentSubscriber($spool, $tenantContext, $eventSpool, self::getService('test.database'));
        $subscriber->setStatsd(new StatsdClient());

        $history = new NetworkDocumentStatusTransition();
        $history->id = 1;
        $history->company = self::$company2;
        $history->status = DocumentStatus::Voided;

        $document = new NetworkDocument();
        $document->from_company = self::$company;
        $document->to_company = self::$company2;
        $spool->shouldReceive('spool')
            ->withArgs([NotificationEventType::NetworkDocumentStatusChange, self::$company->id, 1, null])
            ->once();
        $subscriber->documentTransition(new DocumentTransitionEvent(
            $document,
            $history,
        ));

        $connection = new NetworkConnection();
        $connection->vendor = self::$company;
        $connection->customer = self::$company2;
        $connection->saveOrFail();

        self::hasCustomer();
        self::$customer->network_connection = $connection;
        self::$customer->saveOrFail();

        $document = new NetworkDocument();
        $document->from_company = self::$company;
        $document->to_company = self::$company2;
        $spool->shouldReceive('spool')
            ->withArgs([NotificationEventType::NetworkDocumentStatusChange, self::$company->id, 1, self::$customer->id])
            ->once();
        $subscriber->documentTransition(new DocumentTransitionEvent(
            $document,
            $history,
        ));
    }
}
