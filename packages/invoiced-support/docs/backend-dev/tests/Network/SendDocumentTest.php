<?php

namespace App\Tests\Network;

use App\AccountsPayable\Models\Bill;
use App\Companies\Models\Company;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\Network\Command\SendDocument;
use App\Network\Enums\DocumentFormat;
use App\Network\Enums\DocumentStatus;
use App\Network\Enums\NetworkDocumentType;
use App\Network\Models\NetworkConnection;
use App\Network\Models\NetworkDocumentStatusTransition;
use App\Network\Models\NetworkDocumentVersion;
use App\Tests\AppTestCase;

class SendDocumentTest extends AppTestCase
{
    private static Company $company2;
    private static NetworkConnection $connection;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company2 = self::getTestDataFactory()->createCompany();
        self::getService('test.tenant')->set(self::$company2);
        self::hasVendor();
        self::hasCompany();
        self::$connection = self::getTestDataFactory()->connectCompanies(self::$company, self::$company2);
        self::$vendor->network_connection = self::$connection;
        self::$vendor->saveOrFail();
        self::hasCustomer();
        self::hasInvoice();
        self::$invoice->number = 'INV-12345';
        self::$invoice->saveOrFail();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (isset(self::$company2)) {
            self::$company2->delete();
        }
    }

    private function getCommand(): SendDocument
    {
        return self::getService('test.send_document');
    }

    public function testSendFromXml(): void
    {
        EventSpool::enable();

        $command = $this->getCommand();
        $data = (string) file_get_contents(__DIR__.'/Ubl/data/output/invoice.xml');

        $document = $command->sendFromXml(self::$company, null, self::$connection, $data);

        $this->assertEquals(self::$company->id, $document->from_company->id);
        $this->assertEquals(self::$company2->id, $document->to_company->id);
        $this->assertEquals(1, $document->version);
        $this->assertEquals(DocumentFormat::UniversalBusinessLanguage, $document->format);
        $this->assertEquals(NetworkDocumentType::Invoice, $document->type);
        $this->assertEquals('USD', $document->currency);
        $this->assertEquals(100, $document->total);
        $this->assertEquals('INV-00001', $document->reference);
        $this->assertEquals(DocumentStatus::PendingApproval, $document->current_status);

        // should create version
        $version = NetworkDocumentVersion::where('document_id', $document)->oneOrNull();
        $this->assertInstanceOf(NetworkDocumentVersion::class, $version);
        $this->assertEquals(1, $version->version);
        $this->assertEquals(3824, $version->size);

        // should create status transition
        $transition = NetworkDocumentStatusTransition::where('document_id', $document)->oneOrNull();
        $this->assertInstanceOf(NetworkDocumentStatusTransition::class, $transition);
        $this->assertEquals(DocumentStatus::PendingApproval, $transition->status);
        $this->assertEquals(self::$company->id, $transition->company->id);
        $this->assertEquals(date('Y-m-d'), $transition->effective_date->format('Y-m-d'));
        $this->assertNull($transition->description);

        // should create an event in the sender activity log
        $this->assertHasEvent($document, EventType::NetworkDocumentSent);

        // should create an event in the recipient activity log
        $n = Event::queryWithTenant(self::$company2)
            ->where('type_id', EventType::NetworkDocumentReceived->toInteger())
            ->where('object_type_id', ObjectType::NetworkDocument->value)
            ->where('object_id', $document->id)
            ->count();
        $this->assertEquals(1, $n);

        // should create a bill for the recipient
        $bill = Bill::queryWithTenant(self::$company2)->where('network_document_id', $document)->oneOrNull();
        $this->assertInstanceOf(Bill::class, $bill);
        $this->assertEquals('INV-00001', $bill->number);
    }

    public function testSendFromModel(): void
    {
        EventSpool::enable();

        $command = $this->getCommand();

        $document = $command->sendFromModel(self::$company, null, self::$connection, self::$invoice);

        $this->assertEquals(self::$company->id, $document->from_company->id);
        $this->assertEquals(self::$company2->id, $document->to_company->id);
        $this->assertEquals(1, $document->version);
        $this->assertEquals(DocumentFormat::UniversalBusinessLanguage, $document->format);
        $this->assertEquals(NetworkDocumentType::Invoice, $document->type);
        $this->assertEquals('USD', $document->currency);
        $this->assertEquals(100, $document->total);
        $this->assertEquals('INV-12345', $document->reference);
        $this->assertEquals(DocumentStatus::PendingApproval, $document->current_status);

        // should create version
        $version = NetworkDocumentVersion::where('document_id', $document)->oneOrNull();
        $this->assertInstanceOf(NetworkDocumentVersion::class, $version);
        $this->assertEquals(1, $version->version);
        $this->assertGreaterThan(0, $version->size);

        // should create status transition
        $transition = NetworkDocumentStatusTransition::where('document_id', $document)->oneOrNull();
        $this->assertInstanceOf(NetworkDocumentStatusTransition::class, $transition);
        $this->assertEquals(DocumentStatus::PendingApproval, $transition->status);
        $this->assertEquals(self::$company->id, $transition->company->id);
        $this->assertEquals(date('Y-m-d'), $transition->effective_date->format('Y-m-d'));
        $this->assertNull($transition->description);

        // should mark the invoice as sent
        $this->assertEquals($document->id, self::$invoice->network_document?->id);
        $this->assertTrue(self::$invoice->sent);
        $this->assertGreaterThan(0, self::$invoice->last_sent);

        // should create an event in the sender activity log
        $this->assertHasEvent($document, EventType::NetworkDocumentSent);

        // should create an event in the recipient activity log
        $n = Event::queryWithTenant(self::$company2)
            ->where('type_id', EventType::NetworkDocumentReceived->toInteger())
            ->where('object_type_id', ObjectType::NetworkDocument->value)
            ->where('object_id', $document->id)
            ->count();
        $this->assertEquals(1, $n);
    }

    public function testQueueToSend(): void
    {
        $command = $this->getCommand();

        $customer = self::getTestDataFactory()->createCustomer();
        $invoice = self::getTestDataFactory()->createInvoice($customer);

        $queuedSend = $command->queueToSend(null, $customer, $invoice);

        $this->assertEquals(2, $queuedSend->object_type);
        $this->assertEquals($invoice->id, $queuedSend->object_id);
        $this->assertNull($queuedSend->member);
        $this->assertEquals($customer, $queuedSend->customer);
    }
}
