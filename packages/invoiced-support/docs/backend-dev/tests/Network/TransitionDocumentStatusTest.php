<?php

namespace App\Tests\Network;

use App\CashApplication\Models\Payment;
use App\Companies\Models\Company;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\Network\Command\SendDocument;
use App\Network\Enums\DocumentStatus;
use App\Network\Models\NetworkConnection;
use App\Tests\AppTestCase;

class TransitionDocumentStatusTest extends AppTestCase
{
    private static Company $company2;
    private static NetworkConnection $connection;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company2 = self::getTestDataFactory()->createCompany();
        self::hasCompany();
        self::$connection = self::getTestDataFactory()->connectCompanies(self::$company, self::$company2);
        self::hasCustomer();
        self::$customer->network_connection = self::$connection;
        self::$customer->saveOrFail();
        self::hasInvoice();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (isset(self::$company2)) {
            self::$company2->delete();
        }
    }

    private function getSendCommand(): SendDocument
    {
        return self::getService('test.send_document');
    }

    public function testPayInvoice(): void
    {
        EventSpool::enable();

        $send = $this->getSendCommand();
        $document = $send->sendFromModel(self::$company, null, self::$connection, self::$invoice);

        $payment = new Payment();
        $payment->amount = 100;
        $payment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => self::$invoice,
                'amount' => 100,
            ],
        ];
        $payment->saveOrFail();
        self::getService('test.event_spool')->flush(); // write out events

        $this->assertEquals(DocumentStatus::Paid, $document->refresh()->current_status);

        // should create an event in the sender activity log
        $this->assertHasEvent($document, EventType::NetworkDocumentStatusUpdated);

        // should create an event in the recipient activity log
        $n = Event::queryWithTenant(self::$company2)
            ->where('type_id', EventType::NetworkDocumentStatusUpdated->toInteger())
            ->where('object_type_id', ObjectType::NetworkDocument->value)
            ->where('object_id', $document->id)
            ->count();
        $this->assertEquals(1, $n);
    }
}
