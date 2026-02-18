<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\Core\Statsd\StatsdClient;
use App\AccountsReceivable\Libs\DocumentViewTracker;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\Tests\AppTestCase;

class DocumentViewTrackerTest extends AppTestCase
{
    public function testAddView(): void
    {
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasEstimate();
        self::hasCreditNote();

        $notificationSpool = \Mockery::mock(NotificationSpool::class);
        $events = new CustomerPortalEvents(self::getService('test.database'));
        $tracker = new DocumentViewTracker(self::getService('test.event_spool'), $notificationSpool, $events);
        $tracker->setStatsd(new StatsdClient());

        $notificationSpool->shouldNotReceive('spool');
        $tracker->addView(self::$creditNote, 'test', '127.0.0.1');

        $notificationSpool->shouldReceive('spool')->with(NotificationEventType::EstimateViewed, self::$estimate->tenant_id, self::$estimate->id, self::$estimate->customer)->once();
        $tracker->addView(self::$estimate, 'test', '127.0.0.1');

        $notificationSpool->shouldReceive('spool')->with(NotificationEventType::InvoiceViewed, self::$invoice->tenant_id, self::$invoice->id, self::$invoice->customer)->once();
        $tracker->addView(self::$invoice, 'test', '127.0.0.1');
    }
}
