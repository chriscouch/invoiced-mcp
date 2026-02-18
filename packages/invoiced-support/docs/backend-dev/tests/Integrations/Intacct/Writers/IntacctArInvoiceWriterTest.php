<?php

namespace App\Tests\Integrations\Intacct\Writers;

use App\AccountsReceivable\Models\Invoice;
use App\Core\Statsd\StatsdClient;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Writers\IntacctArInvoiceWriter;
use App\Integrations\Intacct\Writers\IntacctCustomerWriter;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Intacct\Functions\AccountsReceivable\InvoiceCreate;
use Intacct\Functions\AccountsReceivable\InvoiceReverse;
use Mockery;

class IntacctArInvoiceWriterTest extends AppTestCase
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
        self::$syncProfile->item_account = 'SALES001';
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->customer_top_level = false;
        self::$syncProfile->saveOrFail();
    }

    private function getWriter(IntacctApi $intacctApi): IntacctArInvoiceWriter
    {
        $customerWriter = Mockery::mock(IntacctCustomerWriter::class);
        $customerWriter->shouldReceive('createIntacctCustomer')
            ->andReturn('0'); // return isn't used so it doesnt matter
        $customerWriter->shouldReceive('getIntacctEntity')->andReturn(null);

        $writer = new IntacctArInvoiceWriter($intacctApi, $customerWriter);
        $writer->setStatsd(new StatsdClient());
        $writer->setLogger(self::$logger);

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
            ->andReturnUsing(function (InvoiceCreate $intacctInvoice) {
                $this->assertEquals('CUST-00001', $intacctInvoice->getCustomerId());
                $this->assertEquals('INV-00001', $intacctInvoice->getInvoiceNumber());
                $this->assertNull($intacctInvoice->getReferenceNumber());
                $this->assertEquals('Invoice # INV-00001 imported from Invoiced (ID: '.self::$invoice->id().')', $intacctInvoice->getDescription());
                $this->assertEquals(CarbonImmutable::createFromTimestamp(self::$invoice->date), $intacctInvoice->getTransactionDate());
                $this->assertEquals(CarbonImmutable::createFromTimestamp(self::$invoice->date), $intacctInvoice->getDueDate());

                $lines = $intacctInvoice->getLines();
                $this->assertCount(1, $lines);
                $this->assertEquals(100.0, $lines[0]->getTransactionAmount());
                $this->assertEquals('Test Item: test', $lines[0]->getMemo());
                $this->assertEquals('SALES001', $lines[0]->getGlAccountNumber());
                $this->assertEquals('CUST-00001', $lines[0]->getCustomerId());

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
            ->andReturnUsing(function (InvoiceReverse $intacctInvoice) {
                $this->assertEquals('1234', $intacctInvoice->getRecordNo());
                $this->assertEquals('Voided on Invoiced', $intacctInvoice->getMemo());
                $this->assertEquals(CarbonImmutable::createFromTimestamp((int) self::$invoice->date_voided), $intacctInvoice->getReverseDate());

                return '1234';
            })
            ->once();

        $writer = $this->getWriter($intacctApi);

        $writer->update(self::$invoice, self::$intacctAccount, self::$syncProfile);
    }

    public function testCreateCustomFieldMapping(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (InvoiceCreate $intacctInvoice) {
                $expected = [
                    'DEPARTMENT' => 'Sales',
                    'NUMBER' => 'INV-00002',
                ];
                $this->assertEquals($expected, $intacctInvoice->getCustomFields());

                foreach ($intacctInvoice->getLines() as $lineItem) {
                    $expected = [
                        'DEPARTMENT' => 'Sales',
                    ];
                    $this->assertEquals($expected, $lineItem->getCustomFields());
                }

                return '1235';
            })
            ->once();

        self::$syncProfile->invoice_custom_field_mapping = (object) [
            'number' => 'NUMBER',
            'metadata.department' => 'DEPARTMENT',
            'metadata.does_not_exist' => 'SHOULD_NOT_BE_SET',
        ];
        self::$syncProfile->line_item_custom_field_mapping = (object) [
            'metadata.department' => 'DEPARTMENT',
        ];
        self::$syncProfile->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [
            [
                'unit_cost' => 100,
                'name' => 'Test',
                'metadata' => (object) ['department' => 'Sales'],
            ],
        ];
        $invoice->metadata = (object) ['department' => 'Sales'];
        $invoice->saveOrFail();

        $writer = $this->getWriter($intacctApi);

        $writer->create($invoice, self::$intacctAccount, self::$syncProfile);
    }

    public function testCreateDimensionMapping(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (InvoiceCreate $intacctInvoice) {
                [$lineItem1, $lineItem2] = $intacctInvoice->getLines();
                $this->assertEquals('glaccountno', $lineItem1->getGlAccountNumber());
                $this->assertEquals('allocation', $lineItem1->getAllocationId());
                $this->assertEquals('location', $lineItem1->getLocationId());
                $this->assertEquals('department', $lineItem1->getDepartmentId());
                $this->assertEquals('project', $lineItem1->getProjectId());
                $this->assertEquals('vendor', $lineItem1->getVendorId());
                $this->assertEquals('employee', $lineItem1->getEmployeeId());
                $this->assertEquals('item', $lineItem1->getItemId());
                $this->assertEquals('class', $lineItem1->getClassId());
                $this->assertEquals('contract', $lineItem1->getContractId());
                $this->assertEquals('warehouse', $lineItem2->getWarehouseId());
                $this->assertEquals('offsetglaccountno', $lineItem2->getOffsetGLAccountNumber());
                $this->assertEquals('deferred', $lineItem2->getDeferredRevGlAccountNo());
                $this->assertEquals(new CarbonImmutable('2020-12-27T06:00:00'), $lineItem2->getRevRecStartDate());
                $this->assertEquals(new CarbonImmutable('2021-01-27T06:00:00'), $lineItem2->getRevRecEndDate());
                $this->assertEquals('template', $lineItem2->getRevRecTemplateId());

                return '1235';
            })
            ->once();

        self::$syncProfile->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [
            [
                'unit_cost' => 100,
                'name' => 'Test',
                'metadata' => (object) [
                    'intacct_glaccountno' => 'glaccountno',
                    'intacct_allocation' => 'allocation',
                    'intacct_location' => 'location',
                    'intacct_department' => 'department',
                    'intacct_project' => 'project',
                    'intacct_vendor' => 'vendor',
                    'intacct_employee' => 'employee',
                    'intacct_item' => 'item',
                    'intacct_class' => 'class',
                    'intacct_contract' => 'contract',
                ],
            ],
            [
                'unit_cost' => 100,
                'name' => 'Test',
                // split into 2 lines due to metadata limit of 10 per line item
                'metadata' => (object) [
                    'intacct_offsetglaccountno' => 'offsetglaccountno',
                    'intacct_warehouse' => 'warehouse',
                    'intacct_deferredrevaccount' => 'deferred',
                    'intacct_revrecstartdate' => '2020-12-27',
                    'intacct_revrecenddate' => '2021-01-27',
                    'intacct_revrectemplate' => 'template',
                ],
            ],
        ];
        $invoice->saveOrFail();

        $writer = $this->getWriter($intacctApi);

        $writer->create($invoice, self::$intacctAccount, self::$syncProfile);
    }
}
