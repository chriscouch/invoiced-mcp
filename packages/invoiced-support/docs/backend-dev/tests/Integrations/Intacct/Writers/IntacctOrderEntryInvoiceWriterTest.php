<?php

namespace App\Tests\Integrations\Intacct\Writers;

use App\Core\Statsd\StatsdClient;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Writers\IntacctCustomerWriter;
use App\Integrations\Intacct\Writers\IntacctOrderEntryInvoiceWriter;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Intacct\Functions\OrderEntry\OrderEntryTransactionCreate;
use Intacct\Functions\OrderEntry\OrderEntryTransactionDelete;
use Mockery;

class IntacctOrderEntryInvoiceWriterTest extends AppTestCase
{
    private static IntacctSyncProfile $syncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        self::hasIntacctAccount();
        self::$syncProfile = new IntacctSyncProfile();
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->customer_top_level = false;
        self::$syncProfile->saveOrFail();
    }

    private function getWriter(IntacctApi $intacctApi): IntacctOrderEntryInvoiceWriter
    {
        $customerWriter = Mockery::mock(IntacctCustomerWriter::class);
        $customerWriter->shouldReceive('createIntacctCustomer')
            ->andReturn('0'); // return isn't used so it doesnt matter
        $customerWriter->shouldReceive('getIntacctEntity')->andReturn(null);

        $writer = new IntacctOrderEntryInvoiceWriter($intacctApi, $customerWriter);
        $writer->setStatsd(new StatsdClient());

        return $writer;
    }

    public function testIsEnabled(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $writer = $this->getWriter($intacctApi);
        $this->assertFalse($writer->isEnabled(new IntacctSyncProfile(['write_invoices' => false])));
        $this->assertTrue($writer->isEnabled(new IntacctSyncProfile(['write_invoices' => true])));
    }

    public function testCreate(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (OrderEntryTransactionCreate $intacctInvoice) {
                $this->assertEquals('CUST-00001', $intacctInvoice->getCustomerId());
                $this->assertEquals('Sales Invoice', $intacctInvoice->getTransactionDefinition());
                $this->assertEquals('INV-00001', $intacctInvoice->getDocumentNumber());
                $this->assertNull($intacctInvoice->getReferenceNumber());
                $this->assertEquals(CarbonImmutable::createFromTimestamp(self::$invoice->date), $intacctInvoice->getTransactionDate());
                $this->assertEquals(CarbonImmutable::createFromTimestamp(self::$invoice->date), $intacctInvoice->getDueDate());

                $lines = $intacctInvoice->getLines();
                $this->assertCount(1, $lines);
                $this->assertEquals(100.0, $lines[0]->getPrice());
                $this->assertEquals('Test Item', $lines[0]->getItemDescription());
                $this->assertEquals('test', $lines[0]->getMemo());
                $this->assertEquals('Consulting', $lines[0]->getItemId()); // TODO
                $this->assertEquals('Hour', $lines[0]->getUnit()); // TODO

                return '1234';
            })
            ->once();

        $writer = $this->getWriter($intacctApi);

        $writer->create(self::$invoice, self::$intacctAccount, self::$syncProfile);

        /** @var AccountingInvoiceMapping $mapping */
        $mapping = AccountingInvoiceMapping::find(self::$invoice->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }

    public function testVoid(): void
    {
        self::$invoice->void();

        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (OrderEntryTransactionDelete $intacctInvoice) {
                $this->assertEquals('Sales Invoice', $intacctInvoice->getTransactionDefinition());
                $this->assertEquals('1234', $intacctInvoice->getDocumentId());
                $this->assertEquals('Voided on Invoiced', $intacctInvoice->getMessage());

                return '1234';
            })
            ->once();

        $writer = $this->getWriter($intacctApi);

        $writer->update(self::$invoice, self::$intacctAccount, self::$syncProfile);
    }
}
