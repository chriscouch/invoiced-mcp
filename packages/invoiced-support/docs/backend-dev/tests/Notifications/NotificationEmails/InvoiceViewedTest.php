<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\InvoiceViewed;

class InvoiceViewedTest extends AbstractNotificationEmailTest
{
    private array $invoices;

    private function addEvent(): void
    {
        self::hasInvoice();
        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::InvoiceViewed);
        $event->object_id = self::$invoice->id;
        self::$events[] = $event;
        $invoice = self::$invoice->toArray();
        $invoice['customer'] = self::$customer->toArray();
        $this->invoices[] = $invoice;
    }

    private function getEmail(): InvoiceViewed
    {
        return new InvoiceViewed(self::getService('test.database'));
    }

    public function testProcess(): void
    {
        self::hasCustomer();
        $this->addEvent();

        $email = $this->getEmail();

        $this->assertEquals(
            [
                'subject' => 'Invoice was viewed in customer portal',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/invoice-viewed', $email->getTemplate(self::$events));
        $this->assertEquals($this->invoices, $email->getVariables(self::$events)['invoices']);
    }

    public function testProcessBulk(): void
    {
        $email = $this->getEmail();
        self::hasCustomer();
        $this->addEvent();
        $this->addEvent();
        $this->addEvent();
        $this->assertEquals(
            [
                'subject' => 'Invoice was viewed in customer portal',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/invoice-viewed-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'count' => 4,
            ],
            $email->getVariables(self::$events)
        );
    }
}
